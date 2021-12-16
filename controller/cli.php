<?php 

class Cli {

    private $arguments;

    public function __construct()
    {
        // Save any arguments supplied.
        $options = [
            "createTokens:",
            "updateTokens:",
            "updateTransactions:"
        ];
        $this->arguments = getopt('', $options);
    }

    public function requested()
    {
        // Check if arguments were supplied.
        if (count($this->arguments) > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function exec()
    {

        // Create a new api grant for the TDA API.
        if (array_key_exists('createTokens', $this->arguments)) {
            $this->createTdaTokens();
        }

        // Update access and refresh tokens from the TDA API.
        if (array_key_exists('updateTokens', $this->arguments)) {
            $this->updateTdaTokens();
        }
        
        // Download new transactions from the TDA API.
        if (array_key_exists('updateTransactions', $this->arguments)) {
            $this->updateTransactions('2021-10-01', '2021-10-06');
        }

        // Download new orders from the TDA API.
        if (array_key_exists('updateOrders', $this->arguments)) {
            $this->updateOrders();
        }
    }

    public static function createTdaTokens()
    {
        // Retrieve the permission code from the db.
        $permissionCodeQuery = MySql::read('tda_api', 'type', 'permissionCode');
        $permissionCode = $permissionCodeQuery[0]->string;

        // Retrieve the consumer key from the db.
        $consumerKeyQuery = MySql::read('tda_api', 'type', 'consumerKey');
        $consumerKey = $consumerKeyQuery[0]->string;

        // Retrieve the redirect URI from the db
        $redirectUriQuery = MySql::read('tda_api', 'type', 'redirectUri');
        $redirectUri = $redirectUriQuery[0]->string;

        // Request the new tokens.
        $newTokens = TDAToken::create($permissionCode, $consumerKey, $redirectUri);
        if (isset($newTokens->error)) {
            // Log failure.
            $logMessage = 'Error generating new tokens: ' . $newTokens->error;
            saveLog('tokens', $logMessage);
        } else {
            // Save the new tokens to the db.
            $newRefreshToken = $newTokens->refresh_token;
            $newRefreshTokenExpiration = time() + $newTokens->refresh_token_expires_in - 86400;
            $newAccessToken = $newTokens->access_token;
            $newAccessTokenExpiration = time() + $newTokens->expires_in - 60;
            MySql::update('tda_api', ['string' => $newRefreshToken, 'expiration' => $newRefreshTokenExpiration], 'type', 'refreshToken');
            MySql::update('tda_api', ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration], 'type', 'accessToken');
            
            if ($GLOBALS['config']['logs']['tokenSuccess'] === 'true') {
                $logMessage = 'Generated brand new tokens.';
                saveLog('tokens', $logMessage);
            }
        }
    }

    private function updateTdaTokens()
    {
        // Retrieve the consumer key from the db.
        $consumerKeyQuery = MySql::read('tda_api', 'type', 'consumerKey');
        $consumerKey = $consumerKeyQuery[0]->string;
        
        // Get the current Refresh Token and access tokens.
        $refreshTokenQuery = MySql::read('tda_api', 'type', 'refreshToken');
        $refreshToken = $refreshTokenQuery[0]->string;
        $refreshTokenExpiration = $refreshTokenQuery[0]->expiration;
        $accessTokenQuery = MySql::read('tda_api', 'type', 'accessToken');
        $accessTokenExpiration = $accessTokenQuery[0]->expiration;
        
        // Only update tokens if they are about to expire.
        if (time() >= $accessTokenExpiration) {
            // Updating the refresh token also updates the access token, so only call for an update of one or the other.
            if (time() >= $refreshTokenExpiration) {
                // Retrieve both tokens.
                $newRefreshTokenQuery = TDAToken::refresh($refreshToken, $consumerKey);
                if (isset($newRefreshTokenQuery->error)) {
                    // Log failure.
                    $logMessage = 'Error updating Refresh Token: ' . $newRefreshTokenQuery->error;
                    saveLog('tokens', $logMessage);
                } else {
                    $newRefreshToken = $newRefreshTokenQuery->refresh_token;
                    $newRefreshTokenExpiration = time() + $newRefreshTokenQuery->refresh_token_expires_in - 86400;
                    $newAccessToken = $newRefreshTokenQuery->access_token;
                    $newAccessTokenExpiration = time() + $newRefreshTokenQuery->expires_in - 60;
                    
                    // Update the db with both new tokens.
                    MySql::update('tda_api', ['string' => $newRefreshToken, 'expiration' => $newRefreshTokenExpiration], 'type', 'refreshToken');
                    MySql::update('tda_api', ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration], 'type', 'accessToken');
                    
                    // Log success if enabled.
                    if ($GLOBALS['config']['logs']['tokenSuccess'] === 'true') {
                        $logMessage = 'Updated Refresh and Access Tokens.';
                        saveLog('tokens', $logMessage);
                    }
                }
                
            } else {
                // Update just the access token.
                $newAccessTokenQuery = TDAToken::access($refreshToken, $consumerKey);
                
                if (isset($newAccessTokenQuery->error)) {
                    // Log failure.
                    $logMessage = 'Error updating Access Token: ' . $newAccessTokenQuery->error;
                    saveLog('tokens', $logMessage);
                } else {
                    $newAccessToken = $newAccessTokenQuery->access_token;
                    $newAccessTokenExpiration = time() + $newAccessTokenQuery->expires_in - 60;
                    
                    // Update the db.
                    MySql::update('tda_api', ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration], 'type', 'accessToken');
                    
                    // Log success if enabled.
                    if ($GLOBALS['config']['logs']['tokenSuccess'] === 'true') {
                        $logMessage = 'Updated Access Token.';
                        saveLog('tokens', $logMessage);
                    }
                }
            }
        } else {
            // Update log if token success logging is true.
            if ($GLOBALS['config']['logs']['tokenSuccess'] === 'true') {
                $logMessage = 'Tokens are not expired.';
                saveLog('tokens', $logMessage);
            }
        }
    }

    private function updateTransactions($startDate = NULL, $endDate = NULL)
    {
        // If no date range is specified then default to today.
        $today = date("Y-m-d");
        $startDate = $startDate ?? $today;
        $endDate = $endDate ?? $today;
        
        // Get the access token and account number from the db.
        $accessTokenQuery = MySql::read('tda_api', 'type', 'accessToken');
        $accessToken = $accessTokenQuery[0]->string;
        $accountNumberQuery = MySql::read('tda_api', 'type', 'accountNumber');
        $accountNumber = $accountNumberQuery[0]->string;
        
        // Clear the temporary table used for pending transactions.
        MySql::truncate('transactions_today');

        // Retrieve transactions for the specified date range, and reverse the array so oldest transactions are entered first.
        $transactions = TDAAccount::getTransactions($accessToken, $accountNumber, $startDate, $endDate);
        $transactions = array_reverse($transactions);
        // Iterate through the array, flatten each object, and store the data in the database.
        $transactionsUpdated = [];
        $transactionsDuplicates = [];
        $transactionsPending = [];
        $transactionsErrors = [];
        foreach ($transactions as $transaction){
            $insertArray = flatten($transaction);

            // Add only completed transactions to the transaction history. Pending transactions go in the temporary table.
            $pendingFlag = FALSE;
            if (isset($transaction->transactionSubType)) {
                $mySqlResponse = MySql::create('transactions', $insertArray);
            } else {
                $mySqlResponse = MySql::create('transactions_today', $insertArray);
                $pendingFlag = TRUE;
            }

            // Record the number of transactions: new, duplicate, pending, and MySql errors.
            if (is_numeric($mySqlResponse)) {
                if ($mySqlResponse === 0) {
                    $transactionsDuplicates[] = $transaction->transactionId;
                } else {
                    if ($pendingFlag === FALSE) {
                        $transactionsUpdated[] = $transaction->transactionId;
                    } else {
                        $transactionsPending[] = $transaction->orderId;
                    }
                }
            } else {
                $transactionsErrors[] = $mySqlResponse;
            }
        }

        // Save update information to the log.
        $logMessage = 'Transactions Downloaded - New: ' . count($transactionsUpdated) . ' Duplicates: ' . count($transactionsDuplicates) . ' Pending: ' . count($transactionsPending) . ' Errors: ' . count($transactionsErrors);
        saveLog('transactions', $logMessage);
        if (count($transactionsErrors) > 0) {
            saveLog('transactions', 'Failed to add transactions: ' . json_encode($transactionsErrors));
        }
    }

    public function updateOrders()
    {

    }

}

?>
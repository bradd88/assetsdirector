<?php 

class Cli {

    private MySql $mySql;
    private Log $log;
    private TdaApi $tdaApi;
    private array $arguments;

    public function __construct(MySql $mySql, Log $log, TdaApi $tdaApi)
    {
        $this->mySql = $mySql;
        $this->log = $log;
        $this->tdaApi = $tdaApi;
        // Save any arguments supplied.
        $options = [
            "updateTokens:",
            "updateTransactions:"
        ];
        $this->arguments = getopt('', $options);
    }

    public function exec()
    {
        if (count($this->arguments) > 0) {
            $accounts = $this->mySql->read('accounts');
            foreach ($accounts as $account) {
                // Update access and refresh tokens from the TDA API.
                if (array_key_exists('updateTokens', $this->arguments)) {
                    $this->updateTdaTokens($account->account_id);
                }
                
                // Download new transactions from the TDA API.
                if (array_key_exists('updateTransactions', $this->arguments)) {
                    $this->updateTransactions($account->accound_id, '2021-10-01', '2021-10-06');
                }

                // Download new orders from the TDA API.
                if (array_key_exists('updateOrders', $this->arguments)) {
                    $this->updateOrders();
                }
            }
        }
    }

    public function createTdaTokens(string $accountId)
    {
        // Retrieve the permission code from the db.
        $permissionCodeQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'permissionCode']);
        $permissionCode = $permissionCodeQuery[0]->string;

        // Retrieve the consumer key from the db.
        $consumerKeyQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'consumerKey']);
        $consumerKey = $consumerKeyQuery[0]->string;

        // Retrieve the redirect URI from the db
        $redirectUriQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'redirectUri']);
        $redirectUri = $redirectUriQuery[0]->string;

        // Request the new tokens.
        $newTokens = $this->tdaApi->newTokens($permissionCode, $consumerKey, $redirectUri);
        if (isset($newTokens->error)) {
            // Log failure.
            $logMessage = 'Error generating new tokens: ' . $newTokens->error;
            $this->log->save('tokens', $logMessage);
        } else {
            // Save the new tokens to the db.
            $newRefreshToken = $newTokens->refresh_token;
            $newRefreshTokenExpiration = time() + $newTokens->refresh_token_expires_in - 86400;
            $newAccessToken = $newTokens->access_token;
            $newAccessTokenExpiration = time() + $newTokens->expires_in - 60;
            $this->mySql->update('tda_api', ['type' => 'refreshToken'], ['string' => $newRefreshToken, 'expiration' => $newRefreshTokenExpiration]);
            $this->mySql->update('tda_api', ['type' => 'accessToken'], ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration]);
            
            $this->log->save('tokens', 'Generated brand new tokens.');
        }
    }

    private function updateTdaTokens(string $accountId)
    {
        // Retrieve the consumer key from the db.
        $consumerKeyQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'consumerKey']);
        $consumerKey = $consumerKeyQuery[0]->string;
        
        // Get the current Refresh Token and access tokens.
        $refreshTokenQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'refreshToken']);
        $refreshToken = $refreshTokenQuery[0]->string;
        $refreshTokenExpiration = $refreshTokenQuery[0]->expiration;
        $accessTokenQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'accessToken']);
        $accessTokenExpiration = $accessTokenQuery[0]->expiration;
        
        // Only update tokens if they are about to expire.
        if (time() >= $accessTokenExpiration) {
            // Updating the refresh token also updates the access token, so only call for an update of one or the other.
            if (time() >= $refreshTokenExpiration) {
                // Retrieve both tokens.
                $newRefreshTokenQuery = $this->tdaApi->refreshToken($refreshToken, $consumerKey);
                if (isset($newRefreshTokenQuery->error)) {
                    // Log failure.
                    $logMessage = 'Error updating Refresh Token: ' . $newRefreshTokenQuery->error;
                    $this->log->save('tokens', $logMessage);
                } else {
                    $newRefreshToken = $newRefreshTokenQuery->refresh_token;
                    $newRefreshTokenExpiration = time() + $newRefreshTokenQuery->refresh_token_expires_in - 86400;
                    $newAccessToken = $newRefreshTokenQuery->access_token;
                    $newAccessTokenExpiration = time() + $newRefreshTokenQuery->expires_in - 60;
                    
                    // Update the db with both new tokens.
                    $this->mySql->update('tda_api', ['type' => 'refreshToken'], ['string' => $newRefreshToken, 'expiration' => $newRefreshTokenExpiration]);
                    $this->mySql->update('tda_api', ['type' => 'accessToken'], ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration]);
                    
                    $this->log->save('tokens', 'Updated Refresh and Access Tokens.');
                }
                
            } else {
                // Update just the access token.
                $newAccessTokenQuery = $this->tdaApi->accessToken($refreshToken, $consumerKey);
                
                if (isset($newAccessTokenQuery->error)) {
                    // Log failure.
                    $logMessage = 'Error updating Access Token: ' . $newAccessTokenQuery->error;
                    $this->log->save('tokens', $logMessage);
                } else {
                    $newAccessToken = $newAccessTokenQuery->access_token;
                    $newAccessTokenExpiration = time() + $newAccessTokenQuery->expires_in - 60;
                    
                    // Update the db.
                    $this->mySql->update('tda_api', ['type' => 'accessToken'], ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration]);
                    
                    $this->log->save('tokens', 'Updated Access Token.');
                }
            }
        } else {
                $this->log->save('tokens', 'Tokens are not expired.');
        }
    }

    private function updateTransactions(string $accountId, string $startDate = NULL, string $endDate = NULL)
    {
        // If no date range is specified then default to today.
        $today = date("Y-m-d");
        $startDate = $startDate ?? $today;
        $endDate = $endDate ?? $today;
        
        // Get the access token and account number from the db.
        $accessTokenQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'accessToken']);
        $accessToken = $accessTokenQuery[0]->string;
        $accountNumberQuery = $this->mySql->read('tda_api', ['account_id' => $accountId, 'type' => 'accountNumber']);
        $accountNumber = $accountNumberQuery[0]->string;
        
        // Clear the temporary table used for pending transactions.
        $this->mySql->truncate('transactions_today');

        // Retrieve transactions for the specified date range, and reverse the array so oldest transactions are entered first.
        $transactions = $this->tdaApi->getTransactions($accessToken, $accountNumber, $startDate, $endDate);
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
                $mySqlResponse = $this->mySql->create('transactions', $insertArray);
            } else {
                $mySqlResponse = $this->mySql->create('transactions_today', $insertArray);
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
        $this->log->save('transactions', $logMessage);
        if (count($transactionsErrors) > 0) {
            $this->log->save('transactions', 'Failed to add transactions: ' . json_encode($transactionsErrors));
        }
    }

    public function updateOrders()
    {

    }

}

?>
<?php 

/*
 * This is the Command Line Interface controller, which handles updating the database with data from the TDA API.
 * It's reccomended to use a CRON job to schedule these updates.
 */

// Cron Job example:
//   # Update TDA API Tokens every 30 minutes from 2am to 8pm Monday through Friday.
//   */30    2-20    *       *       1-5     root    /usr/local/bin/php /usr/local/www/assetsdirector.com/site/index.php --updateTokens=true

/**
 * Execute the proper function for each command line option received.
 *
 * @param array $cliOptions
 * @return void
 */
function cliOptionsExec($cliOptions) {
    
    // Update Refresh and Access tokens.
    $updateTokens = $cliOptions["updateTokens"] ?? 'false';
    if ($updateTokens == 'true') {
        updateTdaApiTokens();
    }
    
    // Update transaction history.
    $updateTransactions = $cliOptions["updateTransactions"] ?? 'false';
    if ($updateTransactions == 'true') {
        updateTransactions('2021-09-01', '2021-09-13');
    }
    
    // Update orders.
    $updateOrders = $cliOptions["updateOrders"] ?? 'false';
    if ($updateOrders == 'true') {
        //updateOrders();
    }
    
}

/**
 * Update Access and Refresh tokens for the TDA API.
 *
 * @return void
 */
function updateTdaApiTokens() {
    // Retrieve the consumer key from the db.
    $consumerKeyQuery = mySqlRead('tda_api', 'type', 'consumerKey');
    $consumerKey = $consumerKeyQuery[0]->string;
    
    // Get the current Refresh Token and access tokens.
    $refreshTokenQuery = mySqlRead('tda_api', 'type', 'refreshToken');
    $refreshToken = $refreshTokenQuery[0]->string;
    $refreshTokenExpiration = $refreshTokenQuery[0]->expiration;
    $accessTokenQuery = mySqlRead('tda_api', 'type', 'accessToken');
    $accessTokenExpiration = $accessTokenQuery[0]->expiration;
    
    // Only update tokens if they are about to expire.
    if (time() >= $accessTokenExpiration) {
        // Updating the refresh token also updates the access token, so only call for an update of one or the other.
        if (time() >= $refreshTokenExpiration) {
            // Retrieve both tokens.
            $newRefreshTokenQuery = tdaRetrieveRefreshToken($refreshToken, $consumerKey);
            if (isset($newRefreshTokenQuery->error)) {
                // Log failure.
                $logMessage = 'Error updating Refresh Token: ' . $newRefreshTokenQuery->error;
                saveLog('tokens', $logMessage);
            } else {
                $newRefreshToken = $newRefreshTokenQuery->refresh_token;
                $newRefreshTokenExpiration = time() + $newRefreshTokenQuery->refresh_token_expires_in - 60;
                $newAccessToken = $newRefreshTokenQuery->access_token;
                $newAccessTokenExpiration = time() + $newRefreshTokenQuery->expires_in - 60;
                
                // Update the db with both new tokens.
                mySqlUpdate('tda_api', ['string' => $newRefreshToken, 'expiration' => $newRefreshTokenExpiration], 'type', 'refreshToken');
                mySqlUpdate('tda_api', ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration], 'type', 'accessToken');
                
                // Log success if enabled.
                if ($GLOBALS['config']['logs']['tokenSuccess'] == 'true') {
                    $logMessage = 'Updated Refresh and Access Tokens.';
                    saveLog('tokens', $logMessage);
                }
            }
            
        } else {
            // Update just the access token.
            $newAccessTokenQuery = tdaRetrieveAccessToken($refreshToken, $consumerKey);
            
            if (isset($newAccessTokenQuery->error)) {
                // Log failure.
                $logMessage = 'Error updating Access Token: ' . $newAccessTokenQuery->error;
                saveLog('tokens', $logMessage);
            } else {
                $newAccessToken = $newAccessTokenQuery->access_token;
                $newAccessTokenExpiration = time() + $newAccessTokenQuery->expires_in - 60;
                
                // Update the db.
                mySqlUpdate('tda_api', ['string' => $newAccessToken, 'expiration' => $newAccessTokenExpiration], 'type', 'accessToken');
                
                // Log success if enabled.
                if ($GLOBALS['config']['logs']['tokenSuccess'] == 'true') {
                    $logMessage = 'Updated Access Token.';
                    saveLog('tokens', $logMessage);
                }
            }
        }
    } else {
        if ($GLOBALS['config']['logs']['tokenSuccess'] == 'true') {
            $logMessage = 'Tokens are not expired.';
            saveLog('tokens', $logMessage);
        }
    }
}

/**
 * Update transaction data from TDA API and store in the MySql database.
 * If no dates are supplied then only the current day will be updated.
 *
 * @param string $startDate Optional
 * @param string $endDate Optional
 * @return void
 */
function updateTransactions($startDate = NULL, $endDate = NULL){
    
    // If no date range is specified then default to today.
    $today = date("Y-m-d");
    $startDate = $startDate ?? $today;
    $endDate = $endDate ?? $today;
    
    // Get the access token and account number from the db.
    $accessTokenQuery = mySqlRead('tda_api', 'type', 'accessToken');
    $accessToken = $accessTokenQuery[0]->string;
    $accountNumberQuery = mySqlRead('tda_api', 'type', 'accountNumber');
    $accountNumber = $accountNumberQuery[0]->string;
    
    // Clear the temporary table used for pending transactions.
    MySqlTruncate('transactions_today');

    // Retrieve transactions for the specified date range, and reverse the array so oldest transactions are entered first.
    $transactions = tdaRetrieveTransactionHistory($accessToken, $accountNumber, $startDate, $endDate);
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
            $mySqlResponse = mySqlCreate('transactions', $insertArray);
        } else {
            $mySqlResponse = mySqlCreate('transactions_today', $insertArray);
            $pendingFlag = TRUE;
        }

        // Record the number of transactions: new, duplicate, pending, and MySql errors.
        if (is_numeric($mySqlResponse)) {
            if ($mySqlResponse == 0) {
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

?>
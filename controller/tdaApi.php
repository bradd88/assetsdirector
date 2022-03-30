<?php

/** TDA API controller that handles downloading data and saving it to the database. */
class TdaApi
{
    private TdaApiRequest $tdaApiRequest;
    private MySql $mySql;
    private Log $log;

    public function __construct(TdaApiRequest $tdaApiRequest, MySql $mySql, Log $log)
    {
        $this->tdaApiRequest = $tdaApiRequest;
        $this->mySql = $mySql;
        $this->log = $log;
    }

    /** Store tokens in the database after modifying expiration times for a buffer. */
    private function saveTokens(string $accountId, string $accessToken, string $accessTokenExpiration, ?string $refreshToken = NULL, ?string $refreshTokenExpiration = NULL): void
    {
        $accessTokenExpirationModified = $accessTokenExpiration + time() - 60;
        $this->mySql->update('tda_api', ['account_id' => $accountId], [
            'accessToken' => $accessToken,
            'accessTokenExpiration' => $accessTokenExpirationModified
        ]);
        if (!is_null($refreshToken)) {
            $refreshTokenExpirationModified = $refreshTokenExpiration + time() - 86400;
            $this->mySql->update('tda_api', ['account_id' => $accountId], [
                'refreshToken' => $refreshToken,
                'refreshTokenExpiration' => $refreshTokenExpirationModified
            ]);
        }
    }

    /** Create and save brand new Refresh and Access tokens using a Permission Code. */
    public function createTokens(string $accountId, string $permissionCode): void
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        $tokenRequest = $this->tdaApiRequest->newTokens($permissionCode, $accountInfo->consumerKey, $accountInfo->redirectUri);
        if (isset($tokenRequest->error)) {
            $this->log->save('tokens', 'Account#: ' . $accountId . ' - Error creating new tokens: ' . $tokenRequest->error);
        } else {
            $this->saveTokens($accountId, $tokenRequest->access_token, $tokenRequest->expires_in, $tokenRequest->refresh_token, $tokenRequest->refresh_token_expires_in);
            $this->log->save('tokens', 'Account#: ' . $accountId . ' - Successfully created new tokens.');
        }
    }

    /** Update and save expired tokens. */
    public function updateTokens(string $accountId): void
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        if (time() < $accountInfo->accessTokenExpiration) {
            $this->log->save('tokens', 'Account#: ' . $accountId . ' - Tokens are not expired.');
        } else {
            if (time() >= $accountInfo->refreshTokenExpiration) {
                // Refresh token is expired. Update both Refresh and Access tokens.
                $tokenRequest = $this->tdaApiRequest->refreshToken($accountInfo->refreshToken, $accountInfo->consumerKey);
                if (isset($tokenRequest->error)) {
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Error updating refresh token: ' . $tokenRequest->error);
                } else {
                    $this->saveTokens($accountId, $tokenRequest->access_token, $tokenRequest->expires_in, $tokenRequest->refresh_token, $tokenRequest->refresh_token_expires_in);
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Successfully updated refresh and access tokens.');
                }
            } else {
                // Only the access token is expired. Update just the Access token.
                $tokenRequest = $this->tdaApiRequest->accessToken($accountInfo->refreshToken, $accountInfo->consumerKey);
                if (isset($tokenRequest->error)) {
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Error updating access token: ' . $tokenRequest->error);
                } else {
                    $this->saveTokens($accountId, $tokenRequest->access_token, $tokenRequest->expires_in);
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Successfully updated access token.');
                }
            }
        }
    }

    /** Download and save transactions for a specific date range. */
    public function updateTransactions(string $accountId, ?string $startDate = NULL, ?string $endDate = NULL): void
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];

        // Clear old pending transactions, and download transactions for the specified date range.
        $this->mySql->truncate('transactions_pending');
        $today = date("Y-m-d");
        $startDate = $startDate ?? $today;
        $endDate = $endDate ?? $today;
        $transactions = $this->tdaApiRequest->transactions($accountInfo->accessToken, $accountInfo->accountNumber, $startDate, $endDate);
        $transactions = array_reverse($transactions);

        // Flatten each transacation and store in the database. Record transaction type (duplicate, pending, completed), and transaction id/order id.
        $transactionsUpdated = [];
        $transactionsDuplicates = [];
        $transactionsPending = [];
        $transactionsErrors = [];
        foreach ($transactions as $transaction){
            $transaction[] = ['account_id' => $accountId];
            $processedTransaction = flatten($transaction);
            $transactionTable = (isset($transaction->transactionSubType)) ? 'transactions' : 'transactions_pending' ;
            $mySqlResponse = $this->mySql->create($transactionTable, $processedTransaction);
            if (!is_numeric($mySqlResponse)) {
                $transactionsErrors[] = $mySqlResponse;
            } else {
                if ($mySqlResponse === 0) {
                    $transactionsDuplicates[] = $transaction->transactionId;
                } else {
                    if ($transactionTable === 'transactions_pending') {
                        $transactionsPending[] = $transaction->orderId;
                    } else {
                        $transactionsUpdated[] = $transaction->transactionId;
                    }
                }
            }
        }

        // Save update information to the log.
        $logMessage = 'Account#: ' . $accountId . ' - Transactions Downloaded - New: ' . count($transactionsUpdated) . ' Duplicates: ' . count($transactionsDuplicates) . ' Pending: ' . count($transactionsPending) . ' Errors: ' . count($transactionsErrors);
        $this->log->save('transactions', $logMessage);
        if (count($transactionsErrors) > 0) {
            $this->log->save('transactions', 'Account#: ' . $accountId . ' - Failed to add transactions: ' . json_encode($transactionsErrors));
        }
    }

    /** Download and save orders for a specified date range. */
    public function updateOrders(): void
    {
        
    }

}
?>
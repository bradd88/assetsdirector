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
    public function createTokens(string $accountId, string $permissionCode): bool
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        $tokenRequest = $this->tdaApiRequest->newTokens($permissionCode, $accountInfo->consumerKey, $accountInfo->redirectUri);
        if ($tokenRequest->httpdCode !== 200) {
            $this->log->save('tokens', 'Account#: ' . $accountId . ' - Error creating new tokens: ' . $tokenRequest->response->response->error);
            return FALSE;
        } else {
            $this->saveTokens($accountId, $tokenRequest->response->access_token, $tokenRequest->response->expires_in, $tokenRequest->response->refresh_token, $tokenRequest->response->refresh_token_expires_in);
            $this->log->save('tokens', 'Account#: ' . $accountId . ' - Successfully created new tokens.');
            return TRUE;
        }
    }

    /** Update and save expired tokens. Return FALSE if there was an issue updating tokens.*/
    public function updateTokens(string $accountId): bool
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        if (time() < $accountInfo->accessTokenExpiration) {
            $this->log->save('tokens', 'Account#: ' . $accountId . ' - Tokens are not expired.');
            return TRUE;
        } else {
            if (time() >= $accountInfo->refreshTokenExpiration) {
                // Refresh token is expired. Update both Refresh and Access tokens.
                $tokenRequest = $this->tdaApiRequest->refreshToken($accountInfo->refreshToken, $accountInfo->consumerKey);
                if ($tokenRequest->httpdCode !== 200) {
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Error updating refresh token: ' . $tokenRequest->response->error);
                    return FALSE;
                } else {
                    $this->saveTokens($accountId, $tokenRequest->response->access_token, $tokenRequest->response->expires_in, $tokenRequest->response->refresh_token, $tokenRequest->response->refresh_token_expires_in);
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Successfully updated refresh and access tokens.');
                    return TRUE;
                }
            } else {
                // Only the access token is expired. Update just the Access token.
                $tokenRequest = $this->tdaApiRequest->accessToken($accountInfo->refreshToken, $accountInfo->consumerKey);
                if ($tokenRequest->httpdCode !== 200) {
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Error updating access token: ' . $tokenRequest->response->error);
                    return FALSE;
                } else {
                    $this->saveTokens($accountId, $tokenRequest->response->access_token, $tokenRequest->response->expires_in);
                    $this->log->save('tokens', 'Account#: ' . $accountId . ' - Successfully updated access token.');
                    return TRUE;
                }
            }
        }
    }

    /** Download and save transactions for a specific date range. */
    public function updateTransactions(string $accountId, ?string $startDate = NULL, ?string $endDate = NULL): bool
    {
        $today = date("Y-m-d");
        $startDate = $startDate ?? $today;
        $endDate = $endDate ?? $today;
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        $transactionsRequest = $this->tdaApiRequest->transactions($accountInfo->accessToken, $accountInfo->accountNumber, $startDate, $endDate);
        if ($transactionsRequest->httpdCode !== 200) {
            $this->log->save('transactions', 'Error downloading transactions for account' . $accountId . ': ' . $transactionsRequest->response->error);
            return FALSE;
        }

        // Flatten each transacation and store in the database. Record transaction type (duplicate, pending, completed), and transaction id/order id.
        $transactions = array_reverse($transactionsRequest->response);
        $this->mySql->truncate('transactions_pending');
        $transactionsUpdated = [];
        $transactionsDuplicates = [];
        $transactionsPending = [];
        $transactionsErrors = [];
        foreach ($transactions as $transaction){
            $transaction->account_id = $accountId;
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
        $logMessage = 'Account#: ' . $accountId . ' - Transactions Downloaded - New: ' . count($transactionsUpdated) . ' Duplicates: ' . count($transactionsDuplicates) . ' Pending: ' . count($transactionsPending) . ' Errors: ' . count($transactionsErrors);
        $this->log->save('transactions', $logMessage);
        if (count($transactionsErrors) > 0) {
            $this->log->save('transactions', 'Account#: ' . $accountId . ' - Failed to add transactions: ' . json_encode($transactionsErrors));
        }
        return TRUE;
    }

    /** Download and save orders for a specified date range. */
    public function updateOrders(string $accountId, ?string $startDate = NULL, ?string $endDate = NULL)
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        //echo '<pre>';
        //var_dump($accountInfo);
        //echo '</pre>';
        $today = date("Y-m-d");
        $startDate = $startDate ?? $today;
        $endDate = $endDate ?? $today;
        $orders = $this->tdaApiRequest->orders($startDate, $endDate, $accountInfo->accessToken);
        return $orders;
    }

    public function getTdaAccount(string $accountId): object
    {
        $accountInfo = $this->mySql->read('tda_api', ['account_id' => $accountId])[0];
        $tdaAccount = $this->tdaApiRequest->accountInfo($accountInfo->accessToken, $accountInfo->accountNumber);
        return $tdaAccount;
    }

}
?>
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
        $this->mySql->update('tda_api', [
            ['account_id', 'isEqual', $accountId]
        ], [
            'accessToken' => $accessToken,
            'accessTokenExpiration' => $accessTokenExpirationModified
        ]);
        if (!is_null($refreshToken)) {
            $refreshTokenExpirationModified = $refreshTokenExpiration + time() - 86400;
            $this->mySql->update('tda_api', [
                ['account_id', 'isEqual', $accountId]
            ], [
                'refreshToken' => $refreshToken,
                'refreshTokenExpiration' => $refreshTokenExpirationModified
            ]);
        }
    }

    /** Create and save brand new Refresh and Access tokens using a Permission Code. */
    public function createTokens(string $accountId, string $permissionCode): bool
    {
        $accountInfo = $this->mySql->read('tda_api', NULL, [
            ['account_id', 'isEqual', $accountId]
        ])[0];
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
        $accountInfo = $this->mySql->read('tda_api', NULL, [
            ['account_id', 'isEqual', $accountId]
        ])[0];
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

    /**
     * Download and save transactions for a specific date range.
     *
     * @param string $accountId TDA Account ID.
     * @param string $startDate Date in Y-m-d format.
     * @param string $endDate Date in Y-m-d format.
     * @return boolean Returns TRUE if update was successful, or FALSE if there was an error.
     */
    public function updateTransactions(string $accountId, string $startDate, string $endDate): bool
    {
        $accountInfo = $this->mySql->read('tda_api', NULL, [
            ['account_id', 'isEqual', $accountId]
        ])[0];
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
            $processedTransaction = $this->flattenTransaction($transaction);
            $transactionTable = (isset($transaction->transactionSubType)) ? 'transactions' : 'transactions_pending' ;
            $mySqlResponse = $this->mySql->create($transactionTable, $processedTransaction);
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
        $logMessage = 'Account#: ' . $accountId;
        $logMessage .= ' - Transactions Downloaded for ' . $startDate . ' to ' . $endDate;
        $logMessage .= ' - New: ' . count($transactionsUpdated);
        $logMessage .= ' Duplicates: ' . count($transactionsDuplicates);
        $logMessage .= ' Pending: ' . count($transactionsPending);

        $this->log->save('transactions', $logMessage);
        if (count($transactionsErrors) > 0) {
            $this->log->save('transactions', 'Account#: ' . $accountId . ' - Failed to add transactions: ' . json_encode($transactionsErrors));
        }
        return TRUE;
    }

    /** Break transaction updates into daily batches to avoid issues with the TDA API silently dropping data when the response is too large. */
    public function batchUpdateTransactions(string $accountId, string $startDate, string $stopDate): bool
    {
        $startDate = new DateTime($startDate);
        $stopDate = new DateTime($stopDate);
        $oneDay = new DateInterval('P1D');
        $currentDate = new DateTime($startDate->format('Y-m-d'));
        while ($currentDate <= $stopDate) {
            $batchUpdate = $this->updateTransactions($accountId, $currentDate->format('Y-m-d'), $currentDate->format('Y-m-d'));
            if ($batchUpdate === FALSE) {
                return FALSE;
            }
            $currentDate->add($oneDay);
        }
        return TRUE;
    }

    /** Download and save orders for a specified date range. */
    public function updateOrders(string $accountId, ?string $startDate = NULL, ?string $endDate = NULL)
    {
        $accountInfo = $this->mySql->read('tda_api', NULL, [
            ['account_id', 'isEqual', $accountId]
        ])[0];
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
        $accountInfo = $this->mySql->read('tda_api', NULL, [
            ['account_id', 'isEqual', $accountId]
        ])[0];
        $tdaAccount = $this->tdaApiRequest->accountInfo($accountInfo->accessToken, $accountInfo->accountNumber);
        return $tdaAccount;
    }

    /** Flatten an array/objects into an associative array. */
    private function flattenTransaction(array|object $parent): array
    {
        $output = [];
        foreach ($parent as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $child = $this->flattenTransaction($value);
                $output = array_merge($output, $child);
            } else {
                $output[$key] = $value;
            }
        }
        return $output;
    }

}
?>

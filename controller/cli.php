<?php 

class Cli {

    private MySql $mySql;
    private Log $log;
    private TdaApi $tdaApi;
    private array $arguments;
    private object $accountApiInfo;

    public function __construct(MySql $mySql, Log $log, TdaApi $tdaApi, Request $request)
    {
        $this->mySql = $mySql;
        $this->log = $log;
        $this->tdaApi = $tdaApi;
        $this->arguments = array();
        foreach ($request->server->argv as $argument) {
            $argumentParsed = parse_ini_string($argument, false, INI_SCANNER_TYPED);
            if (!empty($argumentParsed)) {
                $this->arguments = array_merge($this->arguments, $argumentParsed);
            }
        }
    }

    /** Parse command line arugments and execute the appropriate tasks. */
    public function exec()
    {
        if (count($this->arguments) > 0) {
            $accounts = $this->mySql->read('accounts');
            foreach ($accounts as $account) {
                $this->accountApiInfo = $this->mySql->read('tda_api', ['account_id' => $account->account_id])[0];

                $updateTokens = $this->arguments['updateTokens'] ?? FALSE;
                if ($updateTokens === TRUE) {
                    $this->updateTdaTokens($account->account_id);
                }
                
                $updateTransactions = $this->arguments['updateTransactions'] ?? FALSE;
                if ($updateTransactions === TRUE) {
                    $this->updateTransactions($account->accound_id, '2021-10-01', '2021-10-06');
                }

                $updateOrders = $this->arguments['updateOrders'] ?? FALSE;
                if ($updateOrders === TRUE) {
                    $this->updateOrders();
                }
            }
        }
    }

    /** Create brand new Refresh and Access tokens using a Permission Code. */
    public function createTdaTokens(string $accountId)
    {
        $newTokens = $this->tdaApi->newTokens($this->accountApiInfo->permissionCode, $this->accountApiInfo->consumerKey, $this->accountApiInfo->redirectUri);
        if (isset($newTokens->error)) {
            $this->log->save('tokens', 'Error generating new tokens: ' . $newTokens->error);
        } else {
            $newRefreshTokenExpiration = time() + $newTokens->refresh_token_expires_in - 86400;
            $newAccessTokenExpiration = time() + $newTokens->expires_in - 60;
            $this->mySql->update('tda_api', ['account_id' => $accountId], [
                'refreshToken' => $newTokens->refresh_token,
                'refreshTokenExpiration' => $newRefreshTokenExpiration,
                'accessToken' => $newTokens->access_token,
                'accessTokenExpiration' => $newAccessTokenExpiration
            ]);
            $this->log->save('tokens', 'Generated brand new tokens.');
        }
    }

    /** Update and save expired tokens. */
    private function updateTdaTokens(string $accountId)
    {
        if (time() < $this->accountApiInfo->accessTokenExpiration) {
            $this->log->save('tokens', 'Tokens are not expired.');
        } else {
            if (time() >= $this->accountApiInfo->refreshTokenExpiration) {
                // Refresh token is expired. Update both Refresh and Access tokens.
                $newRefreshTokenQuery = $this->tdaApi->refreshToken($this->accountApiInfo->refreshToken, $this->accountApiInfo->consumerKey);
                if (isset($newRefreshTokenQuery->error)) {
                    $this->log->save('tokens', 'Error updating Refresh Token: ' . $newRefreshTokenQuery->error);
                } else {
                    $newRefreshTokenExpiration = time() + $newRefreshTokenQuery->refresh_token_expires_in - 86400;
                    $newAccessTokenExpiration = time() + $newRefreshTokenQuery->expires_in - 60;
                    $this->mySql->update('tda_api', ['account_id' => $accountId], [
                        'refreshToken' => $newRefreshTokenQuery->refresh_token,
                        'refreshTokenExpiration' => $newRefreshTokenExpiration,
                        'accessToken' => $newRefreshTokenQuery->access_token,
                        'accessTokenExpiration' => $newAccessTokenExpiration
                    ]);
                    $this->log->save('tokens', 'Updated Refresh and Access Tokens.');
                }
            } else {
                // Only the access token is expired. Update just the Access token.
                $newAccessTokenQuery = $this->tdaApi->accessToken($this->accountApiInfo->refreshToken, $this->accountApiInfo->consumerKey);
                if (isset($newAccessTokenQuery->error)) {
                    $this->log->save('tokens', 'Error updating Access Token: ' . $newAccessTokenQuery->error);
                } else {
                    $newAccessTokenExpiration = time() + $newAccessTokenQuery->expires_in - 60;
                    $this->mySql->update('tda_api', ['account_id' => $accountId], [
                        'accessToken' => $newAccessTokenQuery->access_token,
                        'accessTokenExpiration' => $newAccessTokenExpiration
                    ]);
                    $this->log->save('tokens', 'Updated Access Token.');
                }
            }
        }
    }

    /** Download and save transactions for a specific date range. */
    private function updateTransactions(string $accountId, string $startDate = NULL, string $endDate = NULL)
    {
        // Clear old pending transactions, and download transactions for the specified date range.
        $this->mySql->truncate('transactions_pending');
        $today = date("Y-m-d");
        $startDate = $startDate ?? $today;
        $endDate = $endDate ?? $today;
        $transactions = $this->tdaApi->getTransactions($this->accountApiInfo->accessToken, $this->accountApiInfo->accountNumber, $startDate, $endDate);
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
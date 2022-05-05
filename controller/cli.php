<?php 

/** Command line controller that accepts commands and executes them as necessary. */
class Cli {
    private MySql $mySql;
    private TdaApi $tdaApi;
    private Log $log;
    private array $arguments;

    public function __construct(MySql $mySql, TdaApi $tdaApi, Log $log, Request $request)
    {
        $this->mySql = $mySql;
        $this->tdaApi = $tdaApi;
        $this->log = $log;
        $this->arguments = array();
        foreach ($request->server->argv as $argument) {
            $argumentParsed = parse_ini_string($argument, false, INI_SCANNER_TYPED);
            if (!empty($argumentParsed)) {
                $this->arguments = array_merge($this->arguments, $argumentParsed);
            }
        }
    }

    /** Read command line flags and run appropriate methods on all accounts. */
    public function exec(): void
    {
        if (count($this->arguments) > 0) {
            $accounts = $this->mySql->read('accounts');
            foreach ($accounts as $account) {

                $start = (isset($this->arguments['start'])) ? new DateTime($this->arguments['start']) : new DateTime('today');
                $start = $start->format('Y-m-d');
                $stop = (isset($this->arguments['stop'])) ? new DateTime($this->arguments['stop']) : new DateTime('tomorrow');
                $stop = $stop->format('Y-m-d');

                $updateTokens = $this->arguments['updateTokens'] ?? FALSE;
                if ($updateTokens === TRUE) {
                    $this->tdaApi->updateTokens($account->account_id);
                    $this->log->save('cli', 'Updating tokens for account #' . $account->account_id);
                }
                
                $updateTransactions = $this->arguments['updateTransactions'] ?? FALSE;
                if ($updateTransactions === TRUE) {
                    $this->tdaApi->batchUpdateTransactions($account->account_id, $start, $stop);
                    $this->log->save('cli', 'Updating transactions for account #' . $account->account_id . ': ' . $start . ' to ' . $stop . '.');
                }

                $updateOrders = $this->arguments['updateOrders'] ?? FALSE;
                if ($updateOrders === TRUE) {
                    $start = '2021-10-01';
                    $end = '2021-10-06';
                    $this->tdaApi->updateOrders($account->account_id);
                    $this->log->save('cli', 'Updating orders for account #' . $account->account_id . ': ' . $start . ' to ' . $end . '.');
                }
            }
        }
    }

}

?>
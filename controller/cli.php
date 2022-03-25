<?php 

/** Takes command line arguments and executes the appropriate methods. */
class Cli {
    private MySql $mySql;
    private TdaApi $tdaApi;
    private array $arguments;

    public function __construct(MySql $mySql, TdaApi $tdaApi, Request $request)
    {
        $this->mySql = $mySql;
        $this->tdaApi = $tdaApi;
        $this->arguments = array();
        foreach ($request->server->argv as $argument) {
            $argumentParsed = parse_ini_string($argument, false, INI_SCANNER_TYPED);
            if (!empty($argumentParsed)) {
                $this->arguments = array_merge($this->arguments, $argumentParsed);
            }
        }
    }

    /** Read command line flags and run appropriate methods on all accounts. */
    public function exec()
    {
        if (count($this->arguments) > 0) {
            $accounts = $this->mySql->read('accounts');
            foreach ($accounts as $account) {

                $updateTokens = $this->arguments['updateTokens'] ?? FALSE;
                if ($updateTokens === TRUE) {
                    $this->tdaApi->updateTokens($account->account_id);
                }
                
                $updateTransactions = $this->arguments['updateTransactions'] ?? FALSE;
                if ($updateTransactions === TRUE) {
                    $this->tdaApi->updateTransactions($account->accound_id, $start, $end);
                }

                $updateOrders = $this->arguments['updateOrders'] ?? FALSE;
                if ($updateOrders === TRUE) {
                    $this->tdaApi->updateOrders();
                }
            }
        }
    }

}

?>
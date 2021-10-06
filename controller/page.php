<?php 

class Page {

    public $requested;

    public function exec()
    {
        if (Session::loggedIn() === FALSE) {
            return $this->requireLogin();
        } else {
            return $this->generate($this->requested);
        }
    }

    private function requireLogin()
    {
        // User attempting to access a page when not logged in.
        if (!isset($_POST['username']) && !isset($_POST['password'])) {
            // Display login page.
            return $this->generate('login');
        }

        // User attempting to login.
        if (isset($_POST['username']) && isset($_POST['password'])) {
            // Verify account/password supplied are correct.
            $userId = MySql::verifyLogin($_POST['username'], $_POST['password']);
            if ($userId === FALSE) {
                // Login unsuccessful, display the login page and record the failed attempt.
                return $this->generate('login');
                $logMessage = 'Failed Login - IP: ' . $_SERVER['REMOTE_ADDR'] . ' User: ' . $_POST['username'] . ' Pass: ' . $_POST['password'];
                saveLog('logins', $logMessage);
            } else {
                // Login successful, display the requested page and record the login.
                Session::login($userId);
                return $this->generate($this->requested);
                $logMessage = 'Successful Login - IP: ' . $_SERVER['REMOTE_ADDR'] . ' User: ' . $_POST['username'];
                saveLog('logins2', $logMessage);
            }
        }
    }

    private function generate($page)
    {
        $rootDir = $GLOBALS['config']['application']['root'];
        switch ($page) {
            case 'login':
                require_once $rootDir . '/view/page/login.php';
                $output = pageLogin('Please Login.');
                break;
                
            case 'logout':
                require_once $rootDir . '/view/page/login.php';
                Session::stop();
                header("Location: ./login");
                break;
                
            case 'home':
                require_once $rootDir . '/view/page/home.php';
                $output = pageHome();
                break;
                
            case 'transactions':
                require_once $rootDir . '/model/logic/transactions.php';
                require_once $rootDir . '/view/page/transactions.php';
                $transactionData = filterTransactions(MySql::read('transactions', 'transactionDate', NULL, '2021-09-01T00:00:00+0000', '2021-10-02T00:00:00+0000'), 'TRADE', 'EQUITY', 'AMD');
                $output = pageTransactions(calculateOutstanding($transactionData));
                break;
                
            case 'trades':
                require_once $rootDir . '/model/logic/transactions.php';
                require_once $rootDir . '/model/logic/trades.php';
                require_once $rootDir . '/model/logic/graph.php';
                require_once $rootDir . '/view/page/trades.php';
                require_once $rootDir . '/view/presentation/graph.php';
                $transactionData = filterTransactions(MySql::read('transactions', 'transactionDate', NULL, '2021-09-01T00:00:00+0000', '2021-10-02T00:00:00+0000'), 'TRADE', 'EQUITY', 'AMD');
                $tradeData = calculatePL(createTrades($transactionData));
                $table = pageTrades($tradeData);
                $graphCoords = calculatePlCoordinates($tradeData);
                $graphSettings = configureGraph($graphCoords, 1600, 800);
                $graph = presentationGraph($graphSettings);
                $output = $graph . $table;
                break;
                
            case 'summary':
                require_once $rootDir . '/view/page/summary.php';
                $output = pageSummary();
                break;
        }
        
        require_once $rootDir . '/view/presentation/layout.php';
        require_once $rootDir . '/view/presentation/menu.php';
        $css = file_get_contents($rootDir . '/view/presentation/style.css');
        if ($page === 'login') {
            $output = presentationLayout($output, $css);
        } else {
            $output = presentationLayout($output, $css, presentationMenu());
        }
        $output = preg_replace('( {4})', '', $output);
        
        return $output;
    }

}

?>
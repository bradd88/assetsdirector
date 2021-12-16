<?php 

class Page {

    public $requested;

    public function exec()
    {
        if (Session::loggedIn() === FALSE) {
            return $this->requireLogin();
        } else {
            return $this->generate($this->requested['page']);
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
                return $this->generate($this->requested['page']);
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
                $message = 'Please Login';
                $content = $this->getView('page/login.phtml', ['message' => $message, 'requested' => $this->requested['page']]);
                break;
                
            case 'logout':
                Session::stop();
                header("Location: ./login");
                break;
                
            case 'home':
                $content = $this->getView('page/home.phtml');
                break;
                
            case 'transactions':
                // Retrieve transaction data and mark potential errors by looking for impossible transactions.
                require_once $rootDir . '/model/logic/transactions.php';
                $transactionData = filterTransactions(MySql::read('transactions', 'transactionDate', NULL, '2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000'), 'TRADE', 'EQUITY', 'AMD');
                $transactionDataParsed = calculateOutstanding($transactionData);
                $content = $this->getView('page/transactions.phtml', ['transactions' => $transactionDataParsed]);
                break;
                
            case 'trades':
                // Calculate trades using transaction data.
                require_once $rootDir . '/model/logic/transactions.php';
                require_once $rootDir . '/model/logic/trades.php';

                $transactions = filterTransactions(MySql::read('transactions', 'transactionDate', NULL, '2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000'), 'TRADE', 'EQUITY', 'AMD');
                $tradeList = new TradeList($transactions);
                $table = $this->getView('page/trades.phtml', ['trades' => $tradeList->trades]);

                // Calculate parameters from trade data and draw a graph using javascript.
                require_once $rootDir . '/model/logic/graph.php';
                $graphSettings = configureGraph($tradeList->graphCoordinates, 1600, 800);
                $graph = $this->getView('presentation/graph.phtml', ['graph' => $graphSettings]);

                $content = $graph . $table;
                break;
                
            case 'summary':
                //require_once $rootDir . '/view/page/summary.php';
                $content = $this->getView('page/summary.phtml');
                break;

            case 'account':
                // Retrieve the account information.
                $consumerKeyQuery = MySql::read('tda_api', 'type', 'consumerKey');
                $consumerKey = $consumerKeyQuery[0]->string;
                $redirectUriQuery = MySql::read('tda_api', 'type', 'redirectUri');
                $redirectUri = $redirectUriQuery[0]->string;

                // If a code has been submitted, enter it into the db.
                if (isset($_GET['code'])) {
                    $newPermissionCode = filter_var($_GET['code'], FILTER_SANITIZE_STRING);
                    MySql::update('tda_api', ['string' => $newPermissionCode], 'type', 'permissionCode');
                    Cli::createTdaTokens();
                    // Cleanup the permission code, since it's one time use.
                    MySql::update('tda_api', ['string' => ''], 'type', 'permissionCode');
                }

                // Retrieve refresh token status.
                $refreshTokenQuery = MySql::read('tda_api', 'type', 'refreshToken');
                $refreshTokenExpiration = $refreshTokenQuery[0]->expiration;
                $refreshTokenStatus = ($refreshTokenExpiration > time()) ? 'Good' : 'Bad';

                $content = $this->getView('page/account.phtml', ['consumerKey' => $consumerKey, 'redirectUri' => $redirectUri, 'refreshTokenStatus' => $refreshTokenStatus]);

                break;
            
            default:
                $content = $this->getView('page/404.phtml');
                break;
        }
        
        // Build and output the page.
            $css = $this->getView('presentation/style.css');
            $menu = ($page === 'login') ? '' : $this->getView('presentation/menu.phtml');
            $output = $this->getView('presentation/layout.phtml', ['css' => $css, 'menu' => $menu, 'content' => $content]);
            $output = preg_replace('( {4})', '', $output);
            return $output;
    }

    private function getView($name, $parameters = NULL)
    {
        // Move variables out of the optional parameters array for easier use.
        if (isset($parameters)) {
            foreach ($parameters as $key => $value) {
                $$key = $value;
            }
        }
        // Use the internal buffer to parse the view file and return it as a string.
        $path = $GLOBALS['config']['application']['root'] . '/view/' . $name;
        ob_start();
        require_once $path;
        $view = ob_get_contents();
        ob_end_clean();
        return $view;
    }


}

?>
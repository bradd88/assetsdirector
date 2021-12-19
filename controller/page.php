<?php 

class Page {

    public $requested;
    private $session;

    public function __construct()
    {
        $this->requested = $_GET;
        if (!isset($this->requested['page']) || $this->requested['page'] == 'login') {
            $this->requested['page'] = 'home';
        }
        $this->session = new Session;
    }

    public function exec()
    {
        if ($this->session->check() === FALSE) {
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
                $logMessage = 'Failed Login - IP: ' . $_SERVER['REMOTE_ADDR'] . ' User: ' . $_POST['username'] . ' Pass: ' . $_POST['password'];
                saveLog('login_failure', $logMessage);
                return $this->generate('login', 'Incorrect Username/Password.');
            } else {
                // Login successful, display the requested page and record the login.
                $this->session->login($userId);
                $logMessage = 'Successful Login - IP: ' . $_SERVER['REMOTE_ADDR'] . ' User: ' . $_POST['username'];
                saveLog('login_success', $logMessage);
                return $this->generate($this->requested['page']);
            }
        }
    }

    private function generate($page, $message = NULL)
    {
        $rootDir = $GLOBALS['config']['application']['root'];
        switch ($page) {
            case 'login':
                $message = (isset($message)) ? $message : 'Please Login.' ;
                $content = $this->getView('page/login.phtml', ['message' => $message, 'requested' => $this->requested['page']]);
                break;
                
            case 'logout':
                $this->session->stop();
                header("Location: ./");
                break;
                
            case 'home':
                $content = $this->getView('page/home.phtml');
                break;
                
            case 'transactions':
                // Retrieve transaction data and mark potential errors by looking for impossible transactions.
                require_once $rootDir . '/model/logic/transactions.php';
                $transactionsData = MySql::read('transactions', ['account_id' => $this->session->accountId, 'transactionDate' => ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']]);
                if (count($transactionsData) > 0) {
                    $transactionDataFiltered = filterTransactions($transactionsData, 'TRADE', 'EQUITY', 'AMD');
                    $transactionDataParsed = calculateOutstanding($transactionDataFiltered);
                    $content = $this->getView('page/transactions.phtml', ['transactions' => $transactionDataParsed]);
                } else {
                    $content = $this->getView('page/transactions.phtml', ['transactions' => []]);
                }
                break;
                
            case 'trades':
                // Calculate trades using transaction data.
                require_once $rootDir . '/model/logic/transactions.php';
                require_once $rootDir . '/model/logic/trades.php';
                $transactionsData = MySql::read('transactions', ['account_id' => $this->session->accountId, 'transactionDate' => ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']]);
                if (count($transactionsData) > 0) {
                    $transactionsDataFiltered = filterTransactions($transactionsData, 'TRADE', 'EQUITY', 'AMD');
                    $tradeList = new TradeList($transactionsDataFiltered);
                    if (count($tradeList->trades) > 0) {
                        $table = $this->getView('page/trades.phtml', ['trades' => $tradeList->trades]);

                        // Calculate parameters from trade data and draw a graph using javascript.
                        require_once $rootDir . '/model/logic/graph.php';
                        $graphSettings = configureGraph($tradeList->graphCoordinates, 1600, 800);
                        $graph = $this->getView('presentation/graph.phtml', ['graph' => $graphSettings]);

                        $content = $graph . $table;
                    } else {
                        $content = $this->getView('page/trades.phtml', ['trades' => []]);
                    }
                } else {
                    $content = $this->getView('page/trades.phtml', ['trades' => []]);
                }

                break;
                
            case 'summary':
                //require_once $rootDir . '/view/page/summary.php';
                $content = $this->getView('page/summary.phtml');
                break;

            case 'account':
                // Retrieve the account information.
                $consumerKeyQuery = MySql::read('tda_api', ['account_id' => $this->session->accountId, 'type' => 'consumerKey']);
                $consumerKey = $consumerKeyQuery[0]->string;
                $redirectUriQuery = MySql::read('tda_api', ['account_id' => $this->session->accountId, 'type' => 'redirectUri']);
                $redirectUri = $redirectUriQuery[0]->string;

                // If a code has been submitted, enter it into the db.
                if (isset($_GET['code'])) {
                    $newPermissionCode = filter_var($_GET['code'], FILTER_SANITIZE_STRING);
                    MySql::update('tda_api', ['type' => 'permissionCode'], ['string' => $newPermissionCode]);
                    Cli::createTdaTokens($this->session->accountId);
                    // Cleanup the permission code, since it's one time use.
                    MySql::update('tda_api', ['type' => 'permissionCode'], ['string' => '']);
                }

                // Retrieve refresh token status.
                $refreshTokenQuery = MySql::read('tda_api', ['account_id' => $this->session->accountId, 'type' => 'refreshToken']);
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
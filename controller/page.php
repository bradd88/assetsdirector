<?php 

/** Page navigation controller that handles data between the models and views. Returns a string containing html that can be displayed to the end user. */
class Page
{

    private object $appSettings;
    private MySql $mySql;
    private Session $session;
    private Log $log;
    private Request $request;
    private TdaApi $tdaApi;
    private GraphFactory $graphFactory;

    public function __construct(Config $config, MySql $mySql, Session $session, Log $log, Request $request, TdaApi $tdaApi, GraphFactory $graphFactory)
    {
        $this->appSettings = $config->getSettings('application');
        $this->mySql = $mySql;
        $this->session = $session;
        $this->log = $log;
        $this->request = $request;
        $this->tdaApi = $tdaApi;
        $this->graphFactory = $graphFactory;
    }

    /** Take the page request and determine if the user needs to be redirected to the login page before generating the requested page. */
    public function exec(): string
    {
        if (!isset($this->request->get->page) || $this->request->get->page === 'login') {
            $this->request->get->page = 'home';
        }
        if ($this->session->check() === FALSE) {
            return $this->requireLogin();
        } else {
            return $this->generate($this->request->get->page);
        }
    }

    /** Attempt to login the user. */
    private function requireLogin(): string
    {
        // User attempting to access a page when not logged in.
        if (!isset($this->request->post->username) && !isset($this->request->post->password)) {
            // Display login page.
            return $this->generate('login');
        }

        // User attempting to login.
        if (isset($this->request->post->username) && isset($this->request->post->password)) {
            // Verify account/password supplied are correct.
            $userId = $this->mySql->verifyLogin($this->request->post->username, $this->request->post->password);
            if ($userId === FALSE) {
                // Login unsuccessful, display the login page and record the failed attempt.
                $logMessage = 'Failed Login - IP: ' . $this->request->server->REMOTE_ADDR . ' User: ' . $this->request->post->username . ' Pass: ' . $this->request->post->password;
                $this->log->save('login_failure', $logMessage);
                return $this->generate('login', 'Incorrect Username/Password.');
            } else {
                // Login successful, display the requested page and record the login.
                $this->session->login($userId);
                $logMessage = 'Successful Login - IP: ' . $this->request->server->REMOTE_ADDR . ' User: ' . $this->request->post->username;
                $this->log->save('login_success', $logMessage);
                return $this->generate($this->request->get->page);
            }
        }
    }

    /** Retrieve data for a requested page. */
    private function generate(string $page, ?string $message = NULL): string
    {
        switch ($page) {
            case 'login':
                $message = (isset($message)) ? $message : 'Please Login.' ;
                $content = $this->getView('page/login.phtml', ['message' => $message, 'requested' => $this->request->get->page]);
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
                $transactionsData = $this->mySql->read('transactions', ['account_id' => $this->session->accountId, 'transactionDate' => ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']]);
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
                $transactionsData = $this->mySql->read('transactions', ['account_id' => $this->session->accountId, 'transactionDate' => ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']]);
                if (count($transactionsData) > 0) {
                    $transactionsDataFiltered = filterTransactions($transactionsData, 'TRADE', 'EQUITY', 'AMD');
                    $tradeList = new TradeList($transactionsDataFiltered);
                    if (count($tradeList->trades) > 0) {
                        $table = $this->getView('page/trades.phtml', ['trades' => $tradeList->trades]);

                        // Calculate parameters from trade data and draw a graph using javascript.
                        $graph = $this->graphFactory->create();
                        $graph->addLine('Profit and Loss', 'green', $tradeList->graphCoordinates);
                        $graph->addLabel('x', 'suffix', ' trades');
                        $graph->addLabel('y', 'prefix', '$');
                        $graph->generate(1600, 800, 25, [10, 10, 100, 100], TRUE);

                        $graphPresentation = $this->getView('presentation/graph.phtml', ['graph' => $graph]);
                        $content = $graphPresentation . $table;
                    } else {
                        $content = $this->getView('page/trades.phtml', ['trades' => []]);
                    }
                } else {
                    $content = $this->getView('page/trades.phtml', ['trades' => []]);
                }

                break;
                
            case 'summary':
                $content = $this->getView('page/summary.phtml');
                break;

            case 'account':

                // If a permission grant code has been submitted then generate new TDA tokens for the account.
                if (isset($this->request->get->code)) {
                    $this->tdaApi->createTokens($this->session->accountId, htmlspecialchars($this->request->get->code, ENT_QUOTES));
                }

                // Get the refresh token status
                $accountApiInfo = $this->mySql->read('tda_api', ['account_id' => $this->session->accountId])[0];
                $refreshTokenStatus = ($accountApiInfo->refreshTokenExpiration > time()) ? 'Current' : 'Expired';
                $accessTokenStatus = ($accountApiInfo->accessTokenExpiration > time()) ? 'Current' : 'Expired';

                $content = $this->getView('page/account.phtml', ['consumerKey' => $accountApiInfo->consumerKey, 'redirectUri' => $accountApiInfo->redirectUri, 'refreshTokenStatus' => $refreshTokenStatus, 'accessTokenStatus' => $accessTokenStatus]);

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

    /** Generate presentation object code for the specefied view. */
    private function getView(string $name, ?array $parameters = NULL): string
    {
        // Move variables out of the optional parameters array for easier use.
        if (isset($parameters)) {
            foreach ($parameters as $key => $value) {
                $$key = $value;
            }
        }
        // Use the internal buffer to parse the view file and return it as a string.
        $path = $this->appSettings->rootDir . '/view/' . $name;
        ob_start();
        require_once $path;
        $view = ob_get_contents();
        ob_end_clean();
        return $view;
    }


}

?>
<?php 

/** Page navigation controller that handles data between the models and views. Returns a string containing html that can be displayed to the end user. */
class Page
{
    private MySql $mySql;
    private View $view;
    private Login $login;
    private Request $request;
    private TdaApi $tdaApi;
    private GraphFactory $graphFactory;
    private TransactionList $transactionList;
    private TradeListFactory $tradeListFactory;
    private Calendar $calendar;

    public function __construct(
        MySql $mySql,
        View $view,
        Login $login,
        Request $request,
        TdaApi $tdaApi,
        GraphFactory $graphFactory,
        TransactionList $transactionList,
        TradeListFactory $tradeListFactory,
        Calendar $calendar
        )
    {
        $this->mySql = $mySql;
        $this->view = $view;
        $this->login = $login;
        $this->request = $request;
        $this->tdaApi = $tdaApi;
        $this->graphFactory = $graphFactory;
        $this->transactionList = $transactionList;
        $this->tradeListFactory = $tradeListFactory;
        $this->calendar = $calendar;
    }

    /** Examine the request to determine if the user needs to be redirected to the login page before generating the requested page. */
    public function exec(): string
    {
        $this->request->get->page = $this->request->get->page ?? 'home';
        $this->request->get->page = ($this->request->get->page === 'login') ? 'home' : $this->request->get->page;
        if ($this->login->status() === FALSE) {
            if (isset($this->request->post->username) && isset($this->request->post->password)) {
                $loginAttempt = $this->login->attemptLogin($this->request->post->username, $this->request->post->password, $this->request->server->REMOTE_ADDR);
                if ($loginAttempt === FALSE) {
                    return $this->generate('login', 'Incorrect username/password.');
                }
            } else {
                return $this->generate('login', 'Please login.');
            }
        }
        return $this->generate($this->request->get->page);
    }

    /** Retrieve data for a requested page. */
    private function generate(string $page, ?string $message = NULL): string
    {
        switch ($page) {
            case 'login':
                $content = $this->view->get('page/login.phtml', ['message' => $message, 'requested' => $this->request->get->page]);
                break;
                
            case 'logout':
                $this->login->logout();
                header("Location: ./");
                break;
                
            case 'home':
                $content = $this->view->get('page/home.phtml');
                break;
                
            case 'transactions':
                $databaseResults = $this->mySql->read('transactions', [
                    'account_id' => $this->login->getAccountId(),
                    'type' => 'TRADE',
                    'assetType' => 'EQUITY',
                    'transactionDate' => ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']
                ]);
                $transactions = $this->transactionList->create($databaseResults);
                $content = $this->view->get('page/transactions.phtml', ['transactions' => $transactions, 'outstandingAssets' => $this->transactionList->outstandingAssets]);
                break;
                
            case 'trades':
                $databaseResults = $this->mySql->read('transactions', [
                    'account_id' => $this->login->getAccountId(),
                    'type' => 'TRADE',
                    'assetType' => 'EQUITY',
                    'transactionDate' => ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']
                ]);
                $transactionList = $this->transactionList->create($databaseResults);
                // Create a seperate trade list for each combination of transaction symbol and asset type.
                $tradeLists = array();
                foreach ($transactionList as $transaction) {
                    if (!isset($tradeLists[$transaction->symbol])) {
                        $tradeLists[$transaction->symbol] = array();
                    }
                    if (!isset($tradeLists[$transaction->symbol][$transaction->assetType])) {
                        $tradeLists[$transaction->symbol][$transaction->assetType] = $this->tradeListFactory->create();
                    }
                    /** @var TradeList $tradeList */
                    $tradeList = $tradeLists[$transaction->symbol][$transaction->assetType];
                    $tradeList->addTransaction($transaction);
                }
                // Recombine the lists and sort by trade finish date/time.
                $finalTradeList = $this->tradeListFactory->create();
                foreach ($tradeLists as $symbol) {
                    foreach ($symbol as $assetType) {
                        /** @var TradeList $assetType */
                        foreach ($assetType->getTrades() as $trade) {
                            $finalTradeList->addTrade($trade);
                        }
                    }
                }
                $finalTradeList->sortTrades();
                $finalTradeList->addStatistics();
                $content = $this->view->get('page/trades.phtml', ['calendar' => $this->calendar, 'trades' => $finalTradeList->getTrades()]);
                break;
                
            case 'summary':
                $content = $this->view->get('page/summary.phtml');
                break;

            case 'account':

                // If a permission grant code has been submitted then generate new TDA tokens for the account.
                if (isset($this->request->get->code)) {
                    $this->tdaApi->createTokens($this->login->getAccountId(), htmlspecialchars($this->request->get->code, ENT_QUOTES));
                }

                // Get the refresh token status
                $accountApiInfo = $this->mySql->read('tda_api', ['account_id' => $this->login->getAccountId()])[0];
                $refreshTokenStatus = ($accountApiInfo->refreshTokenExpiration > time()) ? 'Current' : 'Expired';
                $accessTokenStatus = ($accountApiInfo->accessTokenExpiration > time()) ? 'Current' : 'Expired';

                $content = $this->view->get('page/account.phtml', ['consumerKey' => $accountApiInfo->consumerKey, 'redirectUri' => $accountApiInfo->redirectUri, 'refreshTokenStatus' => $refreshTokenStatus, 'accessTokenStatus' => $accessTokenStatus]);

                break;
            
            default:
                $content = $this->view->get('page/404.phtml');
                break;
        }
        
        // Build and output the page.
            $css = $this->view->get('presentation/style.css');
            $menu = ($page === 'login') ? '' : $this->view->get('presentation/menu.phtml');
            $output = $this->view->get('presentation/layout.phtml', ['css' => $css, 'menu' => $menu, 'content' => $content]);
            $output = preg_replace('( {4})', '', $output);
            return $output;
    }

}

?>
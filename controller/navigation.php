<?php 

/*
 * This is the navigation controller, which handles generating pages, retrieval of pages from the cache, login redirection, and login attempts.
 */

// Generate the requested page.
// Argument $request should contain an object with at least the following properties: page, fullLayout, menu, useCached
// Returns a string containg the page HTML.
function retrievePage($request) {
    $rootDir = $GLOBALS['config']['application']['root'];
    
    $output = '';
    // Generate the requested page and store to the cache.
    switch ($request->page) {
        case 'login':
            require_once $rootDir . '/view/page/login.php';
            $output = pageLogin($request->message);
            break;
            
        case 'logout':
            require_once $rootDir . '/view/page/login.php';
            sessionEnd();
            header("Location: ./login");
            break;
            
        case 'home':
            require_once $rootDir . '/view/page/home.php';
            $output = pageHome();
            break;
            
        case 'transactions':
            require_once $rootDir . '/model/logic/transactions.php';
            require_once $rootDir . '/view/page/transactions.php';
            $transactionData = filterTransactions(mySqlReadBetween('transactions', 'transactionDate', '2021-09-01T00:00:00+0000', '2021-10-01T00:00:00+0000'), 'TRADE', 'EQUITY', 'AMD');
            $output = pageTransactions(calculateOutstanding($transactionData));
            break;
            
        case 'trades':
            require_once $rootDir . '/model/logic/transactions.php';
            require_once $rootDir . '/model/logic/trades.php';
            require_once $rootDir . '/model/logic/graph.php';
            require_once $rootDir . '/view/page/trades.php';
            require_once $rootDir . '/view/presentation/graph.php';
            $transactionData = filterTransactions(mySqlReadBetween('transactions', 'transactionDate', '2021-09-07T00:00:00+0000', '2021-09-08T00:00:00+0000'), 'TRADE', 'EQUITY', 'AMD');
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
    
    // If requested, load the full page layout (and optionally the nav menu).
    if ($request->fullLayout == 'true') {
        require_once $rootDir . '/view/presentation/layout.php';
        $css = file_get_contents($rootDir . '/view/presentation/style.css');
        if ($request->menu == 'true') {
            require_once $rootDir . '/view/presentation/menu.php';
            $output = presentationLayout($output, $css, presentationMenu());
        } else {
            $output = presentationLayout($output, $css);
        }
    }
    
    // Minify HTML/CSS output and set the Content-Length header for JS loading bars.
    $output = minifyHtml($output);
    header("Content-Length: " . strlen($output));
    
    return $output;
}

// Redirect to the login page, and attempt to log the user in.
// Argument $request should contain an object with the following properties: page, fullLayout, menu, useCached
// Returns an object with information for either the redirect page if not logged in, or the original page request if logged in.
function logInRedirect($request) {
    
    // Login page information
    $loginPage = new stdClass();
    $loginPage->page = 'login';
    $loginPage->fullLayout = 'true';
    $loginPage->menu = 'false';
    $loginPage->useCached = 'false';
    $loginPage->message = 'Please Login.';
    
    // Get login status.
    $loggedIn = sessionCheck();
    
    // If requesting the login page, then return the login page info.
    if ($request->page == 'login') {
        $output = $loginPage;
    }
    
    // If not logged in and requesting a page other than login, return a 401 and output the login page.
    if ($loggedIn == FALSE && $request->page != 'login' && !isset($_POST['username']) && !isset($_POST['password'])) {
        $output = $loginPage;
    }
    
    // If login information has been submitted, then attempt to login. Keep a record of logins.
    if ($loggedIn == FALSE && isset($_POST['username']) && isset($_POST['password'])) {
        $verifyLogin = mySqlVerifyLogin($_POST['username'], $_POST['password']);
        if ($verifyLogin == FALSE) {
            $loginPage->message = 'Incorrect Username/Password.';
            $output = $loginPage;
            $logMessage = 'Failed Login - IP: ' . $_SERVER['REMOTE_ADDR'] . ' User: ' . $_POST['username'] . ' Pass: ' . $_POST['password'];
            saveLog('logins', $logMessage);
        } else {
            sessionLogin($verifyLogin);
            $output = $request;
            $logMessage = 'Successful Login - IP: ' . $_SERVER['REMOTE_ADDR'] . ' User: ' . $_POST['username'];
            saveLog('logins2', $logMessage);
        }
    }
    
    // If already logged in, return the requested page info.
    if ($loggedIn == TRUE) {
        $output = $request;
    }
    
    return $output;
}

?>
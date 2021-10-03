<?php 
/**
 * This is the main controller, which serves communications between the Model, View, and Client.
 * Control functions/logic are in the Controller folder.
 * Application and Data functions/logic are in the Model folder.
 * Presentation functions/logic are in the View folder.
 * 
 * @author Bradley Duke <bradduke88@gmail.com>
 * @version 1.0.0
 */


// Load application settings.
$rootDir = dirname(__DIR__, 1);
$GLOBALS['config'] = parse_ini_file($rootDir . '/config.ini', TRUE);
$GLOBALS['config']['application']['root'] = $rootDir;
date_default_timezone_set($GLOBALS['config']['application']['timezone']);

// Load model and controller functions.
require_once $rootDir . '/model/database/mySql.php';
require_once $rootDir . '/model/database/flatFile.php';
require_once $rootDir . '/model/api/tda.php';
require_once $rootDir . '/model/logic/misc.php';
require_once $rootDir . '/controller/sessions.php';
require_once $rootDir . '/controller/navigation.php';
require_once $rootDir . '/controller/cli.php';

// Create the database connection and start the session.
MySql::connect();
Session::start();

// Determine if app was called from cli or a browser.
$options = [
    "updateTokens:",
    "updateTransactions:"
];
$cliOptions = getopt('', $options);
if (count($cliOptions) > 0) {
    // Execute the command line options.
    cliOptionsExec($cliOptions);
    
} else {
    // Load web app.
    // Get requested info, or set defaults if nothing was requested.
    $request = new stdClass();
    $request->page = $_GET['page'] ?? 'home';
    $request->fullLayout = $_GET['fullLayout'] ?? 'true';
    $request->menu = $_GET['menu'] ?? 'true';
    $request->useCached = $_GET['useCached'] ?? 'false';
    
    // Make sure user is logged in, then display the requested page.
    echo retrievePage(logInRedirect($request));
}

// Clear the database connection
MySql::disconnect();

?>
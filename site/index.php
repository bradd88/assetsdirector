<?php 

// Load application settings.
$rootDir = dirname(__DIR__, 1);
$GLOBALS['config'] = parse_ini_file($rootDir . '/config.ini', TRUE);
$GLOBALS['config']['application']['root'] = $rootDir;
date_default_timezone_set($GLOBALS['config']['application']['timezone']);

// Load model and controller files.
require_once $rootDir . '/model/database/mySql.php';
require_once $rootDir . '/model/database/flatFile.php';
require_once $rootDir . '/model/api/tda.php';
require_once $rootDir . '/model/logic/misc.php';
require_once $rootDir . '/controller/sessions.php';
require_once $rootDir . '/controller/page.php';
require_once $rootDir . '/controller/cli.php';

// Create the database connection.
MySql::connect();

// If the app was called from the command line with arguments, run them instead of displaying a page.
$cli = new Cli;
if ($cli->requested() === TRUE) {
    $cli->exec();  
} else {
    Session::start();
    $page = new Page;
    $page->requested = $_GET;
    if (!isset($page->requested['page']) || $page->requested['page'] == 'login') {
        $page->requested['page'] = 'home';
    }
    echo $page->exec();
}

// Clear the database connection
MySql::disconnect();

?>
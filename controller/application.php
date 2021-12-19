<?php 

class Application
{
    public function __construct()
    {
         // Load application configuration.
        $rootDir = dirname(__DIR__, 1);
        $GLOBALS['config'] = parse_ini_file($rootDir . '/config.ini', TRUE);
        $GLOBALS['config']['application']['root'] = $rootDir;
        date_default_timezone_set($GLOBALS['config']['application']['timezone']);

        // Load controller and model files.
        require_once $rootDir . '/controller/sessions.php';
        require_once $rootDir . '/controller/page.php';
        require_once $rootDir . '/controller/cli.php';
        require_once $rootDir . '/model/database/mySql.php';
        require_once $rootDir . '/model/database/flatFile.php';
        require_once $rootDir . '/model/api/tda.php';
        require_once $rootDir . '/model/logic/misc.php';
    }

    public function exec()
    {
        // Connect to the database and run the appropriate interface based on where the app was called from.
        MySql::connect();
        if (php_sapi_name() === 'cli') {
            $cli = new Cli;
            $cli->exec();  
        } elseif (php_sapi_name() === 'apache2handler') {
            $page = new Page;
            echo $page->exec();
        }
        MySql::disconnect();
    }
}

?>
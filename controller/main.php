<?php 

/** Main controller that autoloads all other controller and model files, and launches the application. */
class Main
{
    /** Load controller and model class files, and run the application. */
    public function __construct(?string $rootDir = NULL)
    {
        $rootDir = $rootDir ?? dirname(__DIR__, 1);
        $this->autoLoad($rootDir . '/controller');
        $this->autoLoad($rootDir . '/model');
        $this->exec();
    }

    /** Recusively autoload all files in specified directory. */
    private function autoLoad(string $dirPath): void
    {
        $items = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($items as $item) {
            $targetPath = $dirPath . '/' . $item;
            if (is_dir($targetPath)) {
                $this->autoLoad($targetPath);
            } else {
                require_once $targetPath;
            }
        }
    }

    /** Determine the instance type, inject dependencies, and run. */
    private function exec(): void
    {
        $serviceContainer = new ServiceContainer();
        if (php_sapi_name() === 'cli') {
            /** @var Cli $cli */
            $cli = $serviceContainer->create('Cli');
            $cli->exec();
        } else {
            /** @var Navigation $navigation */
            $navigation = $serviceContainer->create('Navigation');
            $pageClass = $navigation->exec();
            /** @var AbstractPage $page */
            $page = $serviceContainer->create($pageClass);
            echo $page->exec();
        }
    }
}

?>
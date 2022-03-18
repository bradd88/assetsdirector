<?php 

class Main
{
    // Load controller and model class files, and run the application.
    public function __construct(string $rootDir = NULL)
    {
        $rootDir = $rootDir ?? dirname(__DIR__, 1);
        $this->autoLoad($rootDir . '/controller');
        $this->autoLoad($rootDir . '/model');
        $this->exec();
    }

    // Recusively autoload all files in specified directory.
    private function autoLoad(string $dirPath)
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

    // Determine the instance type, inject dependencies, and run.
    private function exec()
    {
        $instanceType = (php_sapi_name() === 'cli') ? 'Cli' : 'Page';
        $diContainer = new DIContainer();
        $instance = $diContainer->create($instanceType);
        echo $instance->exec();
    }
}

?>
<?php

class Config
{
    private array $config;

    public function __construct(string $rootDirectory = NULL, string $fileName = NULL)
    {
        $rootDirectory = $rootDirectory ?? dirname(__DIR__, 2);
        $fileName = $fileName ?? 'config.ini';
        $this->load($rootDirectory, $fileName);
    }

    private function load(string $rootDirectory, string $fileName)
    {
        $filePath = $rootDirectory . '/' . $fileName;
        $configuration = parse_ini_file($filePath, TRUE, INI_SCANNER_TYPED);
        $configuration['application']['rootDir'] = $rootDirectory;
        date_default_timezone_set($configuration['application']['timezone']);
        $this->config = $configuration;
    }

    public function getSettings(string $section)
    {
        return (object) $this->config[$section];
    }

}

?>
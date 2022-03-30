<?php

/** This class parses an INI file and stores the data as object properties.  */
class Config
{
    private array $config;

    public function __construct(?string $rootDirectory = NULL, ?string $fileName = NULL)
    {
        $rootDirectory = $rootDirectory ?? dirname(__DIR__, 2);
        $fileName = $fileName ?? 'config.ini';
        $this->load($rootDirectory, $fileName);
    }

    /** Load and parse a specified file, and store the data. */
    private function load(string $rootDirectory, string $fileName): void
    {
        $filePath = $rootDirectory . '/' . $fileName;
        $configuration = parse_ini_file($filePath, TRUE, INI_SCANNER_TYPED);
        $configuration['application']['rootDir'] = $rootDirectory;
        date_default_timezone_set($configuration['application']['timezone']);
        $this->config = $configuration;
    }

    /** return a section of the parsed INI file as object properties. */
    public function getSettings(string $section): object
    {
        return (object) $this->config[$section];
    }

}

?>
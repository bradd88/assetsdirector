<?php 

class Log
{
    private object $logSettings;

    public function __construct(Config $config)
    {
        $this->logSettings = $config->getSettings('logs');
    }

    /**
     * Save string to a flat file.
     *
     * @param string $logName
     * @param string $contents
     * @param string $mode (Optional) Defaults to append mode. See fopen() for valid modes.
     * @return void
     */
    public function save(string $logName, string $contents, string $mode = NULL) {
        if ($this->logSettings->enabled === TRUE && $this->logSettings->$logName === TRUE) {
            $filePath = $this->logSettings->logpath . '/' . $logName;
            $output = date("Y/m/d H:i:s") . ' - ' . $contents . PHP_EOL;

            $action = $action ?? 'a';
            $openFile = fopen($filePath, $mode) or die("Unable to open file: $filePath");
            fwrite($openFile, $contents);
            fclose($openFile);
        }
    }
}

?>
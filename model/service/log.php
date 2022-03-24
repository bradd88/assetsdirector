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
            $filePath = $this->logSettings->path . '/' . $logName;
            $mode = $mode ?? 'a';
            $file = fopen($filePath, $mode) or die("Unable to open file: $filePath");
            $output = date("Y/m/d H:i:s") . ' - ' . $contents . PHP_EOL;
            fwrite($file, $output);
            fclose($file);
        }
    }
}

?>
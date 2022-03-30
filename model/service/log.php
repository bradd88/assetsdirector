<?php 

/** This class creates log entries into appropriate log files, if they are enabled in config.ini */
class Log
{
    private object $logSettings;

    public function __construct(Config $config)
    {
        $this->logSettings = $config->getSettings('logs');
    }

    /** Save a string to a flat file, if the log type is enabled. */
    public function save(string $logName, string $contents, ?string $mode = NULL): void
    {
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
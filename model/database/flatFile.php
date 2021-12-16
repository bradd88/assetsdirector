<?php 

// Save data to a flat file by overwriting.
function saveFile($filePath, $contents) {
    $openFile = fopen($filePath, 'w') or die("Unable to open file: $filePath");
    fwrite($openFile, $contents);
    fclose($openFile);
}

// Save data to a flat file by appending.
function appendFile($filePath, $contents) {
    $openFile = fopen($filePath, 'a') or die("Unable to open file: $filePath");
    fwrite($openFile, $contents);
    fclose($openFile);
}

// Save a message to a log file.
function saveLog($logName, $contents) {
    if ($GLOBALS['config']['logs']['enabled'] == 'true' && $GLOBALS['config']['logs'][$logName] == 'true') {
        $filePath = $GLOBALS['config']['logs']['path'] . '/' . $logName;
        $output = date("Y/m/d H:i:s") . ' - ' . $contents . PHP_EOL;
        appendFile($filePath, $output);
    }
}

?>

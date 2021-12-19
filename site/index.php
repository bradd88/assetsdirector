<?php 

// Run the application.
require_once dirname(__DIR__, 1) . '/controller/application.php';
$instance = new Application;
$instance->exec();

?>
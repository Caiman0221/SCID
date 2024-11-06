<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    function debug($data, $isDie = true, $html = false) {
        if ($html) echo '<pre>' . var_export($data, return: true) . '</pre>';
        else echo "\n" . var_export($data, true) . "\n";
        if ($isDie) {
            die();
        }
    }

    include("autoload.php");
    
    $migration = new migration();
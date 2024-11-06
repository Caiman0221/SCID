<?php
    // Показывать ошибки
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // дебагер
    function debug($data, $isDie = true) {
        echo "\n" . var_export($data, true) . "\n";
        if ($isDie) {
            die();
        }
    }

    include("autoload.php");

    $migration = new migration();
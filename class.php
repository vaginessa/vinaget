<?php
/*
 * Vinaget by LTT
 * Script Name: Vinaget
 * Version: 3.3 LTS
 */

/* Set default timezone */
date_default_timezone_set('UTC');

// autoload
spl_autoload_register(function ($class_name) {
    include 'classes/' . $class_name . '.class.php';
});

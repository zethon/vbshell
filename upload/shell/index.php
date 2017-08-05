<?php

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'shell');

// Set your character set (default: ISO-8859-1)
define('CHARSET','ISO-8859-1');

// run script from the root
chdir('..');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_login.php');
require_once(DIR . '/includes/class_xml.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################
eval('print_output("' . fetch_template('shell_main') . '");');

?>
<?php

error_reporting(E_STRICT);

date_default_timezone_set('America/Los_Angeles');

ini_set('memory_limit','798M');

ini_set("max_execution_time","30");

ini_set("display_startup_errors","1");

ini_set("display_errors","1");

$url = $_SERVER['SERVER_NAME'];

if(strpos($url, 'loomis') === FALSE) {
    define('GATEWAY_BRAND', 'canpar');
} else {
    define('GATEWAY_BRAND', 'loomis');
}
 

$uri = isset($_SERVER['REQUEST_URI']) ?  $_SERVER['REQUEST_URI'] : '';

if(strpos($uri, '?')) {
    $uri = substr($uri, 0, strpos($uri, '?'));
}

$uri = str_replace('index,php', '', $uri);
$uri = str_replace('//', '/', $uri);
$parts = explode( "/", $uri);
if($uri[0] == '/') {
    array_shift($parts);
}
for($i= 0; $i< count($parts);$i+=2) {
    $_GET[$parts[$i]] = $parts[$i+1];
}

require_once __DIR__ . "/lib/processor.php";

$processor = new processor;

$processor->setOriginalGet($_GET);

$processor->run();

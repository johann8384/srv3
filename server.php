<?php
$path = '/Applications/XAMPP/xamppfiles/lib/php/pear/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

$path = './libs/';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once 'System/Daemon.php';

System_Daemon::setOption("appName", "FastAGI");
System_Daemon::setOption("authorEmail", "jonathan@scotttechnology.net");

System_Daemon::start();

ob_implicit_flush(true);

require_once 'Zend/Loader.php';
Zend_Loader::registerAutoload();

require_once 'Net/Server.php';

require_once 'libs/FastAGI.php';

$server = Net_Server::create('fork', '127.0.0.1', 10045);
$server->setEndCharacter("\n\n");
$server->setCallbackObject(new FastAGI());

$server->start();

System_Daemon::stop();
?>
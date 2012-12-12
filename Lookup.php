<?php
error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));
Include 'Server.php';


function find_record($domain, $querytype)
{
	switch ($querytype)
	{
		case 'CNAME':
			return 'www.google.com';
			break;
		default:
			return '10.3.2.1';
			break;
	}
	echo "got a request of $querytype for $domain\r\n";
}

$server = new Server('find_record');
$server->listen();

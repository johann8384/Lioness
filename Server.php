<?php
/**
 * DNS Server Class
 *
 * @author Jonathan Creasy <jcreasy@box.com>
 * @version 0.1.0
 * @package Dns
 * @subpackage Server
 * @url http://www.phpclasses.org/browse/file/18217.html
 * This code is released into the public domain.  Feel free to use it and distribute it however you wish.
 * @author Cesar Rodas <saddor@guaranix.org>
 */
class Server
{
	private $func;
	private $socket;
	private $types;
	private $localIp;

	/**
	 * Constructor
	 */
	public function __construct($callback, $ip = '0.0.0.0')
	{
		$this->localIp = $ip;

		$this->func = $callback;

		set_time_limit(0);

		$this->types = Array();
		$this->types['A'] = 1;
		$this->types['NS'] = 2;
		$this->types['CNAME'] = 5;
		$this->types['SOA'] = 6;
		$this->types['WKS']= 11;
		$this->types['PTR'] = 12;
		$this->types['HINFO'] = 13;
		$this->types['MX'] = 15;
		$this->types['TXT'] = 16;
		$this->types['RP'] = 17;
		$this->types['SIG'] = 24;
		$this->types['KEY'] = 25;
		$this->types['LOC'] = 29;
		$this->types['NXT'] = 30;
		$this->types['SRV'] = 33;
		$this->types['AAAA'] = 28;
		$this->types['CERT'] = 37;
		$this->types['A6'] = 38;
		$this->types['AXFR'] = 252;
		$this->types['IXFR'] = 251;
		$this->types['*'] = 255;
	}

	public function listen()
	{
		$this->socket = socket_create(AF_INET,SOCK_DGRAM, SOL_UDP);

		if ($this->socket < 0)
		{
			printf("Error in line %d", __LINE__ - 3);
			return false;
		}

		if (socket_bind($this->socket, $this->localIp, "53") == false)
		{
			printf("Error in line %d",__LINE__-2);
			exit();
		}

		while(1)
		{
			$len = socket_recvfrom($this->socket, $buf, 1024*4, 0, $ip, $port);
			if ($len > 0)
			{
				$this->HandleQuery($buf,$ip,$port);
			}
		}
	}

	function HandleQuery($buf, $clientip, $clientport)
	{
		$domain="";

		$tmp = substr($buf,12);
		$e=strlen($tmp);

		for($i=0; $i < $e; $i++)
		{
			$len = ord($tmp[$i]);
			if ($len==0)
			break;
			$domain .= substr($tmp,$i+1, $len).".";
			$i += $len;
		}

		$i++;$i++; /* move two char */

		$querytype = array_search((string)ord($tmp[$i]), $this->types ) ;
		if (empty($querytype))
		{
			echo "unsupported query type: " . (string)ord($tmp[$i]) . "\r\n";
		}
		$domain = substr($domain,0,strlen($domain)-1);

		$callback = $this->func;

		$ips = $callback($domain, $querytype);

		$answ = $buf[0].$buf[1].chr(129).chr(128).$buf[4].$buf[5].$buf[4].$buf[5].chr(0).chr(0).chr(0).chr(0);
		$answ .= $tmp;
		$answ .= chr(192).chr(12);
		$answ .= chr(0).chr(1).chr(0).chr(1).chr(0).chr(0).chr(0).chr(60).chr(0).chr(4);
		$answ .= $this->TransformIP($ips);

		if (socket_sendto($this->socket,$answ, strlen($answ), 0,$clientip, $clientport) === false)
		{
			printf("Error in socket\n");
		}
	}

	function TransformIP($ip)
	{
		$nip="";
		foreach(explode(".",$ip) as $pip)
		{
			$nip.=chr($pip);
		}
		return $nip;
	}
}
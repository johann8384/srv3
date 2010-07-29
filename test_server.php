#!/Applications/XAMPP/xamppfiles/bin/php
<?php
// server base class
require_once 'Net/Server.php';

// base class for the handler
require_once 'Net/Server/Handler.php';
include_once('/Applications/XAMPP/xamppfiles/lib/php/pear/MDB2.php');
include_once('/Applications/XAMPP/xamppfiles/lib/php/pear/Net/IPv4.php');
include_once('/Applications/XAMPP/xamppfiles/lib/php/pear/Net/DNS.php');
class cache
{
	public static function cache_put($key, $val, $ttl)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan.announcemedia.com', 11211) or die ("Could not connect");
		return $memcache->set($key, $val, false, $ttl) or die ("Failed to save data at the server");
	}
	public static function cache_get($key)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan.announcemedia.com', 11211) or die ("Could not connect");
		return $memcache->get($key);
	}
	public static function cache_increment($key)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan.announcemedia.com', 11211) or die ("Could not connect");
		return $memcache->increment($key, rand(1,10));
	}
}
class srv_lookup
{
	public static function lookup_hosts($service, $location, $domain)
	{
		$servers = Array();
		$ttl = false;
		$host = "_$service._udp.$location.$domain";
		echo "Service: $service, Location: $location, Domain: $domain\r\nHost: $host\r\n";
		$servers = cache::cache_get($host);
		if ($servers === false)
		{
			echo "Cache Status: MISS\r\n";
			$resolver = new Net_DNS_Resolver();
			$response = $resolver->query($host, 'SRV');
			if ($response) {
				foreach ($response->answer as $rr) {
					if($ttl !== false)
					{
						$ttl = $rr->ttl;
					}
					//$servers[$rr->preference][$rr->weight][] = $rr->target.":".$rr->port;
					$servers[$rr->preference][$rr->weight][] = $rr->target;
				}
			}
			cache::cache_put($host, $servers, $ttl);
		}
		else
		{
			echo "Cache Status: HIT\r\n";
		}
		return $servers;
	}
}
class stats
{
	public static function routed_calls($host)
	{
		if (cache::cache_get('routed_calls'.$host) === false)
		{
			cache::cache_put('routed_calls'.$host, 0);
		}
		return cache::cache_get('routed_calls'.$host);
	}
	public static function route_call($host)
	{
		if (cache::cache_get('routed_calls'.$host) === false)
		{
			cache::cache_put('routed_calls'.$host, 0);
		}
		return cache::cache_increment('routed_calls'.$host);
	}
	public static function get_next_server($accountcode, $group = 0)
	{
		//TODO: support multiple preferences with server health checks
		$records = srv_lookup::lookup_hosts('sip', $accountcode, 'voxitas.com');
		if (!array_key_exists($records[$group])) {
			$group = 0;
		}
		$weights = $records[$group];
		print_r($records);
		foreach($weights as $weight=>$servers)
		{
			foreach ($servers as $server)
			{
				$count = self::routed_calls($server);
				echo "Weight: $weight, Server: $server, Count: $count\r\n";
				$counts[$server] = $count / $weight;
			}
		}
		$winner = '';
		$winning_count = max($counts);
		foreach($counts as $destination=>$count)
		{
			if($count <= $winning_count)
			{
			 	$winner = $destination;
			 	$winning_count = $count;
			}
		}
		self::route_call($winner);
		return $winner;
	}
}

class Net_Server_Handler_Talkback extends Net_Server_Handler
{
   /**
    * If the user sends data, send it back to him
    *
    * @access   public
    * @param    integer $clientId
    * @param    string  $data
    */
    var $lines = Array();
    var $command = Array();
    
    function    onReceiveData( $clientId = 0, $data = "" )
    {
    	$tmp = explode(":", $data);
    	$this->command[$clientId][trim($tmp[0])] = trim($tmp[1]);
    	if (trim($tmp[0]) == 'agi_accountcode')
    	{
    		$this->_server->sendData($clientId, $this->process_command($clientId), true);
    	}
    	if (strpos($data, 'result=') !== false)
    	{
    		$tmp = explode(' ', $data);
    		$res = explode('=', $tmp[1]);
    		$this->command[$clientId][$res[0]] = $res[1];
    		if ($this->command[$clientId]['result'] == 0 && $this->command[$clientId]['group'] < 4)
    		{
    			$this->_server->sendData($clientId, $this->process_command($clientId), true);
		    	$this->_server->sendData($clientId, 'HANGUP');
    			$this->_server->closeConnection();
    		}
    		else
    		{
    			$this->_server->sendData($clientId, 'HANGUP');
    			$this->_server->closeConnection();
    		}
       	}
   	}
    function process_command($clientId = 0)
    {
   // 	print_r($this->command);
    	$dest = stats::get_next_server($this->command[$clientId]['agi_accountcode'], $this->make_up_group($clientId));
    	echo "Selected Destination: $dest\r\n";
		return "EXEC Dial \"IAX2/$dest/".$this->command[$clientId]['agi_extension']."|20\"";
    }
    function make_up_group($clientId)
    {
    	if(array_key_exists('group', $this->command[$clientId]))
    	{
    		$this->command[$clientId]['group'] = $this->command[$clientId]['group']+1;
    	}
    	else
    	{
    		$this->command[$clientId]['group'] = 0;
    	}
    	return $this->command[$clientId]['group'];
    }
}
    
    
    
    // create a server that forks new processes
    $server  = &Net_Server::create('fork', '10.10.17.17', 9090);
    
    $handler = &new Net_Server_Handler_Talkback;
    
    // hand over the object that handles server events
    $server->setCallbackObject($handler);
    
    // start the server
    $server->start();
?>

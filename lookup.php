<?php
include_once('MDB2.php');
include_once('Net/IPv4.php');
include_once('Net/DNS.php');

class cache
{
	public static function cache_put($key, $val, $ttl)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan', 11211) or die ("Could not connect");
		return $memcache->set($key, $val, false, $ttl) or die ("Failed to save data at the server");
	}
	public static function cache_get($key)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan', 11211) or die ("Could not connect");
		return $memcache->get($key);
	}
	public static function cache_increment($key)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan', 11211) or die ("Could not connect");
		return $memcache->increment('routed_calls'.$host);
	}
}

class SRV_lookup
{
	function lookup_hosts($service, $location, $domain)
	{
		$servers = Array();
		$ttl = false;
		$host = "_$service._udp.$location.$domain";
		echo "Service: $service, Location: $location, Domain: $domain\r\nHost: $host\r\n";
		$servers = cache_get($host);
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
					$servers[$rr->preference][$rr->weight][] = $rr->target.":".$rr->port;
				}
			}
			cache_put($host, $servers, $ttl);
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
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan', 11211) or die ("Could not connect");
		return $memcache->get('routed_calls' . $host);
	}

	public static function route_call($host)
	{
		$memcache = new Memcache;
		$memcache->connect('www0stl0.lan', 11211) or die ("Could not connect");
		return $memcache->increment('routed_calls' . $host);
	}

	public static function get_next_server($servers)
	{
		//TODO: support multiple preferences with server health checks
		$weights = 
	}
}
?>
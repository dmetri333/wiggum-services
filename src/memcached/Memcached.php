<?php
namespace wiggum\services\memcached;

class Memcached {
	
	protected $memcached;
	
	/**
	 * 
	 */
	public function __construct($config) {
		$this->memcached = new \Memcached();
		$this->memcached->addServer($config['host'], $config['port']);
	}
	
	/**
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @param int $expiration
	 * @return boolean
	 */
	public function set($key, $value, $expiration = 0) {
		return $this->memcached->set($key, $value, $expiration);
	}
	
	/**
	 * 
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		return $this->memcached->get($key);
	}
	
}

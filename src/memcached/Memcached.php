<?php

namespace wiggum\services\memcached;

class Memcached
{

	protected $memcached;

	/**
	 * 
	 */
	public function __construct($config)
	{
		$this->memcached = new \Memcached();
		$this->memcached->addServer($config['host'], $config['port']);
	}

	/**
	 * Delete an item
	 *
	 * @param string $key
	 * @param integer $time
	 * @return boolean
	 */
	public function delete(string $key, int $time = 0)
	{
		return $this->memcached->delete($key, $time);
	}

	/**
	 * Delete multiple items
	 *
	 * @param array $keys
	 * @param integer $time
	 * @return boolean
	 */
	public function deleteMulti(array $keys, int $time = 0)
	{
		return $this->memcached->deleteMulti($keys, $time);
	}

	/**
	 * Invalidate all items in the cache
	 * 
	 * @param integer $delay
	 * @return boolean
	 */
	public function flush(int $delay = 0)
	{
		return $this->memcached->flush($delay);
	}

	/**
	 * Retrieve an item
	 * 
	 * @param string $key
	 * @return mixed
	 */
	public function get(string $key)
	{
		return $this->memcached->get($key);
	}

	/**
	 * Return the result code of the last operation
	 *
	 * @return int
	 */
	public function getResultCode()
	{
		return $this->memcached->getResultCode();
	}

	/**
	 * Store an item
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @param int $expiration
	 * @return boolean
	 */
	public function set(string $key, $value, int $expiration = 0)
	{
		return $this->memcached->set($key, $value, $expiration);
	}

	/**
	 * Set a new expiration on an item
	 *
	 * @param string $key
	 * @param integer $expiration
	 * @return void
	 */
	public function touch(string $key, int $expiration = 0)
	{
		return $this->memcached->touch($key, $expiration);
	}
}

<?php
namespace wiggum\services\storage;

use wiggum\foundation\Application;

class StorageDriver {

     private $app;
     
	/**
	 * 
	 * @param Application $app
	 */
	public function __construct(Application $app)
	{
	    $this->app = $app;
	}
	
	/**
	 * 
	 * @param array $config
	 */
	public function setConfig(array $config)
	{
	    $this->config = $config;
	}
	
}

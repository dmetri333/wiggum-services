<?php
namespace wiggum\services\storage;

use wiggum\foundation\Application;

class Storage {

     private $app;
     private $config;
     private $defaultDriver = 'wiggum\services\storage\drivers\FileUploader';

	/**
	 * 
	 * @param Application $app
	 */
	public function __construct(Application $app)
	{
	    $this->app = $app;
	    $this->config = $this->app->config->get('services.storage', []);
	    $this->defaultDriver = isset($this->config['driver']) ? $this->config['driver'] : $this->defaultDriver;   
	}

	/**
	 * 
	 * @param string $path
	 * @param boolean $createDir
	 * @return StorageDriver
	 */
	public function path($path, $createDir = false)
	{
	    $uploader = new $this->defaultDriver($this->app);
	    $uploader->setConfig($this->config);
	    
	    return $uploader->path($path, $createDir);
	}
	
}

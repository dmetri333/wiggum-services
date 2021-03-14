<?php
namespace wiggum\services\upload;

use wiggum\foundation\Application;

class Upload {

	private $app;
	private $config;

	/**
	 *
	 * @param Application $app
	 */
	public function __construct(Application $app = null)
	{
		$this->app = $app;
		$this->config = isset($app) ? $this->app->config->get('services.upload') : [ 'adapter' => 'wiggum\services\upload\adapter\LocalAdapter' ];
	}
	
	/**
	 * 
	 * @param string $path
	 * @param boolean $createDir
	 * @return UploadAdapter
	 */
	public function path(string $path, bool $createDir = false): UploadAdapter
	{
		$adapter = $this->config['adapter'];
	    $uploader = new $adapter($this->app);
	    
	    return $uploader->path($path, $createDir);
	}
	
}

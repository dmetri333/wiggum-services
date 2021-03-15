<?php
namespace wiggum\services\storage;

use \wiggum\foundation\Application;
use \League\Flysystem\Filesystem;

abstract class StorageAdapter {

    protected $app;

	/**
	 * 
	 * @param array $config
	 */
    public function __construct(Application $app)
	{
        $this->app = $app;
    }

    /**
     *
     * @param array $config
     * @return Filesystem
     */
    abstract public function getFilesystem(array $config): Filesystem;

}
<?php
namespace wiggum\services\image;

use Intervention\Image\ImageManager;

class Image {

    private $config;
    
	/**
	 * 
	 * @param string $driver
	 */
    public function __construct(array $config = [])
	{
	    $this->config = $config;
	}

	/**
	 * 
	 * @param mixed $source
	 * @return Intervention\Image\ImageManager
	 */
	public function make($source)
	{
	    $manager = new ImageManager($this->config);
	    
	    return $manager->make($source);
	}
	
}

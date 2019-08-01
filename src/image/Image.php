<?php
namespace wiggum\services\image;

use Intervention\Image\ImageManager;

class Image {

    private $config;
    
	/**
	 * 
	 * @param array $config
	 */
    public function __construct(array $config = [])
	{
	    $this->config = $config;
	}

	/**
	 * 
	 * @param mixed $source
	 * 
	 * @return \Intervention\Image\Image
	 */
	public function make($source)
	{
	    $manager = new ImageManager($this->config);
	    
	    return $manager->make($source);
	}

	/**
	 * 
	 * @param integer $width
	 * @param integer $height
	 * @param mixed $background
	 * 
	 * @return \Intervention\Image\Image
	 */
	public function canvas($width, $height, $background = null)
	{
	    $manager = new ImageManager($this->config);
	    
	    return $manager->canvas($width, $height, $background);
	}
	
}

<?php
namespace wiggum\services\upload;

class Upload {

	/**
	 * 
	 * @param string $path
	 * @param boolean $createDir
	 * @return Uploader
	 */
	public function path($path, $createDir = false)
	{
	    $uploader = new Uploader();
	    
	    return $uploader->path($path, $createDir);
	}
	
}

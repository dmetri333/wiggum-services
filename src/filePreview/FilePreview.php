<?php
namespace wiggum\services\filePreview;

use \Exception;
use \Imagick;
use \FFmpegMovie;

class FilePreview {
	
	private $config;
	
	/**
	 * 
	 * @param array $config
	 */
	public function __construct(array $config = []) {
		
		$defaultConfig = [
			'ffmpegBinaryPath' => '/usr/local/bin/ffmpeg'
		];
		
		$this->config = array_merge($defaultConfig, $config);
	}
	 
	/**
	 * Create a thumbnail in same directory as original image.
	 * 
	 * @param string $fileInput
	 * @param array $options
	 * @param Callable $callback
	 * @return string[]|unknown
	 */
	 public function create($fileInput, array $options = [], $callback = null) {
	
	 	$fileInfo = [];
	 	try {
	 		
	 		$fileInfo = $this->getFileInfo($fileInput);
	 		
	 		$options['extension'] = isset($options['extension']) ? $options['extension'] : ''; 
	 		$options['path'] = isset($options['path']) ? $options['path'] : $fileInfo['path'];
	 		$options['name'] = isset($options['name']) ? $options['name'] : $fileInfo['name'];
	 		$options['animated'] = isset($options['animated']) ? (int) $options['animated'] : false;
	 		
	 		// Check for supported output format
	 		if ($options['extension'] != 'gif' && $options['extension'] != 'jpg' && $options['extension'] != 'png' && $options['extension'] != '') {
	 			throw new \Exception('Output format not supported');
	 		}
	 		
	 		$fileType = 'other';
	 		
	 		$mimeParts = explode('/', $fileInfo['mimeType']);
	 		if ($mimeParts[0] == 'image') {
	 			$fileType = 'image';
	 		} else if ($mimeParts[0] == 'video') {
	 			$fileType = 'video';
	 		} else {
	 			$fileType = 'other';
	 		}
	 		
	 		//special cases and select best extension
	 		if (in_array($fileInfo['extension'], ['pdf', 'ai', 'psd', 'eps', 'gif'])) {
	 			$fileType = 'image';
	 			
	 			if ($options['extension'] == '')
	 				$options['extension'] = 'png';
	 		}
	 		
	 		//if output needs to maintain animation
	 		if ($options['animated'] && in_array($fileInfo['extension'], ['gif'])) {
	 			$fileType = 'animated-image';
	 			
	 			$options['extension'] = 'gif';
	 		}
	 		
	 		//if no extension has been decided
	 		if ($options['extension'] == '')
	 			$options['extension'] = 'jpg';
	 		
	 			
	 			
	 		$fileOutput = $options['path'].$options['name'].'.'.$options['extension'];
	 		
			$result = false;
	 		if ($fileType == 'video') {
	 			
	 			$this->processVideo($fileInput, $fileOutput, $options['width'], $options['height']);
	 			
	 		} else if ($fileType == 'image') {
	 			
	 			$this->processImage($fileInput, $fileOutput, $options['width'], $options['height']);
	 		
	 		} else if ($fileType == 'animated-image') {
	 					
	 			$this->processAnimatedImage($fileInput, $fileOutput, $options['width'], $options['height']);
	 			
	 		} else if ($fileType == 'other') {
	 			// unoconv
	 			
	 			throw new \Exception('Format not supported');
	 		}
	 		
	 		return ['status' => 'success', 'previewFile' => $this->getFileInfo($fileOutput), 'originalFile' => $fileInfo];
	 		
	 	} catch (Exception $e) {
	 		return ['status' => 'fail', 'error' => true, 'errorMessage' => $e->getMessage(), 'originalFile' => $fileInfo];
	 	}
	 	
	 }
	 
	
	 /**
	  * 
	  * @param unknown $fileInput
	  * @param unknown $fileOutput
	  * @param unknown $width
	  * @param unknown $height
	  * @throws \Exception
	  */
	 private function processVideo($fileInput, $fileOutput, $width, $height) {
	 	// ffmpeg
	 	
	 	$movie = new FFmpegMovie($fileInput, null, $this->config['ffmpegBinaryPath'] );
	 	
	 	$thumbWidth = null;
	 	$thumbHeight = null;
	 	
	 	$currentWidth = $movie->getFrameWidth();
	 	$currentHeight = $movie->getFrameHeight();
	 	
	 	if ($currentWidth > $currentHeight) {
	 		$thumbWidth = (int) $width;
	 		$thumbHeight = (int) ($currentHeight * ($height/$currentWidth));
	 	} else if ($currentWidth < $currentHeight) {
	 		$thumbWidth = (int) ($currentWidth * ($width/$currentHeight));
	 		$thumbHeight = (int) $height;
	 	} else if ($currentWidth == $currentHeight) {
	 		$thumbWidth = (int) $width;
	 		$thumbHeight = (int) $height;
	 	}
	 	
	 	$output = [];
	 	$result = $movie->getFrameAtTime(1, $thumbWidth, $thumbHeight, null, $fileOutput, $output);
	 		
	 	if ($result === false) {
	 		throw new \Exception('Could not generate preview');
	 	}
	 		
	 }
	 
	 /**
	  *
	  * @param unknown $fileInput
	  * @param unknown $fileOutput
	  * @param unknown $width
	  * @param unknown $height
	  * @throws \Exception
	  */
	 private function processImage($fileInput, $fileOutput, $width, $height) {
	 	// image magick
	 
	 	$imagick = new Imagick($fileInput . '[0]');
	 
	 	// add a white background in case the page is transparent
	 	$imagick->setImageBackgroundColor('white');
	 
	 	// rotate based on exif data
	 	$this->autorotate($imagick);
	 
	 	$width = min($width, $imagick->getImageWidth());
	 	$height = min($height, $imagick->getImageHeight());
	 
	 	$result = $imagick->scaleImage($width, $height, true);
	 
	 	if ($result !== false) {
	 
	 		// strip exif metadata to reduce file size
	 		$imagick->stripImage();
	 	 	
	 		$imagick->writeImage($fileOutput);
	 
	 		$imagick->clear();
	 		$imagick->destroy();
	 
	 	}
	 
	 }
	 
	 /**
	  * 
	  * @param unknown $fileInput
	  * @param unknown $fileOutput
	  * @param unknown $width
	  * @param unknown $height
	  */
	 private function processAnimatedImage($fileInput, $fileOutput, $width, $height) {
	 	$image = new Imagick($fileInput);
	 
	 	$image = $image->coalesceImages();
	 
	 	$width = min($width, $image->getImageWidth());
	 	$height = min($height, $image->getImageHeight());
	 
	 	$count = 0;
	 	foreach ($image as $frame) {
	 		$frame->scaleImage($width, $height, true);
	 	}
	 
	 	$image = $image->deconstructImages();
	 	$image->writeImages($fileOutput, true);
	 
	 	$image->clear();
	 	$image->destroy();
	 }
	 
	 
	 /**
	  * 
	  * @param unknown $filePath
	  * @throws BadFunctionCallException
	  * @throws RuntimeException
	  * @return array
	  */
	 private function getFileInfo($filePath) {
	 	if (!function_exists('finfo_open')) {
	 		throw new \BadFunctionCallException('Function does not exist: finfo_open. The Fileinfo extension is required.');
	 	}
	 	
	 	//use this for newer versions of php 5.3
	 	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	 	if (!$finfo) 
	 		throw new \RuntimeException('finfo cannot read mime db');
	 	
	 	$mimeType = finfo_file($finfo, $filePath);
	 	finfo_close($finfo);
	 
	 	if ($mimeType === false) {
	 		throw new \RuntimeException('Could not Generate preview');
	 	}
	 	
	 	//sometimes finfo may include charset with mime (i.e. image/jpeg; charset=binary)
	 	if (strpos($mimeType, ';') > 0) {
	 		$finfoData = explode(';', $mimeType);
	 		$mimeType = $finfoData[0];
	 	}
	 
	 	if (preg_match('#^image/.*$#', $mimeType, $matches))
		 	list($width, $height) = getimagesize($filePath);
	 	
	 	$pathInfo = pathinfo($filePath);
	 	
	 	$fileSize = filesize($filePath);
	 	
	 	return [
	 			'filename' => $pathInfo['basename'],
	 			'name' => $pathInfo['filename'],
	 			'path' => $pathInfo['dirname'],
	 			'extension' => $pathInfo['extension'],
	 			'width' => isset($width) ? $width : '',
	 			'height' => isset($height) ? $height : '',
	 			'size' => $fileSize,
	 			'mimeType' => $mimeType
	 	];
	 	
	 }
	 
	 /**
	  * Rotate image based on image meta data
	  *
	  * @param Imagick $image
	  * @return unknown
	  */
	 private function autorotate(Imagick $image) {
	 	switch ($image->getImageOrientation()) {
	 		case Imagick::ORIENTATION_TOPLEFT:
	 			break;
	 		case Imagick::ORIENTATION_TOPRIGHT:
	 			$image->flopImage();
	 			break;
	 		case Imagick::ORIENTATION_BOTTOMRIGHT:
	 			$image->rotateImage("#000", 180);
	 			break;
	 		case Imagick::ORIENTATION_BOTTOMLEFT:
	 			$image->flopImage();
	 			$image->rotateImage("#000", 180);
	 			break;
	 		case Imagick::ORIENTATION_LEFTTOP:
	 			$image->flopImage();
	 			$image->rotateImage("#000", -90);
	 			break;
	 		case Imagick::ORIENTATION_RIGHTTOP:
	 			$image->rotateImage("#000", 90);
	 			break;
	 		case Imagick::ORIENTATION_RIGHTBOTTOM:
	 			$image->flopImage();
	 			$image->rotateImage("#000", 90);
	 			break;
	 		case Imagick::ORIENTATION_LEFTBOTTOM:
	 			$image->rotateImage("#000", -90);
	 			break;
	 		default: // Invalid orientation
	 			break;
	 	}
	 	$image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
	 	return $image;
	 }
	 
	 
/*
	 $fileInput = /path/blah/user_manual.pdf
	 $options = [width: 100, height: 100, extension: jpg, path: /path/blah/, name: thumb_user_manual ]
	 
	 function generate($fileInput, array $options = [], $callback = null) {
	 	
	 	return [
	 		"status" => "success",	
 			"previewFile" => [
 					"filename" => "thumb_user_manual.png",
 					"name" => "thumb_user_manual",
 					"path" => "/path/blah/",
 					"extension" => "png",
 					"width" => 100,
 					"height" => 100,
 					"size" => 16905,
 					"mimetype" => "image/png"
 			],
	 		"originalFile" => [
	 			"filename" => "user_manual.pdf",
	 			"name" => "user_manual",
	 			"path" => "/path/blah/",
	 			"extension" => "pdf",
	 			"width" => 500,
	 			"height" => 500,
	 			"size" => 416905,
	 			"mimetype" => "application/pdf"
	 		]
	 	];	
	 }
	 
*/	 
	 
	 
	 
}
?>
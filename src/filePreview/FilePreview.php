<?php
namespace wiggum\services\filePreview;

use \Exception;
use \Imagick;
use \FFmpegMovie;
use wiggum\services\filePreview\lib\VideoGif;

class FilePreview {

	private $config;

	/**
	 *
	 * @param array $config
	 */
	public function __construct(array $config = []) {

		$defaultConfig = [
				'ffmpegBinaryPath' => '/usr/local/bin/ffmpeg',
				'ffprobeBinaryPath' => '/usr/local/bin/ffprobe'
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

	 			if ($options['animated']) {
	 				$this->processVideoAnimation($fileInput, $fileOutput, $options['width'], $options['height']);
		 			
	 			} else {
	 				$this->processVideoFrame($fileInput, $fileOutput, $options['width'], $options['height']);
	 				
	 			}
	 				
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
	private function processVideoFrame($fileInput, $fileOutput, $width, $height) {
		// ffmpeg
		 
		$movie = new FFmpegMovie($fileInput, null, $this->config['ffmpegBinaryPath'] );
		 
		$dimensions = $this->getDimensions($width, $height, $movie->getFrameWidth(), $movie->getFrameHeight());
		 
		$width = $dimensions['width'];
		$height = $dimensions['height'];
		 
		$output = [];
		$result = $movie->getFrameAtTime(1, $width, $height, null, $fileOutput, $output);

		if ($result === false) {
			throw new \Exception('Could not generate preview');
		}

	}
	
	/**
	 * 
	 * @param string $fileInput
	 * @param string $fileOutput
	 * @param int $width
	 * @param int $height
	 */
	private function processVideoAnimation($fileInput, $fileOutput, $width, $height) {
		$movie = new FFmpegMovie($fileInput, null, $this->config['ffmpegBinaryPath'] );
		$frameRate = (int) ($movie->getFrameRate() / 2);
		$duration = $movie->getDuration();
		$options = [
				'binaries' => [
						'ffmpeg.binaries' => $this->config['ffmpegBinaryPath'], 
						'ffprobe.binaries' => $this->config['ffprobeBinaryPath']
				]
		];
		$videoStart = 3;
		$gifLength = 3;
		if ($duration < ($videoStart + $gifLength)) {
			if ($duration < $gifLength) {
				$videoStart = 0;
				$gifLength = $duration;
			} else {
				$videoStart = 0;
			}
		}
		$videoGif = new VideoGif('/content/tmp', $options);
		$videoGif->create($fileInput, $fileOutput, $frameRate * $gifLength, 200 / $frameRate, $width, $height, $videoStart, $videoStart + $gifLength);
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

		$imagick= $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
		
		// rotate based on exif data
		$this->autorotate($imagick);

		$dimensions = $this->getDimensions($width, $height, $imagick->getImageWidth(), $imagick->getImageHeight());
		 
		$width = $dimensions['width'];
		$height = $dimensions['height'];
		 
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

		$dimensions = $this->getDimensions($width, $height, $image->getImageWidth(), $image->getImageHeight());

		$width = $dimensions['width'];
		$height = $dimensions['height'];

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
		 			'path' => $pathInfo['dirname'].DIRECTORY_SEPARATOR,
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

	/**
	 *
	 * @param integer $width
	 * @param integer $height
	 * @param integer $originalWidth
	 * @param integer $originalHeight
	 * @param string $option
	 * @throws \RuntimeException
	 * @return integer[]
	 */
	private function getDimensions($width, $height, $originalWidth, $originalHeight, $option = 'auto') {
		switch ($option) {
			case 'exact':
				$optimalWidth = $width;
				$optimalHeight = $height;
				break;
			case 'heightBound':
				$optimalHeight = $originalHeight < $height ? $originalHeight : $height;
				$optimalWidth = $this->getSizeByFixedHeight($optimalHeight, $originalWidth, $originalHeight);
				break;
			case 'widthBound':
				$optimalWidth = $originalWidth < $width ? $originalWidth : $width;
				$optimalHeight = $this->getSizeByFixedWidth($optimalWidth, $originalWidth, $originalHeight);
				break;
			case 'auto': // bounding box
				$optionArray = $this->getSizeByAuto($width, $height, $originalWidth, $originalHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			default:
				throw new \RuntimeException('Resize option "' . $option . '" not defined');
		}
		
		return array('width' => (int) $optimalWidth, 'height' => (int) $optimalHeight);
	}
	
	/**
	 *
	 * @param integer $height
	 * @return number
	 */
	private function getSizeByFixedHeight($height, $originalWidth, $originalHeight) {
		if ($height == $originalHeight) {
			return $originalWidth;
		} else {
			$ratio = $originalWidth / $originalHeight;
			return $height * $ratio;
		}
	}

	/**
	 *
	 * @param integer $width
	 * @return number
	 */
	private function getSizeByFixedWidth($width, $originalWidth, $originalHeight) {
		if ($width == $originalWidth) {
			return $originalHeight;
		} else {
			$ratio = $originalHeight / $originalWidth;
			return $width * $ratio;
		}
	}
	/**
	 *
	 * @param integer $width
	 * @param integer $height
	 * @param integer $originalWidth
	 * @param integer $originalHeight
	 * @return integer[]
	 */
	private function getSizeByAuto($width, $height, $originalWidth, $originalHeight) {
		
		if ($originalHeight < $originalWidth) {
			//Image to be resized is wider (landscape)
			$optimalWidth = $originalWidth < $width ? $originalWidth : $width;
			$optimalHeight = $this->getSizeByFixedWidth($optimalWidth, $originalWidth, $originalHeight);
		} elseif ($originalHeight > $originalWidth) {
			//Image to be resized is taller (portrait)
			$optimalHeight = $originalHeight < $height ? $originalHeight : $height;
			$optimalWidth = $this->getSizeByFixedHeight($optimalHeight, $originalWidth, $originalHeight);
		} else {
			//Image to be resizerd is a square
			if ($height < $width) {
				$optimalWidth = $originalWidth < $width ? $originalWidth : $width;
				$optimalHeight = $this->getSizeByFixedWidth($optimalWidth, $originalWidth, $originalHeight);
			} else if ($height > $width) {
				$optimalHeight = $originalHeight < $height ? $originalHeight : $height;
				$optimalWidth = $this->getSizeByFixedHeight($optimalHeight, $originalWidth, $originalHeight);
			} else {
				//Sqaure being resized to a square
				$optimalWidth = $originalWidth < $width ? $originalWidth : $width;
				$optimalHeight = $originalHeight < $height ? $originalHeight : $height;
			}
		}
		
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
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

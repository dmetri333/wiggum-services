<?php
namespace wiggum\services\upload;

use wiggum\foundation\Application;
use wiggum\commons\helpers\FileHelper;
use wiggum\commons\helpers\SecurityHelper;

abstract class UploadAdapter {
	
	protected $app;

    protected $errors     = [];
    protected $imgMimes   = ['image/gif', 'image/jpeg', 'image/png'];
    
    protected $fileTemp;
    protected $fileSize;
    protected $fileType;
    protected $fileExt;
    
    protected $uploadPath = '';
    protected $allowedTypes = [];
	protected $fileName = '';
	protected $fileNameOverride = '';
    protected $originalName = '';
    protected $createDir = false;
    protected $overwrite = false;
    protected $maxSize = 0;
    protected $maxWidth = 0;
    protected $maxHeight = 0;
    protected $minWidth = 0;
    protected $minHeight = 0; 
    protected $maxFilenameIncrement = 100;
    protected $encryptName = false;
    protected $xssClean = false;
    protected $removeSpaces = true;

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
     * @param string $uploadPath
     * @param boolean $createDir
     * @return \wiggum\services\upload\UploadAdapter
     */
	public function path(string $uploadPath, $createDir = false): UploadAdapter
	{
	    $this->uploadPath = rtrim($uploadPath, '/').'/';
	    $this->createDir = $createDir;
	    
	    return $this;
	}
	
	/**
	 *
	 * @param bool $overwrite
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function overwrite(bool $overwrite): UploadAdapter
	{
	    $this->overwrite = $overwrite;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param array $allowedTypes
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function allowedTypes(array $allowedTypes): UploadAdapter
	{
	    $this->allowedTypes = $allowedTypes;
	    
	    return $this;
	}
	
	/**
	 *
	 * @param bool $fileName
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function fileName(string $fileName): UploadAdapter
	{
	    $this->fileNameOverride = $fileName;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxSize
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function maxSize($maxSize): UploadAdapter
	{
	    $this->maxSize = $maxSize < 0 ? 0 : (int) $maxSize;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxWidth
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function maxWidth($maxWidth): UploadAdapter
	{
	    $this->maxWidth = $maxWidth < 0 ? 0 : (int) $maxWidth;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxHeight
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function maxHeight($maxHeight): UploadAdapter
	{
	    $this->maxHeight = $maxHeight < 0 ? 0 : (int) $maxHeight;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $minWidth
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function minWidth($minWidth): UploadAdapter
	{
	    $this->minWidth = $minWidth < 0 ? 0 : (int) $minWidth;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $minHeight
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function minHeight($minHeight): UploadAdapter
	{
	    $this->minHeight = $minHeight < 0 ? 0 : (int) $minHeight;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxFilenameIncrement
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function maxFilenameIncrement($maxFilenameIncrement): UploadAdapter
	{
	    $this->maxFilenameIncrement = $maxFilenameIncrement;
	    
	    return $this;
	}

	/**
	 * 
	 * @param boolean $xssClean
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function xssClean($xssClean): UploadAdapter
	{
	    $this->xssClean = $xssClean;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param boolean $encryptName
	 * @return \wiggum\services\upload\UploadAdapter
	 */
	public function encryptName($encryptName): UploadAdapter
	{
	    $this->encryptName = $encryptName;
	    
	    return $this;
	}

	/**
	 * 
	 * @param array $file
	 * @return boolean
	 */
	public abstract function upload(array $file): bool;

 	/**
 	 * Finalized Data Array
 	 *
 	 * @param string $index
 	 * @return object
 	 */
 	public function data($index = null): object
 	{
 	    $data = [
 	        'fileName'		=> $this->fileName,
 	        'fileType'		=> $this->fileType,
 	        'filePath'		=> $this->uploadPath,
 	        'fullPath'		=> $this->uploadPath.$this->fileName,
 	        'baseName'		=> basename($this->fileName, '.'.$this->fileExt),
 	        'originalName'	=> $this->originalName,
 	        'fileExt'		=> $this->fileExt,
 	        'fileSize'		=> $this->fileSize,
 	        'isImage'		=> $this->isImage()
 	    ];
 	    
 	    $data['image'] = $this->getImageProperties($this->uploadPath.$this->fileName);
 	    
 	    if (!empty($index)) {
 	        return isset($data[$index]) ? $data[$index] : null;
 	    }
 	    
 	    return (object) $data;
 	}

 	/**
 	 * Prep Filename
 	 *
 	 * Prevents possible script execution from Apache's handling
 	 * of files' multiple extensions.
 	 *
 	 * @link https://httpd.apache.org/docs/1.3/mod/mod_mime.html#multipleext
 	 *
 	 * @param string $filename
 	 * @return string
 	 */
 	protected function prepFileName($filename): string
 	{
 	    $extPos = strrpos($filename, '.');
 	    if (empty($this->allowedTypes) || $extPos === false) {
 	        return $filename;
 	    }
 	    
 	    $ext = strtolower(substr($filename, $extPos));
 	    $filename = substr($filename, 0, $extPos);
 	    return str_replace('.', '_', $filename).$ext;
 	}
 	
 	/**
 	 * 
 	 * @param string $fileName
 	 * @return string
 	 */
	protected function createFileName($fileName): string
	{
	    
	    if ($this->encryptName === true) {
	        $fileName = md5(uniqid(mt_rand())).'.'.$this->fileExt;
	    }
	    
	    if ($this->overwrite === true || !file_exists($this->uploadPath.$fileName)) {
	        return $fileName;
	    }
	    
	    $fileName = str_replace('.'.$this->fileExt, '', $fileName);
	    
	    $newFileName = '';
	    for ($i = 1; $i < $this->maxFilenameIncrement; $i++) {
	        if (!file_exists($this->uploadPath.$fileName.$i.'.'.$this->fileExt)) {
	            $newFileName = $fileName.$i.'.'.$this->fileExt;
	            break;
	        }
	    }
	    
	    return $newFileName;
	}
	
	
	/**
	 * Validate Upload Path
	 *
	 * Verifies that it is a valid upload path with proper permissions.
	 *
	 * @return boolean
	 */
	protected function validateUploadPath(): bool
	{
	    if ($this->uploadPath === '') {
	        $this->setError('upload.noFilepath');
	        return false;
	    }
	    
	    if (realpath($this->uploadPath) !== false) {
	        $this->uploadPath = str_replace('\\', '/', realpath($this->uploadPath));
	    }
	    
	    if ($this->createDir) {
	        FileHelper::createFolder($this->uploadPath);
	    }
	    
	    if (!is_dir($this->uploadPath)) {
	        $this->setError('upload.noFilepath');
	        return false;
	    }
	    
	    if (!FileHelper::isWritable($this->uploadPath)) {
	        $this->setError('upload.notWritable');
	        return false;
	    }
	    
	    $this->uploadPath = preg_replace('/(.+?)\/*$/', '\\1/', $this->uploadPath);
	    return true;
	}
	
	/**
	 * Verify that the filetype is allowed
	 *
	 * @param boolean $ignore_mime
	 * @return boolean
	 */
	protected function isAllowedFileType(): bool
	{
	    if (empty($this->allowedTypes)) {
	        return true;
	    }
	    
	    if (!in_array($this->fileExt, $this->allowedTypes, true)) {
	        return false;
	    }
	    
	    // Images get some additional checks
	    if (in_array($this->fileExt, ['gif', 'jpg', 'jpeg', 'jpe', 'png'], true) && @getimagesize($this->fileTemp) === false) {
	        return false;
	    }
	    
	    return true;
	}
	
	/**
	 * Verify that the file is within the allowed size
	 *
	 * @return boolean
	 */
	protected function isAllowedFilesize(): bool
	{
	    return $this->maxSize === 0 || $this->maxSize > $this->fileSize;
	}
	
	/**
	 * Validate the image
	 *
	 * @return boolean
	 */
	protected function isImage(): bool
	{
	    return in_array($this->fileType, $this->imgMimes, true);
	}
	
	/**
	 * Verify that the image is within the allowed width/height
	 *
	 * @return boolean
	 */
	protected function isAllowedDimensions(): bool
	{
	    if (!$this->isImage()) {
	        return true;
	    }
	    
	    if (function_exists('getimagesize')) {
	        $D = @getimagesize($this->fileTemp);
	        
	        if ($this->maxWidth > 0 && $D[0] > $this->maxWidth) {
	            return false;
	        }
	        
	        if ($this->maxHeight > 0 && $D[1] > $this->maxHeight) {
	            return false;
	        }
	        
	        if ($this->minWidth > 0 && $D[0] < $this->minWidth) {
	            return false;
	        }
	        
	        if ($this->minHeight > 0 && $D[1] < $this->minHeight) {
	            return false;
	        }
	    }
	    
	    return true;
	}
	
	/**
	 * Set Image Properties
	 *
	 * Uses GD to determine the width/height/type of image
	 *
	 * @param	string	$path
	 * @return	array
	 */
	protected function getImageProperties($path = ''): array
	{
	    $image = [];
	    if ($this->isImage() && function_exists('getimagesize')) {
	        if (false !== ($D = @getimagesize($path))) {
	            $types = [1 => 'gif', 2 => 'jpeg', 3 => 'png'];
	            
	            $image['width'] = $D[0];
	            $image['height'] = $D[1];
	            $image['type']	= isset($types[$D[2]]) ? $types[$D[2]] : 'unknown';
	        }
	    }
	    
	    return $image;
	}
	
    /**
     * Runs the file through the XSS clean function
     *
     * This prevents people from embedding malicious code in their files.
     * I'm not sure that it won't negatively affect certain files in unexpected ways,
     * but so far I haven't found that it causes trouble.
     *
     * @return string|boolean
     */
    protected function doXssClean()
    {
        $file = $this->fileTemp;
        
        if (filesize($file) == 0) {
            return false;
        }
        
        if (memory_get_usage() && ($memory_limit = ini_get('memory_limit')) > 0)
        {
            $memory_limit = str_split($memory_limit, strspn($memory_limit, '1234567890'));
            if ( ! empty($memory_limit[1]))
            {
                switch ($memory_limit[1][0])
                {
                    case 'g':
                    case 'G':
                        $memory_limit[0] *= 1024 * 1024 * 1024;
                        break;
                    case 'm':
                    case 'M':
                        $memory_limit[0] *= 1024 * 1024;
                        break;
                    default:
                        break;
                }
            }
            
            $memory_limit = (int) ceil(filesize($file) + $memory_limit[0]);
            ini_set('memory_limit', $memory_limit); // When an integer is used, the value is measured in bytes. - PHP.net
        }
        
        // If the file being uploaded is an image, then we should have no problem with XSS attacks (in theory), but
        // IE can be fooled into mime-type detecting a malformed image as an html file, thus executing an XSS attack on anyone
        // using IE who looks at the image. It does this by inspecting the first 255 bytes of an image. To get around this
        // CI will itself look at the first 255 bytes of an image to determine its relative safety. This can save a lot of
        // processor power and time if it is actually a clean image, as it will be in nearly all instances _except_ an
        // attempted XSS attack.
        
        if (function_exists('getimagesize') && @getimagesize($file) !== false) {
            if (($file = @fopen($file, 'rb')) === false) { // "b" to force binary
                return false; // Couldn't open the file, return FALSE
            }
            
            $openingBytes = fread($file, 256);
            fclose($file);
            
            // These are known to throw IE into mime-type detection chaos
            // <a, <body, <head, <html, <img, <plaintext, <pre, <script, <table, <title
            // title is basically just in SVG, but we filter it anyhow
            
            // if it's an image or no "triggers" detected in the first 256 bytes - we're good
            return ! preg_match('/<(a|body|head|html|img|plaintext|pre|script|table|title)[\s>]/i', $openingBytes);
        }
        
        if (($data = @file_get_contents($file)) === false) {
            return false;
        }
        
        return SecurityHelper::xssClean($data, true);
    }
    
	/**
	 *
	 * @param string $error
	 * @return void
	 */
    public function setError(string $error): void
    {
        $this->errors[] = $error;
    }
    
    /**
     * @param int $index
     * @return string
     */
    public function getError(int $index): string
    {
        return isset($this->errors[$index]) ? $this->errors[$index] : '';
    }
    
    /**
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

}

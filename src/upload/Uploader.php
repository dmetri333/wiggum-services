<?php
namespace wiggum\services\upload;

use wiggum\commons\helpers\FileHelper;
use wiggum\commons\helpers\SecurityHelper;

class Uploader {

    private $errors     = [];
    private $imgMimes   = ['image/gif', 'image/jpeg', 'image/png'];
    
    public $fileTemp;
    public $fileSize;
    public $fileType;
    public $fileExt;
    
    public $uploadPath = '';
    public $allowedTypes = [];
	public $fileName = '';
	public $fileNameOverride = '';
    public $originalName = '';
    public $createDir = false;
    public $overwrite = false;
    public $maxSize = 0;
    public $maxWidth = 0;
    public $maxHeight = 0;
    public $minWidth = 0;
    public $minHeight = 0; 
    public $maxFilenameIncrement = 100;
    public $encryptName = false;
    public $xssClean = false;
    public $removeSpaces = true;
	
    /**
     * 
     * @param string $uploadPath
     * @param boolean $createDir
     * @return \wiggum\services\upload\Uploader
     */
	public function path($uploadPath, $createDir = false): Uploader
	{
	    $this->uploadPath = rtrim($uploadPath, '/').'/';
	    $this->createDir = $createDir;
	    
	    return $this;
	}
	
	/**
	 *
	 * @param bool $overwrite
	 * @return \wiggum\services\upload\Uploader
	 */
	public function overwrite(bool $overwrite): Uploader
	{
	    $this->overwrite = $overwrite;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param array $allowedTypes
	 * @return \wiggum\services\upload\Uploader
	 */
	public function allowedTypes(array $allowedTypes): Uploader
	{
	    $this->allowedTypes = $allowedTypes;
	    
	    return $this;
	}
	
	/**
	 *
	 * @param bool $fileName
	 * @return \wiggum\services\upload\Uploader
	 */
	public function fileName(string $fileName): Uploader
	{
	    $this->fileNameOverride = $fileName;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxSize
	 * @return \wiggum\services\upload\Uploader
	 */
	public function maxSize($maxSize): Uploader
	{
	    $this->maxSize = $maxSize < 0 ? 0 : (int) $maxSize;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxWidth
	 * @return \wiggum\services\upload\Uploader
	 */
	public function maxWidth($maxWidth): Uploader
	{
	    $this->maxWidth = $maxWidth < 0 ? 0 : (int) $maxWidth;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxHeight
	 * @return \wiggum\services\upload\Uploader
	 */
	public function maxHeight($maxHeight): Uploader
	{
	    $this->maxHeight = $maxHeight < 0 ? 0 : (int) $maxHeight;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $minWidth
	 * @return \wiggum\services\upload\Uploader
	 */
	public function minWidth($minWidth): Uploader
	{
	    $this->minWidth = $minWidth < 0 ? 0 : (int) $minWidth;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $minHeight
	 * @return \wiggum\services\upload\Uploader
	 */
	public function minHeight($minHeight): Uploader
	{
	    $this->minHeight = $minHeight < 0 ? 0 : (int) $minHeight;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param integer $maxFilenameIncrement
	 * @return \wiggum\services\upload\Uploader
	 */
	public function maxFilenameIncrement($maxFilenameIncrement): Uploader
	{
	    $this->maxFilenameIncrement = $maxFilenameIncrement;
	    
	    return $this;
	}

	/**
	 * 
	 * @param boolean $xssClean
	 * @return \wiggum\services\upload\Uploader
	 */
	public function xssClean($xssClean): Uploader
	{
	    $this->xssClean = $xssClean;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param boolean $encryptName
	 * @return \wiggum\services\upload\Uploader
	 */
	public function encryptName($encryptName): Uploader
	{
	    $this->encryptName = $encryptName;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param array $file
	 * @return boolean
	 */
	public function upload($file)
	{
 	   
	    if (!isset($file)) {
	        $this->setError('upload.noFileSelected');
	        return false;
	    }
	    
	    // Is the upload path valid?
	    if (!$this->validateUploadPath()) {
	        // errors will already be set by validateUploadPath() so just return false
	        return false;
	    }
	    
	    // Was the file able to be uploaded? If not, determine the reason why.
	    if (!is_uploaded_file($file['tmp_name'])) {
	        $error = isset($file['error']) ? $file['error'] : 4;
	        
	        switch ($error)
	        {
	            case UPLOAD_ERR_INI_SIZE:
	                $this->setError('upload.fileExceedsLimit');
	                break;
	            case UPLOAD_ERR_FORM_SIZE:
	                $this->setError('upload.fileExceedsFormLimit');
	                break;
	            case UPLOAD_ERR_PARTIAL:
	                $this->setError('upload.filePartial');
	                break;
	            case UPLOAD_ERR_NO_FILE:
	                $this->setError('upload.noFileSelected');
	                break;
	            case UPLOAD_ERR_NO_TMP_DIR:
	                $this->setError('upload.noTempDirectory');
	                break;
	            case UPLOAD_ERR_CANT_WRITE:
	                $this->setError('upload.unableToWriteFile');
	                break;
	            case UPLOAD_ERR_EXTENSION:
	                $this->setError('upload.stoppedExtension');
	                break;
	            default:
	                $this->setError('upload.noFileSelected');
	                break;
	        }
	        
	        return false;
	    }
	    
 	    // Set the uploaded data as class variables
 	    $this->fileTemp       = $file['tmp_name'];
 	    $this->fileSize       = $file['size'];
 	    $this->fileName       = $this->prepFileName($file['name']);
 	    $this->originalName   = $this->fileName;
 	    $this->fileExt	      = FileHelper::extension($this->fileName);
 	    $this->fileType       = FileHelper::mimeType($file['tmp_name'], $file['type']);
 	    
	    // Is the file type allowed to be uploaded?
	    if (!$this->isAllowedFileType()) {
	        $this->setError('upload.invalidFiletype');
	        return false;
		}

		if ($this->fileNameOverride !== '') {
			$this->fileName = $this->prepFileName($this->fileNameOverride);
			
			// If no extension was provided in the file_name config item, use the uploaded one
			if (strpos($this->fileNameOverride, '.') === false) {
				$this->fileName .= $this->fileExt;
			} else {
				// An extension was provided, let's have it!
				$this->fileExt	= FileHelper::extension($this->fileNameOverride);
			}

			if (!$this->isAllowedFileType()) {
				$this->setError('upload.invalidFiletype');
				return false;
			}
		}
	    
	    // Is the file size within the allowed maximum?
 	    if (!$this->isAllowedFilesize()) {
 	        $this->setError('upload.invalidFilesize');
 	        return false;
 	    }
	    
	    // Are the image dimensions within the allowed size?
	    // Note: This can fail if the server has an open_basedir restriction.
	    if (!$this->isAllowedDimensions()) {
	        $this->setError('upload.invalidDimensions');
	        return false;
	    }
	    
 	    // Sanitize the file name for security
	    $this->fileName = SecurityHelper::sanitizeFilename($this->fileName);
	    
	    // Remove white spaces in the name
	    if ($this->removeSpaces === true) {
	        $this->fileName = preg_replace('/\s+/', '_', $this->fileName);
	    }
	    
	    /*
	     * Validate the file name
	     * This function appends an number onto the end of
	     * the file if one with the same name already exists.
	     * If it returns false there was a problem.
	     */
	    if (false === ($this->fileName = $this->createFileName($this->fileName))) {
	        $this->setError('upload.badFilename');
	        return false;
	    }
	    
	    /*
	     * Run the file through the XSS hacking filter
	     * This helps prevent malicious code from being
	     * embedded within a file. Scripts can easily
	     * be disguised as images or other file types.
	     */
	    if ($this->xssClean && $this->doXssClean() === false) {
	        $this->setError('upload.unableToWriteFile');
	        return false;
	    }
	    
	    /*
	     * Move the file to the final destination
	     * To deal with different server configurations
	     * we'll attempt to use copy() first. If that fails
	     * we'll use move_uploaded_file(). One of the two should
	     * reliably work in most environments
	     */
	    if (!@copy($this->fileTemp, $this->uploadPath.$this->fileName)) {
	        if (!@move_uploaded_file($this->fileTemp, $this->uploadPath.$this->fileName)) {
	            $this->setError('upload.destinationError');
	            return false;
	        }
	    }
	   
	    return true;
 	}

 	/**
 	 * Finalized Data Array
 	 *
 	 * @param string $index
 	 * @return object
 	 */
 	public function data($index = null)
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
 	private function prepFileName($filename)
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
 	 * @return string|boolean
 	 */
	private function createFileName($fileName)
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
	    
	    if ($newFileName === '') {
	        return false;
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
	private function validateUploadPath()
	{
	    if ($this->uploadPath === '') {
	        $this->setError('upload_no_filepath');
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
	private function isAllowedFileType()
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
	private function isAllowedFilesize()
	{
	    return $this->maxSize === 0 || $this->maxSize > $this->fileSize;
	}
	
	/**
	 * Validate the image
	 *
	 * @return boolean
	 */
	private function isImage()
	{
	    return in_array($this->fileType, $this->imgMimes, true);
	}
	
	/**
	 * Verify that the image is within the allowed width/height
	 *
	 * @return boolean
	 */
	private function isAllowedDimensions()
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
	private function getImageProperties($path = '')
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
     * @return	string
     */
    private function doXssClean()
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
     */
    public function setError($error)
    {
        $this->errors[] = $error;
    }
    
    /**
     * @param int $index
     * @return array
     */
    public function getError($index)
    {
        return isset($this->errors[$index]) ? $this->errors[$index] : '';
    }
    
    /**
     * 
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

}

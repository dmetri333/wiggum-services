<?php
namespace wiggum\services\storage\drivers;

use wiggum\services\storage\StorageDriver;

class FileUploader extends StorageDriver {

    private $errors     = [];
    private $pngMimes   = ['image/x-png'];
    private $jpegMimes  = ['image/jpg', 'image/jpe', 'image/jpeg', 'image/pjpeg'];
    private $imgMimes   = ['image/gif', 'image/jpeg', 'image/png'];
    
    public $fileTemp;
    public $fileSize;
    public $fileType;
    public $fileExt;
    
    public $uploadPath = '';
    public $allowedTypes = [];
    public $fileName = '';
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
	
	public function path($uploadPath, $createDir = false)
	{
	    $this->uploadPath = rtrim($uploadPath, '/').'/';
	    $this->createDir = $createDir;
	    
	    return $this;
	}
	
	public function allowedTypes(array $allowedTypes)
	{
	    $this->allowedTypes = $allowedTypes;
	    
	    return $this;
	}
	
	/*public function fileName($fileName)
	{
	    $this->fileName = $fileName;
	    
	    return $this;
	}
	*/
	
	public function maxSize($maxSize)
	{
	    $this->maxSize = $maxSize < 0 ? 0 : (int) $maxSize;
	    
	    return $this;
	}
	
	public function maxWidth($maxWidth)
	{
	    $this->maxWidth = $maxWidth < 0 ? 0 : (int) $maxWidth;
	    
	    return $this;
	}
	
	public function maxHeight($maxHeight)
	{
	    $this->maxHeight = $maxHeight < 0 ? 0 : (int) $maxHeight;
	    
	    return $this;
	}
	
	public function minWidth($minWidth)
	{
	    $this->minWidth = $minWidth < 0 ? 0 : (int) $minWidth;
	    
	    return $this;
	}
	
	public function minHeight($minHeight)
	{
	    $this->minHeight = $minHeight < 0 ? 0 : (int) $minHeight;
	    
	    return $this;
	}
	
	public function maxFilenameIncrement($maxFilenameIncrement)
	{
	    $this->maxFilenameIncrement = $maxFilenameIncrement;
	    
	    return $this;
	}

	public function xssClean($xssClean)
	{
	    $this->xssClean = $xssClean;
	    
	    return $this;
	}
	
	public function encryptName($encryptName)
	{
	    $this->encryptName = $encryptName;
	    
	    return $this;
	}
	
	/**
	 * 
	 * @param array $file
	 * @return boolean
	 */
	public function upload($file) {
 	   
	    if (!isset($file)) {
	        $this->setError('upload_no_file_selected');
	        return false;
	    }
	    
	    // Is the upload path valid?
	    if (!$this->validateUploadPath()) {
	        // errors will already be set by validateUploadPath() so just return FALSE
	        return false;
	    }
	    
	    // Was the file able to be uploaded? If not, determine the reason why.
	    if (!is_uploaded_file($file['tmp_name'])) {
	        $error = isset($file['error']) ? $file['error'] : 4;
	        
	        switch ($error)
	        {
	            case UPLOAD_ERR_INI_SIZE:
	                $this->setError('upload_file_exceeds_limit');
	                break;
	            case UPLOAD_ERR_FORM_SIZE:
	                $this->setError('upload_file_exceeds_form_limit');
	                break;
	            case UPLOAD_ERR_PARTIAL:
	                $this->setError('upload_file_partial');
	                break;
	            case UPLOAD_ERR_NO_FILE:
	                $this->setError('upload_no_file_selected');
	                break;
	            case UPLOAD_ERR_NO_TMP_DIR:
	                $this->setError('upload_no_temp_directory');
	                break;
	            case UPLOAD_ERR_CANT_WRITE:
	                $this->setError('upload_unable_to_write_file');
	                break;
	            case UPLOAD_ERR_EXTENSION:
	                $this->setError('upload_stopped_by_extension');
	                break;
	            default:
	                $this->setError('upload_no_file_selected');
	                break;
	        }
	        
	        return false;
	    }
	    
 	    // Set the uploaded data as class variables
 	    $this->fileTemp       = $file['tmp_name'];
 	    $this->fileSize       = $file['size'];
 	    $this->fileName       = $this->prepFileName($file['name']);
 	    $this->originalName   = $this->fileName;
 	    $this->fileExt	      = $this->getExtension($this->fileName);
 	    $this->fileType       = $this->getType($file);
 	    
	    // Is the file type allowed to be uploaded?
	    if (!$this->isAllowedFileType()) {
	        $this->setError('upload_invalid_filetype');
	        return false;
	    }
	    
	    // Convert the file size to kilobytes
	    if ($this->fileSize > 0) {
	        $this->fileSize = round($this->fileSize/1024, 2);
	    }
	    
 	    // Is the file size within the allowed maximum?
 	    if (!$this->isAllowedFilesize()) {
 	        $this->setError('upload_invalid_filesize');
 	        return false;
 	    }
	    
	    // Are the image dimensions within the allowed size?
	    // Note: This can fail if the server has an open_basedir restriction.
	    if (!$this->isAllowedDimensions()) {
	        $this->setError('upload_invalid_dimensions');
	        return false;
	    }
	    
 	    // Sanitize the file name for security?  
	    
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
	        return false;
	    }
	    
	    /*
	     * Run the file through the XSS hacking filter
	     * This helps prevent malicious code from being
	     * embedded within a file. Scripts can easily
	     * be disguised as images or other file types.
	     */
	    if ($this->xssClean && $this->doXssClean() === false) {
	        $this->setError('upload_unable_to_write_file');
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
	            $this->setError('upload_destination_error');
	            return false;
	        }
	    }
	   
	    return true;
 	}

 	/**
 	 * Finalized Data Array
 	 *
 	 * Returns an associative array containing all of the information
 	 * related to the upload, allowing the developer easy access in one array.
 	 *
 	 * @param	string	$index
 	 * @return	mixed
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
 	 * @link	https://httpd.apache.org/docs/1.3/mod/mod_mime.html#multipleext
 	 *
 	 * @param	string	$filename
 	 * @return	string
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
	private function createFileName($fileName) {
	    
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
	        $this->setError('upload_bad_filename');
	        return false;
	    }
	    
	    return $newFileName;
	}
	
	
	/**
	 * Validate Upload Path
	 *
	 * Verifies that it is a valid upload path with proper permissions.
	 *
	 * @return	bool
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
	        $this->createFolder($this->uploadPath);
	    }
	    
	    if (!is_dir($this->uploadPath)) {
	        $this->setError('upload_no_filepath');
	        return false;
	    }
	    
	    if (!$this->isWritable($this->uploadPath)) {
	        $this->setError('upload_not_writable');
	        return false;
	    }
	    
	    $this->uploadPath = preg_replace('/(.+?)\/*$/', '\\1/', $this->uploadPath);
	    return true;
	}
	
	/**
	 * Verify that the filetype is allowed
	 *
	 * @param	bool	$ignore_mime
	 * @return	bool
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
	 * @return	bool
	 */
	private function isAllowedFilesize()
	{
	    return $this->maxSize === 0 || $this->maxSize > $this->fileSize;
	}
	
	/**
	 * Validate the image
	 *
	 * @return	bool
	 */
	private function isImage()
	{
	    return in_array($this->fileType, $this->imgMimes, true);
	}
	
	/**
	 * Verify that the image is within the allowed width/height
	 *
	 * @return	bool
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
	 * Create a folder
	 *
	 * @param string $path - the directory to create
	 * @param boolean $makeParents [default=true] - true to create parent directories
	 * @return boolean
	 */
	private function createFolder($path, $recursive = true, $mode = 0755)
	{
	    if (file_exists($path)) {
	        return true;
	    } else {
	        $result = mkdir($path, $mode, $recursive);
	        if (!$result) {
	            return false;
	        }
	    }
	    return true;
	}
	
	/**
	 * 
	 * @param string $file
	 * @return boolean
	 */
	private function isWritable($file)
	{
	    // If we're on a UNIX-like server, just is_writable()
	    if (DIRECTORY_SEPARATOR === '/') {
	        return is_writable($file);
	    }
	    
	    /* For Windows servers and safe_mode "on" installations we'll actually
	     * write a file then read it. Bah...
	     */
	    if (is_dir($file)) {
	        $file = rtrim($file, '/').'/'.md5(mt_rand());
	        if (($fp = @fopen($file, 'ab')) === false) {
	            return false;
	        }
	        fclose($fp);
	        @chmod($file, 0777);
	        @unlink($file);
	        return true;
	    } else if (!is_file($file) || ($fp = @fopen($file, 'ab')) === false) {
	        return false;
	    }
	    
	    fclose($fp);
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
     * Extract the file extension
     *
     * @param	string	$filename
     * @return	string
     */
    private function getExtension($filename)
    {
        $x = explode('.', $filename);
        
        if (count($x) === 1) {
            return '';
        }
        
        $ext = strtolower(end($x));
        return $ext;
    }
    
    /**
     *
     * @param string $file
     */
    private function getType($file)
    {
        // MIME type detection?
        $fileType = $this->fileMimeType($file);
        
        $fileType = preg_replace('/^(.+?);.*$/', '\\1', $fileType);
        $fileType = strtolower(trim(stripslashes($fileType), '"'));
        
        // IE will sometimes return odd mime-types during upload, so here we just standardize all
        // jpegs or pngs to the same file type.
        if (in_array($fileType, $this->pngMimes)) {
            $fileType = 'image/png';
        } else if (in_array($fileType, $this->jpegMimes)) {
            $fileType = 'image/jpeg';
        }
        
        return $fileType;
    }
    
    /**
     * File MIME type
     *
     * Detects the (actual) MIME type of the uploaded file, if possible.
     * The input array is expected to be $_FILES[$field]
     *
     * @param	array	$file
     * @return	void
     */
    private function fileMimeType($file)
    {
        // We'll need this to validate the MIME info string (e.g. text/plain; charset=us-ascii)
        $regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+)(;\s.+)?$/';
        
        /**
         * Fileinfo extension - most reliable method
         *
         * Apparently XAMPP, CentOS, cPanel and who knows what
         * other PHP distribution channels EXPLICITLY DISABLE
         * ext/fileinfo, which is otherwise enabled by default
         * since PHP 5.3 ...
         */
        if (function_exists('finfo_file'))
        {
            $finfo = @finfo_open(FILEINFO_MIME);
            if (is_resource($finfo)) // It is possible that a FALSE value is returned, if there is no magic MIME database file found on the system
            {
                $mime = @finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                /* According to the comments section of the PHP manual page,
                 * it is possible that this function returns an empty string
                 * for some files (e.g. if they don't exist in the magic MIME database)
                 */
                if (is_string($mime) && preg_match($regexp, $mime, $matches))
                {
                    return $matches[1];
                }
            }
        }
        
        /* This is an ugly hack, but UNIX-type systems provide a "native" way to detect the file type,
         * which is still more secure than depending on the value of $_FILES[$field]['type'], and as it
         * was reported in issue #750 (https://github.com/EllisLab/CodeIgniter/issues/750) - it's better
         * than mime_content_type() as well, hence the attempts to try calling the command line with
         * three different functions.
         *
         * Notes:
         *	- the DIRECTORY_SEPARATOR comparison ensures that we're not on a Windows system
         *	- many system admins would disable the exec(), shell_exec(), popen() and similar functions
         *	  due to security concerns, hence the function_usable() checks
         */
        if (DIRECTORY_SEPARATOR !== '\\')
        {
            $cmd = 'file --brief --mime '.escapeshellarg($file['tmp_name']).' 2>&1';
            
            if (function_usable('exec'))
            {
                /* This might look confusing, as $mime is being populated with all of the output when set in the second parameter.
                 * However, we only need the last line, which is the actual return value of exec(), and as such - it overwrites
                 * anything that could already be set for $mime previously. This effectively makes the second parameter a dummy
                 * value, which is only put to allow us to get the return status code.
                 */
                $mime = @exec($cmd, $mime, $return_status);
                if ($return_status === 0 && is_string($mime) && preg_match($regexp, $mime, $matches))
                {
                    return $matches[1];
                }
            }
            
            if (function_usable('shell_exec'))
            {
                $mime = @shell_exec($cmd);
                if (strlen($mime) > 0)
                {
                    $mime = explode("\n", trim($mime));
                    if (preg_match($regexp, $mime[(count($mime) - 1)], $matches))
                    {
                        return $matches[1];
                    }
                }
            }
            
            if (function_usable('popen'))
            {
                $proc = @popen($cmd, 'r');
                if (is_resource($proc))
                {
                    $mime = @fread($proc, 512);
                    @pclose($proc);
                    if ($mime !== FALSE)
                    {
                        $mime = explode("\n", trim($mime));
                        if (preg_match($regexp, $mime[(count($mime) - 1)], $matches))
                        {
                            return $matches[1];
                        }
                    }
                }
            }
        }
        
        // Fall back to mime_content_type(), if available (still better than $_FILES[$field]['type'])
        if (function_exists('mime_content_type'))
        {
            $fileType = @mime_content_type($file['tmp_name']);
            if (strlen($fileType) > 0) // It's possible that mime_content_type() returns FALSE or an empty string
            {
                return $fileType;
            }
        }
        
        return $file['type'];
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
        
        return false; //maybe clean it?
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
     *
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
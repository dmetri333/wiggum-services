<?php
namespace wiggum\services\upload\adapters;

use wiggum\services\upload\UploadAdapter;
use wiggum\commons\helpers\FileHelper;
use wiggum\commons\helpers\SecurityHelper;

class LocalAdapter extends UploadAdapter {

	/**
	 * 
	 * @param array $file
	 * @return boolean
	 */
	public function upload(array $file): bool
	{
 	   
		if (!$this->check($file)) {
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
	 * 
	 * @param array $file
	 * @return boolean
	 */
	public function check(array $file): bool
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
				$this->fileExt = FileHelper::extension($this->fileNameOverride);
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
		$this->fileName = $this->createFileName($this->fileName);
	    if ($this->fileName === '') {
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
	    
	    return true;
 	}

}

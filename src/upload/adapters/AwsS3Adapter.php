<?php
namespace wiggum\services\upload\adapters;

use wiggum\services\upload\UploadAdapter;
use wiggum\commons\helpers\FileHelper;
use wiggum\commons\helpers\SecurityHelper;

class AwsS3Adapter extends UploadAdapter {

	protected $imageSizeData = false;
	
    	/**
	 *
	 * @param string $uploadPath
	 * @param boolean $createDir
	 * @return UploadAdapter
	 */
	public function path(string $uploadPath, $createDir = false): UploadAdapter
	{
	    $this->uploadPath = rtrim($uploadPath, '/').'/';
		$this->uploadPath = ltrim($this->uploadPath, '/');
	    $this->createDir = $createDir;

	    return $this;
	}

	/**
	 * 
	 * @param array $file
	 * @return boolean
	 */
    	public function upload(array $file): bool
	{

        if (!isset($file)) {
	        $this->setError('upload.noFileSelected');
	        return false;
	    }
	
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

		$this->fileTemp       = $file['tmp_name'];
 	    $this->fileSize       = $file['size'];
 	    $this->fileName       = $this->prepFileName($file['name']);
 	    $this->originalName   = $this->fileName;
 	    $this->fileExt	      = FileHelper::extension($this->fileName);
 	    $this->fileType       = FileHelper::mimeType($file['tmp_name'], $file['type']);

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
		$this->fileName = $this->createFileName($this->fileName); //UPDATE
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

	   if ($this->isImage() && function_exists('getimagesize')) {
		$this->imageSizeData = @getimagesize($this->fileTemp);
	   }
	   
		try {
			$stream = fopen($this->fileTemp, 'r+');

			$this->app->storage->disk('s3')->writeStream($this->uploadPath.$this->fileName, $stream);
		} catch (\League\Flysystem\FilesystemException $exception) {
			$this->setError('upload.destinationError');
			return false;
		} catch (\Exception $exception) {
			$this->setError('upload.destinationError');
			return false;
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
		if (false !== $this->imageSizeData) {
			$types = [1 => 'gif', 2 => 'jpeg', 3 => 'png'];
	
			$image['width'] = $this->imageSizeData[0];
			$image['height'] = $this->imageSizeData[1];
			$image['type']	= isset($types[$this->imageSizeData[2]]) ? $types[$this->imageSizeData[2]] : 'unknown';
		}
	    
	    return $image;
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
		
		if ($this->overwrite === true) {
			return $fileName;
		}

		$files = [];
		try {
			$listing = $this->app->storage->disk('s3')->listContents($this->uploadPath);
			
			/** @var \League\Flysystem\StorageAttributes $item */
			foreach ($listing as $item) {
				if ($item instanceof \League\Flysystem\FileAttributes) {
					$files[] =  pathinfo($item->path(), PATHINFO_FILENAME);
				}
			}
			
		} catch (\League\Flysystem\FilesystemException $exception) {
			return '';
		} catch (\Exception $exception) {
			return '';
		}

		$fileName = str_replace('.'.$this->fileExt, '', $fileName);
		if (!in_array($fileName, $files)) {
	        return $fileName.'.'.$this->fileExt;
	    }
		
		$newFileName = '';
		for ($i = 1; $i < $this->maxFilenameIncrement; $i++) {
			if (!in_array($fileName.$i, $files)) {
				$newFileName = $fileName.$i.'.'.$this->fileExt;
				break;
			}
		}

		return $newFileName;
	}

}

<?php
namespace awiggum\services\storage\exceptions;

use \Exception;

class StorageNotFoundException extends Exception {
    
   /**
    * 
    * @param string $message
    */
    public function __construct($message = null) {
        parent::__construct($message, 404);
    }
    
}
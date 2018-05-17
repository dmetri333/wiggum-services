<?php
namespace wiggum\services\csrf\exceptions;

use \Exception;

class TokenMismatchException extends Exception {
    
   /**
    * 
    * @param string $message
    */
    public function __construct($message = null) {
        parent::__construct('Failed CSRF check!', 400);
    }
    
}
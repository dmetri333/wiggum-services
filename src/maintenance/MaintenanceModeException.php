<?php
namespace wiggum\services\maintenance;

use \Exception;

class MaintenanceModeException extends Exception {
	
    /**
     *
     * @param string $message
     */
    public function __construct(string $message = null)
    {
        parent::__construct($message, 503);
    }
    
}
<?php
namespace wiggum\services\dictionary;

class ErrorHandler {
	
	protected $dictionary;
	
	/**
	 * 
	 * @param array $dictionary
	 */
	public function __construct(array $dictionary) {
	    $this->dictionary = $dictionary;
	}
	
	/**
	 *
	 * @param string $key
	 * @param string $prefix
	 * @return mixed
	 */
	public function get($key, $prefix = null) {
	    
	    $key = isset($prefix) ? $prefix.'.'.$key : $key;
	    if (!isset($this->dictionary[$key])) {
	        return null;
	    }
	    
	    return $this->dictionary[$key];
	}
	
	/**
	 *
	 * @param string $key
	 * @param array $replace
	 * @param string $prefix
	 * @return mixed
	 */
	public function replace($key, array $replace, $prefix = null) {
	    return str_replace(array_keys($replace), array_values($replace), $this->get($key, $prefix));
	}

}

<?php
namespace wiggum\services\dictionary;

class Dictionary {
	
	protected array $dictionary;
	
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
	public function get(string $key, ?string $prefix = null) {
	    
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
	public function replace(string $key, array $replace, ?string $prefix = null) {
	    return str_replace(array_keys($replace), array_values($replace), $this->get($key, $prefix));
	}

}

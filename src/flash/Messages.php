<?php
namespace wiggum\services\flash;

use \ArrayAccess;
use \RuntimeException;
use \InvalidArgumentException;

class Messages {
	
	protected $fromPrevious = [];
	protected $forNext = [];
	protected $storage;
	protected $storageKey = 'wiggumFlash';
	
	/**
	 *
	 * @param null|array|ArrayAccess $storage        	
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 */
	public function __construct(&$storage = null) {
		
		// Set storage
		if (is_array($storage) || $storage instanceof ArrayAccess) {
			$this->storage = &$storage;
		} else if (is_null($storage)) {
			if (!isset($_SESSION)) {
				throw new RuntimeException('Flash messages middleware failed. Session not found.');
			}
			
			$this->storage = &$_SESSION;
		} else {
			throw new InvalidArgumentException('Flash messages storage must be an array or implement \ArrayAccess');
		}
		
		// Load messages from previous request
		if (isset($this->storage[$this->storageKey]) && is_array($this->storage[$this->storageKey])) {
			$this->fromPrevious = $this->storage[$this->storageKey];
		}
		
		$this->storage[$this->storageKey] = [];
	}
	
	/**
	 *
	 * @param string $key
	 * @param mixed $message
	 */
	public function addMessage($key, $message) {
		// Create Array for this key
		if (!isset($this->storage[$this->storageKey][$key])) {
			$this->storage[$this->storageKey][$key] = array();
		}
		
		// Push onto the array
		$this->storage[$this->storageKey][$key][] = $message;
	}
	
	/**
	 *
	 * @return array
	 */
	public function getMessages() {
		return $this->fromPrevious;
	}
	
	/**
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	public function getMessage($key) {
		// If the key exists then return all messages or null
		return (isset($this->fromPrevious[$key])) ? $this->fromPrevious[$key] : null;
	}
	
	/**
	 * 
	 * @param string $message
	 */
	public function addSuccessMessage($message) {
		$this->addMessage('success', $message);
	}
	
	/**
	 *
	 * @param string $message
	 */
	public function addWarningMessage($message) {
		$this->addMessage('warning', $message);
	}
	
	/**
	 *
	 * @param string $message
	 */
	public function addErrorMessage($message) {
		$this->addMessage('error', $message);
	}
	
}
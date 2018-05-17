<?php
namespace wiggum\services\csrf;

use ArrayAccess;
use RuntimeException;
use wiggum\http\Request;
use wiggum\http\Response;

/**
 * CSRF protection middleware and service
 */
class CSRFGuard
{

    /**
     * Key for CSRF parameter
     *
     * @var string
     */
    protected $key;

    /**
     * CSRF storage
     *
     * Should be either an array or an object. If an object is used, then it must
     * implement ArrayAccess
     *
     * @var array|ArrayAccess
     */
    protected $storage;

    /**
     * CSRF Strength
     *
     * @var int
     */
    protected $strength;

    /**
     * Callable to be executed if the CSRF validation fails
     *
     * Signature of callable is:
     * function($request, $response, $next)
     * and a $response must be returned.
     *
     * @var callable
     */
    protected $failureCallable;


    /**
     * Create new CSRF guard
     *
     * @param string $key
     * @param null|array|ArrayAccess $storage
     * @param null|callable $failureCallable
     * @param integer $strength
     * @throws RuntimeException if the session cannot be found
     */
    public function __construct($key = 'csrf', &$storage = null, callable $failureCallable = null, $strength = 16)
    {
        $this->key = trim($key);
        if ($strength < 16) {
            throw new RuntimeException('CSRF middleware failed. Minimum strength is 16.');
        }
        $this->strength = $strength;
        $this->storage = &$storage;
        $this->setFailureCallable($failureCallable);
    }

    /**
     * Invoke middleware
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     *            
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $this->validateStorage();
        
        // Validate POST, PUT, DELETE, PATCH requests
        if (in_array($request->getMethod(), [
            'POST',
            'PUT',
            'DELETE',
            'PATCH'
        ])) {
            $token = $request->getParameter($this->key, false);
            if (!$token || !$this->validateToken($token)) {
                
                $failureCallable = $this->getFailureCallable();
                return $failureCallable($request, $response, $next);
            }
        }
        
        return $next($request, $response);
    }

    /**
     * 
     * @return string
     */
    public function generateFormFields()
    {
        $this->validateStorage();
        
        // Generate new CSRF token
        $storedToken = $this->geToken();
        
        return $storedToken ? '<input type="hidden" name="'.$this->key.'" value="'. $storedToken .'">' : '';
    }
    
    /**
     * 
     * @return string|boolean
     */
    private function geToken()
    {
        $storedToken = isset($this->storage[$this->key]) ? $this->storage[$this->key] : false;
        if (!$storedToken) {
            $storedToken = $this->generateToken();
        }
        
        return $storedToken;
    }
    
    /**
     * Generates a new CSRF token
     *
     * @return string
     */
    private function generateToken()
    {
        // Generate new CSRF token
        $token = bin2hex(random_bytes($this->strength));
        $this->storage[$this->key] = $token;
        return $token;
    }

    /**
     * Validate CSRF token from current request
     * against token value stored in $_SESSION
     *
     * @param string $token
     *            
     * @return bool
     */
    private function validateToken($token)
    {
        
        $storedToken = isset($this->storage[$this->key]) ? $this->storage[$this->key] : false;
        
        if (function_exists('hash_equals')) {
            $result = ($storedToken !== false && hash_equals($storedToken, $token));
        } else {
            $result = ($storedToken !== false && $storedToken === $token);
        }
        
        return $result;
    }

   /**
    * 
    * @throws RuntimeException
    * @return array|ArrayAccess
    */
    private function validateStorage()
    {
        if (is_array($this->storage)) {
            return $this->storage;
        }
        
        if ($this->storage instanceof ArrayAccess) {
            return $this->storage;
        }
        
        if (!isset($_SESSION)) {
            throw new RuntimeException('CSRF middleware failed. Session not found.');
        }
        
        if (!array_key_exists($this->key, $_SESSION)) {
            $_SESSION[$this->key] = null;
        }
        
        $this->storage = &$_SESSION;
        
        return $this->storage;
    }

    /**
     * Getter for failureCallable
     *
     * @return callable|\Closure
     */
    private function getFailureCallable()
    {
        if (is_null($this->failureCallable)) {
            $this->failureCallable = function (Request $request, Response $response, $next) {
                $response->withStatus(400, 'Failed CSRF check!');
                $response->addHeader('Content-type', 'text/plain');
                return $response;
            };
        }
        
        return $this->failureCallable;
    }

    /**
     * Setter for failureCallable
     *
     * @param mixed $failureCallable
     * @return $this
     */
    private function setFailureCallable($failureCallable)
    {
        $this->failureCallable = $failureCallable;
        return $this;
    }

}
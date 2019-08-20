<?php
namespace wiggum\services\csrf;

use ArrayAccess;
use RuntimeException;
use wiggum\http\Request;
use wiggum\http\Response;
use wiggum\services\csrf\exceptions\TokenMismatchException;

/**
 * CSRF protection middleware and service
 */
class CSRFGuard
{
    
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [];
    
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
     * Create new CSRF guard
     *
     * @param string $key
     * @param null|array|ArrayAccess $storage
     * @param integer $strength
     * @throws RuntimeException if the session cannot be found
     */
    public function __construct($key = 'csrf_token', &$storage = null, $strength = 16)
    {
        $this->key = trim($key);
        if ($strength < 16) {
            throw new RuntimeException('CSRF middleware failed. Minimum strength is 16.');
        }
        $this->strength = $strength;
        $this->storage = &$storage;
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
        
        if ($this->isReading($request) ||
            $this->inExceptArray($request) ||
            $this->tokensMatch($request)) {
                
            return $next($request, $response);
        }
            
        throw new TokenMismatchException();
    }
    
    /**
     *
     * @return string
     */
    public function generateFormField()
    {
        $token = $this->getToken();
        return $token ? '<input type="hidden" name="'.$this->key.'" value="'. $token .'">' : '';
    }
    
    /**
     *
     * @return string
     */
    public function generateMetaTag()
    {
        $token = $this->getToken();
        return $token ? '<meta name="'.$this->key.'" content="'. $token .'">' : '';
    }
    
    /**
     *
     * @return string|boolean
     */
    public function getToken()
    {
        $this->validateStorage();
        
        // Generate new CSRF token
        $storedToken = isset($this->storage[$this->key]) ? $this->storage[$this->key] : false;
        if (!$storedToken) {
            $storedToken = $this->generateToken();
        }
        
        return $storedToken;
    }
    
    /**
     *
     * @param array $except
     */
    public function setExcept(array $except)
    {
        $this->except = $except;
    }
    
    /**
     * 
     * @return array
     */
    public function getExcept()
    {
        return $this->except;
    }
    
    /**
     * Generates a new CSRF token
     *
     * @return string
     */
    protected function generateToken()
    {
        // Generate new CSRF token
        $token = bin2hex(random_bytes($this->strength));
        $this->storage[$this->key] = $token;
        return $token;
    }
    
    /**
     *
     * @throws RuntimeException
     * @return array|ArrayAccess
     */
    protected function validateStorage()
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
     * Determine if the HTTP request uses a ‘read’ verb.
     *
     * @param  Request  $request
     * @return boolean
     */
    protected function isReading(Request $request)
    {
        return in_array($request->getMethod(), ['HEAD', 'GET', 'OPTIONS']);
    }
    
    /**
     *
     * @param Request $request
     * @return boolean
     */
    protected function inExceptArray(Request $request)
    {
        foreach ($this->except as $except) {
            if ($except == $request->getRequestURI()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Determine if the storage and input CSRF tokens match.
     *
     * @param  Request $request
     * @return boolean
     */
    protected function tokensMatch(Request $request)
    {
        $token = $this->getTokenFromRequest($request);
        $storedToken = $this->getToken();
        
        return  is_string($storedToken) &&
                is_string($token) &&
                hash_equals($storedToken, $token);
    }
    
    /**
     * Get the CSRF token from the request.
     *
     * @param  Request $request
     * @return string
     */
    protected function getTokenFromRequest(Request $request)
    {
        $token = $request->getParameter($this->key) ?: $request->getHeader('X-CSRF-TOKEN');
        
        return $token;
    }
    
}
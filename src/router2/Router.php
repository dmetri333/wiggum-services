<?php
namespace wiggum\services\router2;

use \InvalidArgumentException;
use \wiggum\http\Request;
use \wiggum\exceptions\PageNotFoundException;
use \wiggum\exceptions\InternalErrorException;
use \FastRoute\Dispatcher;
use \FastRoute\RouteCollector;

class Router extends wiggum\http\Router {
    
    protected $dispatcher;
    protected $routes = [];
    protected $routeGroups = [];
    protected $routeCounter;
    
    /**
     * 
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function get(string $pattern, $handler) : Route
    {
        return $this->map(['GET'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function post(string $pattern, $handler) : Route
    {
        return $this->map(['POST'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function put(string $pattern, $handler) : Route
    {
        return $this->map(['PUT'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function patch(string $pattern, $handler) : Route
    {
        return $this->map(['PATCH'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function delete(string $pattern, $handler) : Route
    {
        return $this->map(['DELETE'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function options(string $pattern, $handler) : Route
    {
        return $this->map(['OPTIONS'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return Route
     */
    public function any(string $pattern, $handler) : Route
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $handler);
    }
    
    /**
     * 
     * @param array $methods
     * @param string $pattern
     * @param mixed $handler
     * @throws InvalidArgumentException
     * @return Route
     */
    public function map(array $methods, string $pattern, $handler) : Route
    {
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Route pattern must be a string');
        }
        
        // uppercase methods
        $methods = array_map('strtoupper', $methods);
        
        // Process groups
        if (!empty($this->routeGroups)) {
            $pattern = $this->processGroups() . $pattern;
        }
        
        // Add route
        $route = new Route($methods, $pattern, $handler, $this->routeGroups, $this->routeCounter);
        $this->routes[$route->getIdentifier()] = $route;
        $this->routeCounter++;
        
        return $route;
    }
	
	
	/* Groups */
	
    /**
     * 
     * @param string $prefix
     * @param callable $callable
     * @return RouteGroup
     */
	public function group(string $prefix, callable $callable) : RouteGroup
	{
	    
	    $group = new RouteGroup($prefix, $callable);
	    
	    array_push($this->routeGroups, $group);
	    
	    $group->collectRoutes($this);
	    
	    array_pop($this->routeGroups);
	    
	    return $group;
	}
	
	
	/**
	 * 
	 * @return string
	 */
	protected function processGroups() : string
	{
	    $pattern = '';
	    foreach ($this->routeGroups as $group) {
	        $pattern .= $group->getPattern();
	    }
	    return $pattern;
	}


	/* runners */
	
	/**
	 * 
	 * @param Request $request
	 * @throws InternalErrorException
	 * @throws PageNotFoundException
	 * @return Route
	 */
	public function lookup(Request $request) : Route
	{
	    
	    $routeInfo = $this->dispatch($request);
	    
	    switch ($routeInfo[0]) {
	        case Dispatcher::FOUND:
	            
	            $identifier = $routeInfo[1];
	            $parameters = $routeInfo[2];
	            
	            $route = $this->routes[$identifier];
	            $route->setParameters($parameters);
	            $route->appendGroupMiddleware();
	            $route->appendGroupFilters();
	            
	            return $route;
	            
	            break;
	        case Dispatcher::METHOD_NOT_ALLOWED:
	            // ... 405 Method Not Allowed
	            
	            $allowedMethods = $routeInfo[1];
	            throw new InternalErrorException('Method "'.$request->getMethod().'" Not Allowed ['.implode(', ', $allowedMethods).' accepted], Ref: '.$request->getContextPath(), 405);
	            
	            break;
	        case Dispatcher::NOT_FOUND:
	            // ... 404 Not Found
	            
	            throw new PageNotFoundException();
	            
	            break;
	    }
	    
	    throw new PageNotFoundException();
	}
	
	/**
	 * 
	 * @param Request $request
	 * @return Dispatcher
	 */
	protected function dispatch(Request $request) : Dispatcher
	{
	    
	    return $this->createDispatcher()->dispatch(
	        $request->getMethod(),
	        $request->getContextPath()
	    );
	    
	}
	
	/**
	 * 
	 * @return Dispatcher
	 */
	protected function createDispatcher() : Dispatcher
	{
	    
	    if ($this->dispatcher) {
	        return $this->dispatcher;
	    }
	    
	    $routeDefinitionCallback = function (RouteCollector $r) {
	        foreach ($this->routes as $route) {
	            $r->addRoute($route->getMethods(), $route->getPattern(), $route->getIdentifier());
	        }
	    };
	    
	    $this->dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback);

	    return $this->dispatcher;
	}
	
}
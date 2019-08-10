<?php
namespace wiggum\services\router2;

use \InvalidArgumentException;
use \wiggum\http\Request;
use \wiggum\exceptions\PageNotFoundException;
use \wiggum\exceptions\InternalErrorException;
use \FastRoute\Dispatcher;
use \FastRoute\RouteCollector;

class Router {
    
    protected $dispatcher;
    protected $routes = [];
    protected $routeGroups = [];
    protected $routeCounter;
    
    /**
     * 
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function get($pattern, $handler)
    {
        return $this->map(['GET'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function post($pattern, $handler)
    {
        return $this->map(['POST'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function put($pattern, $handler)
    {
        return $this->map(['PUT'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function patch($pattern, $handler)
    {
        return $this->map(['PATCH'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function delete($pattern, $handler)
    {
        return $this->map(['DELETE'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function options($pattern, $handler)
    {
        return $this->map(['OPTIONS'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function any($pattern, $handler)
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $handler);
    }
    
    /**
     * 
     * @param mixed $methods
     * @param string $pattern
     * @param mixed $handler
     * @throws InvalidArgumentException
     * @return \wiggum\services\router\Route
     */
    public function map($methods, $pattern, $handler)
    {
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Route pattern must be a string');
        }
        
        // Clean methods
        $methods = is_string($methods) ? [$methods] : $methods;
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
     * @param string $attributes
     * @param callable $callable
     * @return RouteGroup
     */
	public function group($prefix, $callable)
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
	protected function processGroups()
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
	 * @throws PageNotFoundException
	 * @throws InternalErrorException
	 * @return \wiggum\services\router\Route
	 */
	public function lookup(Request $request)
	{
	    
	    $routeInfo = $this->dispatch($request);
	    
	    switch ($routeInfo[0]) {
	        case Dispatcher::FOUND:
	            
	            $identifier = $routeInfo[1];
	            $parameters = $routeInfo[2];
	            
	            $route = $this->routes[$identifier];
	            $route->setParameters($parameters);
	            $route->appendGroupMiddleware();
	            
	            return $route;
	            
	            break;
	        case Dispatcher::METHOD_NOT_ALLOWED:
	            // ... 405 Method Not Allowed
	            
	            $allowedMethods = $routeInfo[1];
	            throw new InternalErrorException('Method Not Allowed ['.implode(', ', $allowedMethods).' accepted], Ref: '.$request->getContextPath(), 405);
	            
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
	 * @return \FastRoute\simpleDispatcher
	 */
	protected function dispatch(Request $request) {
	    
	    return $this->createDispatcher()->dispatch(
	        $request->getMethod(),
	        $request->getContextPath()
	    );
	    
	}
	
	/**
	 * 
	 * @return \FastRoute\simpleDispatcher
	 */
	protected function createDispatcher() {
	    
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
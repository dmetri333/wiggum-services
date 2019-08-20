<?php
namespace wiggum\services\router2;

class Route extends  wiggum\http\Route {
    
    protected $methods = [];
    protected $pattern;
    protected $callable;
    protected $groups = [];
    protected $identifier;
    protected $middleware = [];
    protected $filters = [];
    protected $parameters = [];

    /**
     * 
     * @param array $methods
     * @param string $pattern
     * @param callable $callable
     * @param array $groups
     * @param int $identifier
     */
    public function __construct(array $methods, string $pattern, callable $callable, array $groups = [], int $identifier = 0)
    {
        $this->methods  = $methods;
        $this->pattern  = $pattern;
        $this->callable = $callable;
        $this->groups = $groups;
        $this->identifier = 'route' . $identifier;
    }
    
    /**
     * 
     * @return array
     */
    public function getMethods() : array
    {
        return $this->methods;
    }
    
    /**
     * 
     * @return string
     */
    public function getPattern() : string
    {
        return $this->pattern;
    }

    /**
     * 
     * @return callable
     */
    public function getCallable() : callable
    {
        return $this->callable;
    }
    
    /**
     * 
     * @return string
     */
    public function getIdentifier() : string
    {
        return $this->identifier;
    }
    
    /**
     * 
     * @param array $parameters
     */
    public function setParameters(array $parameters) : void
    {
        $this->parameters = $parameters;
    }
    
    /**
     * 
     * @return array
     */
    public function getParameters() : array
    {
        return $this->parameters;
    }

    /**
     * 
     * @param array $middleware
     */
    public function setMiddleware(array $middleware) : void
    {
        $this->middleware = $middleware;
    }
    
    /**
     * 
     * @return array
     */
    public function getMiddleware() : array
    {
        return $this->middleware;
    }
    
    /**
     * 
     * @param callable $middleware
     * @return Route
     */
    public function addMiddleware(callable $middleware) : Route
    {
        $this->middleware[] = $middleware;
        
        return $this;
    }
    
    /**
     * 
     * @param array $filters
     */
    public function setFilters(array $filters) : void
    {
        $this->filters = $filters;
    }
    
    /**
     * 
     * @return array
     */
    public function getFilters() : array
    {
        return $this->filter;
    }
    
    /**
     * 
     * @param callable $filter
     * @return Route
     */
    public function addFilter(callable $filter) : Route
    {
        $this->filters[] = $filter;
        
        return $this;
    }
    
    /**
     * 
     */
    public function appendGroupMiddleware() : void
    {
        foreach ($this->groups as $group) {
            foreach ($group->getMiddleware() as $middleware) {
                $this->addMiddleware($middleware);
            }
        }
    }
    
    /**
     * 
     */
    public function appendGroupFilters() : void
    {
        foreach ($this->groups as $group) {
            foreach ($group->getFilters() as $filter) {
                $this->addFilter($filter);
            }
        }
    }
    
    /**
     * 
     * @return array
     */
    public function process() : array
    {
        
        $actions = [];
        
        if (is_string($this->callable)) {
            $routeSegments = explode('@', $this->callable);
            
            $actions['classPath'] = $routeSegments[0];
            
            if (isset($routeSegments[1]) && $routeSegments[1] != '') {
                $actions['method'] = $routeSegments[1];
            }
            
            
        } else if (is_array($this->callable)) {
            $routeSegments = explode('@', $this->callable['classPath']);
            
            $actions['classPath'] = $routeSegments[0];
            
            if (isset($routeSegments[1]) && $routeSegments[1] != '') {
                $actions['method'] = $routeSegments[1];
            }
            
        } else if (is_callable($this->callable)) {
            $actions = (array) call_user_func_array($this->callable, [$this->parameters]);
        }
        
        // add route filters
        foreach ($this->filters as $filter) {
            $actions = $filter($actions);
        }
        
        return $actions;
    }
    
}
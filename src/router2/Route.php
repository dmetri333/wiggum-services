<?php
namespace wiggum\services\router2;

class Route {
    
    protected $methods = [];
    protected $pattern;
    protected $callable;
    protected $groups = [];
    protected $identifier;
    protected $middleware = [];
    protected $parameters =[];

    public function __construct(array $methods, $pattern, $callable, $groups = [], $identifier = 0)
    {
        $this->methods  = $methods;
        $this->pattern  = $pattern;
        $this->callable = $callable;
        $this->groups = $groups;
        $this->identifier = 'route' . $identifier;
    }
    
    public function getMethods()
    {
        return $this->methods;
    }
    
    public function getPattern()
    {
        return $this->pattern;
    }

    public function getCallable()
    {
        return $this->callable;
    }
    
    public function getIdentifier()
    {
        return $this->identifier;
    }
    
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }
    
    public function getParameters()
    {
        return $this->parameters;
    }
 
    public function setMiddleware(array $middleware)
    {
        $this->middleware = $middleware;
    }
    
    public function getMiddleware()
    {
        return $this->middleware;
    }
    
    public function addMiddleware($middleware)
    {
        $this->middleware[] = $middleware;
        
        return $this;
    }
    
    public function appendGroupMiddleware()
    {
        foreach ($this->groups as $group) {
            foreach ($group->getMiddleware() as $middleware) {
                $this->addMiddleware($middleware);
            }
        }
    }
    
    public function process()
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
        
        
        return $actions;
    }
    
}
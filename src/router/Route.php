<?php
namespace wiggum\services\router;

class Route {
    
    protected $methods = [];
    protected $pattern;
    protected $callable;
    protected $identifier;
    protected $middleware = [];
    protected $filters = [];
    protected $parameters =[];

    public function __construct(array $methods, $pattern, $callable, $identifier = 0) {
        $this->methods  = $methods;
        $this->pattern  = $pattern;
        $this->callable = $callable;
        $this->identifier = 'route' . $identifier;
    }
    
    public function getMethods() {
        return $this->methods;
    }
    
    public function getPattern() {
        return $this->pattern;
    }

    public function getCallable() {
        return $this->callable;
    }
    
    public function getIdentifier() {
        return $this->identifier;
    }
    
    public function setMiddleware(array $middleware) {
        $this->middleware = $middleware;
    }
    
    public function getMiddleware() {
        return $this->middleware;
    }

    public function setFilters(array $filters) {
        $this->filters = $filters;
    }
    
    public function getFilters() {
        return $this->filters;
    }
    
    public function setParameters(array $parameters) {
        $this->parameters = $parameters;
    }
    
    public function getParameters() {
        return $this->parameters;
    }
    
}
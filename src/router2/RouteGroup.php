<?php
namespace wiggum\services\router2;

class RouteGroup {

    protected $prefix;
    protected $callable;
    protected $middleware = [];
    
    public function __construct($prefix, $callable)
    {
        $this->prefix  = $prefix;
        $this->callable = $callable;
    }

    public function collectRoutes(Router $router)
    {
        $callable = $this->callable;

        $callable($router);
    }
    
    public function getPattern()
    {
        return $this->prefix;
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

}
<?php
namespace wiggum\services\router;

class RouteGroup {

    protected $attributes;
    protected $callable;
    
    public function __construct($attributes, $callable) {
        $this->attributes  = $attributes;
        $this->callable = $callable;
    }

    public function __invoke(Router $router) {
        $callable = $this->callable;

        $callable($router);
    }
    
    public function getPattern() {
        return isset($this->attributes['prefix']) ? $this->attributes['prefix'] : '';
    }
    
    public function getMiddleware() {
        return isset($this->attributes['middleware']) ? $this->attributes['middleware'] : [];
    }
    
    public function getFilters() {
        return isset($this->attributes['filters']) ? $this->attributes['filters'] : [];
    }
    
}

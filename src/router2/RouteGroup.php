<?php
namespace wiggum\services\router2;

class RouteGroup {

    protected $prefix;
    protected $callable;
    protected $middleware = [];
    protected $filters = [];
    
    /**
     * 
     * @param string $prefix
     * @param callable $callable
     */
    public function __construct(string $prefix, callable $callable)
    {
        $this->prefix  = $prefix;
        $this->callable = $callable;
    }

    /**
     * 
     * @param Router $router
     */
    public function collectRoutes(Router $router): void
    {
        $callable = $this->callable;

        $callable($router);
    }
    
    /**
     * 
     * @return string
     */
    public function getPattern(): string
    {
        return $this->prefix;
    }
    
    /**
     * 
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
    
    /**
     * 
     * @param callable $middleware
     * @return RouteGroup
     */
    public function addMiddleware(callable $middleware): RouteGroup
    {
        $this->middleware[] = $middleware;
        
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
    
    /**
     * 
     * @param callable $filter
     * @return RouteGroup
     */
    public function addFilter(callable $filter): RouteGroup
    {
        $this->filters[] = $filter;
        
        return $this;
    }

}
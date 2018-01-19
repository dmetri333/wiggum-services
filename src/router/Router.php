<?php
namespace wiggum\services\router;

use \InvalidArgumentException;
use \wiggum\http\Request;
use \wiggum\http\Response;
use \wiggum\exceptions\PageNotFoundException;
use \wiggum\exceptions\InternalErrorException;
use \FastRoute\Dispatcher;
use \FastRoute\RouteCollector;

class Router extends \wiggum\foundation\Router {
    
    protected $routes = [];
    protected $routeGroups = [];
    protected $routeCounter;

    public function get($pattern, $handler) {
        return $this->map(['GET'], $pattern, $handler);
    }

    public function post($pattern, $handler) {
        return $this->map(['POST'], $pattern, $handler);
    }

    public function put($pattern, $handler) {
        return $this->map(['PUT'], $pattern, $handler);
    }

    public function patch($pattern, $handler) {
        return $this->map(['PATCH'], $pattern, $handler);
    }

    public function delete($pattern, $handler) {
        return $this->map(['DELETE'], $pattern, $handler);
    }

    public function options($pattern, $handler) {
        return $this->map(['OPTIONS'], $pattern, $handler);
    }

    public function any($pattern, $handler) {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $handler);
    }
    
    public function map($methods, $pattern, $handler) {
        
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Route pattern must be a string');
        }
        
        $methods = is_string($methods) ? [$methods] : $methods;
        $methods = array_map("strtoupper", $methods);
        $filters = [];
        $middleware = [];
        
        // Process Groups
        if ($this->routeGroups) {
            $groupPattern = '';
            foreach ($this->routeGroups as $group) {
                $groupPattern .= $group->getPattern();
                $filters = array_merge($filters, $group->getFilters());
                $middleware = array_merge($middleware, $group->getMiddleware());
            }
            
            $pattern = $groupPattern . $pattern;
        }
        
        // Add route
        $route = new Route($methods, $pattern, $handler, $this->routeCounter);
        $route->setFilters($filters);
        $route->setMiddleware($middleware);
        
        $this->routes[$route->getIdentifier()] = $route;
        $this->routeCounter++;
        
        return $route;
    }
  
	/**
	 * 
	 * @param Request $request
	 * @param Response $response
	 * @throws PageNotFoundException
	 */
	public function process(Request $request, Response $response) {
        $actions = null;
        
        $routeDefinitionCallback = function (RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route->getMethods(), $route->getPattern(), $route->getIdentifier());
            }
        };
        
        $dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback);
        
        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getContextPath());
       
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                // ... 404 Not Found
                
                throw new PageNotFoundException();
                
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                // ... 405 Method Not Allowed
               
                $allowedMethods = $routeInfo[1];
                throw new InternalErrorException('Method Not Allowed ['.implode(', ', $allowedMethods).' accepted], Ref: '.$request->getContextPath(), 405);
               
                break;
            case Dispatcher::FOUND:
                $identifier = $routeInfo[1];
                $parameters = $routeInfo[2];
                
                $actions = $this->controllerActions($identifier, $parameters);
                
                break;
        }
    
        return $actions;
    }
    
	/**
	 * 
	 * @param array $actions
	 * @param mixed $data
	 * @param array $parameters
	 * @return multitype:array | null
	 */
    private function controllerActions($identifier, array $parameters = []) {
        $route = $this->routes[$identifier];
        $handler = $route->getCallable();
        
        $actions = [];
        
        // add filters to actions
        if (!empty($route->getFilters())) {
            $actions['filters'] = $this->filterArray($this->registeredfilters, $route->getFilters());
        }
        
        // add middleware to actions
        if (!empty($route->getMiddleware())) {
            $actions['middleware'] = $this->filterArray($this->registeredMiddleware, $route->getMiddleware());
        }
       
		if (is_string($handler)) {
		    $routeSegments = explode('@', $handler); 
		    
		    $actions['classPath'] = $routeSegments[0];
		
		    if (isset($routeSegments[1]) && $routeSegments[1] != '') {
		        $actions['method'] = $routeSegments[1];
			}
			
			if (!empty($parameters)) {
				$actions['parameters'] = $parameters;
			}
		
			return $actions;
		} else if (is_array($handler)) {
		    if (isset($handler['classPath'])) {
		        $actions['classPath'] = $handler['classPath'];
			}
			
			if (isset($handler['method'])) {
			    $actions['method'] = $handler['method'];
			}
			
			if (!empty($parameters)) {
				$actions['parameters'] = $parameters;
			}
			
			if (isset($handler['properties'])) {
			    $actions['properties'] = $handler['properties'];
			}
			
			return $actions;
		} else if (is_callable($handler)) {
		    return array_merge($actions, (array) call_user_func_array($handler, [$parameters])) ;
		}
		
		return null;
	}

	
	
	/* Groups */
	
	public function group($attributes, $callable) {
	    $group = $this->pushGroup($attributes, $callable);
	    $group($this);
	    $this->popGroup();
	    return $group;
	}
	
	protected function processGroups() {
	    $filters = [];
	    $middleware = [];
	    $pattern = '';
	    
	    foreach ($this->routeGroups as $group) {
	        $pattern .= $group->getPattern();
	        $filters = array_merge($filters, $group->getFilters());
	        $middleware = array_merge($middleware, $group->getMiddleware());

	        
	    }
	    return ['pattern' => $pattern, 'filters' => $filters, 'middleware' => $middleware ];
	}
	
	protected function pushGroup($attributes, $callable) {
	    $group = new RouteGroup($attributes, $callable);
	    array_push($this->routeGroups, $group);
	    return $group;
	}
	
	protected function popGroup() {
	    $group = array_pop($this->routeGroups);
	    return $group instanceof RouteGroup ? $group : false;
	}
	
	
	private function filterArray($array, $allowed) {
	    return array_intersect_key($array, array_flip($allowed));
	}
	
}
?>

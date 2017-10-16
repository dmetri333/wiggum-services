<?php
namespace wiggum\services\router;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use wiggum\exceptions\PageNotFoundException;
use wiggum\foundation\Application;
use wiggum\http\Request;
use wiggum\http\Response;

class Router {
	
	private $app;
	private $routes;
	
	/**
	 * 
	 * @param array $app
	 */
	public function __construct(Application $app) {
		$this->app = $app;
		$this->routes = $this->app->getRoutes();
	}
	
	/**
	 * 
	 * @param Request $request
	 * @param Response $response
	 * @throws PageNotFoundException
	 */
	public function process(Request $request, Response $response) {
        $actions = $this->parseURL($request);
	    
	    if (!isset($actions))
	        throw new PageNotFoundException();
	        
	    if (!isset($actions['classPath']))
	        throw new PageNotFoundException();
	            
	    $controller = new $actions['classPath']($this->app);
	            
        if (isset($actions['parameters'])) {
            $request->setParameters(array_merge($request->getParameters(), $actions['parameters']));
        }
	            
        if (isset($actions['properties'])) {
            foreach ($actions['properties'] as $property => $value) {
                $controller->{$property} = $value;
            }
        }
	            
        $method = isset($actions['method']) && method_exists($controller, $actions['method']) ? $actions['method'] : 'doDefault';
        return $controller->$method($request, new Response());
	}
	
	/**
	 * 
	 * @param Request $request
	 * @return multitype:array |NULL
	 */
	private function parseURL(Request $request) {
	    $routeDefinitionCallback = function (RouteCollector $r) {
	        foreach ($this->routes as $route) {
	            $r->addRoute($route['methods'], $route['pattern'], $route['route']);
	        }
	    };
	    
	    $dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback);
	    
	    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getContextPath());
	    
	    switch ($routeInfo[0]) {
	        case Dispatcher::NOT_FOUND:
	            // ... 404 Not Found
	            return null;
	        case Dispatcher::METHOD_NOT_ALLOWED:
	            $allowedMethods = $routeInfo[1];
	            // ... 405 Method Not Allowed
	            return null;
	        case Dispatcher::FOUND:
	            $handler = $routeInfo[1];
	            $vars = $routeInfo[2];
	            
	            return $this->controllerActions($handler, $vars);
	    }
		
		return null;
	}
	
	/**
	 * 
	 * @param array $actions
	 * @param mixed $data
	 * @param array $parameters
	 * @return multitype:array | null
	 */
	private function controllerActions($route, array $parameters = []) {
	  
		$actions = [];
		if (is_string($route)) {
		    $routeSegments = explode(':', $route); 
		    
		    $actions['classPath'] = $routeSegments[0];
		
		    if (isset($routeSegments[1]) && $routeSegments[1] != '') {
		        $actions['method'] = $routeSegments[1];
			}
			
			if (!empty($parameters)) {
				$actions['parameters'] = $parameters;
			}
		
			return $actions;
		} else if (is_array($route)) {
		    if (isset($route['classPath'])) {
		        $actions['classPath'] = $route['classPath'];
			}
			
			if (isset($route['method'])) {
			    $actions['method'] = $route['method'];
			}
			
			if (!empty($parameters)) {
				$actions['parameters'] = $parameters;
			}
			
			return $actions;
		} else if (is_callable($route)) {
		    return (array) call_user_func_array($route, [$parameters]);
		}
		
		return null;
	}

}
?>
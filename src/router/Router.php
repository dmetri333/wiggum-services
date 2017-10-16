<?php
namespace wiggum\services\router;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use wiggum\exceptions\PageNotFoundException;
use wiggum\http\Request;
use wiggum\http\Response;
use wiggum\exceptions\HTTPException;

class Router extends \wiggum\foundation\Router {
	
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
                $r->addRoute($route['methods'], $route['pattern'], $route['route']);
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
                throw new HTTPException('Method Not Allowed ['.implode(', ', $allowedMethods).' accepted]', 405);
               
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                
                $actions = $this->controllerActions($handler, $vars);
                
                break;
        }
         
        return $this->execute($actions, $request, new Response());
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
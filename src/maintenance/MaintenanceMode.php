<?php
namespace wiggum\services\maintenance;

use \wiggum\foundation\Application;
use \wiggum\http\Request;
use \wiggum\http\Response;

class MaintenanceMode
{

	protected $down = false;
	protected $urls = [];
	protected $ips = [];
	
	/**
	 * 
	 * @param Application $app
	 */
	public function __construct(Application $app)
	{
	    $this->down = $app->config->get('app.environment') == 'maintenance';
	    $this->urls = $app->config->get('services.maintenance.urls', []);
		$this->ips = $app->config->get('services.maintenance.ips', []);
	}
	
	/**
	 * 
	 * @param Request $request
	 * @param Response $response
	 * @param callable $next
	 * @throws MaintenanceModeException
	 * @return Response
	 */
	public function __invoke(Request $request, Response $response, callable $next): Response
	{
	    if (!$this->down) {
	        return $next($request, $response);
	    }
	    
	    // check urls
	    if ($this->inUrlArray($request)) {
            return $next($request, $response);
        }
        
        // check ips
        if ($this->inIpArray($request)) {
            return $next($request, $response);
        }
        
        throw new MaintenanceModeException();
	}
	
	/**
	 * 
	 * @param Request $request
	 * @return bool
	 */
	protected function inUrlArray(Request $request): bool
	{
	    foreach ($this->urls as $url) {
	        
	        //TODO stripe end url slashes
	        
	        if ($request->getRequestURI() == $url) {
	            return true;
	        }
	    }
	    
	    return false;
	}

	/**
	 * 
	 * @param Request $request
	 * @return bool
	 */
	protected function inIpArray(Request $request): bool
	{
	    return in_array($_SERVER['REMOTE_ADDR'], $this->ips);
	}
	
}

<?php
namespace services\errorHandler;

use \wiggum\commons\Handler;
use \wiggum\http\Request;
use \wiggum\http\Response;
use \wiggum\commons\template\Template;

class ErrorHandler extends Handler {
	
	private $verboseMode; 
	private $templates;
	
	/**
	 */
	public function __construct(\wiggum\foundation\Application $app) {
		$this->verboseMode = $app->settings->get('config.environment', 'development') == 'development' ? true : false;
		$this->templates = $app->settings->get('services.errorHandler', []);
	}
	
	/**
	 * Invoke error handler
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param \Throwable $error  	
	 *        	
	 * @return Response
	 * @throws UnexpectedValueException
	 */
	public function __invoke(Request $request, Response $response, \Throwable $error) {
		$output = '';
		$contentType = $this->determineContentType($request);
		switch ($contentType) {
			case 'application/json' :
				$output = $this->renderErrorMessage($error, 'json');
				break;
			
			case 'text/xml' :
			case 'application/xml' :
				$output = $this->renderErrorMessage($error, 'xml');
				break;
			
			case 'text/html' :
				$output = $this->renderErrorMessage($error, 'html');
				break;
			default :
				throw new UnexpectedValueException('Cannot render unknown content type ' . $contentType);
		}
		
		$this->writeToErrorLog($error);
		
		$response->withStatus(500);
		$response->setContentType($contentType);
		$response->setOutput($output);
		return $response;
	}
	
	/**
	 * Render error
	 *
	 * @param \Throwable $error        	
	 *
	 * @return string
	 */
	protected function renderErrorMessage(\Throwable $error, $type) {
		if (!isset($this->templates[$type]))
			throw new UnexpectedValueException('Cannot render template type "' . $type . '"');
		
		$tpl = new Template('');
		$tpl->setTemplatePath(BASE_PATH . '/' . $this->templates[$type]);
		
		$tpl->set('error', $error);
		$tpl->set('verboseMode', $this->verboseMode);
		
		return $tpl->fetch('');
	}

}
?>
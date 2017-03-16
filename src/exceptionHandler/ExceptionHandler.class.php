<?php
namespace wiggum\services\exceptionHandler;

use \UnexpectedValueException;
use \wiggum\commons\Handler;
use \wiggum\commons\template\Template;
use \wiggum\http\Request;
use \wiggum\http\Response;

class ExceptionHandler extends Handler {
	
	protected $app;
	protected $verboseMode;
	protected $templates;
	
	/**
	 *
	 */
	public function __construct(\wiggum\foundation\Application $app) {
		$this->app = $app;
		$this->verboseMode = $app->config->get('app.environment', 'development') == 'development' ? true : false;
		$this->templates = $app->config->get('services.exceptionHandler', []);
	}
	
	/**
	 * 
	 * @param Request $request
	 * @param Response $response
	 * @param \Exception $exception
	 */
	public function __invoke(Request $request, Response $response, \Exception $exception) {
		$output = '';

		$contentType = $this->determineContentType($request);
		switch ($contentType) {
			case 'application/json':
				$output = $this->renderErrorMessage($exception, 'json');
				break;
		
			case 'text/xml':
			case 'application/xml':
				$output = $this->renderErrorMessage($exception, 'xml');
				break;
		
			case 'text/html':
				$output = $this->renderErrorMessage($exception, 'html');
				break;
		
			default:
				throw new UnexpectedValueException('Cannot render unknown content type ' . $contentType);
		}
		
		if ($exception->getCode() >= 500)
			$this->writeToErrorLog($exception);
		
		$response->withStatus($exception->getCode());
		$response->setContentType($contentType);
		$response->setOutput($output);
		return $response;
	}
	
	/**
	 * Render error
	 *
	 * @param \Exception $error
	 *
	 * @return string
	 */
	protected function renderErrorMessage(\Exception $error, $type) {
		
		if (!isset($this->templates[$type]))
			throw new UnexpectedValueException('Cannot render template type "' . $type . '"');
		
		$template = $this->templates[$type];
		
		if (is_array($this->templates[$type]))	{
			$templateKey = property_exists($error, 'template') ? $error->template : $error->getCode();
			if (isset($this->templates[$type][$templateKey])) {
				$template = $this->templates[$type][$templateKey];
			} else {
				$template = $this->templates[$type][500];
			}
		}
		
		$tpl = new Template('', '');
		$tpl->setTemplatePath($template);
	
		$tpl->set('app', $this->app);
		$tpl->set('error', $error);
		$tpl->set('verboseMode', $this->verboseMode);
	
		return $tpl->fetch('');
	}
	
}
?>

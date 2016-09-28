<?php
namespace services\exceptionHandler;

use \UnexpectedValueException;
use \wiggum\commons\Handler;
use \wiggum\commons\template\Template;
use \wiggum\http\Request;
use \wiggum\http\Response;

class ExceptionHandler extends Handler {
	
	private $verboseMode;
	private $templates;
	
	/**
	 *
	 */
	public function __construct(\wiggum\foundation\Application $app) {
		$this->verboseMode = $app->settings->get('config.environment', 'development') == 'development' ? true : false;
		$this->templates = $app->settings->get('services.exceptionHandler', []);
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
			if (isset($this->templates[$type][$error->getCode()])) {
				$template = $this->templates[$type][$error->getCode()];
			} else {
				throw new UnexpectedValueException('Cannot render template error code "' . $error->getCode() . '"');
			}
		}
		
		$tpl = new Template('');
		$tpl->setTemplatePath(BASE_PATH . '/' . $template);
	
		$tpl->set('error', $error);
		$tpl->set('verboseMode', $this->verboseMode);
	
		return $tpl->fetch('');
	}
	
}
?>
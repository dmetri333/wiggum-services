<?php
namespace wiggum\services\template;

use \wiggum\commons\template\Template;
use \wiggum\foundation\Application;

class TemplateFactory {
	
	private $app;
	
	/**
	 * 
	 * @param Application $app
	 */
	public function __construct(Application $app) {
		$this->app = $app; 
	}
	
	/**
	 * 
	 * @param string $directory
	 * @param string $fileExtension
	 * 
	 * @return \wiggum\commons\template\Template
	 */
	public function getTemplate($directory, $fileExtension = 'tpl.php') {
		
		$template = new Template($directory, $this->app->getBasePath(), $fileExtension);
		$template->set('app', $this->app);
		
		return $template;
	}
	
}
?>
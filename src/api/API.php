<?php
namespace wiggum\services\api;

use \wiggum\commons\helpers\StatusCodeHelper;
use \wiggum\http\Response;

class API {
	
	private $messages = [];

	/**
	 *
	 * @param Response $response
	 * @param mixed $payload
	 * @param integer $code
	 * @param string $message
	 * @return Response
	 */
	public function packageResponse(Response $response, $payload = [], int $code = StatusCodeHelper::HTTP_OK, string $message = ''): Response
	{
	    $message = $this->determineMessage($code, $message);
		$error = $code >= 200 && $code < 300  ? false : true;
	    
	    $output = [
	        'payload' => $payload,
	        'status' => (object) ['error' => $error, 'code' => $code, 'message' => $message],
	        'processTime' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
	    ];
	    
            if ($code >= 100 && $code <= 599)
                $response->withStatus($code, $message);	    
            
	    $response->setContentType('application/json');
	    $response->addHeader('Access-Control-Allow-Origin', '*');
            $response->addHeader('X-Wiggum-API', 'true');
	    $response->setOutput(json_encode($output, JSON_NUMERIC_CHECK));
	    return $response;
	}

	/**
	 * 
	 * @param Response $response
	 * @param int $code
	 * @param string $message
	 * @return Response
	 */
	public function getErrorResponse(Response $response, int $code, string $message = ''): Response
	{
	    return $this->packageResponse($response, false, $code, $message);
	}
	
	/**
	 *
	 * @param integer $code
	 * @param string $message
	 * @return string
	 */
	protected function determineMessage(int $code, string $message = ''): string
	{
		if ($message != '') {
			return $message;
		}

		if (isset($this->messages[$code])) {
			return $this->messages[$code];
		}

		return StatusCodeHelper::getReasonPhrase($code);
	}

	/**
	 *
	 * @param array $messages
	 * @return void
	 */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }
    
    /**
	 *
	 * @param integer $code
	 * @param string $message
	 * @return void
	 */
    public function setMessage(int $code, string $message): void
    {
        $this->messages[$code] = $message;
    }

}	

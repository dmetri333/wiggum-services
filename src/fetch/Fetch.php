<?php
namespace wiggum\services\fetch;

use \wiggum\commons\helpers\StatusCodeHelper;

class Fetch {
	
	private $options = [
		'method' => 'GET',
		'connectTimeout' => 10,
		'debug' => false,
		'sslVerifyPeer' => true,  // set to false to allow self signed certificates
		'sslVerifyHost' => 2,
		'async' => false,
		'userAgent' => '',
		'cookies' => [],
		'headers' => [],
		'data' => [],
		'files' => [],
		'body' => []
	];

	/**
	 *
	 * @param string $url
	 * @param array $options
	 * @return void
	 */
	public function get(string $url, array $options = [])
    {
		$options['method'] = 'GET';
		return $this->request($url, $options);
	}

	/**
	 *
	 * @param string $url
	 * @param array $options
	 * @return void
	 */
	public function post(string $url, array $options = [])
    {
		$options['method'] = 'POST';
		return $this->request($url, $options);
	}

	/**
	 *
	 * @param string $url
	 * @param array $options
	 * @return void
	 */
	public function request(string $url, array $options = [])
    {
        $options = $this->buildOptionsArray($options);
      	
		$data = $this->buildDataArray($options['data']);
		
		if (!empty($data) && $options['method'] == 'GET') {
			$url = $url . '?' . http_build_query($data);
		}

		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
		curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_VERBOSE, $options['debug']);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $options['connectTimeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $options['sslVerifyPeer']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, (int) $options['sslVerifyHost']);

		if ($options['method'] == 'POST' && !empty($options['files'])) {
			foreach ($options['files'] as $name => $file) {
				if (!empty($file['tmp_name']) || !empty($file['type']) || !empty($file['name'])) {
					$data[$name] = curl_file_create($file['tmp_name'], $file['type'], $file['name']);
				}
			}

			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} else if ($options['method'] == 'POST' && !empty($options['body'])) {
			curl_setopt($ch, CURLOPT_POST, true); 
			curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
		} else if ($options['method'] == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true); 
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		} else if ($options['method'] != 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $options['method']);  // for other types of requests including DELETE
        }

        if ($options['async']) { // for async request wait as little as possible before exiting
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 250);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        }

        if ($options['userAgent'] != '') {
            curl_setopt($ch, CURLOPT_USERAGENT, $options['userAgent']);
        }

        if (!empty($options['cookies'])) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $options['cookies']));
        }

        $curlResult = curl_exec($ch);

		if ($options['debug']) {
			$this->debug($url, $options, $data, $curlResult);
		}

		$result = $curlResult;

        $responseCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlinfoContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);   // content type can be in the format application/json;charset=UTF-8
        $responseContentTypes = explode(';', $curlinfoContentType);
        $responseContentType = $responseContentTypes[0];

		if ($responseContentType == 'application/json') {
            $result = json_decode($result);
        }

        if ($curlResult === false) {
            $result = (object) [
				'payload' => false,
				'status' => (object) ['error' => true, 'code' => $responseCode, 'message' => StatusCodeHelper::getReasonPhrase($responseCode)],
				'processTime' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
			];
		}

        curl_close($ch);

        return $result;
    }

	/**
	 *
	 * @param array $options
	 * @return array
	 */
	private function buildOptionsArray(array $options): array
	{
		return array_merge($this->options, $options);
	}

	/**
	 *
	 * @param array $data
	 * @return array
	 */
	private function buildDataArray(array $data): array
	{
		$formData = [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $formData[$key] = json_encode($value);
            } else {
                $formData[$key] = $value;
            }
        }

		return $formData;
	}

	/**
	 *
	 * @param string $url
	 * @param array $options
	 * @param array $data
	 * @param mixed $result
	 * @return void
	 */
	private function debug(string $url, array $options, array $data, $result)
	{
		$error = "\nCURL DEBUG\n";
		$error .= "---------------------------\n";
		$error .= "\n";

		$error .= "Request Url: \n";
		$error .= $url . "\n";
		$error .= "\n";

		$error .= "Request Options: \n";
		$error .= print_r($options, true) . "\n";
		$error .= "\n";

		$error .= "Request Data: \n";
		$error .= print_r($data, true) . "\n";
		$error .= http_build_query($data) . "\n";
		$error .= "\n";

		$error .= "Response Result: \n";
		$error .= print_r($result, true) . "\n";
		$error .= "\n";

		error_log($error);
	}

}

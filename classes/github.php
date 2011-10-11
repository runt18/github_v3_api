<?php
/**
 * Top-level interface to the GitHub API, which encapsulates all HTTP request 
 * processing and offers functions to retrieve specific top-level Github objects.
 * All objects returned from the Github class are lazily loaded.
 * 
 *     $github = new Github;
 *     $user = $github->get_user()
 *				->load();
 * 
 */ 
class Github
{
	public static $base_url = 'https://api.github.com/';
	
	protected $_response_headers = null;
	
    protected $_repos = array();
	
	protected $_default_expect_status = array(
			Request::GET => '200',
			Request::POST => '201',
			Request::PUT => '200',
			'PATCH' => '200',
			Request::DELETE => '204'
			);
	
	/**
	 * Wraps a raw call to the Github API, including setting up authentication
	 * and checking for a valid response. See also [Github::api_json] which adds
	 * a further layer to decode a JSON response to an array.
	 * 
	 * If passed an array in $body and $content_type of application/json, will
	 * automatically convert to a json string.
	 * 
	 * @param string $url The API URL - relative paths will be translated to fullly qualified
	 * @param string $method The request method - defaults to GET
	 * @param mixed $body Either an array or a string body
	 * @param array $options An options array, including request_content_type, response_content_type and expect_status
	 * @return Response
	 */
	public function api($url, $method = Request::GET, 
			$body = null, $options = array())
	{
		// Convert to an absolute url if required
		if (strpos($url, '://') === FALSE)
		{
			$url = self::$base_url . $url;
		}
		
		$this->_response_headers = null;
		
		// Fill out the options
		$options = Arr::merge(
				array(
					'request_content_type' => 'application/json',
					'response_content_type' => 'application/json',
					'expect_status' => $this->_default_expect_status[$method]
				),
				$options);
		
		$request_content_type = $options['request_content_type'];
		$response_content_type = $options['response_content_type'];
		
		// Create the request
		$request = Request::factory($url)
					->method($method);
		
		echo "-- $method $url\r\n";
		
		// Set up the body
		if (($request_content_type == 'application/json') AND is_array($body))
		{
			$request->body(json_encode($body));			
		}
		else
		{
			$request->body($body);
		}
		$request->headers('Content-type', $request_content_type);
		
		// Setup the authentication info
		$request->headers('Authorization', 'Basic ' . base64_encode("{$_ENV['gh_user']}:{$_ENV['gh_pwd']}"));
		$request->client()->options(CURLOPT_CAINFO,'c:\windows\system32\curl-ca-bundle.crt');
		
		// Execute the request
		$response = $request->execute();
		$this->_response_headers = $response->headers();
		
		// Check for response status
		$status = $response->status();		
		
		if ($status != $options['expect_status'])
		{
			throw new Kohana_Exception("Error! Code $status - {$response->body()}");
		}
		
		return $response;
		
	}
	    
	/**
	 * Sends an API call and decodes the result from JSON into an array
	 * 
	 * @param string $url The API URL - relative paths will be translated to fullly qualified
	 * @param string $method The request method - defaults to GET
	 * @param mixed $body Either an array or a string body
	 * @param array $options An options array, including request_content_type, response_content_type and expect_status
	 * @return array
	 */
	public function api_json($url, $method = Request::GET,			
			$body = null, $options = array())
	{		
		$response = $this->api($url, $method, $body, $options);		
		return json_decode($response->body(),true);		
	}
	
	/**
	 * Returns the last headers received from the API
	 * @return HTTP_Header
	 */
	public function api_response_headers()
	{
		return $this->_response_headers;
	}
	
	/**
	 * Returns an individual repository object.
	 * 
	 * @param string $username
	 * @param string $repo
	 * @return Github_Repo 
	 */
	public function get_repo($username, $repo)
    {
        if ( ! isset($this->_repos["$username/$repo"]))
        {
            $this->_repos["$username/$repo"] = new Github_Repo($this, 
					array('url'=>"repos/$username/$repo",
						'owner'=>$username, 
						'name'=>$repo)); 
        }
        return $this->_repos["$username/$repo"];
    }
	
	/**
	 * Loads information for a specific user, or the current user if no username
	 * is passed in.
	 * 
	 * @param string $user 
	 * @return Github_User 
	 */
	public function get_user($username = null)
	{
		$url = $username === null ? 'user' : "users/$username";
		
		return new Github_User(
				$this,
				array(
					'url'=> $url
				));
	}
	
}
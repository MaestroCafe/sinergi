<?php
namespace sinergi;

trait Oauth_provider {
	private $oauth_params, $oauth_uri, $oauth_provider, $oauth_error = false, $consumer_secret, $token_secret;
	
	public function __construct() {
		/* Get OAuth request parameters */
		$this->oauth_params = [];		
		foreach($_GET as $key=>$value) {
			if (substr($key, 0, 6)=='oauth_' || $key == 'user_token') {
				$this->oauth_params[$key] = $value;
			}
		}
		
		/* Get OAuth request URI */
		$this->oauth_uri = explode('?', URL, 2)[0];
		
		/* Create the provider */
		$this->oauth_provider = new \OAuthProvider($this->oauth_params);
	}
	
	
	/**
	 * Bind the functions related to the request token to the provider
	 *
	 */
	 public function handle_request_token() {
	 	/* OAuthProvider will call this callback with the $provider object as an argument, you can throw errors from that handler and set the $provider->consumer_key if all is good */
		$this->oauth_provider->consumerHandler([$this, 'lookup_consumer']);
		
		/* similar to consumerHandler, throw errors related to the timestamp/nonce in this callback */
		$this->oauth_provider->timestampNonceHandler([$this, 'timestamp_nonce_checker']);
	 }
	 
	 
	 /**
	  * Bind the functions related to the access token to the provider
	  *
	  */
	 public function handle_access_token() {
	 	$this->access_token_procedure = true;
	 	
	 	/* similar to consumerHandler, throw errors related to the timestamp/nonce in this callback */
		$this->oauth_provider->timestampNonceHandler([$this, 'timestamp_nonce_checker']);
	 	
	 	/* OAuthProvider will call this callback with the $provider object as an argument, you can throw errors from that handler and set the $provider->consumer_key if all is good */
		$this->oauth_provider->consumerHandler([$this, 'lookup_consumer']);
	 	
	  	/* Bind provider with out token handler */
		$this->oauth_provider->tokenHandler([$this, 'check_token']);
	}
	
	
	public function handle_data_access() {
	  	/* OAuthProvider will call this callback with the $provider object as an argument, you can throw errors from that handler and set the $provider->consumer_key if all is good */
		$this->oauth_provider->consumerHandler([$this, 'lookup_consumer']);
		
		/* similar to consumerHandler, throw errors related to the timestamp/nonce in this callback */
		$this->oauth_provider->timestampNonceHandler([$this, 'timestamp_nonce_checker']);
		
		/* Bind provider with out token handler */
		$this->oauth_provider->tokenHandler([$this, 'check_token']);
	  }
	
	/**
	 * This function throw an error at the consumer
	 * 
	 * @access private
	 * @return void
	 */
	private function oauth_error($status_code, $message=null) {
		$this->oauth_error = true;
		
		switch($status_code) {
			case 400:
				header("HTTP/1.1 400 Bad Request");
				$error = $message;
				break;
			case 401:
				header("HTTP/1.1 401 Unauthorized");
				$error = "Authentication credentials were missing or incorrect.";
				break;
		}
		$this->log(URL, $error);
		echo json_encode(["request"=>$_SERVER["REQUEST_URI"], "error"=>$error], JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK|JSON_FORCE_OBJECT);
		exit;
	}
	
	/**
	 * This function check the handlers that we added in the constructor and then checks for a valid signature
	 * 
	 * @access private
	 * @return void
	 */
	private function check_request() {
		try {
			if($this->access_token_procedure) {
				$this->oauth_provider->isRequestTokenEndpoint(false);
			}
			
			$this->oauth_provider->checkOAuthRequest($this->oauth_uri, OAUTH_HTTP_METHOD_GET);
		} catch (\OAuthException $E) {
			$this->oauth_error = true;
			
			parse_str(\OAuthProvider::reportProblem($E), $error);
			echo \OAuthProvider::reportProblem($E);
			echo $E->getMessage();
			
			switch($error['oauth_problem']) {
				case 'parameter_absent':
					$this->oauth_error(400, "Parameter {$error['oauth_parameters_absent']} is absent.");
					break;
				case 'signature_invalid':
					$this->oauth_error(400, "Signature is invalid.");
					break;
			}
			
			echo \OAuthProvider::reportProblem($E);
		}
	}
	
	/**
	 * Lookup consumer and get consumer secret
	 * 
	 * @param $provider
	 * @var object
	 * @access public
	 * @return const
	 */
	public function lookup_consumer($provider) {
		if($provider->consumer_key=="unknown") {
			return OAUTH_CONSUMER_KEY_UNKNOWN;
		} else if($provider->consumer_key=="blacklisted" || $provider->consumer_key=="throttled") {
			return OAUTH_CONSUMER_KEY_REFUSED;
		}
		
		$provider->consumer_secret = $this->consumer_secret;
		return OAUTH_OK;
	}
	
	/**
	 * Check if timestamp and nonce are valid
	 * 
	 * @param $provider
	 * @var object
	 * @access public
	 * @return const
	 */
	public function timestamp_nonce_checker($provider) {
		if($provider->nonce=="bad") {
			return OAUTH_BAD_NONCE;
		} else if($provider->timestamp=="0") {
			return OAUTH_BAD_TIMESTAMP;
		}
		return OAUTH_OK;
	}
		
	/**
	 * Check if timestamp and nonce are valid
	 * 
	 * @param $provider
	 * @var object
	 * @access public
	 * @return const
	 */
	public function check_token($provider) {		
		if($provider->token=="rejected") {
		    return OAUTH_TOKEN_REJECTED;
		} else if($provider->token=="revoked") {
		    return OAUTH_TOKEN_REVOKED;
		}
		
		$provider->token_secret = $this->token_secret;		
		return OAUTH_OK;
	}
	
	/**
	 * Generates a request token and save it in the db then returns the oauth_token and the oauth_token_secret
	 * 
	 * @access private
	 * @return array
	 */
	private function generate_token() {
		/* Check request */
		$this->check_request();
		
		/* If OAuth have an error, return false and stop */
		if($this->oauth_error){
			return false;
		}
		
		$token = $this->rand_token(32);
		
		$token_secret = $this->rand_token(32);
		
		
		return ["oauth_token"=> $token, "oauth_token_secret"=> $token_secret];
	}
	
	/**
	 * Create unqiue ID (By the way you HAVE more chance of winning the lottery than having twice the same ID). 
	 *
	 */
	private function rand_token ($num, $type='alphadigit') {
		/**
		 * Characters allowed (Should not be anything other than a-zA-Z0-9). 
		 *
		 */
		$characters = array('a','b','c','d','e','f','g','h','o','j','k','l',
		'm','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D',
		'E','F','G','H','O','J','K','L','M','N','O','P','Q','R','S','T','U',
		'V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9');
		if ($type=='alpha') $characters = array_splice($characters, 0, 52);
		if ($type=='digit') $characters = array_splice($characters, 52, 10);
		/**
		 * Shuffle characters.
		 *
		 */
		srand((float) microtime() * 1000000);
		shuffle($characters);
		
		/**
		 * Loop $num times and get characters. 
		 *
		 */
		$id = null;
		do { $id .= $characters[mt_rand(0, (count($characters)-1))]; }
		while (strlen($id) < $num);
		
		/**
		 * Return freshly generated ID. 
		 *
		 */
		return $id;
	}
}
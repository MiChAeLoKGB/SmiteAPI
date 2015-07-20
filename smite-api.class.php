<?php
/*--------------------------------------+
|   Filename: smite-api.class.php  
|   Author: MiChAeLoKGB
|   Copyright: © 2015 - MiChAeLoKGB
|   Version: 1.1.1
+---------------------------------------*/
 
// Settings
$dev_id = 1234; // Change me
$api_key = '';  // Change me
$api_url = 'http://api.smitegame.com/smiteapi.svc/';

class SmiteAPI{

	private $api_key;
	private $dev_id;
	private $site_url;
	private $session;

	// Here you can set maximum limit of retries before function will return definitive FALSE.
	private $call_limit = 5;
	private $access_calls = 0;

	// Those are messages returned by API, here they can be easily changed if API changes
	private $session_max_error = "Maximum number of active sessions reached."; 
	private $session_valid_error = "Failed to validate SessionId.";
	private $session_ok = "Approved";
	private $session_test = "successful";

	public function __construct($api_key, $dev_id, $site_url, $session){
		$this->api_key = $api_key;
		$this->dev_id = $dev_id;
		$this->site_url = $site_url;
		$this->session = $session;
		if (!empty($session)){
			$this->testSession(true);
		}else{
			$this->createSession();
		}
	}

	public function accessAPI($request, $url = "", $format = "JSON", $decodeJSON = true){

		$url_prepend  = $this->dev_id ."/";
		$url_prepend .= $this->createAuth($request) ."/";
		$url_prepend .= $request !== "createsession" ? $this->session ."/" : "";
		$url_prepend .= gmdate('YmdHis')."/";

		$url = "/".$url_prepend.$url;		
		$url = rtrim($url, "/");

		$response = file_get_contents($this->site_url.$request.$format.$url);
		if ($response === false){
			$this->access_calls ++;
			if ($this->access_calls >= $this->call_limit){ $this->access_calls = 0; return FALSE; }else{ return $this->accessAPI($request, $url, $format, $decodeJSON); }
		} elseif (is_array($response) AND $response[0]->ret_msg === $this->session_valid_error){
			$this->testSession(true);				
			return $this->accessAPI($request, $url, $format, $decodeJSON);
		}

		if($decodeJSON){
			$response = json_decode($response, false);
		}
		return $response;
	}

	public function hasReplay($result, $id = false){
		if ($id !== false){
			$response = $this->accessAPI("getmatchdetails", $result, "JSON", true);
			return $response[0]->hasReplay === "y";
		}else{
			return $result[0]->hasReplay === "y";
		}
	}

	private function createAuth($request){
		return md5($this->dev_id.$request.$this->api_key.gmdate('YmdHis'));
	}

	function createSession(){
		$response = $this->accessAPI("createsession", "", "JSON", true);
		if ($response->ret_msg === $this->session_ok){
			// ---------------------------------------------------------------------//
			// Save new session to DB and overwrite old one. Add your DB call here! //
			/***********************************************************************************************************************************/
			$save = $conn->query("UPDATE ".DB_SMITE_API." SET session='".$response->session_id."', timestamp='".(time()+870)."' WHERE id='1'");
			/***********************************************************************************************************************************/
			return $this->session = $response->session_id;
		}else{
			return FALSE;
		}
	}

	private function testSession($create = false){
		$response = $this->accessAPI("testsession", "", "JSON", true);
		if ($create){
			if (strpos($response, $this->session_test) === false){
				return $this->createSession();
			}else{
				return true;
			}
		}else{
			return (strpos($response, $this->session_test) !== false ? true : false);
		}
	}
}
?>

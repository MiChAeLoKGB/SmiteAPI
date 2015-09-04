<?php
/*--------------------------------------+
|   Filename: smite-api.class.php  
|   Author: MiChAeLoKGB
|   Copyright: © 2015 - MiChAeLoKGB
|   Version: 1.3.0
+---------------------------------------*/

// Settings
$smapi_settings = array();

// You can get your API key and Dev ID from https://fs12.formsite.com/HiRez/form48/secure_index.html
$smapi_settings["dev_id"] = 0;
$smapi_settings["api_key"] = "";
$smapi_settings["api_url"] = "http://api.smitegame.com/smiteapi.svc/"; // Url to get data about PC players/matches
$smapi_settings["api_xurl"] = "http://api.xbox.smitegame.com/smiteapi.svc"; // Url to get data about xBox players/matches

// Database settings (for storing sessions)
$smapi_settings["db_prefix"] = ""; // If your table names in DB have any prefix (like fus145a_), put it here, otherwise, leave it empty
$smapi_settings["db_conn"] = 0; // If your site already has MySQLi connection established, put the variable ($connection not string "connection") here, so this class does not have to create new one

// Those can be left empty if db_conn is set to use already established connection
$smapi_settings["db_host"] = ""; // Host of the database -> localhost or something like mysqlXX.site.com
$smapi_settings["db_port"] = ""; // Port to access the DB -> default is 3306
$smapi_settings["db_name"] = ""; // Name of your database
$smapi_settings["db_user"] = ""; // Name of user that has access to database
$smapi_settings["db_pass"] = ""; // Password to access the database

/*

To use this class, just use those three lines and you are ready to go (if you want to use PC version either write PC instead of XBOX or dont type anything):

include_once "smite.class.php";
$variable = new SmiteAPI($smapi_settings);
unset($smapi_settings);

*/

class SmiteAPI{

	private $api_key;
	private $dev_id;
	private $api_url;
	private $session;
	private $session_id;

	private $db_host;
	private $db_port;
	private $db_name;
	private $db_user;
	private $db_pass;
	private $db_table;

	private $smapi_conn;
	private $our_conn = FALSE;

	// Here you can set maximum limit of retries before accessAPI function will return definitive FALSE.
	private $call_limit = 5;
	private $access_calls = 0;

	// Those are messages returned by API, here they can be easily changed if API changes.
	private $session_max_error = "Maximum number of active sessions reached."; 
	private $session_valid_error = "Failed to validate SessionId.";
	private $session_ok = "Approved";
	private $session_test = "successful";

	// Contructor function is called every time when class is loaded and sets up all variables.
	public function __construct($smapi_settings, $version = "PC"){

		$this->api_key = $smapi_settings["api_key"];
		$this->dev_id = $smapi_settings["dev_id"];
		$this->db_table = $smapi_settings["db_prefix"]."smapi_sessions";

		if (strtoupper($version) === "XBOX"){
			$this->api_url = $smapi_settings["api_xurl"];
			$this->session_id = 2;
		}else{
			$this->api_url = $smapi_settings["api_url"];
			$this->session_id = 1;
		}

		if ($smapi_settings["db_conn"] instanceof MySQLi){

			$this->smapi_conn = $smapi_settings["db_conn"];

		}elseif (!empty($smapi_settings["db_host"]) AND !empty($smapi_settings["db_port"]) AND !empty($smapi_settings["db_name"]) AND !empty($smapi_settings["db_user"]) AND !empty($smapi_settings["db_pass"])){

			$this->db_host = $smapi_settings["db_host"];
			$this->db_port = $smapi_settings["db_port"];
			$this->db_name = $smapi_settings["db_name"];
			$this->db_user = $smapi_settings["db_user"];
			$this->db_pass = $smapi_settings["db_pass"];
			$this->smapi_conn = $this->databaseCONN("connect");
			$this->our_conn = TRUE;

		}else{
			throw new Exception("No database connection info specified. Can't create/use MySQLi connection!");
		}
		
		// You can comment/remove those 3 lines after DB table was succesfully created (less load on your server)
		if (!$this->tableExists($this->db_table)){
			$this->createTable();
		}

		$this->session = $this->accessDB("get_session");

		if ($this->session === FALSE){
			throw new Exception("Failed to get/create session on load!");
		}elseif ($this->our_conn){
			$this->databaseCONN("close");
		}
	}

	// Function which makes all calls to API.
	public function accessAPI($request, $url = "", $format = "JSON", $decodeJSON = true){

		$url_prepend  = $this->dev_id."/";
		$url_prepend .= $this->createAuth($request)."/";
		$url_prepend .= $request !== "createsession" ? $this->session."/" : "";
		$url_prepend .= gmdate('YmdHis')."/";

		$url = "/".$url_prepend.$url;		
		$url = rtrim($url, "/");

		$response = file_get_contents($this->api_url.$request.$format.$url);

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

	// Function to quickly check if match has viewable replay stored or not.
	public function hasReplay($result, $id = false){

		if ($id !== false){
			$response = $this->accessAPI("getmatchdetails", $result);
			return !empty($response) ? $response[0]->hasReplay === "y" : FALSE;
		}else{
			return !empty($result) ? $result[0]->hasReplay === "y" : FALSE;
		}
	}

	// Function to create authentification hash required by API
	private function createAuth($request){
		return md5($this->dev_id.$request.$this->api_key.gmdate('YmdHis'));
	}

	// Function which asks the API for new session and then saves it in DB.
	private function createSession(){
		$response = $this->accessAPI("createsession");
		if ($response->ret_msg === $this->session_ok){
			return $this->accessDB("save_session", $response->session_id);
		}else{
			return FALSE;
		}
	}

	// Function that tests current session and if true is passed to it, automatically creates new session if current one is invalid.
	private function testSession($create = false){
		$response = $this->accessAPI("testsession");
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

	// Function to connect and close custom database connection (not used if there was already established connection provided).
	private function databaseCONN($request){
		if ($request === "connect"){
			$smapi_conn = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port);
			if ($smapi_conn->connect_error) {
			    throw new Exception("Connection to DB failed: ".$smapi_conn->connect_error);
			}else{
				return $smapi_conn;
			}
		}elseif ($request === "close" AND $this->smapi_conn instanceof MySQLi){
			$this->smapi_conn->close();
		}
	}

	// Function to create table for storing sessions (creates table only once, still its good to comment out marked line in constructor).
	private function createTable(){
		$request = 	"CREATE TABLE IF NOT EXISTS ".$this->db_table." 
					(	id		INT(11)		UNSIGNED		NOT NULL 	AUTO_INCREMENT,
						session 	TEXT 					NOT NULL,
						timestamp 	TEXT 					NOT NULL,
						PRIMARY KEY (id)
					) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";
		$this->smapi_conn->query($request);
		$this->smapi_conn->query("INSERT INTO ".$this->db_table." (session, timestamp) VALUES ('', '0')");
		$this->smapi_conn->query("INSERT INTO ".$this->db_table." (session, timestamp) VALUES ('', '0')");
		$this->createSession();
	}

	// Function which checks if table does exist in DB or not
	private function tableExists($table){
		$response = $this->smapi_conn->query("SHOW TABLES LIKE '".$table."'");
		return $response->num_rows > 0;
	}

	// Function to get and save sessions to database.
	private function accessDB($request, $session = ""){
		if ($request === "get_session"){
			$get_session = $this->smapi_conn->query("SELECT * FROM ".$this->db_table." WHERE id='".$this->session_id."'");
			$session_data = $get_session->fetch_assoc();
			if ($session_data["timestamp"] <= time()){
				return $this->createSession();
			}else{
				$session = $session_data["session"];
			}
		}elseif($request === "save_session"){
			$save = $this->smapi_conn->query("UPDATE ".$this->db_table." SET session='".$session."', timestamp='".(time()+870)."' WHERE id='".$this->session_id."'");
		}
		return $session;
	}
}
?>

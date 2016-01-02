<?php

// DB settings
$db_host = "";
$db_port = "";
$db_user = "";
$db_pass = "";
$db_name = "";

// Create DB connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
unset($db_host, $db_port, $db_user, $db_pass);

if ($conn->connect_error) {
    die("Connection failed: ".$conn->connect_error);
}

/*
Dont forget to edit smite-api.class.php settings.
As I am using already established connection here, I'll ignore host, port etc., and set db_conn like this:

$smapi_settings["db_conn"] = &$conn; (the & before $conn menas its being passed as a refference, dont forget to use it)
*/



include_once "smite-api.class.php";

$smite = new SmiteAPI($smapi_settings); // <-- This line will set SmiteAPI class to variable $smite. PC version of API will be used.
//$smite = new SmiteAPI($smapi_settings, "XBOX"); <-- USE THIS LINE IF YOU WANT TO USE XBOX API (Can't use both at same time! You would have to declare PC to one variable and xBox to another.).
unset($smapi_settings); // Not really neccessary, but I recommend to use it.


// Mass check for replay files. Separate IDs by space or new line
if (isset($_POST['submit'])){
	echo "<br>";
	$output = preg_split("/(\n+|\s+)/", $_POST['ids']);
	foreach ($output as $replay_id) {
		if (!empty($replay_id)){
			if ($smite->hasReplay($replay_id, true)){
				echo $replay_id." - <span style='font-size: bold; color: green;'>has</span> a replay!";
			}else{
				echo $replay_id." - <span style='font-size: bold; color: red;'>does NOT</span> have a replay!";
			}
		}else{
			echo $replay_id." - WRONG ID!";
		}

		echo "<br>";
	}
	echo "<br><hr><br>";
}

// Form itself
echo "<form method='post' action=''>";
	echo "<textarea name='ids' placeholder='Separate by space or new line' style='width: 150px; height:250px;'></textarea>";
	echo "<br>";
	echo "<input type='submit' name='submit' value='Submit'>";
echo "</form>";


echo "<br><hr><br>";


// To check if match has replay file stored, you can use longer and short version. Both will return same value.
// Shorthand version uses ID as first parameter and true as second.
	var_dump($smite->hasReplay("166832256", true));
	echo "<br>";

// To use longer version, first get match details and then use hasReplay like this:
	$replay = $smite->accessAPI("getmatchdetails", "166832256");
	var_dump($smite->hasReplay($replay));


echo "<br><hr><br>";


// Get details about match with some ID. Go to url: index.php?id=166832256
if (isset($_GET['id']) AND is_numeric($_GET['id'])){
	var_dump($smite->accessAPI("getmatchdetails", $_GET['id']));
}

?>

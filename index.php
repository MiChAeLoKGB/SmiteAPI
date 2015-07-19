<?php

include_once "smite.class.php";

// Get session from database. Change this for your own DB call.
$get_session = dbarray(dbquery("SELECT * FROM ".DB_SMITE_API." WHERE id='1'"));
if ($get_session['timestamp'] <= time()){ 
	$session = ""; // Empty session will force class to instantly create a new one
}else{
	$session = $get_session['session'];
}

$smite = new SmiteAPI($api_key, $dev_id, $api_url, $session);
unset($api_key, $dev_id, $api_url, $session);


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

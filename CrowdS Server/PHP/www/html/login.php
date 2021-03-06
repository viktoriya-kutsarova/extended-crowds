<?php
include("config.php");
include("database.php");
include("system_utils.php");

$current_os = getOSName();

$dbc = new DatabaseController();
$dbc->connect();

// sent from mobile app
$id = $dbc->escapeString($_POST["email"]);
$password = $dbc->escapeString($_POST["password"]);
$firebase = $dbc->escapeString($_POST["firebase"]);

$admin = $dbc->getUserFields($id, array("admin"))["admin"];

// allow users with admin permision to login during maintenance
// sent an error message back to everyone else with the remaining time
if(MAINTENANCE && $admin == 0){
    
    $finish_at = strtotime(MAINTENANCE_TIME);
    $time_left = ceil(($finish_at - strtotime("now"))/60);
    
    $reply = array('status' => "ERROR", 
                   'reason' => "maintenance",
                   'time' => "$time_left");
    echo json_encode($reply);
    return;
}


// coordinates are sent from the mobile app
$lat = $dbc->escapeString($_POST["lat"]);
$lng = $dbc->escapeString($_POST["lng"]);
$os = $dbc->escapeString($_POST["os"]);
$device_version = $dbc->escapeString($_POST["version"]);    

// get server version for the same operating system as the device
if($os == "ios"){
    $server_version = IOS_VERSION;
}
else if($os == "android"){
    $server_version = ANDROID_VERSION;
}


// check if the user is registered
$registered = $dbc->isRegistered($id, $password);

if(!$registered){
	$reply = array('status' => "ERROR", 'reason' => "not a registered user"); 
}

else{
    // only let the device login if it has the correct version
    if($server_version == $device_version){
			
        // schedule a heartbeat in the future using 'at' commands,
				// to check if the user is still connected to the system or offline
        $future = date('H:i', strtotime("+".HB_TIME));
        $taskid = $id;

        $server_os = getOSName();
        $initheartbeatcommand = '';
        if ($server_os === "Windows") {
            $taskid = uniqid($id);
            $initheartbeatcommand = "schtasks.exe /Create /st ".$future." /tn ".$taskid." /sc ONCE /tr \"php".ROOT_PATH."html\heartbeat_check.php ".$id."\" 2>&1";
        } else if ($server_os === "Linux") {
            $initheartbeatcommand = 'echo "php '.ROOT_PATH.'html/heartbeat.php '.$id.'" | at '.$future.' 2>&1';
        } else {
            error_log("Unsupported OS.");
        }
        $atjob = exec($initheartbeatcommand);
//        $at = explode(" ", $atjob);
//        $atid = $at[1];

		// get old heartbeat info from database
        $heartbeat = $dbc->getUserFields($id, array("heartbeat"))["heartbeat"];
        list($old_id, $timestamp) = explode(":", $heartbeat);
        
        $timestamp = strtotime("now");

        $deletetaskcommand = "";
        if ($server_os === "Windows") {
            $deletetaskcommand = "schtasks.exe /Delete /tn ".$old_id." /f 2>&1";
        } else if ($server_os === "Linux") {
            $deletetaskcommand = "atrm ".$old_id." 2>&1";
        } else {
            error_log("Unsupported OS.");
        }
//        error_log("Delete command: " . $deletetaskcommand);
        exec($deletetaskcommand);
        $hb = $taskid.':'.$timestamp;
        
        // update login time and coordinates
        $dbc->updateUser($id, array("login_time", "latitude", "longitude", "firebase", "heartbeat", "active"), array("NOW()", "'$lat'", "'$lng'", "'$firebase'", "'$hb'", "1"));   
    
        // retrieve username
        $username = $dbc->getUserFields($id, array("username"))["username"];
        
        if($os == "ios"){

            // create reply message	
            $reply = array('status' => "OK",
                'username' =>$username);	
        }
        else if($os == "android"){

            // create reply message	
            $reply = array('status' => "OK",
                'username' =>$username);
        }
        else{
            $reply = array('status' => "ERROR", 
                           'reason' => "UNKNOWN OS");
        }

    }
    else{
        $reply = array('status' => "ERROR", 
                       'reason' => "outdated");
    }
}

echo json_encode($reply);

?>

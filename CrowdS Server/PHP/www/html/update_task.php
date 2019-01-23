<?php
include("config.php");
include("firebase.php");
include("database.php");
include("notification.php");
include("system_utils.php");
include(constant('REP'));
include(constant('REW'));

$dbc = new DatabaseController();
$dbc->connect();

// include the file that handles the update
$file_path = "task_update/" . $_POST['file'];
include($file_path);

// get all data from POST
foreach ($_POST as $key => $value){
    $fields[$key] = $dbc->escapeString($value);
}

// include quality control file
$QC = 'QC_' . strtoupper($_POST['type']);
include(constant($QC));

// update the task
$task_updator = getClass($file_path, $fields);
$task_updator->updateTask();

// if logging is activated
if(LOG){
    list($log_file,$index) = explode(':',$fields['id']);
    if($task_updator->getType() == "hit"){
        $log_path = ROOT_PATH."html/log/hit_".$log_file.".json";
    }
    else{
        $log_path = ROOT_PATH."html/log/sensor_".$log_file.".json";
    }
    
    $log = json_decode(file_get_contents($log_path), true);
    
    $now = date('Y-m-d H:i:s');
    
    if(isset($log['received_data'])){
        array_push($log['received_data'],
                        array("from"=>$fields['email'],
                              "data"=>$fields['data'],
                              "time"=>$now));
    }
    else{
        $rd = array(array("from"=>$fields['email'],
          "data"=>$fields['data'],
          "time"=>$now));
        
        $log['received_data'] = $rd;
    }
}

// if this was the last update to complete the task
if($task_updator->isCompleted()){
    $task_data = $task_updator->finishTask();
    $task_updator->sendNotification();
    
    $qc = getClass(constant($QC), $task_data);
    
		// if the task was not auto completed (time ran out)
    if($qc->anyoneParticipated()){
			
				// apply the quality control
        $qc->calculateQuality();
        $qc->updateQuality();
        $result = $qc->setTaskResult();
        
				// get and apply reward management
        $rew = getClass(constant('REW'));
        list($ind,$ids) = explode(':',$fields['id']);
        
        if($task_data['task_type'] == 'sensor'){
            $rew->incrementSensorPoints($task_data['task_giver']);
            $task = $dbc->getSensorTaskFields($ids, array("cost"));
        }
        else{
            $rew->incrementHitPoints($task_data['task_giver']);
            $task = $dbc->getHumanTaskFields($ids, array("cost"));
        }
        
				// reward all participants
        $participants = explode(";", $task_data['participants']);
        foreach($participants as $str){
            list($index, $tid) = explode(":", $str);
            $rew->addPay($tid, $task["cost"]);
        }

    }
    
		// get and apply reward management
    $rep = getClass(constant('REP'), $task_data);
    $rep->calculateReputation();
    $rep->updateReputation();
    
		// if logging is activated
    if(LOG){
        $participants = explode(";", $task_data['participants']);
        $p = array();
        foreach($participants as $str){
            list($index, $id) = explode(":", $str);
            $p[$index] = $id;
        }
        
        $log['completed'] = array("result"=>$result,
                                  "participated"=>$p,
                                  "time"=>$now);
    }
}

// if logging is activated
if(LOG){
    $fp = fopen($log_path, 'w');
    fwrite($fp, json_encode($log,JSON_PRETTY_PRINT));
    fclose($fp);
}

// send reply
$reply = array('status' => "OK");
echo json_encode($reply);

?>
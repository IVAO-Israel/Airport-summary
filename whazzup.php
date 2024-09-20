<?php
	$db = mysqli_connect('localhost', 'root', '', 'ilivao_airports');
	$url = "https://api.ivao.aero/v2/tracker/whazzup";
	date_default_timezone_set('UTC');
	
	$db->begin_transaction();
	try{
		$updatedAt = (int)$db->query("SELECT value FROM data WHERE data='updatedAt'")->fetch_assoc()["value"];
		if((strtotime("now")-$updatedAt) > 15){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://api.ivao.aero/v2/tracker/whazzup");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec($ch);
			curl_close($ch);
			if ($data !== FALSE) {
				$data = json_decode($data, true);
				$stmt = $db->prepare("UPDATE data SET value=? WHERE DATA='updatedAt'");
				$updatedAt = strtotime($data["updatedAt"]);
				$stmt->bind_param("s", $updatedAt);
				$stmt->execute();
				$db->query("DELETE FROM pilots");
				$stmt = $db->prepare("INSERT INTO pilots (id, callsign, departureTime, departureID, departureDistance, arrivalID, arrivalDistance, remainingTime, groundSpeed, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
				$stmt->bind_param("isisdsdiis", $id, $callsign, $departureTime, $departureId, $departureDistance, $arrivalId, $arrivalDistance, $remainingTime, $groundSpeed, $state);
				foreach($data["clients"]["pilots"] as $pilot){
					if($pilot["flightPlan"] && $pilot["lastTrack"]){
						$id= $pilot["id"];
						$callsign = $pilot["callsign"];
						$departureTime = $pilot["flightPlan"]["departureTime"];
						$departureId = $pilot["flightPlan"]["departureId"];
						$departureDistance = $pilot["lastTrack"]["departureDistance"];
						$arrivalId= $pilot["flightPlan"]["arrivalId"];
						$arrivalDistance = $pilot["lastTrack"]["arrivalDistance"];
						$groundSpeed = $pilot["lastTrack"]["groundSpeed"];
						if($groundSpeed != 0){
							$remainingTime = ($arrivalDistance/$groundSpeed)*3600;
						} else {
							$remainingTime = 0;
						}
						$state = $pilot["lastTrack"]["state"];
						$stmt->execute();
					}
				}
				$db->commit();
			}
		}
	} catch (Exception $e){
		$db->rollback();
	}
	
	function get_seconds($timestamp){
    	$date = date('Y-m-d', $timestamp);
    	$midnightTimestamp = strtotime($date . ' 00:00:00');
    	$secondsSinceMidnight = $timestamp - $midnightTimestamp;
    	return $secondsSinceMidnight;
	}
	
	if(isset($_GET['airport'])){
		$airport = $_GET['airport'];
		$stmt = $db->prepare("SELECT * FROM data WHERE data='airport' AND value=?");
		$stmt->bind_param("s", $airport);
		$stmt->execute();
		$result = $stmt->get_result();
		$return_data = [];
		$updatedAt = get_seconds((int)$db->query("SELECT value FROM data WHERE data='updatedAt'")->fetch_assoc()["value"]);
		$return_data["updatedAt"] = $updatedAt;
		if($result->num_rows == 1){
			$stmt = $db->prepare("SELECT * FROM pilots WHERE departureId=?");
			$stmt->bind_param("s", $airport);
			$stmt->execute();
			$result = $stmt->get_result();
			if($result){
				$return_data["departures"] = $result->fetch_all(MYSQLI_ASSOC);
			}
			$stmt = $db->prepare("SELECT * FROM pilots WHERE arrivalId=? ORDER BY remainingTime");
			$stmt->bind_param("s", $airport);
			$stmt->execute();
			$result = $stmt->get_result();
			if($result){
				$data = $result->fetch_all(MYSQLI_ASSOC);
				for($i=0;$i<count($data);$i++){
					$data[$i]["estimatedTime"] = $updatedAt+$data[$i]["remainingTime"];
				}
				$return_data["arrivals"] = $data;
			}
		} else {
			$return_data["message"] = "forbidden";
		}
		echo json_encode($return_data);
	}
?>
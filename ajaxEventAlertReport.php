<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);
ini_set('max_execution_time', -1);
ini_set('memory_limit', '-1');
ini_set('max_input_vars', 3000);
// echo 'Current max_input_vars: ' . ini_get('max_input_vars') . "<br>";
// echo 'Current post_max_size: ' . ini_get('post_max_size') . "<br>";
// echo 'Current upload_max_filesize: ' . ini_get('upload_max_filesize') . "<br>";
// phpinfo();

include_once('../../include_files/connectDb.php');
include_once('/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/troubleTicketTransactions.php');
include_once('/var/www/html/ncrm/tempelate/include_files/track_session.php');
include_once "../../include_files/connection_oracle.php";
$operator = $_SESSION['logincrmid'];
$dbObj = new DbClass1();

$type = $_GET['type'];
$key = $_POST['key'];

$ttTranscation = new troubleTicketTransaction();

$smsLogEscalation = false;

if ($type == "getUserIDReport") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_GET['eventname'];
	// var_dump('eventname', $eventname);

	//	Changed by Hammad on 1 July 2024, Start:

	// $query = "SELECT E.EVENTNAME,E.EVENTID,E.HOURS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS,UE.STATUS AS USTATUS,UE.USERID,to_char(e.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME
	// ,to_char(e.datetime + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE FROM EVENTALERTS E
	// INNER JOIN USEREVENTALERT UE ON E.EVENTID=UE.EVENTID
	// WHERE trim(E.EVENTNAME)=trim('$eventname')";

	$query = "SELECT E.EVENTNAME,E.EVENTID,E.HOURS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS,UE.STATUS AS USTATUS,UE.USERID,to_char(UE.DATETIME,'DD-MM-YYYY HH24:MI:SS') as DATETIME
	,to_char(e.DATETIME + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE FROM EVENTALERTS E
	INNER JOIN USEREVENTALERT UE ON E.EVENTID=UE.EVENTID
	WHERE trim(E.EVENTNAME)=trim(:eventname)";
	// var_dump('eventname', $query);

	//	Changed by Hammad on 1 July 2024, End.

	$checkLogParams = [
		':eventname' => $eventname
	];
	$result = $dbObject->execSelect($query, $checkLogParams);
	// var_dump('result', $result);

	//var_dump($result);exit();
	$dataArray = array();
	for ($i = 0; $i < count($result); $i++) {
		$dataArray[$i][0] = $i + 1;
		$dataArray[$i][1] = $result[$i]['EVENTNAME'];
		$dataArray[$i][2] = $result[$i]['DESCRIPTION'];
		/**/
		$eventid = $result[$i]['EVENTID'];
		// var_dump('eventid', $eventid);

		$startdate = date("d-m-Y H:i:s");
		$hourdate = $result[$i]['HOURDATE'];

		// $startdate1=strtotime($startdate);
		// $hourdate1=strtotime($hourdate);
		// echo $diff= ($hourdate1 - $startdate1)/60/60/24; 
		// echo $days=$diff->format("%R%a days %H:%I:%S");exit();
		/**/
		$hours = $result[$i]['HOURS'];
		$minutes = $result[$i]['MINUTES'];
		// var_dump('hours', $hours);
		// var_dump('minutes', $minutes);

		// $entryTime = $result[$i]['DATETIME'];

		$checkLogQuery = "SELECT to_char(DATETIME, 'DD-MM-YYYY HH24:MI:SS') AS DATETIME, COMMENTS
		FROM (
			SELECT DATETIME, COMMENTS
			FROM NAYATELUSER.EVENTINTIMATIONLOGS
			WHERE EVENTID = :eventid
			AND KEY = :key
			AND STATUS = 'Active'
			AND SUBVALUE = :subvalue
			ORDER BY DATETIME DESC
		)
		WHERE ROWNUM = 1";

		$checkLogParams = [
			':eventid' => $eventid,
			':key' => 'updateProvidedBy',
			':subvalue' => 'YES',
		];

		// Execute query to check for update
		$updateProvidedBYResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);
		// var_dump('updateProvidedBYResult', $updateProvidedBYResult);

		// Default duration calculation logic
		$date1 = date_create($startdate);
		$date2 = date_create($hourdate);

		// If no update is given
		if (empty($updateProvidedBYResult)) {
			$diff = date_diff($date1, $date2);
			$days = $diff->format("%R%H:%I:%S");
			// var_dump('daysss', $days);

			// $dataArray[$i][4] = $days; // Save the calculated duration
		} else {
			// Update is provided; extract information
			$updateDatetime = $updateProvidedBYResult[0]['DATETIME'];
			$lob = $updateProvidedBYResult[0]['COMMENTS'];

			$comments = $lob->load();
			// var_dump('updateDatetime', $updateDatetime);
			// var_dump('comments', $comments);

			// Parse hours and minutes from comments
			preg_match('/(\d+) Hour.*?(\d+) Minutes/', $comments, $matches);
			$updateHours = $matches[1] ?? 0; // Default to 0 if not found
			// var_dump('updateHours', $updateHours);

			$updateMinutes = $matches[2] ?? 0;
			// var_dump('updateMinutes', $updateMinutes);

			// Calculate the resolution time by adding parsed hours/minutes to the update datetime
			$resolutionTime = date("d-m-Y H:i:s", strtotime("+$updateHours hours +$updateMinutes minutes", strtotime($updateDatetime)));
			// var_dump('resolutionTime', $resolutionTime);

			// Calculate the difference between current time and resolution time
			$resolutionDate = date_create($resolutionTime);
			// var_dump('resolutionDate', $resolutionDate);
			$diff = date_diff($date1, $resolutionDate);
			$days = $diff->format("%R%H:%I:%S");
			// var_dump('days', $days);
			// exit;
			// $dataArray[$i][4] = $days; // Save the updated duration
		}

		// $actualTime=date('d-M-Y H:i:s',strtotime("+$hours hour",strtotime($entryTime)));
		// $date1 = date_create($startdate);
		// $date2 = date_create($hourdate);
		// $diff = date_diff($date1, $date2);
		// $days = $diff->format("%R%H:%I:%S"); // - azeem



		$d = $result[$i]['DURATION'];  //- azeem
		// Use regex to remove "0 Day : " or any similar day-related format
		$d = preg_replace("/\d+ Day\s*:\s*/", "", $d); // - azeem

		// $dataArray[$i][3] = $result[$i]['DURATION']; - azeem
		$dataArray[$i][3] = $d;

		$dataArray[$i][4] = $days;
		// var_dump('$dataArray[$i][4] ', $dataArray[$i][4]);

		$dataArray[$i][5] = $result[$i]['USERID'];
		// var_dump('user',  $result[$i]['USERID']);

		// Changed by Hammad on 1 July 2024, Start:

		// $dataArray[$i][6] = $result[$i]['STATUS'];
		// $dataArray[$i][7] = $result[$i]['USTATUS'];
		$dataArray[$i][6] = $result[$i]['USTATUS'];
		$dataArray[$i][7] = $result[$i]['STATUS'];

		// Changed by Hammad on 1 July 2024, End.

		$dataArray[$i][8] = $result[$i]['DATETIME'];
		$userid = $result[$i]['USERID'];
		// var_dump('dataArray', $dataArray);
		// exit;
		$eventid = $result[$i]['EVENTID'];
		$dataArray[$i][9] = "<div class='center'>
			<a class='btn btn-xs btn-red tooltips' title='DisableUser' data-placement='top' data-original-title='Edit' onclick='disableUser(\"$eventid\",\"$userid\")'>
					<span class='glyphicon glyphicon-remove'></span>
			</a>
			<a class='btn btn-xs btn-red tooltips' title='EnableUser' data-placement='top' data-original-title='Edit' onclick='enableUser(\"$eventid\",\"$userid\")'>
					<span class='glyphicon glyphicon-ok'></span>
			</a>
			<a class='btn btn-xs btn-red tooltips' title='DeleteUser' data-placement='top' data-original-title='Edit' onclick='deleteUser(\"$eventid\",\"$userid\")'>
					<span class='glyphicon glyphicon-trash'></span>
			</a>
			</div>";
	}
	//var_dump($dataArray);exit();
	$res = array('data' => $dataArray);
	echo json_encode($res);
} else if ($type == "deleteUser") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$userid = $_POST['userid'];
	// $queryinsert = "delete FROM USEREVENTALERT WHERE USERID='$userid' AND EVENTID='$eventid'";
	$queryinsert = "UPDATE USEREVENTALERT SET STATUS = 'DELETED' WHERE USERID= :userid AND EVENTID= :eventid";
	$checkLogParams = [
		':eventid' => $eventid,
		':userid' => $userid,
	];

	// Execute query to check for update
	$updateProvidedBYResult = $dbObject->execSelect($queryinsert, $checkLogParams);

	echo json_encode("success");
} else if ($type == "disableUser") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$userid = $_POST['userid'];
	$queryinsert = "UPDATE USEREVENTALERT SET STATUS='DISABLE' WHERE USERID = :userid AND EVENTID = :eventid";

	$checkLogParams = [
		':eventid' => $eventid,
		':userid' => $userid,
	];

	$updateProvidedBYResult = $dbObject->execSelect($queryinsert, $checkLogParams);

	echo json_encode("success");
} else if ($type == "enableUser") {
	$eventid = $_POST['eventid'];
	$userid = $_POST['userid'];
	$queryinsert = "UPDATE USEREVENTALERT SET STATUS='ACTIVE' WHERE USERID= :userid AND EVENTID = :eventid";

	$checkLogParams = [
		':eventid' => $eventid,
		':userid' => $userid,
	];

	$updateProvidedBYResult = $dbObject->execSelect($queryinsert, $checkLogParams);
	echo json_encode("success");
} else if ($type == "getEventsReport") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	//	Changed by Hammad on 28 June 2024, Start:

	// $query = "SELECT distinct E.EVENTNAME,E.EVENTID,E.HOURS,E.DAYS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS,to_char(e.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME
	// 	,to_char(e.datetime + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE FROM EVENTALERTS E
	// 	--where e.created_by='momna.hassan'
	// 	";

	$query = "SELECT distinct E.EVENTNAME,E.EVENTID,E.HOURS,E.DAYS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS,E.ESCALATED_TO, to_char(E.ESCALATION_TIME,'DD-MM-YYYY HH24:MI:SS') as ESCALATION_TIME, E.RESOLUTION_PROVIDED_BY ,to_char(e.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME
		,to_char(e.DATETIME + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE , E.CREATED_BY FROM EVENTALERTS E ORDER by e.EVENTID DESC
	";


	$result = $dbObj->Get_Array($query);
	//var_dump($result);exit();


	$dataArray = array();
	for ($i = 0; $i < count($result); $i++) {
		$eventid = $result[$i]['EVENTID'];
		$eventCreatedBy = $result[$i]['CREATED_BY'];

		// Common data
		$dataArray[$i][0] = $i + 1;
		$dataArray[$i][1] = $result[$i]['EVENTNAME'];
		$dataArray[$i][2] = $result[$i]['DESCRIPTION'];
		$startdate = date("d-m-Y H:i:s");
		$hourdate = $result[$i]['HOURDATE'];

		// $startdate1=strtotime($startdate);
		// $hourdate1=strtotime($hourdate);
		// echo $diff= ($hourdate1 - $startdate1)/60/60/24; 
		// echo $days=$diff->format("%R%a days %H:%I:%S");exit();
		/**/
		$eventname = $result[$i]['EVENTNAME'];

		// $eventdescription = $result[$i]['DESCRIPTION'];
		$eventdescription = trim($result[$i]['DESCRIPTION']);
		$hours = $result[$i]['HOURS'];
		$minutes = $result[$i]['MINUTES'];
		$daysactual = $result[$i]['DAYS'];
		// $entryTime = $result[$i]['DATETIME'];

		// $actualTime=date('d-M-Y H:i:s',strtotime("+$hours hour",strtotime($entryTime)));
		// $date1 = date_create($startdate);
		// $date2 = date_create($hourdate);
		// $diff = date_diff($date1, $date2);
		// $days = $diff->format("%R%H:%I:%S"); // - azeem

		if ($eventCreatedBy === 'system') {
			$d = $result[$i]['DURATION'];  //- azeem
			// Use regex to remove "0 Day : " or any similar day-related format
			$d = preg_replace("/\d+ Day\s*:\s*/", "", $d); // - azeem

			// $dataArray[$i][3] = $result[$i]['DURATION']; - azeem
			$dataArray[$i][3] = $d;
		} else {
			$dataArray[$i][3] = $result[$i]['DURATION'];
		}


		if ($eventCreatedBy === 'system') {
			// SQL Query to check for updates
			$checkLogQuery = "SELECT to_char(DATETIME, 'DD-MM-YYYY HH24:MI:SS') AS DATETIME, COMMENTS
			FROM (
				SELECT DATETIME, COMMENTS
				FROM NAYATELUSER.EVENTINTIMATIONLOGS
				WHERE EVENTID = :eventid
				AND KEY = :key
				AND STATUS = 'Active'
				AND SUBVALUE = :subvalue
				ORDER BY DATETIME DESC
			)
			WHERE ROWNUM = 1";

			$checkLogParams = [
				':eventid' => $eventid,
				':key' => 'updateProvidedBy',
				':subvalue' => 'YES',
			];

			// Execute query to check for update
			$updateProvidedBYResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

			// Default duration calculation logic
			$date1 = date_create($startdate);
			$date2 = date_create($hourdate);

			// If no update is given
			if (empty($updateProvidedBYResult)) {
				$diff = date_diff($date1, $date2);
				$days = $diff->format("%R%H:%I:%S");
				// $dataArray[$i][4] = $days; // Save the calculated duration
			} else {
				// Update is provided; extract information
				$updateDatetime = $updateProvidedBYResult[0]['DATETIME'];
				$lob = $updateProvidedBYResult[0]['COMMENTS'];

				$comments = $lob->load();
				// var_dump('updateDatetime', $updateDatetime);
				// var_dump('comments', $comments);

				// Parse hours and minutes from comments
				preg_match('/(\d+) Hour.*?(\d+) Minutes/', $comments, $matches);
				$updateHours = $matches[1] ?? 0; // Default to 0 if not found
				// var_dump('updateHours', $updateHours);

				$updateMinutes = $matches[2] ?? 0;
				// var_dump('updateMinutes', $updateMinutes);

				// Calculate the resolution time by adding parsed hours/minutes to the update datetime
				$resolutionTime = date("d-m-Y H:i:s", strtotime("+$updateHours hours +$updateMinutes minutes", strtotime($updateDatetime)));
				// var_dump('resolutionTime', $resolutionTime);

				// Calculate the difference between current time and resolution time
				$resolutionDate = date_create($resolutionTime);
				// var_dump('resolutionDate', $resolutionDate);
				$diff = date_diff($date1, $resolutionDate);
				$days = $diff->format("%R%H:%I:%S");
				// var_dump('days', $days);
				// exit;
				// $dataArray[$i][4] = $days; // Save the updated duration
			}

			$dataArray[$i][4] = $days;
		} else {
			$date1 = date_create($startdate);
			$date2 = date_create($hourdate);
			$diff = date_diff($date1, $date2);
			$days = $diff->format("%R%a days %H:%I:%S");
			$dataArray[$i][4] = $days;
		}

		$dataArray[$i][5] = $result[$i]['STATUS'];

		// Convert DATETIME string into PHP DateTime object
		// var_dump($result[$i]['DATETIME']);
		$dateTime = new DateTime($result[$i]['DATETIME']); // Added by azeem
		// var_dump($dateTime);
		// exit;
		$dataArray[$i][6] = $dateTime->format('Y-m-d H:i:s'); // Added by azeem

		// var_dump('eventid', $eventid);

		if ($eventCreatedBy === 'system') {
			$escalatedTo = $result[$i]['ESCALATED_TO']; // Added by azeem
			if ($escalatedTo === NULL) {
				$escalatedTo = 'empty';
			}

			//	PHASE2 - M Azeem Khan - Start
			$checkLogQuery = "SELECT VALUE, COMMENTS
			FROM (
				SELECT VALUE, COMMENTS
				FROM NAYATELUSER.EVENTINTIMATIONLOGS
				WHERE EVENTID = :eventid
				AND KEY = :key
				AND STATUS = 'Active'
				ORDER BY datetime DESC
			)
			WHERE ROWNUM = 1
			";

			$checkLogParams = [
				':eventid' => $eventid,
				':key' => 'updateProvidedBy',
				':status' => 'Active',
			];
			$updateProvidedResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

			// var_dump('updateProvidedResult', $updateProvidedResult);

			$updateProvidedBy = $updateProvidedResult[0]['VALUE'];

			// var_dump('updateProvidedBy', $updateProvidedBy);

			$noReasonEttrQuery = "SELECT COMMENTS
			FROM (
				SELECT VALUE, COMMENTS
				FROM NAYATELUSER.EVENTINTIMATIONLOGS
				WHERE EVENTID = :eventid
				AND KEY = :key
				AND SUBKEY = :subkey
				AND SUBVALUE = :subvalue
				AND STATUS = 'Active'
				ORDER BY datetime DESC
			)
			WHERE ROWNUM = 1
			";

			$checkLogParams = [
				':eventid' => $eventid,
				':key' => 'updateProvidedBy',
				':subkey' => 'ettrStatus',
				':subvalue' => 'NO',
				':status' => 'Active',
			];
			$noReasonEttrQueryResult = $dbObject->execSelect($noReasonEttrQuery, $checkLogParams);

			if (!empty($noReasonEttrQueryResult)) {
				$lob = $noReasonEttrQueryResult[0]['COMMENTS']; // Fetch the CLOB descriptor

				// Read the content of the CLOB
				if (is_a($lob, 'OCI-Lob')) {
					$noETTRReason = $lob->load(); // Load the content of the CLOB
				} else {
					$noETTRReason = $lob; // If it's already a string
				}
			} else {
				$noETTRReason = null; // No result
			}
			// var_dump('updateProvidedBy', $updateProvidedBy);

			//	PHASE2 - M Azeem Khan - End

			if ($updateProvidedBy === NULL) {
				$updateProvidedBy = 'empty';
			}

			// $noETTRReason = $result[$i]['NO_ETTR_REASON']; // Added by azeem

			$resolutionProvidedBy = $result[$i]['RESOLUTION_PROVIDED_BY']; // Added by azeem
			if ($resolutionProvidedBy === NULL) {
				$resolutionProvidedBy = 'empty';
			}

			$escalation_time = $result[$i]['ESCALATION_TIME']; // Added by azeem phase2 
			if ($escalation_time === NULL) {
				$escalation_time = 'empty';
			}

			$disableButton = '';
			if (strpos($eventname, 'FC-') !== false && $escalatedTo === 'empty') {
				// Disable the button if conditions match
				$disableButton = 'disabled';
			}
			// var_dump('updateProvidedBy', $updateProvidedBy);

			$dataArray[$i][7] = "<div class='center'>
					<a class='btn btn-xs btn-red tooltips' title='DisableEvent' data-placement='top' data-original-title='Edit' onclick='disableEvent(\"$eventid\",\"$resolutionProvidedBy\")' $disableButton>
						<span class='glyphicon glyphicon-remove'></span>
					</a>
	
					<a class='btn btn-xs btn-red tooltips' title='EnableEvent' data-placement='top' data-original-title='Edit' onclick='EnableEvent(\"$eventid\")'>
						<span class='glyphicon glyphicon-ok'></span>
					</a>
	
					<a class='btn btn-xs btn-red tooltips' title='UpdateEvent' data-placement='top' data-original-title='Edit' onclick='updateEvent(\"$eventid\",\"$eventname\",\"$eventdescription\",\"$hours\",\"$minutes\",\"$daysactual\",\"$escalatedTo\",\"$updateProvidedBy\",\"$noETTRReason\",\"$resolutionProvidedBy\",\"$escalation_time\")'> 
						<i class='fa fa-edit'></i>
					</a>
	
					<a class='btn btn-xs btn-primary tooltips' title='AddUser' data-placement='top' data-original-title='Edit' onclick='addUsers(\"$eventid\")'>
						<span class='glyphicon glyphicon-user'></span>
					</a>
	
					<a class='btn btn-xs btn-primary tooltips' title='ViewUsers' data-placement='top' data-original-title='ViewUsers' onclick='viewUsers(\"$eventname\")'>
						<span class='glyphicon glyphicon-eye-open'></span>
					</a>  
	
					<a class='btn btn-xs btn-success tooltips' title='TrackEvent' data-placement='top' data-original-title='Track Event' onclick='trackerRoute(\"$eventname\")'>
					   <i class='fa fa-search'></i> 
					</a>
					
					</div>";
		} else {
			$dataArray[$i][7] = "<div class='center'>
				<a class='btn btn-xs btn-red tooltips' title='DisableEvent' data-placement='top' data-original-title='Edit' onclick='disableEvent(\"$eventid\")'>
					<span class='glyphicon glyphicon-remove'></span>
				</a>

				<a class='btn btn-xs btn-red tooltips' title='EnableEvent' data-placement='top' data-original-title='Edit' onclick='EnableEvent(\"$eventid\")'>
					<span class='glyphicon glyphicon-ok'></span>
				</a>

				<a class='btn btn-xs btn-red tooltips' title='UpdateEvent' data-placement='top' data-original-title='Edit' onclick='updateManualEvent(\"$eventid\",\"$eventname\",\"$eventdescription\",\"$hours\",\"$minutes\",\"$daysactual\")'>
					<i class='fa fa-edit'></i>
				</a>

				<a class='btn btn-xs btn-primary tooltips' title='AddUser' data-placement='top' data-original-title='Edit' onclick='addUsers(\"$eventid\")'>
				    <span class='glyphicon glyphicon-user'></span>
				</a>

                <a class='btn btn-xs btn-primary tooltips' title='ViewUsers' data-placement='top' data-original-title='ViewUsers' onclick='viewManualUsers(\"$eventname\")'>
				    <span class='glyphicon glyphicon-eye-open'></span>
				</a>
				
				</div>";
		}
	}
	$res = array('data' => $dataArray);
	echo json_encode($res);
} else if ($type == "disableEvent") {

	$eventid = $_POST['eventid'];
	// var_dump('eventid', $eventid);

	$queryupdate = "UPDATE  EVENTALERTS SET STATUS='DISABLE',OPERATOR='$operator' WHERE EVENTID='$eventid'";
	$res = $dbObj->insert($queryupdate);
	// var_dump('res', $res);
	// by momna on 1stNov2023, converting days,hours,mins into outage_end_dateTime
	$querySelect = "SELECT * from USEREVENTALERT where eventid='$eventid'";
	$userids = $dbObj->Get_Array($querySelect);
	// var_dump('userids', $userids);

	for ($i = 0; $i < count($userids); $i++) {
		$querySelect = "SELECT to_char(a.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME,a.HOURS,a.MINUTES,a.EVENT_TYPE,a.EVENTID from
		 EVENTALERTS a
		 JOIN USEREVENTALERT b
		 on a.eventid=b.eventid 
		 where a.status='ACTIVE' 
		 and a.event_type='Fiber Cut'
		 and b.userid='" . $userids[$i]['USERID'] . "'";

		$resultSelect = $dbObj->Get_Array($querySelect);
		// var_dump('resultSelect', $resultSelect);

		// if user is involved in another on going-fiber cut outage then update outage flags in multi IVR table, 
		// otherwise set flag to null
		if (count($resultSelect) > 0) {
			$hours = $resultSelect[0]['HOURS'];
			$mins = $resultSelect[0]['MINUTES'];
			$event_id = $resultSelect[0]['EVENTID'];
			// var_dump('hours', $hours);
			// var_dump('mins', $mins);
			// var_dump('event_id', $event_id);

			$date = new DateTime($resultSelect[0]['DATETIME']);
			$date->add(new DateInterval("PT{$hours}H{$mins}M"));
			// var_dump('date', $date);


			$dateString = $date->format('d-M-Y H:i:s');
			// var_dump('dateString', $dateString);

			$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG=1,EVENTID='$event_id',OUTAGE_END_TIME=to_date('$dateString', 'DD-MON-YYYY HH24:MI:SS') WHERE USERID = '" . $userids[$i]['USERID'] . "'";
			$res = $dbObj->insert($query);
			// var_dump('res', $res);
		} else {
			$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG=0 WHERE USERID = '" . $userids[$i]['USERID'] . "'";
			$res = $dbObj->insert($query);
		}
	}
	//end 

	// $queryinsert="UPDATE  USEREVENTALERT SET STATUS='DISABLE' WHERE EVENTID='$eventid'";
	// $res=$dbObj->insert($queryinsert);

	echo json_encode("success");
} else if ($type == "EnableEvent") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	// var_dump('eventid', $eventid);

	$queryupdate = "UPDATE EVENTALERTS SET STATUS='ACTIVE',OPERATOR=:operator WHERE EVENTID=:eventid";
	$checkLogParams = [
		':eventid' => $eventid,
		':operator' => $operator,
	];
	$res = $dbObject->execInsertUpdate($queryupdate, $checkLogParams);
	// var_dump('res', $res);

	// by momna on 1stNov2023, converting days,hours,mins into outage_end_dateTime
	$query = "SELECT * from EVENTALERTS WHERE EVENTID=:eventid";
	$checkLogParams = [
		':eventid' => $eventid,
	];
	$resultSelect = $dbObject->execSelect($query, $checkLogParams);
	// var_dump('resultSelect', $resultSelect);

	$eventtype = $resultSelect[0]['EVENT_TYPE'];
	// var_dump('eventtype', $eventtype);

	if ($eventtype == 'Fiber Cut') {
		$hours = $resultSelect[0]['HOURS'];
		$mins = $resultSelect[0]['MINUTES'];
		// var_dump('hours', $hours);
		// var_dump('mins', $mins);

		$date = new DateTime($resultSelect[0]['DATETIME']);
		$date->add(new DateInterval("PT{$hours}H{$mins}M"));

		$dateString = $date->format('d-M-Y H:i:s');
		// var_dump('dateString', $dateString);

		$querySelect = "SELECT * from USEREVENTALERT where eventid=:eventid";
		$checkLogParams = [
			':eventid' => $eventid,
		];
		$userids = $dbObject->execSelect($querySelect, $checkLogParams);
		// var_dump('userids', $userids);

		for ($i = 0; $i < count($userids); $i++) {

			// if this is the only event where user was involved, then simply update flag
			// otherwise replace flags with the other ongoing event
			$query = "SELECT * from NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY where userid = :userid and eventid = :eventid and outage_flag=0";
			// $res = $dbObj->Get_Array($query);
			$checkLogParams = [
				':eventid' => $eventid,
				':userid' => $userids[$i]['USERID'],
			];
			$res = $dbObject->execSelect($query, $checkLogParams);
			// var_dump('res', $res);

			if (count($res) >= 1) {
				$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG = 1 WHERE USERID = :userid and EVENTID = :eventid";
				// $res = $dbObj->insert($query);
				$updateParams = [
					':userid' => $userids[$i]['USERID'],
					':eventid' => $eventid,
				];
				$res = $dbObject->execInsertUpdate($query, $updateParams);
			} else {

				$query = "SELECT * from NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY where userid = :userid and (OUTAGE_END_TIME is null or OUTAGE_END_TIME <= to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS')) ";
				// $res = $dbObj->Get_Array($query);
				$selectParams = [
					':userid' => $userids[$i]['USERID'],
					':dateString' => $dateString,
				];
				$res = $dbObject->execSelect($query, $selectParams);
				// var_dump(count($res));exit;

				if (count($res) >= 1) {
					$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG=1 , EVENTID=:eventid, OUTAGE_END_TIME=to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS') WHERE USERID = :userid";
					// $res = $dbObj->insert($query);
					$updateParams = [
						':userid' => $userids[$i]['USERID'],
						':eventid' => $eventid,
						':dateString' => $dateString,
					];
					$res = $dbObject->execInsertUpdate($query, $updateParams);
				}
			}
		}
	}

	// end

	// $queryinsert="UPDATE  USEREVENTALERT SET STATUS='ACTIVE' WHERE EVENTID='$eventid'";
	// $res=$dbObj->insert($queryinsert);

	echo json_encode("success");
} else if ($type == "updateEvent") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$eventdescription = $_POST['eventdescription'];

	// var_dump('eventid : ', $eventid);

	$queryinsert = "UPDATE EVENTALERTS SET DESCRIPTION = :eventdescription , OPERATOR=:operator, UPDATED_AT = sysdate,  UPDATED_BY = :operator WHERE EVENTID=:eventid"; // changes made by Azeem
	$dbObject->execSelect($queryinsert, [
		':eventdescription' => $eventdescription,
		':operator' => $operator,
		':eventid' => $eventid,
	]);

	echo json_encode("success");
}
//added by waqas 01-FEB-2021, adding users to activity on run time...
else if ($type == "ADD USERS") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventid = $_POST['userEventId'];
	$userids = $_POST['userids'];
	$userids = ltrim($userids, ',');
	$userids = explode(',', $userids);
	// var_dump("here");exit;
	// by momna on 1stNov2023, converting days,hours,mins into outage_end_dateTime

	$querySelect = "SELECT to_char(datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME,HOURS,MINUTES,EVENT_TYPE from EVENTALERTS where eventid=:eventid";
	// var_dump($querySelect);exit;
	// $resultSelect = $dbObj->Get_Array($querySelect);
	$paramsSelect = [':eventid' => $eventid];
	$resultSelect = $dbObject->execSelect($querySelect, $paramsSelect);


	$eventtype = $resultSelect[0]['EVENT_TYPE'];
	$hours = $resultSelect[0]['HOURS'];
	$mins = $resultSelect[0]['MINUTES'];

	$date = new DateTime($resultSelect[0]['DATETIME']);
	$date->add(new DateInterval("PT{$hours}H{$mins}M"));

	$dateString = $date->format('d-M-Y H:i:s');

	//end

	for ($i = 0; $i < count($userids); $i++) {
		$userid = $userids[$i];

		// by momna on 1stNov2023
		if ($eventtype == 'Fiber Cut') {

			$query = "SELECT * from NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY where userid = :userid and (OUTAGE_END_TIME is null or OUTAGE_END_TIME < to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS'))";
			// $res = $dbObj->Get_Array($query);
			$params = [
				':userid' => $userid,
				':dateString' => $dateString,
			];
			$res = $dbObject->execSelect($query, $params);

			// var_dump(count($res));exit;

			if (count($res) >= 1) {
				$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG=1 , EVENTID = :eventid , OUTAGE_END_TIME=to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS') WHERE USERID = :userid";
				// $res = $dbObj->insert($query);
				$paramsUpdate = [
					':eventid' => $eventid,
					':dateString' => $dateString,
					':userid' => $userid,
				];
				$res = $dbObject->execInsertUpdate($query, $paramsUpdate);
			}
		}
		// end

		$querySelect = "select * from USEREVENTALERT where eventid = :eventid and userid = :userid";
		// $resultSelect = $dbObj->Get_Array($querySelect);
		$paramsUserEvent = [
			':eventid' => $eventid,
			':userid' => $userid,
		];
		$resultSelect = $dbObject->execSelect($querySelect, $paramsUserEvent);

		if (!isset($resultSelect[0])) {
			$queryInsert = "INSERT INTO USEREVENTALERT (ID,USERID,EVENTID,OPERATOR,DATETIME,STATUS)
			VALUES (USEREVENTALERT_ID_SEQ.NEXTVAL, :userid , :eventid ,:operator,sysdate,'ACTIVE')";
			// $resInsert = $dbObj->insert($queryInsert);
			$paramsInsert = [
				':userid' => $userid,
				':eventid' => $eventid,
				':operator' => $operator,
			];
			$resInsert = $dbObject->execInsertUpdate($queryInsert, $paramsInsert);
		} else {
			//do nothing.
		}
	}

	echo json_encode("true");
}
//waqas code ends here 01-FEB-2021...

//Azeem code Start here 09-18-2024
elseif ($type == "getEventReportById") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$event_id = $_GET['event_id'];

	$query = "SELECT distinct E.EVENTNAME,E.EVENTID,E.HOURS,E.DAYS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS, E.ESCALATED_TO,to_char(E.ESCALATION_TIME,'DD-MM-YYYY HH24:MI:SS') as ESCALATION_TIME, E.RESOLUTION_PROVIDED_BY ,to_char(e.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME
		,to_char(e.DATETIME + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE FROM EVENTALERTS E WHERE E.EVENTNAME = :event_id";

	// $result = $dbObj->Get_Array($query);
	$params = [':event_id' => $event_id];
	$result = $dbObject->execSelect($query, $params);

	// var_dump($result);
	// exit();
	$dataArray = array();

	for ($i = 0; $i < count($result); $i++) {
		$dataArray[$i][0] = $i + 1;
		$dataArray[$i][1] = $result[$i]['EVENTNAME'];
		$dataArray[$i][2] = $result[$i]['DESCRIPTION'];
		/**/
		$startdate = date("d-m-Y H:i:s");
		$hourdate = $result[$i]['HOURDATE'];
		$eventid = $result[$i]['EVENTID'];

		// $startdate1=strtotime($startdate);
		// $hourdate1=strtotime($hourdate);
		// echo $diff= ($hourdate1 - $startdate1)/60/60/24; 
		// echo $days=$diff->format("%R%a days %H:%I:%S");exit();
		/**/
		$eventname = $result[$i]['EVENTNAME'];
		// $eventdescription = $result[$i]['DESCRIPTION'];
		$eventdescription = trim($result[$i]['DESCRIPTION']);
		$hours = $result[$i]['HOURS'];
		$minutes = $result[$i]['MINUTES'];
		$daysactual = $result[$i]['DAYS'];
		// $entryTime = $result[$i]['DATETIME'];

		// $actualTime=date('d-M-Y H:i:s',strtotime("+$hours hour",strtotime($entryTime)));
		// $date1 = date_create($startdate);
		// $date2 = date_create($hourdate);
		// $diff = date_diff($date1, $date2);
		// $days = $diff->format("%R%H:%I:%S"); // - azeem

		// SQL Query to check for updates
		$checkLogQuery = "SELECT to_char(DATETIME, 'DD-MM-YYYY HH24:MI:SS') AS DATETIME, COMMENTS
			FROM (
				SELECT DATETIME, COMMENTS
				FROM NAYATELUSER.EVENTINTIMATIONLOGS
				WHERE EVENTID = :eventid
				AND KEY = :key
				AND STATUS = 'Active'
				AND SUBVALUE = :subvalue
				ORDER BY DATETIME DESC
			)
			WHERE ROWNUM = 1";

		$checkLogParams = [
			':eventid' => $eventid,
			':key' => 'updateProvidedBy',
			':subvalue' => 'YES',
		];

		// Execute query to check for update
		$updateProvidedBYResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

		// Default duration calculation logic
		$date1 = date_create($startdate);
		$date2 = date_create($hourdate);

		// If no update is given
		if (empty($updateProvidedBYResult)) {
			$diff = date_diff($date1, $date2);
			$days = $diff->format("%R%H:%I:%S");
			// $dataArray[$i][4] = $days; // Save the calculated duration
		} else {
			// Update is provided; extract information
			$updateDatetime = $updateProvidedBYResult[0]['DATETIME'];
			$lob = $updateProvidedBYResult[0]['COMMENTS'];

			$comments = $lob->load();
			// var_dump('updateDatetime', $updateDatetime);
			// var_dump('comments', $comments);

			// Parse hours and minutes from comments
			preg_match('/(\d+) Hour.*?(\d+) Minutes/', $comments, $matches);
			$updateHours = $matches[1] ?? 0; // Default to 0 if not found
			// var_dump('updateHours', $updateHours);

			$updateMinutes = $matches[2] ?? 0;
			// var_dump('updateMinutes', $updateMinutes);

			// Calculate the resolution time by adding parsed hours/minutes to the update datetime
			$resolutionTime = date("d-m-Y H:i:s", strtotime("+$updateHours hours +$updateMinutes minutes", strtotime($updateDatetime)));
			// var_dump('resolutionTime', $resolutionTime);

			// Calculate the difference between current time and resolution time
			$resolutionDate = date_create($resolutionTime);
			// var_dump('resolutionDate', $resolutionDate);
			$diff = date_diff($date1, $resolutionDate);
			$days = $diff->format("%R%H:%I:%S");
			// var_dump('days', $days);
			// exit;
			// $dataArray[$i][4] = $days; // Save the updated duration
		}

		$d = $result[$i]['DURATION'];  //- azeem
		// Use regex to remove "0 Day : " or any similar day-related format
		$d = preg_replace("/\d+ Day\s*:\s*/", "", $d); // - azeem

		$dataArray[$i][3] = $d;

		$dataArray[$i][4] = $days;
		$dataArray[$i][5] = $result[$i]['STATUS'];

		// Convert DATETIME string into PHP DateTime object
		// var_dump($result[$i]['DATETIME']);
		$dateTime = new DateTime($result[$i]['DATETIME']); // Added by azeem
		// var_dump($dateTime);
		// exit;
		$dataArray[$i][6] = $dateTime->format('Y-m-d H:i:s'); // Added by azeem

		$eventid = $result[$i]['EVENTID'];
		$escalatedTo = $result[$i]['ESCALATED_TO']; // Added by azeem
		if ($escalatedTo === NULL) {
			$escalatedTo = 'empty';
		}

		//	PHASE2 - M Azeem Khan - Start

		$checkLogQuery = "SELECT VALUE, COMMENTS
             FROM (
                 SELECT VALUE, COMMENTS
                 FROM NAYATELUSER.EVENTINTIMATIONLOGS
                 WHERE EVENTID = :eventid
                 AND KEY = :key
                 AND STATUS = 'Active'
                 ORDER BY datetime DESC
             )
             WHERE ROWNUM = 1
         ";

		$checkLogParams = [
			':eventid' => $eventid,
			':key' => 'updateProvidedBy',
			':status' => 'Active',
		];
		$updateProvidedResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

		$updateProvidedBy = $updateProvidedResult[0]['VALUE'];


		$noReasonEttrQuery = "SELECT COMMENTS
             FROM (
                 SELECT VALUE, COMMENTS
                 FROM NAYATELUSER.EVENTINTIMATIONLOGS
                 WHERE EVENTID = :eventid
                 AND KEY = :key
                 AND SUBKEY = :subkey
                 AND SUBVALUE = :subvalue
                 AND STATUS = 'Active'
                 ORDER BY datetime DESC
             )
             WHERE ROWNUM = 1
         ";

		$checkLogParams = [
			':eventid' => $eventid,
			':key' => 'updateProvidedBy',
			':subkey' => 'ettrStatus',
			':subvalue' => 'NO',
			':status' => 'Active',
		];
		$noReasonEttrQueryResult = $dbObject->execSelect($noReasonEttrQuery, $checkLogParams);
		// $noETTRReason = $noReasonEttrQueryResult[0]['COMMENTS'];

		// var_dump('noETTRReason', $noETTRReason);
		// var_dump('updateProvidedBy', $updateProvidedBy);

		//	PHASE2 - M Azeem Khan - End
		if (!empty($noReasonEttrQueryResult)) {
			$lob = $noReasonEttrQueryResult[0]['COMMENTS']; // Fetch the CLOB descriptor

			// Read the content of the CLOB
			if (is_a($lob, 'OCI-Lob')) {
				$noETTRReason = $lob->load(); // Load the content of the CLOB
			} else {
				$noETTRReason = $lob; // If it's already a string
			}
		} else {
			$noETTRReason = null; // No result
		}

		// $updateProvidedBy = $result[$i]['UPDATE_PROVIDED_BY']; // Added by azeem
		if ($updateProvidedBy === NULL) {
			$updateProvidedBy = 'empty';
		}

		// $noETTRReason = $result[$i]['NO_ETTR_REASON']; // Added by azeem

		$resolutionProvidedBy = $result[$i]['RESOLUTION_PROVIDED_BY']; // Added by azeem
		if ($resolutionProvidedBy === NULL) {
			$resolutionProvidedBy = 'empty';
		}

		$escalation_time = $result[$i]['ESCALATION_TIME']; // Added by azeem phase2 
		if ($escalation_time === NULL) {
			$escalation_time = 'empty';
		}

		$disableButton = '';
		if (strpos($eventname, 'FC-') !== false && $escalatedTo === 'empty') {
			// Disable the button if conditions match
			$disableButton = 'disabled';
		}

		// var_dump('resolutionProvidedBy: ', $resolutionProvidedBy);
		// exit;
		$dataArray[$i][7] = "<div class='center'>
                <a class='btn btn-xs btn-red tooltips' title='DisableEvent' data-placement='top' data-original-title='Edit' onclick='disableEvent(\"$eventid\",\"$resolutionProvidedBy\")' $disableButton>
                    <span class='glyphicon glyphicon-remove'></span>
                </a>

				<a class='btn btn-xs btn-red tooltips' title='EnableEvent' data-placement='top' data-original-title='Edit' onclick='EnableEvent(\"$eventid\")'>
					<span class='glyphicon glyphicon-ok'></span>
				</a>

				<a class='btn btn-xs btn-red tooltips' title='UpdateEvent' data-placement='top' data-original-title='Edit' onclick='updateEvent(\"$eventid\",\"$eventname\",\"$eventdescription\",\"$hours\",\"$minutes\",\"$daysactual\",\"$escalatedTo\",\"$updateProvidedBy\",\"$noETTRReason\",\"$resolutionProvidedBy\",\"$escalation_time\")'>   
					<i class='fa fa-edit'></i>
				</a>

				<a class='btn btn-xs btn-primary tooltips' title='AddUser' data-placement='top' data-original-title='Edit' onclick='addUsers(\"$eventid\")'>
				    <span class='glyphicon glyphicon-user'></span>
				</a>

                <a class='btn btn-xs btn-primary tooltips' title='ViewUsers' data-placement='top' data-original-title='ViewUsers' onclick='viewUsers(\"$eventname\")'>
				    <span class='glyphicon glyphicon-eye-open'></span>
				</a>

                <a class='btn btn-xs btn-success tooltips' title='TrackEvent' data-placement='top' data-original-title='Track Event' onclick='trackerRoute(\"$eventname\")'>
                   <i class='fa fa-search'></i> 
                </a>
				
				</div>";
	}
	$res = array('data' => $dataArray);
	echo json_encode($res);
} else if ($type == "sendIntimationOnM") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	// $users = $_POST['users'];
	$SLATelcoUsers = $_POST['SLATelcoUsers'];
	$escalated_to = $_POST['escalated_to'];
	$escalated_by = $_POST['escalated_by'];
	$event_start_time = $_POST['event_start_time'];
	$escalation_time = $_POST['escalation_time'];
	$cluster_name = $_POST['cluster_name'];
	$event_id = $_POST['event_id'];
	$ecalation_level = $_POST['ecalation_level'];
	$message = $_POST['smsDraft'];
	$pushMessage = $_POST['pushDraft'];
	$pons_array = $_POST['pons_array'];
	$eventdescription = $_POST['eventdescription'];

	// var_dump('eventdescription: ', $eventdescription);

	// var_dump('eventname:', $eventname);
	// var_dump('users:', $users);
	// var_dump('SLATelcoUsers:', $SLATelcoUsers);
	// var_dump('cluster_name:', $cluster_name);
	// var_dump('ecalation_level:', $ecalation_level);
	// var_dump('event_id:', $event_id);
	// var_dump('message:', $message);
	// var_dump('pushMessage:', $pushMessage);
	// var_dump('pons_array:', $pons_array);
	// var_dump('escalated_to:', $escalated_to);
	// var_dump('escalated_by:', $escalated_by);
	// var_dump('escalation_time:', $escalation_time);
	// var_dump('Received users count:', count($_POST['users']));

	$insertDescription = "UPDATE EVENTALERTS SET DESCRIPTION = :description WHERE EVENTNAME = :eventname"; // added by Azeem
	// $resDescription = $dbObj->insert($insertDescription);
	// Define the parameters to bind
	$params = [
		':description' => $eventdescription,
		':eventname' => $eventname
	];

	// Execute the prepared query for update
	$resDescription = $dbObject->execInsertUpdate($insertDescription, $params);

	// var_dump('resDescription updation: ', $resDescription);
	// var_dump('insertDescription : ', $insertDescription);

	sendEscalationSMSLog($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);


	$eventNo = generateEventId($dbObject);
	// var_dump('EEventNo:', $eventNo);

	$occurrenceTimeQuery = "SELECT TO_CHAR(ALARMDATE, 'YYYY-MM-DD HH24:MI:SS.FF') AS ALARMDATE
		FROM (
			SELECT ALARMDATE
			FROM usereventalert
			WHERE eventid = :event_id
			ORDER BY ALARMDATE ASC
		) 
		WHERE ROWNUM = 1
		";

	// $resOccurrenceTimeQuery  = $dbObj->Get_Array($occurrenceTimeQuery);
	$params = [
		':event_id' => $event_id
	];

	// Execute the query with binded parameters
	$resOccurrenceTimeQuery = $dbObject->execSelect($occurrenceTimeQuery, $params);

	$occurrenceTime = $resOccurrenceTimeQuery['ALARMDATE'];
	$occurrenceTime = !empty($resOccurrenceTimeQuery) ? $resOccurrenceTimeQuery[0]['ALARMDATE'] : null;

	// var_dump('Result for resOccurrenceTimeQuery: ', $resOccurrenceTimeQuery);
	// var_dump('Occurence Time: ', $occurrenceTime);

	$mgmntTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME FROM NAYATELUSER.SMS_INTIMATION WHERE event_id = :event_id AND MODULE LIKE '%PONDownPhase2%' ORDER BY DATETIME ASC";
	// $resMgmntTimeQuery  = $dbObject->execSelect($mgmntTimeQuery, []);
	$paramsMgmnt = [
		':event_id' => $event_id
	];
	$resMgmntTimeQuery = $dbObject->execSelect($mgmntTimeQuery, $paramsMgmnt);

	// $mgmntTime = $resMgmntTimeQuery[0]['DATETIME'];

	// var_dump('resMgmntTimeQuery : ', $resMgmntTimeQuery);
	$mgmntTime = $resMgmntTimeQuery[0]['DATETIME'];
	// var_dump('mgmntTime : ', $mgmntTime);

	$customerIntimationTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME FROM NAYATELUSER.SMS_INTIMATION WHERE event_id = :event_id AND MODULE LIKE '%PONDownIntimationsAndEventLogging%' ORDER BY DATETIME ASC";
	// $resCustomerIntimationTimeQuery  = $dbObject->execSelect($customerIntimationTimeQuery, []);
	$paramsCustomerIntimation = [
		':event_id' => $event_id
	];
	$resCustomerIntimationTimeQuery = $dbObject->execSelect($customerIntimationTimeQuery, $paramsCustomerIntimation);

	// var_dump('resCustomerIntimationTimeQuery : ', $resCustomerIntimationTimeQuery);
	$customerIntimationTime = $resCustomerIntimationTimeQuery[0]['DATETIME'];
	// var_dump('customerIntimationTime : ', $customerIntimationTime);

	$eventDescription = "This ID is currently affected due to fiber cut issue (Event : $eventname)";
	$smsIntimation = 'Yes';
	$operator = 'System';
	$eventAlertPopUp = 'Yes';
	$mgmtIntimation = 'Yes';
	$bulkTT = 'No';

	$occurrenceTimeTrimmed = substr($occurrenceTime, 0, 23);  // Trims it to "2024-09-21 21:08:42.000"
	// var_dump('occurrenceTimeTrimmed : ', $occurrenceTimeTrimmed);

	// Format the occurrence time
	// $occurrenceTimeFormatted = "TO_TIMESTAMP('$occurrenceTimeTrimmed', 'YYYY-MM-DD HH24:MI:SS.FF3')";
	// var_dump('occurrenceTimeFormatted: ', $occurrenceTimeFormatted);
	// var_dump('customerIntimationTime: ', $customerIntimationTime);
	// var_dump('mgmntTime: ', $mgmntTime);


	$insertEventLoggerFormQuery = "INSERT INTO NAYATELUSER.EVENTLOGGERFORM (ID ,EVENTNO, EVENTDESCRIPTION, OCCURENCETIME ,SMSINTIMATION ,SMSTIME ,EMAILTIME ,STATUS,OPERATOR,DATETIME,INCIDENTCATEGORY ,EVENT_ALERT_POPUP ,MGMT_INTIMATION,MGMT_TIME ,CALL_LOAD,ESCALATIONTIME ,BULK_TT )
            VALUES (NAYATELUSER.EVENTLOGGERFORM_id_seq.nextval, :eventNo, :eventDescription , TO_TIMESTAMP(:occurrenceTimeTrimmed, 'YYYY-MM-DD HH24:MI:SS.FF3'), :smsIntimation , TO_TIMESTAMP(:customerIntimationTime, 'YYYY-MM-DD HH24:MI:SS.FF3') , NULL , 'ACTIVE' , :escalatedBy , SYSDATE , NULL , :eventAlertPopUp , :mgmtIntimation , TO_TIMESTAMP(:mgmntTime, 'YYYY-MM-DD HH24:MI:SS.FF3')  , '' , TO_TIMESTAMP(:mgmntTime, 'YYYY-MM-DD HH24:MI:SS.FF3'), :bulkTT)
        ";

	// var_dump('insertEventLoggerFormQuery: ', $insertEventLoggerFormQuery);

	// $resEventLoggerFormQuery = $dbObj->insert($insertEventLoggerFormQuery);
	// Define the parameters to bind
	$paramsInsert = [
		':eventNo' => $eventNo,
		':eventDescription' => $eventDescription,
		':occurrenceTimeTrimmed' => $occurrenceTimeTrimmed,
		':smsIntimation' => $smsIntimation,
		':customerIntimationTime' => $customerIntimationTime,
		':escalatedBy' => $escalated_by,
		':eventAlertPopUp' => $eventAlertPopUp,
		':mgmtIntimation' => $mgmtIntimation,
		':mgmntTime' => $mgmntTime,
		':escalationTime' => $escalation_time,
		':bulkTT' => $bulkTT
	];
	$resEventLoggerFormQuery = $dbObject->execInsertUpdate($insertEventLoggerFormQuery, $paramsInsert);

	// var_dump('resEventLoggerFormQuery:', $resEventLoggerFormQuery);


	// Retrieve the newly inserted EVENTID
	$selectEventIdQuery = "SELECT ID FROM NAYATELUSER.EVENTLOGGERFORM WHERE EVENTNO = :eventNo";

	// var_dump('selectEventIdQuery', $selectEventIdQuery);

	// Binding valid parameter
	$paramsSelectEventId = [
		':eventNo' => $eventNo, // Ensure $eventNo contains 'NTL-20250103-2'
	];

	// Execute the query
	$resEventId = $dbObject->execSelect($selectEventIdQuery, $paramsSelectEventId);

	// var_dump('resEventId: ', $resEventId);


	// Safely access the 'ID' from the first result
	$eventLoggerId = isset($resEventId[0]['ID']) ? $resEventId[0]['ID'] : null;

	// var_dump('Inserted EventID:', $eventLoggerId);

	$customerSLATelco_count = count($SLATelcoUsers);

	// Prepare the data to insert into EVENTLOGGERFORMDETAIL
	$operationDetails = [
		['REPORTED BY PERSON', "$escalated_by"],
		['ESCALATED TO PERSON', "$escalated_to"],
		['DEPARTMENT', 'Transmission'],
		// ['MANUAL CUSTOMER COUNT', "$customer_count"],
		['TELCO COUNT', "$customerSLATelco_count"],
		['NOC COMMENTS', "Event auto logged via NOC OSP Incident mgmt ($eventname)"],
		['SERVICE NAME', "All"],
		['CUSTOMER EFFECTED', "Yes"],
	];

	// Loop through and insert each record into EVENTLOGGERFORMDETAIL
	foreach ($operationDetails as $detail) {
		$operationKey = $detail[0];
		$operationValue = $detail[1];

		$insertEventLoggerFormDetailQuery = "INSERT INTO NAYATELUSER.EVENTLOGGERFORMDETAIL 
			(ID, EVENTID, OPERATIONKEY, OPERATIONVALUE, STATUS, OPERATOR, DATETIME)
			VALUES (NAYATELUSER.EVENTLOGGERFORMDETAIL_id_seq.nextval, :eventLoggerId, :operationKey, :operationValue, 'ACTIVE', :operator, SYSDATE)
		";

		// $resEventLoggerFormDetailQuery = $dbObj->insert($insertEventLoggerFormDetailQuery);
		$paramsInsertDetail = [
			':eventLoggerId' => $eventLoggerId,
			':operationKey' => $operationKey,
			':operationValue' => $operationValue,
			':operator' => $operator
		];

		$resEventLoggerFormDetailQuery = $dbObject->execInsertUpdate($insertEventLoggerFormDetailQuery, $paramsInsertDetail);
		// var_dump('Inserted into EVENTLOGGERFORMDETAIL:', $resEventLoggerFormDetailQuery);
	}

	// // Retrieve user information
	$allUserQuery = "SELECT DISTINCT(USERID) FROM usereventalert WHERE eventid = :event_id";
	// var_dump('ALL user Query:', $allUserQuery);
	// $resAllUsers = $dbObj->Get_Array($allUserQuery);
	$paramsAllUsers = [
		':event_id' => $event_id
	];

	// Execute the query to fetch users
	$resAllUsers = $dbObject->execSelect($allUserQuery, $paramsAllUsers);
	// var_dump('resAllUsers:', $resAllUsers);

	// // Convert result to an array of user IDs
	$users = $resAllUsers ? array_column($resAllUsers, 'USERID') : [];
	// var_dump('users:', $users);

	// Loop through and insert each user record into EVENTLOGGERFORMDETAIL
	foreach ($users as $user) {
		// var_dump('user:', $user);

		$insertUserDetailQuery = "INSERT INTO NAYATELUSER.EVENTLOGGERFORMDETAIL 
			(ID, EVENTID, OPERATIONKEY, OPERATIONVALUE, STATUS, OPERATOR, DATETIME)
			VALUES (NAYATELUSER.EVENTLOGGERFORMDETAIL_id_seq.nextval, :eventLoggerId, 'USER', :operationValue, 'ACTIVE', :operator, SYSDATE)
			";

		// $resUserDetailInsert = $dbObj->insert($insertUserDetailQuery);
		$paramsUserDetail = [
			':eventLoggerId' => $eventLoggerId,
			':operationValue' => $user,
			':operator' => $operator
		];

		$resUserDetailInsert = $dbObject->execInsertUpdate($insertUserDetailQuery, $paramsUserDetail);
		// var_dump('Inserted user into EVENTLOGGERFORMDETAIL:', $resUserDetailInsert);
	}

	// $pons = explode(',', $pons_list); // Convert string to array

	// Loop through and insert each PON record into EVENTLOGGERFORMDETAIL
	foreach ($pons_array as $pon) {
		$insertPonDetailQuery = "INSERT INTO NAYATELUSER.EVENTLOGGERFORMDETAIL 
			(ID, EVENTID, OPERATIONKEY, OPERATIONVALUE, STATUS, OPERATOR, DATETIME)
			VALUES (NAYATELUSER.EVENTLOGGERFORMDETAIL_id_seq.nextval, :eventLoggerId, 'OLT SERVICE', :pon, 'ACTIVE', :operator, SYSDATE)
			";

		// $resPonDetailInsert = $dbObj->insert($insertPonDetailQuery);
		$paramsPonDetail = [
			':eventLoggerId' => $eventLoggerId,
			':pon' => $pon,
			':operator' => $operator
		];

		$resPonDetailInsert = $dbObject->execInsertUpdate($insertPonDetailQuery, $paramsPonDetail);
		// var_dump('Inserted PON into EVENTLOGGERFORMDETAIL:', $resPonDetailInsert);
	}

	$incidentId = "-$eventNo";

	$queryIncidentTable = "INSERT INTO NAYATELUSER.INCIDENTFORM (ID, INCIDENTID, EVENTID,STATUS,DATETIME , CUSTOMER_COUNT)
        VALUES(NAYATELUSER.INCIDENTFORM_ID_SEQ.NEXTVAL, :incidentId, :eventLoggerId, 'ACTIVE', SYSDATE , :customer_count)";
	// var_dump('$queryIncidentTable: ', $queryIncidentTable);
	// $resQueryIncidentTable = $dbObj->insert($queryIncidentTable);
	$paramsIncidentTable = [
		':incidentId' => $incidentId,
		':eventLoggerId' => $eventLoggerId,
		':customer_count' => $customer_count
	];

	$resQueryIncidentTable = $dbObject->execInsertUpdate($queryIncidentTable, $paramsIncidentTable);
	// var_dump('resQueryIncidentTable: ', $resQueryIncidentTable);

	// var_dump('SLATelcoUsers: ', $SLATelcoUsers);
	if (empty(array_filter($SLATelcoUsers))) {
		// echo "No SLATelcoUsers found for this event.";
	} else {
		foreach ($SLATelcoUsers as $SLATelcoUser) {
			// var_dump('SLATelcoUser: ', $SLATelcoUser);

			$troubleTicketIDQuery = "SELECT ntlcrm.troubleticket_id_seq.nextval FROM dual";
			// $resTroubleTicketIDQuery = $dbObj->Get_Row($troubleTicketIDQuery);
			$resTroubleTicketIDQuery = $dbObject->execSelect($troubleTicketIDQuery, []);
			$TT_ID = $resTroubleTicketIDQuery['NEXTVAL'];
			// var_dump('resTroubleTicketIDQuery: ', $resTroubleTicketIDQuery);
			// var_dump('TT_ID', $TT_ID);

			// Inserting data TT
			$TTDescription = "Auto TT launched for TP site/agg due to OSP incident # $eventNo";
			// $troubleTicketQuery = "INSERT INTO ntlcrm.troubleticket 
			// (ID,USERID, FAULTTYPE , SUBFAULTTYPE ,TT_CREATION_TIME , DESCRIPTION , CREATEDBY)
			// VALUES ('$TT_ID','$SLATelcoUser' ,'ONT', 'RED', SYSDATE ,'$TTDescription' , 'system')";

			// var_dump('troubleTicketQuery', $troubleTicketQuery);

			// $resTroubleTicketQuery = $dbObj->insert($troubleTicketQuery);

			$ttTranscation->addTroubleTicket($TT_ID, $SLATelcoUser, $CATEGORY = '', $TTDescription, $TICKETTYPE = '', $FAULTTYPE = 'ONT', $SUBFAULTTYPE = 'RED', $WEB = '', $PRIORITY = '', $DEPARTMENT = '', $FORWARDTO = '', $CREATEDBY = 'system', $POCNAME, $POCCONTACT, $OPERATOR_TEAMID);

			$OnMTeamQuery = "SELECT ONMTEAM FROM nayateluser.clusters WHERE CLUSTER_NAME = :cluster_name AND status = 'Active'";
			// $resCustomerSLATelcoQuery = $dbObject->execSelect($OnMTeamQuery, []);
			$paramsOnMTeam = [':cluster_name' => $cluster_name];
			$resCustomerSLATelcoQuery = $dbObject->execSelect($OnMTeamQuery, $paramsOnMTeam);
			$OnMTeam = $resCustomerSLATelcoQuery[0]['ONMTEAM'];
			// var_dump('OnMTeam:', $OnMTeam);

			$ttUserDataQuery = "SELECT ALARMDESCRIPTION , TO_CHAR(ALARMDATE, 'YYYY-MM-DD HH24:MI:SS') AS ALARMDATE FROM USEREVENTALERT WHERE USERID = :SLATelcoUser AND EVENTID = :event_id";
			// $resTTUserDataQuery = $dbObject->execSelect($ttUserDataQuery, []);
			$paramsTTUserData = [
				':SLATelcoUser' => $SLATelcoUser,
				':event_id' => $event_id
			];
			$resTTUserDataQuery = $dbObject->execSelect($ttUserDataQuery, $paramsTTUserData);

			$alarmDescription = $resTTUserDataQuery[0]['ALARMDESCRIPTION'];
			$alarmData = $resTTUserDataQuery[0]['ALARMDATE'];
			// var_dump('alarmDescription:', $alarmDescription);
			// var_dump('alarmData:', $alarmData);

			// Creating TT deatils
			$operationType = "FORWARD TT";
			$comment = "This site is down since $alarmData, please visit and troubleshoot.\n($alarmDescription - $alarmData)";
			// $troubleTicketDetailQuery = "INSERT INTO ntlcrm.troubleticketdetail 
			// (ID,TICKETID, OPERATIONTYPE , OPERATIONVALUE , OPERATOR , COMMENTS , DATETIME)
			// VALUES (ntlcrm.troubleticketdetail_id_seq.nextval,'$TT_ID', '$operationType', '$OnMTeam', 'system' ,'$comment' , SYSDATE)";

			// var_dump('troubleTicketDetailQuery', $troubleTicketDetailQuery);
			$ttTranscation->addTTDetails($TT_ID, $OPERATIONTYPE = 'ISSUE TT', $OPERATIONVALUE = 'ONT', $OPERATIONSUBVALUE = 'RED', $OPERATIONDATE = '', $OPERATIONSTATUS = '', $OPERATOR = 'system', $COMMENTS = 'system has auto launched a trouble ticket', $PROBLEMDESCRIPTION = '', $PROBLEMSOLUTION = '', $DATETIME = '', $STATUS = 'open', $DEPARTMENT = '', $FAULTTYPEAUTOCOMMENTS = 'TT LAUNCHED');

			// $resTroubleTicketDetailQuery = $dbObj->insert($troubleTicketDetailQuery);
			$ttTranscation->addTTDetails($TT_ID, $operationType, $OnMTeam, $OPERATIONSUBVALUE = '', $OPERATIONDATE = '', $OPERATIONSTATUS = '', $OPERATOR = 'system', $COMMENTS = "Ticket is being forwarded to $OnMTeam", $PROBLEMDESCRIPTION = '', $PROBLEMSOLUTION = '', $DATETIME = '', $STATUS = 'open', $DEPARTMENT = '', $FAULTTYPEAUTOCOMMENTS = '');
			$ttTranscation->addTTDetails($TT_ID, $OPERATIONTYPE = 'COMMENT', $OPERATIONVALUE, $OPERATIONSUBVALUE = '', $OPERATIONDATE = '', $OPERATIONSTATUS = '', $OPERATOR = 'system', $comment, $PROBLEMDESCRIPTION = '', $PROBLEMSOLUTION = '', $DATETIME = '', $STATUS = 'open', $DEPARTMENT = '', $FAULTTYPEAUTOCOMMENTS = '');
			$ttTranscation->addTTDetails($TT_ID, 'SLA CHECK', 'active', '0', '', '', 'system', 'System:SLA Notification check has been added', '', '', '', 'open', 'Enterprise Solutions', '');
			// sendSmsToSLATelcoCustomer($eventid, $SLATelcoUser, $newDateString);
			$emailSubject = "Automated PON Down/Fiber Cut Alert | Event Name: $eventname";
			$comments = "TT auto generated via NOC OSP Incident mgmt";

			$slaFTTTescalationQuery = "INSERT INTO SLA_FTTT_ESCLAIONS
				(ID, USERID, TTID, STARTTIME, DATETIME, OPERATOR, STATUS ,ISSUE_REPORTED_BY ,EMAIL_SUBJECT ,EVENT_ID ,COMMENTS )
				VALUES (SLA_FTTT_ESCLAIONS_id_seq.nextval, :SLATelcoUser, ':TT_ID', TO_TIMESTAMP(:event_start_time, 'YYYY-MM-DD HH24:MI:SS'),SYSDATE, :escalated_by, 'active' ,'NTL' , :emailSubject, :eventNo , :comments)
				";

			// $resSlaFTTTescalationQuery = $dbObj->insert($slaFTTTescalationQuery);
			// Bind parameters for SLA escalation
			$paramsSlaEscalation = [
				':SLATelcoUser' => $SLATelcoUser,
				':TT_ID' => $TT_ID,
				':event_start_time' => $event_start_time,
				':escalated_by' => $escalated_by,
				':emailSubject' => "Automated PON Down/Fiber Cut Alert | Event Name: $eventname",
				':eventNo' => $eventNo,
				':comments' => "TT auto generated via NOC OSP Incident mgmt"
			];

			// Execute the SLA escalation query
			$resSlaFTTTescalationQuery = $dbObject->execInsertUpdate($slaFTTTescalationQuery, $paramsSlaEscalation);
			// var_dump('resSlaFTTTescalationQuery: ', $resSlaFTTTescalationQuery);
		}
	}

	// Send response back to the AJAX call
	// Sample success response
	echo json_encode("success");
} else if ($type == "getDraftMessage") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$escalated_to = $_POST['escalatedTo'];

	// var_dump('eventname: ', $eventname);
	// var_dump('escalated_to: ', $escalated_to);


	// Query to get event data from ntlcrm.ponDownInfo
	$query = "SELECT * FROM ntlcrm.ponDownInfo WHERE EVENTNAME = :eventname";
	// $result = $dbObj->Get_Array($query);
	$params = [':eventname' => $eventname];
	$result = $dbObject->execSelect($query, $params);
	// var_dump('Result for ponDownInfo: ', $result);

	// Prepare the response array
	$response = [
		'status' => 'success',
		'smsDraft' => '',
		'pushDraft' => '',
		'users' => [],
		'SLATelcoUsers' => [],
		'escalated_to' => '',
		'escalated_by' => '',
		'event_start_time' => '',
		'escalation_time' => '',
		'cluster_name' => '',
		'event_id' => '',
		'ecalation_level' => '',
		'pons_array' => '',
		'message' => ''
	];

	if (!empty($result)) {
		// var_dump('responseEscaalted: ', $responseEscaalted);

		$insertEscalatedTo = "UPDATE EVENTALERTS SET ESCALATED_TO = :escalatedTo , ESCALATION_TIME = SYSDATE , ESCALATED_BY = :operator WHERE EVENTNAME = :eventname"; // added by Azeem
		// $resEscalatedTo = $dbObj->insert($insertEscalatedTo);
		$updateParams = [
			':escalatedTo' => $escalated_to,
			':operator' => $operator,
			':eventname' => $eventname
		];
		$resEscalatedTo = $dbObject->execInsertUpdate($insertEscalatedTo, $updateParams);

		$EventIdquery = "SELECT EVENTID , ESCALATION_TIME FROM eventalerts WHERE EVENTNAME = :eventname";
		// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
		$eventIdParams = [':eventname' => $eventname];
		$eventIdResult = $dbObject->execSelect($EventIdquery, $eventIdParams);
		// var_dump('eventIdResult: ', $eventIdResult);
		$event_id = $eventIdResult[0]['EVENTID'];
		// $escalation_time = $eventIdResult[0]['ESCALATION_TIME'];

		// var_dump('event_id: ', $eventIdResult);
		// var_dump('escalation_time: ', $escalation_time);


		// Initialize arrays to store PONs, cluster names, and user counts
		$pons_array = [];
		$cluster_name = '';
		$user_count = 0;

		// Loop through each row and extract the PON, cluster name, and user count
		foreach ($result as $row) {
			if (is_a($row['PONS_LIST'], 'OCI-Lob')) {
				$pon = $row['PONS_LIST']->load();  // Load the LOB data as a string if necessary
			} else {
				$pon = $row['PONS_LIST'];
			}

			$pons_array[] = $pon;  // Add PON to the array
			$cluster_name = $row['CLUSTERNAME'];  // Since cluster name and user count are the same for all rows
			$user_count = $row['USER_COUNT'];     // we can update them only once (if needed)
		}

		// Count the number of PONs
		$pon_count = count($pons_array);

		$mainDataQuery = "WITH FilteredUsers AS (
            SELECT DISTINCT ua.USERID
            FROM usereventalert ua
            INNER JOIN mbluser mu ON ua.USERID = mu.USERID
            WHERE ua.EVENTID = :eventId
              AND (ua.USERID LIKE '%tptower%' OR ua.USERID LIKE '%tpagg%')
              AND mu.CUSTOMERPRIORITY IS NOT NULL
              AND mu.CUSTOMERPRIORITY != 'Normal'
        ),
        DistinctUserIDs AS (
            SELECT DISTINCT USERID 
            FROM usereventalert 
            WHERE EVENTID = :eventId
        ),
        EventDetails AS (
            SELECT 
                TO_CHAR(ESCALATION_TIME, 'YYYY-MM-DD HH24:MI:SS') AS ESCALATION_TIME, 
                TO_CHAR(EVENT_START_TIME, 'YYYY-MM-DD HH24:MI:SS') AS EVENT_START_TIME,
                ESCALATED_BY, 
                ESCALATED_TO,
                EVENTID
            FROM eventalerts 
            WHERE EVENTID = :eventId
        )
        SELECT 
            (SELECT COUNT(*) FROM FilteredUsers) AS SLATelcoUsersCount,
            (SELECT COUNT(*) FROM DistinctUserIDs) AS TOTAL_DISTINCT_USERS,
            (SELECT RTRIM(XMLAGG(XMLELEMENT(E, USERID || ', ') ORDER BY USERID).EXTRACT('//text()').GETCLOBVAL()) 
             FROM FilteredUsers) AS SLATelcoUserIDs,
            (SELECT RTRIM(XMLAGG(XMLELEMENT(E, USERID || ', ') ORDER BY USERID).EXTRACT('//text()').GETCLOBVAL()) 
             FROM DistinctUserIDs) AS DISTINCT_USER_IDS,
            e.ESCALATION_TIME,
            e.EVENT_START_TIME,
            e.ESCALATED_BY,
            e.ESCALATED_TO
        FROM EventDetails e
        ";

		// $resMainDataQuery = $dbObject->execSelect($mainDataQuery, []);
		$mainDataParams = [':eventId' => $event_id];
		$resMainDataQuery = $dbObject->execSelect($mainDataQuery, $mainDataParams);
		// var_dump('resMainDataQuery: ', $resMainDataQuery);

		// Extract data from the result set using the correct variable
		$customerSLATelco_count = $resMainDataQuery[0]['SLATELCOUSERSCOUNT'] ?? 0;
		$customer_count = $resMainDataQuery[0]['TOTAL_DISTINCT_USERS'] ?? 0;

		// Read the LOB objects properly
		$SLATelcoUsersRes = $resMainDataQuery[0]['SLATELCOUSERIDS'] ?? '';
		$usersRes = $resMainDataQuery[0]['DISTINCT_USER_IDS'] ?? '';

		// Handle LOB for SLATelcoUSERIDS
		if (is_a($resMainDataQuery[0]['SLATELCOUSERIDS'], 'OCI-Lob')) {
			$SLATelcoUsersRes = $resMainDataQuery[0]['SLATELCOUSERIDS']->load();  // Load the LOB data as a string
		} else {
			$SLATelcoUsersRes = $resMainDataQuery[0]['SLATELCOUSERIDS'] ?? '';
		}

		// Handle LOB for DISTINCT_USER_IDS
		if (is_a($resMainDataQuery[0]['DISTINCT_USER_IDS'], 'OCI-Lob')) {
			$usersRes = $resMainDataQuery[0]['DISTINCT_USER_IDS']->load();  // Load the LOB data as a string
		} else {
			$usersRes = $resMainDataQuery[0]['DISTINCT_USER_IDS'] ?? '';
		}

		// Convert comma-separated strings into arrays and trim whitespace
		$users = array_filter(array_map(function ($user) {
			return rtrim(trim($user), ','); // Trim whitespace and remove trailing comma
		}, explode(", ", $usersRes)));

		// First trim the entire string to remove any trailing commas or whitespace
		$SLATelcoUsersRes = rtrim($SLATelcoUsersRes, ', ');

		// Then convert the string into an array
		$SLATelcoUsers = array_filter(array_map(function ($user) {
			return rtrim(trim($user), ','); // Trim whitespace and remove trailing comma
		}, explode(", ", $SLATelcoUsersRes)));


		$escalation_time = $resMainDataQuery[0]['ESCALATION_TIME'] ?? '';
		$event_start_time = $resMainDataQuery[0]['EVENT_START_TIME'] ?? '';
		$escalated_by = $resMainDataQuery[0]['ESCALATED_BY'] ?? '';
		$escalated_to = $resMainDataQuery[0]['ESCALATED_TO'] ?? '';

		$emailTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME
                        FROM NAYATELUSER.emaillogs_ponIntimation 
                        WHERE SUBJECT LIKE :eventnamePattern
                        ORDER BY DATETIME DESC
					";
		// $emailTimeResult = $dbObject->execSelect($emailTimeQuery, []);
		$emailTimeParams = [
			':eventnamePattern' => '%' . $eventname . '%'
		];

		$emailTimeResult = $dbObject->execSelect($emailTimeQuery, $emailTimeParams);

		$emailTime = $emailTimeResult[0]['DATETIME'];

		// var_dump('emailTimeQuery: ', $emailTimeQuery);
		// var_dump('emailTimeResult: ', $emailTimeResult);
		// var_dump('emailTime: ', $emailTime);

		// // // Output the extracted values for debugging
		// var_dump('customerSLATelco_count:', $customerSLATelco_count);
		// var_dump('customer_count:', $customer_count);
		// var_dump('SLATelcoUserIDs:', $SLATelcoUsersRes); // Should now show correct user IDs
		// var_dump('users:', $users); // Should now show correct users
		// var_dump('escalation_time:', $escalation_time);
		// var_dump('event_start_time:', $event_start_time);
		// var_dump('escalated_by:', $escalated_by);
		// var_dump('escalated_to:', $escalated_to);

		// Format escalation time to HH:MM
		$escalation_time_formatted = date('H:i', strtotime($escalation_time));
		// var_dump('escalation_time_formatted:', $escalation_time_formatted);

		if ($user_count > 500) {
			// var_dump('USER COUNT IS GREATER THAN 500');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $customer_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Escalated to: $escalated_to / TX @ $escalation_time_formatted\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $customer_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Escalated to: $escalated_to / TX @ $escalation_time_formatted\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 500 Customers';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} elseif ($pon_count == 1 || $pon_count == 2) {
			// var_dump('PON IS LESS THAN 2');

			$pons_to_display = implode(", ", $pons_array);  // Join PONs for display
			// var_dump('pons_to_display: ', $pons_to_display);

			// Extract device names from PONs
			$device_names = array_map(function ($pon) {
				// Assuming the device name is the first part of the PON before the first hyphen
				return explode('-', $pon)[0];
			}, $pons_array);
			// var_dump('device_names: ', $device_names);

			// Join unique device names for display
			$unique_device_names = implode(", ", array_unique($device_names));
			// var_dump('unique_device_names: ', $unique_device_names);


			$message .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$message .= "PONs: $pons_to_display\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $customer_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Escalated to: $escalated_to / TX @ $escalation_time_formatted\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$pushMessage .= "PONs: $pons_to_display\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $customer_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Escalated to: $escalated_to / TX @ $escalation_time_formatted\n";
			$pushMessage .= "NOC";


			if ($pon_count == 1) {
				$ecalation_level = '1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			} else {
				$ecalation_level = 'More than 1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			}

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} else {
			// If more than 2 PONs are affected
			// var_dump('PON IS GREATER THAN 2');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $customer_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Escalated to: $escalated_to / TX @ $escalation_time_formatted\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $customer_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Escalated to: $escalated_to / TX @ $escalation_time_formatted";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 1 PON Down';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		}

		$response['users'] = $users;
		$response['SLATelcoUsers'] = $SLATelcoUsers;
		$response['escalated_to'] = $escalated_to;
		$response['escalated_by'] = $escalated_by;
		$response['event_start_time'] = $event_start_time;
		$response['escalation_time'] = $escalation_time;
		$response['cluster_name'] = $cluster_name;
		$response['event_id'] = $event_id;
		$response['pons_array'] = $pons_array;
		$response['status'] = 'success';  // Set success status

		// Output the response as JSON
		echo json_encode($response);
	} else {
		echo json_encode(['status' => 'error', 'message' => 'Event not found']);
	}
} else if ($type == "updateEventCancel") {

	$eventname = $_POST['eventname'];
	// var_dump('eventname: ', $eventname);

	$insertEscalatedTo = "UPDATE EVENTALERTS SET ESCALATED_TO = NULL, ESCALATION_TIME = NULL WHERE EVENTNAME = '$eventname'"; // added by Azeem
	// $resEscalatedTo = $dbObj->insert($insertEscalatedTo);
	$insertEscalatedToParams = [
		':eventname' => $eventname
	];

	$resEscalatedTo = $dbObject->execInsertUpdate($insertEscalatedToQuery, $insertEscalatedToParams);


	echo json_encode(["status" => "success", "message" => "Event cancellation update successful"]);
} else if ($type == "getNoReasonForEttrMessage") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$updateProvidedBy = $_POST['updateProvidedBy'];
	$noETTRReason = $_POST['noETTRReason'];

	// var_dump('eventname: ', $eventname);
	// var_dump('updateProvidedBy: ', $updateProvidedBy);
	// var_dump('noETTRReason: ', $noETTRReason);


	// Query to get event data from ntlcrm.ponDownInfo
	$query = "SELECT * FROM ntlcrm.ponDownInfo WHERE EVENTNAME = :eventname";
	// $result = $dbObj->Get_Array($query);
	$checkLogParams = [
		':eventname' => $eventname,
	];
	$result = $dbObject->execSelect($query, $checkLogParams);
	// var_dump('query: ', $query);

	// var_dump('Result for ponDownInfo: ', $result);

	// Prepare the response array
	$response = [
		'status' => 'success',
		'smsDraft' => '',
		'pushDraft' => '',
		'updateProvidedBy' => '',
		'updateProvidedTime' => '',
		'emailTime' => '',
		'cluster_name' => '',
		'event_id' => '',
		'ecalation_level' => '',
		'message' => ''
	];


	if (!empty($result)) {

		// $insertUpdateProvidedBy = "UPDATE EVENTALERTS SET UPDATE_PROVIDED_BY = '$updateProvidedBy' , UPDATE_PROVIDED_TIME = SYSDATE , NO_ETTR_REASON = '$noETTRReason'  WHERE EVENTNAME = '$eventname'"; // added by Azeem
		// $resUpdateProvidedBy = $dbObj->insert($insertUpdateProvidedBy);

		$EventIdquery = "SELECT EVENTID FROM eventalerts WHERE EVENTNAME = :eventname";
		// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
		$eventIdParams = [
			':eventname' => $eventname
		];

		$eventIdResult = $dbObject->execSelect($EventIdquery, $eventIdParams);
		// var_dump('eventIdResult: ', $eventIdResult);
		$event_id = $eventIdResult[0]['EVENTID'];
		// $updateProvidedTime = $eventIdResult[0]['UPDATE_PROVIDED_TIME'];

		// Insert into EVENTINTIMATIONLOGS table to log no ettr reason and update provided by and its time
		$logInsertQuery = "INSERT INTO NAYATELUSER.EVENTINTIMATIONLOGS (
            ID, EVENTID, KEY, VALUE, SUBKEY, SUBVALUE, COMMENTS, DATETIME, STATUS, OPERATOR
            ) 
            VALUES (
                NAYATELUSER.EVENTINTIMATIONLOGS_ID_SEQ.nextval, :eventid, :key, :value, :subkey, :subvalue, :comments, SYSDATE, :status, :operator
            )
            ";

		// Parameters for the insert query
		$logParameters = [
			':eventid' => $event_id,
			':key' => 'updateProvidedBy',
			':value' => $updateProvidedBy,
			':subkey' => 'ettrStatus',
			':subvalue' => 'NO',
			':comments' => $noETTRReason,
			':status' => 'Active',
			':operator' => 'system',
		];

		// Execute the insertion query
		$logResult = $dbObject->execInsertUpdate($logInsertQuery, $logParameters);

		$checkLogQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME FROM NAYATELUSER.EVENTINTIMATIONLOGS 
        WHERE EVENTID = :eventid 
        AND KEY = :key
        AND VALUE = :value
        AND SUBKEY = :subkey
        AND SUBVALUE = :subvalue
        AND STATUS = 'Active'
        ORDER BY datetime DESC
        ";

		$checkLogParams = [
			':eventid' => $event_id,
			':key' => 'updateProvidedBy',
			':value' => $updateProvidedBy,
			':subkey' => 'ettrStatus',
			':subvalue' => 'NO',
			':status' => 'Active',
		];
		$updateProvidedResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

		$updateProvidedTime = $updateProvidedResult[0]['DATETIME'];

		// var_dump('updateProvidedResult: ', $updateProvidedResult);
		// var_dump('updateProvidedTime: ', $updateProvidedTime);


		// Initialize arrays to store PONs, cluster names, and user counts
		$pons_array = [];
		$cluster_name = '';
		$user_count = 0;

		// Loop through each row and extract the PON, cluster name, and user count
		foreach ($result as $row) {
			if (is_a($row['PONS_LIST'], 'OCI-Lob')) {
				$pon = $row['PONS_LIST']->load();  // Load the LOB data as a string if necessary
			} else {
				$pon = $row['PONS_LIST'];
			}

			$pons_array[] = $pon;  // Add PON to the array
			$cluster_name = $row['CLUSTERNAME'];  // Since cluster name and user count are the same for all rows
			$user_count = $row['USER_COUNT'];     // we can update them only once (if needed)
		}

		// Count the number of PONs
		$pon_count = count($pons_array);

		$emailTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME
                        FROM NAYATELUSER.emaillogs_ponIntimation 
                        WHERE SUBJECT LIKE :subjectPattern
                        ORDER BY DATETIME DESC";
		// $emailTimeResult = $dbObject->execSelect($emailTimeQuery, []);
		$emailTimeParams = [
			':subjectPattern' => '%' . $eventname . '%'
		];
		$emailTimeResult = $dbObject->execSelect($emailTimeQuery, $emailTimeParams);

		$emailTime = $emailTimeResult[0]['DATETIME'];

		// Format escalation time to HH:MM
		$updateProvidedTime_formatted = date('H:i', strtotime($updateProvidedTime));
		// var_dump('escalation_time_formatted:', $escalation_time_formatted);

		if ($user_count > 500) {
			// var_dump('USER COUNT IS GREATER THAN 500');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "No ETTR yet : $noETTRReason\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "No ETTR yet : $noETTRReason\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 500 Customers';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} elseif ($pon_count == 1 || $pon_count == 2) {
			// var_dump('PON IS LESS THAN 2');

			$pons_to_display = implode(", ", $pons_array);  // Join PONs for display
			// var_dump('pons_to_display: ', $pons_to_display);

			// Extract device names from PONs
			$device_names = array_map(function ($pon) {
				return explode('-', $pon)[0];
			}, $pons_array);
			// var_dump('device_names: ', $device_names);

			// Join unique device names for display
			$unique_device_names = implode(", ", array_unique($device_names));
			// var_dump('unique_device_names: ', $unique_device_names);
			$message .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			// $message .= "Devices:$unique_device_names\n";
			$message .= "PONs: $pons_to_display\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "No ETTR yet : $noETTRReason\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$pushMessage .= "PONs: $pons_to_display\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "No ETTR yet : $noETTRReason\n";
			$pushMessage .= "NOC";

			if ($pon_count == 1) {
				$ecalation_level = '1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			} else {
				$ecalation_level = 'More than 1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			}

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} else {
			// If more than 2 PONs are affected
			// var_dump('PON IS GREATER THAN 2');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "No ETTR yet : $noETTRReason\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted";
			$pushMessage .= "No ETTR yet : $noETTRReason\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 1 PON Down';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		}

		$response['updateProvidedBy'] = $updateProvidedBy;
		$response['updateProvidedTime'] = $updateProvidedTime;
		$response['emailTime'] = $emailTime;
		$response['cluster_name'] = $cluster_name;
		$response['event_id'] = $event_id;
		$response['ecalation_level'] = $ecalation_level;
		$response['status'] = 'success';  // Set success status

		// Output the response as JSON
		echo json_encode($response);
	} else {
		echo json_encode(['status' => 'error', 'message' => 'Event not found']);
	}
} elseif ($type == "sendIntimationOnMnoEttr") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$updateProvidedBy = $_POST['updateProvidedBy'];
	$updateProvidedTime = $_POST['updateProvidedTime'];
	$emailTime = $_POST['emailTime'];
	$cluster_name = $_POST['cluster_name'];
	$event_id = $_POST['event_id'];
	$ecalation_level = $_POST['ecalation_level'];
	$message = $_POST['smsDraft'];
	$pushMessage = $_POST['pushDraft'];
	$eventdescription = $_POST['eventdescription'];

	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('updateProvidedBy: ', $updateProvidedBy);
	// var_dump('updateProvidedTime: ', $updateProvidedTime);
	// var_dump('emailTime: ', $emailTime);
	// var_dump('cluster_name: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);

	$ettrStatus = 'NO';
	$insertDescription = "UPDATE EVENTALERTS SET DESCRIPTION = :eventdescription , ETTR_STATUS = :ettrStatus , UPDATED_AT = SYSDATE , UPDATED_BY = :operator  WHERE EVENTNAME = :eventname"; // added by Azeem
	// $resDescription = $dbObj->insert($insertDescription);
	$paramsDescription = [
		':eventdescription' => $eventdescription,
		':ettrStatus' => $ettrStatus,
		':operator' => $operator,
		':eventname' => $eventname
	];

	$resDescription = $dbObject->execInsertUpdate($insertDescription, $paramsDescription);

	// var_dump('resDescription updation: ', $resDescription);
	// var_dump('insertDescription : ', $insertDescription);

	sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
	echo json_encode("success");
} elseif ($type == 'NoReasonForEttrcancelation') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];

	// var_dump('eventname: ', $eventname);
	$EventIdquery = "SELECT EVENTID FROM eventalerts WHERE EVENTNAME = :eventname";
	// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
	$paramsEventId = [':eventname' => $eventname];
	$eventIdResult = $dbObject->execSelect($EventIdquery, $paramsEventId);
	$event_id = $eventIdResult[0]['EVENTID'];

	// $insertEscalatedTo = "UPDATE EVENTALERTS SET UPDATE_PROVIDED_BY = NULL, UPDATE_PROVIDED_TIME = NULL , DESCRIPTION = NULL , ETTR_STATUS = NULL , NO_ETTR_REASON = NULL WHERE EVENTNAME = '$eventname'"; // added by Azeem
	// $resEscalatedTo = $dbObj->insert($insertEscalatedTo);

	$noReasonEttrQuery = "UPDATE NAYATELUSER.EVENTINTIMATIONLOGS 
    SET VALUE = NULL, SUBVALUE = NULL , STATUS = 'Disable' , COMMENTS = 'Update provided by for No Reason Ettr is cancelled' 
    WHERE EVENTID = :event_id 
    AND DATETIME = (
      SELECT MAX(DATETIME)
      FROM NAYATELUSER.EVENTINTIMATIONLOGS
      WHERE EVENTID = :event_id
    )"; // added by Azeem
	// $noReasonEttrCanceled = $dbObject->execInsertUpdate($noReasonEttrQuery);

	$paramsNoReasonEttr = [':event_id' => $event_id];

	$noReasonEttrCanceled = $dbObject->execInsertUpdate($noReasonEttrQuery, $paramsNoReasonEttr);

	echo json_encode(["status" => "success", "message" => "Event cancellation update successful"]);
} elseif ($type == 'getGreaterEttrMessage') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PAHSE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$updateProvidedBy = $_POST['updateProvidedBy'];

	// $days = $_POST['days'];
	$minutes = $_POST['minutes'];
	$hours = $_POST['hours'];
	$currentTime = $_POST['currentTime'];

	// var_dump('days: ', $days);
	// var_dump('minutes: ', $minutes);
	// var_dump('hours: ', $hours);
	// var_dump('currentTime: ', $currentTime);

	$totalhours = 0;
	$duration = '';
	// $d = '';
	$h = '';
	$m = '';

	// if ($days != '') {
	// 	$totalhours = $days * 24;
	// 	$d = $days . " Day";
	// }

	if ($hours != '') {
		$totalhours = $totalhours + $hours;
		$h = $hours . " Hour";
		$hc = $hours . " Hour";
	}

	if ($minutes != '') {
		$m = " : " . $minutes . " Minutes";
	}

	$duration = $h . $m;
	// $customerDurationMsg = $hc . $m;

	// var_dump('$d: ', $d);
	// var_dump('h: ', $h);
	// var_dump('m: ', $m);

	// Query to get event data from ntlcrm.ponDownInfo
	$query = "SELECT * FROM ntlcrm.ponDownInfo WHERE EVENTNAME = :eventname";
	// $result = $dbObj->Get_Array($query);
	$params = [':eventname' => $eventname];

	$result = $dbObject->execSelect($query, $params);
	// var_dump('Result for ponDownInfo: ', $result);

	// Prepare the response array
	$response = [
		'status' => 'success',
		'smsDraft' => '',
		'pushDraft' => '',
		'updateProvidedBy' => '',
		'updateProvidedTime' => '',
		'emailTime' => '',
		'cluster_name' => '',
		'event_id' => '',
		'ecalation_level' => '',
		'duration' => '',
		'minutes' => '',
		'totalhours' => '',
		'customerMessage' => '',
		'customerMessage2' => ''
	];

	if (!empty($result)) {

		// $insertUpdateProvidedBy = "UPDATE EVENTALERTS SET HOURS = '$totalhours' , MINUTES = '$minutes' , DURATION = '$duration' WHERE EVENTNAME = '$eventname'"; // added by Azeem - removed udate rovided by and ots time 
		// $resUpdateProvidedBy = $dbObj->insert($insertUpdateProvidedBy);
		$dateTime = new DateTime($currentTime);
		// var_dump('dateTime: ', $dateTime);

		$cutomerMsgDuration = $dateTime->format('h:i A');
		// var_dump('cutomerMsgDuration: ', $cutomerMsgDuration);

		$EventIdquery = "SELECT EVENTID , to_char(datetime + ((:hours / 24) + (:minutes/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS RESOLUTIONTIME FROM eventalerts WHERE EVENTNAME = :eventname";
		// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
		$params = [
			':eventname' => $eventname,
			':hours' => $hours,
			':minutes' => $minutes
		];

		$eventIdResult = $dbObject->execSelect($EventIdquery, $params);
		// var_dump('eventIdResult: ', $eventIdResult);
		$event_id = $eventIdResult[0]['EVENTID'];
		// $cutomerMsgDuration = $eventIdResult[0]['RESOLUTIONTIME'];
		// $updateProvidedTime = $eventIdResult[0]['UPDATE_PROVIDED_TIME'];
		// $hours = $eventIdResult[0]['HOURS'];
		// $minutes = $eventIdResult[0]['MINUTES'];

		$customerMessage = "Restoration work for fiber cut, against User ID, is in progress. Services are expected to be fully restored by approximately $cutomerMsgDuration. We appreciate your understanding.";
		// var_dump('customerMessage: ', $customerMessage);

		// exit;
		// Insert into EVENTINTIMATIONLOGS table to log no ettr reason and update provided by and its time
		$logInsertQuery = "INSERT INTO NAYATELUSER.EVENTINTIMATIONLOGS (
                    ID, EVENTID, KEY, VALUE, SUBKEY, SUBVALUE, COMMENTS, DATETIME, STATUS, OPERATOR
                    ) 
                    VALUES (
                        NAYATELUSER.EVENTINTIMATIONLOGS_ID_SEQ.nextval, :eventid, :key, :value, :subkey, :subvalue, :comments, SYSDATE, :status, :operator
                    )
                    ";

		// Parameters for the insert query
		$logParameters = [
			':eventid' => $event_id,
			':key' => 'updateProvidedBy',
			':value' => $updateProvidedBy,
			':subkey' => 'ettrStatus',
			':subvalue' => 'YES',
			':comments' => "The Updated Greater Ettr is: $duration",
			':status' => 'Active',
			':operator' => 'system',
		];

		// Execute the insertion query
		$logResult = $dbObject->execInsertUpdate($logInsertQuery, $logParameters);

		$checkLogQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME FROM NAYATELUSER.EVENTINTIMATIONLOGS 
        WHERE EVENTID = :eventid 
        AND KEY = :key
        AND VALUE = :value
        AND SUBKEY = :subkey
        AND SUBVALUE = :subvalue
        AND STATUS = 'Active'
        ORDER BY datetime DESC
        ";

		$checkLogParams = [
			':eventid' => $event_id,
			':key' => 'updateProvidedBy',
			':value' => $updateProvidedBy,
			':subkey' => 'ettrStatus',
			':subvalue' => 'YES',
			':status' => 'Active',
		];
		$updateProvidedResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

		$updateProvidedTime = $updateProvidedResult[0]['DATETIME'];

		// var_dump('event_id: ', $eventIdResult);
		// var_dump('updateProvidedTime: ', $updateProvidedTime);

		// Initialize arrays to store PONs, cluster names, and user counts
		$pons_array = [];
		$cluster_name = '';
		$user_count = 0;

		// Loop through each row and extract the PON, cluster name, and user count
		foreach ($result as $row) {
			if (is_a($row['PONS_LIST'], 'OCI-Lob')) {
				$pon = $row['PONS_LIST']->load();  // Load the LOB data as a string if necessary
			} else {
				$pon = $row['PONS_LIST'];
			}

			$pons_array[] = $pon;  // Add PON to the array
			$cluster_name = $row['CLUSTERNAME'];  // Since cluster name and user count are the same for all rows
			$user_count = $row['USER_COUNT'];     // we can update them only once (if needed)
		}

		// Count the number of PONs
		$pon_count = count($pons_array);

		$emailTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME
                        FROM NAYATELUSER.emaillogs_ponIntimation 
                        WHERE SUBJECT LIKE :subject  
                        ORDER BY DATETIME DESC";
		// $emailTimeResult = $dbObject->execSelect($emailTimeQuery, []);
		$params = [
			':subject' => "%$eventname%"  // Using parameterized LIKE query
		];

		$emailTimeResult = $dbObject->execSelect($emailTimeQuery, $params);

		$emailTime = $emailTimeResult[0]['DATETIME'];

		// Format escalation time to HH:MM
		$updateProvidedTime_formatted = date('H:i', strtotime($updateProvidedTime));
		// var_dump('escalation_time_formatted:', $escalation_time_formatted);

		if ($user_count > 500) {
			// var_dump('USER COUNT IS GREATER THAN 500');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "ETTR: $duration\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "ETTR: $duration\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 500 Customers';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} elseif ($pon_count == 1 || $pon_count == 2) {
			// var_dump('PON IS LESS THAN 2');

			$pons_to_display = implode(", ", $pons_array);  // Join PONs for display
			// var_dump('pons_to_display: ', $pons_to_display);

			// Extract device names from PONs
			$device_names = array_map(function ($pon) {
				return explode('-', $pon)[0];
			}, $pons_array);
			// var_dump('device_names: ', $device_names);

			// Join unique device names for display
			$unique_device_names = implode(", ", array_unique($device_names));
			// var_dump('unique_device_names: ', $unique_device_names);

			// $message .= "Devices:$unique_device_names\n";
			$message .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$message .= "PONs: $pons_to_display\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "ETTR: $duration \n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$pushMessage .= "PONs: $pons_to_display\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "ETTR: $duration \n";
			$pushMessage .= "NOC";

			if ($pon_count == 1) {
				$ecalation_level = '1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			} else {
				$ecalation_level = 'More than 1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			}

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} else {
			// If more than 2 PONs are affected
			// var_dump('PON IS GREATER THAN 2');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "ETTR: $duration\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "ETTR: $duration \n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 1 PON Down';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		}

		$response['updateProvidedBy'] = $updateProvidedBy;
		$response['updateProvidedTime'] = $updateProvidedTime;
		$response['emailTime'] = $emailTime;
		$response['cluster_name'] = $cluster_name;
		$response['event_id'] = $event_id;
		$response['ecalation_level'] = $ecalation_level;

		$response['duration'] = $duration;
		$response['minutes'] = $minutes;
		$response['totalhours'] = $totalhours;

		$response['customerMessage'] = $cutomerMsgDuration;
		$response['customerMessage2'] = $customerMessage;
		$response['status'] = 'success';  // Set success status

		// Output the response as JSON
		echo json_encode($response);
	} else {
		echo json_encode(['status' => 'error', 'message' => 'Event not found']);
	}
} elseif ($type == 'sendIntimationOnMGreaterEttr') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$updateProvidedBy = $_POST['updateProvidedBy'];
	$updateProvidedTime = $_POST['updateProvidedTime'];
	// $emailTime = $_POST['emailTime'];
	$cluster_name = $_POST['cluster_name'];
	$event_id = $_POST['event_id'];
	$ecalation_level = $_POST['ecalation_level'];
	$message = $_POST['smsDraft'];
	$pushMessage = $_POST['pushDraft'];
	$eventdescription = $_POST['eventdescription'];

	$duration = $_POST['duration'];
	$minutes = $_POST['minutes'];
	$totalhours = $_POST['totalhours'];
	$customerMessage = $_POST['customerMessage'];
	// var_dump('customerMessage: ', $customerMessage);

	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('updateProvidedBy: ', $updateProvidedBy);
	// var_dump('updateProvidedTime: ', $updateProvidedTime);
	// var_dump('emailTime: ', $emailTime);
	// var_dump('cluster_name: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);

	$ettrStatus = 'YES';

	$insertDescription = "UPDATE EVENTALERTS SET DESCRIPTION = :description, ETTR_STATUS = :ettrStatus, HOURS = :totalhours , MINUTES = :minutes , DURATION = :duration,  UPDATED_AT = SYSDATE , UPDATED_BY = :operator WHERE EVENTNAME = :eventname"; // added by Azeem
	// $resDescription = $dbObj->insert($insertDescription);
	$params = [
		':description' => $eventdescription,
		':ettrStatus' => $ettrStatus,
		':totalhours' => $totalhours,
		':minutes' => $minutes,
		':duration' => $duration,
		':operator' => $operator,
		':eventname' => $eventname
	];

	$resDescription = $dbObject->execInsertUpdate($insertDescription, $params);

	// var_dump('resDescription updation: ', $resDescription);
	// var_dump('insertDescription : ', $insertDescription);

	sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);

	$allUserQuery = "SELECT DISTINCT(USERID) FROM usereventalert WHERE eventid = :event_id";
	// var_dump('ALL user Query:', $allUserQuery);
	// $resAllUsers = $dbObj->Get_Array($allUserQuery);
	$params = [
		':event_id' => $event_id
	];
	$resAllUsers = $dbObject->execSelect($allUserQuery, $params);
	// var_dump('resAllUsers:', $resAllUsers);

	// Convert result to an array of user IDs
	$users = $resAllUsers ? array_column($resAllUsers, 'USERID') : [];

	foreach ($users as $user) {
		// Sending SMS
		// Query to fetch phone numbers for the user
		$phoneQuery = "SELECT LISTAGG(y.areacode || y.phoneno, ',') WITHIN GROUP (ORDER BY y.areacode, y.phoneno) AS phone_numbers
        FROM mbluser x
        JOIN customercontact y ON x.accountid = y.accountid
        WHERE x.userid = :user_id  AND y.phonetype LIKE '%_IT_%'";

		// Bind parameters for the phone number query
		$phoneParams = [
			':user_id' => $user
		];

		// Execute the query to get phone numbers
		$phoneResult = $dbObject->execSelect($phoneQuery, $phoneParams);

		// Check if phone_result is not empty and it has at least one element
		if (!empty($phoneResult) && isset($phoneResult[0]['PHONE_NUMBERS'])) {
			// Access the 'PHONE_NUMBERS' key inside the first element of phone_result array
			$phoneNumbersString = $phoneResult[0]['PHONE_NUMBERS'];

			// Explode the phone numbers string to get an array of phone numbers
			$phoneNumbers = explode(',', $phoneNumbersString);

			// Now you can use $phoneNumbers array
			// var_dump('phoneNumbers', $phoneNumbers);

			foreach ($phoneNumbers as $phoneNumber) {
				$phoneNumber = substr($phoneNumber, 0, 20);
				// Construct the SMS message
				$smsMsg = "Restoration work for fiber cut, against User ID $user, is in progress. Services are expected to be fully restored by approximately $customerMessage. We appreciate your understanding";
				// var_dump('smsMsg: ', $smsMsg);

				$MODULE = 'eventAlertReportGreaterEttr';
				$OPERATOR_DEPT = '';
				$CATEGORY = '';

				// $phoneNumber = '03008672128';
				// Prepare the SMS insert query
				$smsQuery = "INSERT INTO NAYATELUSER.SMS_INTIMATION(ID, CELLNUMBER, MESSAGE, MODULE, OPERATOR, OPERATOR_DEPT, STATUS,DATETIME, CATEGORY , EVENT_ID)
                 VALUES(NAYATELUSER.SMS_INTIMATION_ID_SEQ.nextval,:cellnumber , :smsMsg, :MODULE, 'system', :OPERATOR_DEPT, 'PENDING', sysdate, '', :EVENT_ID)";

				// Bind parameters for the SMS query
				$smsParameters = [
					':cellnumber' => $phoneNumber,
					':smsMsg' => $smsMsg,
					':operator' => 'system',
					':MODULE' => $MODULE,
					':OPERATOR_DEPT' => $OPERATOR_DEPT,
					// ':CATEGORY' => $CATEGORY,
					':EVENT_ID' => $event_id,
				];

				// Execute the SMS insert query
				$smsResult = $dbObject->execInsertUpdate($smsQuery, $smsParameters);

				// Check the response of the SMS sending
				if ($smsResult) {
					// echo "</br>" . "SMS sent successfully to {$username} at $phoneNumber with event ID $seq\n";
				} else {
					// echo "Failed to send SMS to {$username} at $phoneNumber\n";
				}
			}
		} else {
			// echo "No phone numbers found for user ID {$user}\n";
		}

		// Prepare data for pushing notification to the users
		$notificationCategory = "Fiber Cut";
		$notificationTitle = "Fiber Cut Alert";
		$notificationText = $customerMessage;

		// Log notification in PUSHNOTIFICATIONHISTORY table
		$query = "INSERT INTO NAYATELUSER.PUSHNOTIFICATIONHISTORY (ID, USERID, NOTIFICATIONCATEGORY, NOTFICATIONTITLE, NOTFICATIONTEXT, NOTIFICATIONTIME, STATUS, DATETIME, OPERATOR, NOTIFICATIONTYPE)
		VALUES (NAYATELUSER.PUSHNOTIFICATIONHISTORY_id_seq.nextval ,:user_id, :category, :title, :text, SYSDATE, 'pending', SYSDATE, :operator, 'INTIMATION')";
		$parameters = [
			':user_id' => $user,
			':category' => $notificationCategory,
			':title' => $notificationTitle,
			':text' => $notificationText,
			':operator' => 'system'
		];

		// Execute query to log push notification
		$notificationResult = $dbObj->execInsertUpdate($query, $parameters);
		if ($notificationResult) {
			// echo "Notification logged successfully for $user\n";
		} else {
			// echo "Failed to log notification for user $user\n";
		}
	}

	echo json_encode("success");
} elseif ($type == 'GreaterEttrcancelation') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];

	// var_dump('eventname: ', $eventname);
	$EventIdquery = "SELECT EVENTID FROM eventalerts WHERE EVENTNAME = :eventname";
	// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
	$eventIdParams = [':eventname' => $eventname];
	$eventIdResult = $dbObject->execSelect($EventIdquery, $eventIdParams);
	$event_id = $eventIdResult[0]['EVENTID'];

	// $insertEscalatedTo = "UPDATE EVENTALERTS SET UPDATE_PROVIDED_BY = NULL, UPDATE_PROVIDED_TIME = NULL , DESCRIPTION = NULL , ETTR_STATUS = NULL , NO_ETTR_REASON = NULL WHERE EVENTNAME = '$eventname'"; // added by Azeem
	// $resEscalatedTo = $dbObj->insert($insertEscalatedTo);

	$noReasonEttrQuery = "UPDATE NAYATELUSER.EVENTINTIMATIONLOGS 
    SET VALUE = NULL, SUBVALUE = NULL , STATUS = 'Disable' , COMMENTS = 'Update provided by for Greater Ettr is cancelled' 
    WHERE EVENTID = :event_id
    AND DATETIME = (
      SELECT MAX(DATETIME)
      FROM NAYATELUSER.EVENTINTIMATIONLOGS
      WHERE EVENTID = :event_id
    ) "; // added by Azeem
	// $noReasonEttrCanceled = $dbObject->execInsertUpdate($noReasonEttrQuery);
	$noReasonEttrParams = [
		':event_id' => $event_id
	];

	// Execute the query with parameters
	$noReasonEttrCanceled = $dbObject->execInsertUpdate($noReasonEttrQuery, $noReasonEttrParams);
	echo json_encode(["status" => "success", "message" => "Event cancellation update successful"]);
} elseif ($type == 'getLessThanEttrMessage') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PAHSE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$updateProvidedBy = $_POST['updateProvidedBy'];

	// $days = $_POST['days'];
	$minutes = $_POST['minutes'];
	$hours = $_POST['hours'];

	// var_dump('Days: ', $days);
	// var_dump('minutes: ', $minutes);
	// var_dump('hours: ', $hours);

	$totalhours = 0;
	$duration = '';
	// $d = '';
	$h = '';
	$m = '';

	// var_dump('$d: ', $d);
	// var_dump('h: ', $h);
	// var_dump('m: ', $m);

	// if ($days != '') {
	// 	$totalhours = $days * 24;
	// 	$d = $days . " Day";
	// }

	if ($hours != '') {
		$totalhours = $totalhours + $hours;
		$h = $hours . " Hour";
	}

	if ($minutes != '') {
		$m = " : " . $minutes . " Minutes";
	}

	$duration = $h . $m;

	// var_dump('Duration: ', $duration);

	// Query to get event data from ntlcrm.ponDownInfo
	$query = "SELECT * FROM ntlcrm.ponDownInfo WHERE EVENTNAME = :eventname";
	// $result = $dbObj->Get_Array($query);
	$queryParams = [':eventname' => $eventname];
	$result = $dbObject->execSelect($query, $queryParams);
	// var_dump('Result for ponDownInfo: ', $result);

	// Prepare the response array
	$response = [
		'status' => 'success',
		'smsDraft' => '',
		'pushDraft' => '',
		'updateProvidedBy' => '',
		'updateProvidedTime' => '',
		'emailTime' => '',
		'cluster_name' => '',
		'event_id' => '',
		'ecalation_level' => '',
		'duration' => '',
		'minutes' => '',
		'totalhours' => '',
		'message' => ''
	];


	if (!empty($result)) {

		// $insertUpdateProvidedBy = "UPDATE EVENTALERTS SET HOURS = '$totalhours' , MINUTES = '$minutes', DURATION = '$duration' WHERE EVENTNAME = '$eventname'"; // added by Azeem
		// $resUpdateProvidedBy = $dbObj->insert($insertUpdateProvidedBy);

		// var_dump('insertUpdateProvidedBy: ', $insertUpdateProvidedBy);


		$EventIdquery = "SELECT EVENTID FROM eventalerts WHERE EVENTNAME = :eventname";
		// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
		$eventIdParams = [':eventname' => $eventname];
		$eventIdResult = $dbObject->execSelect($EventIdquery, $eventIdParams);

		// var_dump('eventIdResult: ', $eventIdResult);
		$event_id = $eventIdResult[0]['EVENTID'];
		// $updateProvidedTime = $eventIdResult[0]['UPDATE_PROVIDED_TIME'];
		// $hours = $eventIdResult[0]['HOURS'];
		// $minutes = $eventIdResult[0]['MINUTES'];


		// Insert into EVENTINTIMATIONLOGS table to log no ettr reason and update provided by and its time
		$logInsertQuery = "INSERT INTO NAYATELUSER.EVENTINTIMATIONLOGS (
            ID, EVENTID, KEY, VALUE, SUBKEY, SUBVALUE, COMMENTS, DATETIME, STATUS, OPERATOR
            ) 
            VALUES (
                NAYATELUSER.EVENTINTIMATIONLOGS_ID_SEQ.nextval, :eventid, :key, :value, :subkey, :subvalue, :comments, SYSDATE, :status, :operator
            )
            ";

		// Parameters for the insert query
		$logParameters = [
			':eventid' => $event_id,
			':key' => 'updateProvidedBy',
			':value' => $updateProvidedBy,
			':subkey' => 'ettrStatus',
			':subvalue' => 'YES',
			':comments' => "The Updated Less Ettr is: $duration",
			':status' => 'Active',
			':operator' => 'system',
		];

		// Execute the insertion query
		$logResult = $dbObject->execInsertUpdate($logInsertQuery, $logParameters);

		$checkLogQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME FROM NAYATELUSER.EVENTINTIMATIONLOGS 
        WHERE EVENTID = :eventid 
        AND KEY = :key
        AND VALUE = :value
        AND SUBKEY = :subkey
        AND SUBVALUE = :subvalue
        AND STATUS = 'Active'
        ORDER BY datetime DESC
        ";

		$checkLogParams = [
			':eventid' => $event_id,
			':key' => 'updateProvidedBy',
			':value' => $updateProvidedBy,
			':subkey' => 'ettrStatus',
			':subvalue' => 'YES',
			':status' => 'Active',
		];
		$updateProvidedResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);

		$updateProvidedTime = $updateProvidedResult[0]['DATETIME'];


		// var_dump('event_id: ', $eventIdResult);
		// var_dump('updateProvidedTime: ', $updateProvidedTime);

		// Initialize arrays to store PONs, cluster names, and user counts
		$pons_array = [];
		$cluster_name = '';
		$user_count = 0;

		// Loop through each row and extract the PON, cluster name, and user count
		foreach ($result as $row) {
			if (is_a($row['PONS_LIST'], 'OCI-Lob')) {
				$pon = $row['PONS_LIST']->load();  // Load the LOB data as a string if necessary
			} else {
				$pon = $row['PONS_LIST'];
			}

			$pons_array[] = $pon;  // Add PON to the array
			$cluster_name = $row['CLUSTERNAME'];  // Since cluster name and user count are the same for all rows
			$user_count = $row['USER_COUNT'];     // we can update them only once (if needed)
		}

		// Count the number of PONs
		$pon_count = count($pons_array);

		$emailTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME
                        FROM NAYATELUSER.emaillogs_ponIntimation 
                        WHERE SUBJECT LIKE :eventnamepattern 
                        ORDER BY DATETIME DESC";
		// $emailTimeResult = $dbObject->execSelect($emailTimeQuery, []);
		$emailTimeParams = [':eventnamepattern' => '%' . $eventname . '%'];
		$emailTimeResult = $dbObject->execSelect($emailTimeQuery, $emailTimeParams);

		$emailTime = $emailTimeResult[0]['DATETIME'];

		// Format escalation time to HH:MM
		$updateProvidedTime_formatted = date('H:i', strtotime($updateProvidedTime));
		// var_dump('escalation_time_formatted:', $escalation_time_formatted);

		if ($user_count > 500) {
			// var_dump('USER COUNT IS GREATER THAN 500');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "ETTR: $duration\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "ETTR: $duration\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 500 Customers';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} elseif ($pon_count == 1 || $pon_count == 2) {
			// var_dump('PON IS LESS THAN 2');

			$pons_to_display = implode(", ", $pons_array);  // Join PONs for display
			// var_dump('pons_to_display: ', $pons_to_display);

			// Extract device names from PONs
			$device_names = array_map(function ($pon) {
				return explode('-', $pon)[0];
			}, $pons_array);
			// var_dump('device_names: ', $device_names);

			// Join unique device names for display
			$unique_device_names = implode(", ", array_unique($device_names));
			// var_dump('unique_device_names: ', $unique_device_names);

			$message .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$message .= "PONs: $pons_to_display\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "ETTR: $duration \n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$pushMessage .= "PONs: $pons_to_display\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "ETTR: $duration \n";
			$pushMessage .= "NOC";

			if ($pon_count == 1) {
				$ecalation_level = '1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			} else {
				$ecalation_level = 'More than 1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			}

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} else {
			// If more than 2 PONs are affected
			// var_dump('PON IS GREATER THAN 2');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$message .= "ETTR: $duration\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Updated by: $updateProvidedBy / TX @ $updateProvidedTime_formatted\n";
			$pushMessage .= "ETTR: $duration \n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 1 PON Down';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		}

		$response['updateProvidedBy'] = $updateProvidedBy;
		$response['updateProvidedTime'] = $updateProvidedTime;
		$response['emailTime'] = $emailTime;
		$response['cluster_name'] = $cluster_name;
		$response['event_id'] = $event_id;
		$response['ecalation_level'] = $ecalation_level;

		$response['duration'] = $duration;
		$response['minutes'] = $minutes;
		$response['totalhours'] = $totalhours;


		$response['status'] = 'success';  // Set success status

		// Output the response as JSON
		echo json_encode($response);
	} else {
		echo json_encode(['status' => 'error', 'message' => 'Event not found']);
	}
} elseif ($type == 'sendIntimationOnMlessThanEttr') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$updateProvidedBy = $_POST['updateProvidedBy'];
	$updateProvidedTime = $_POST['updateProvidedTime'];
	$emailTime = $_POST['emailTime'];
	$cluster_name = $_POST['cluster_name'];
	$event_id = $_POST['event_id'];
	$ecalation_level = $_POST['ecalation_level'];
	$message = $_POST['smsDraft'];
	$pushMessage = $_POST['pushDraft'];
	$eventdescription = $_POST['eventdescription'];

	$duration = $_POST['duration'];
	$totalhours = $_POST['totalhours'];
	$minutes = $_POST['minutes'];

	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('updateProvidedBy: ', $updateProvidedBy);
	// var_dump('updateProvidedTime: ', $updateProvidedTime);
	// var_dump('emailTime: ', $emailTime);
	// var_dump('cluster_name: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);
	// var_dump('eventdescription: ', $eventdescription);

	$ettrStatus = 'YES';

	$insertDescription = "UPDATE EVENTALERTS SET DESCRIPTION = :eventdescription, HOURS = :totalhours , MINUTES = :minutes, DURATION = :duration ,  ETTR_STATUS = :ettrStatus , UPDATED_AT = SYSDATE , UPDATED_BY = :operator WHERE EVENTNAME = :eventname"; // added by Azeem
	$insertDescriptionParams = [
		':eventdescription' => $eventdescription,
		':totalhours' => $totalhours,
		':minutes' => $minutes,
		':duration' => $duration,
		':ettrStatus' => $ettrStatus,
		':operator' => $operator,
		':eventname' => $eventname,
	];
	// $resDescription = $dbObj->insert($insertDescription);
	$resDescription = $dbObj->execInsertUpdate($insertDescription, $insertDescriptionParams);

	// var_dump('resDescription updation: ', $resDescription);
	// var_dump('insertDescription : ', $insertDescription);

	sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
	echo json_encode("success");
} elseif ($type == 'LessThanEttrcancelation') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];

	// var_dump('eventname: ', $eventname);
	$EventIdquery = "SELECT EVENTID FROM eventalerts WHERE EVENTNAME = :eventname";
	// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
	$EventIdParams = [
		':eventname' => $eventname,
	];
	$eventIdResult = $dbObject->execSelect($EventIdquery, $EventIdParams);
	$event_id = $eventIdResult[0]['EVENTID'];

	// $insertEscalatedTo = "UPDATE EVENTALERTS SET UPDATE_PROVIDED_BY = NULL, UPDATE_PROVIDED_TIME = NULL , DESCRIPTION = NULL , ETTR_STATUS = NULL , NO_ETTR_REASON = NULL WHERE EVENTNAME = '$eventname'"; // added by Azeem
	// $resEscalatedTo = $dbObj->insert($insertEscalatedTo);

	$noReasonEttrQuery = "UPDATE NAYATELUSER.EVENTINTIMATIONLOGS 
    SET VALUE = NULL, SUBVALUE = NULL , STATUS = 'Disable' , COMMENTS = 'Update provided by for Less Ettr is cancelled' 
    WHERE EVENTID = :event_id
    AND DATETIME = (
      SELECT MAX(DATETIME)
      FROM NAYATELUSER.EVENTINTIMATIONLOGS
      WHERE EVENTID = :event_id
    )"; // added by Azeem
	// $noReasonEttrCanceled = $dbObject->execInsertUpdate($noReasonEttrQuery);
	$noReasonEttrParams = [
		':event_id' => $event_id,
	];
	$noReasonEttrCanceled = $dbObject->execInsertUpdate($noReasonEttrQuery, $noReasonEttrParams);

	echo json_encode(["status" => "success", "message" => "Event cancellation update successful"]);
} elseif ($type == 'getResolutionProvidedMessage') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$resolutionProvideBy = $_POST['resolutionProvideBy'];

	// Query to get event data from ntlcrm.ponDownInfo
	$query = "SELECT * FROM ntlcrm.ponDownInfo WHERE EVENTNAME = :eventname";
	// $result = $dbObj->Get_Array($query);
	$params = [
		':eventname' => $eventname
	];
	$result = $dbObject->execSelect($query, $params);
	// var_dump('Result for ponDownInfo: ', $result);

	// Prepare the response array
	$response = [
		'status' => 'success',
		'smsDraft' => '',
		'pushDraft' => '',
		'resolutionProvideBy' => '',
		'resolutionProvidedTime' => '',
		'emailTime' => '',
		'cluster_name' => '',
		'event_id' => '',
		'ecalation_level' => '',
		'message' => ''
	];

	if (!empty($result)) {

		$updatetResolutionProvidedBy = "UPDATE EVENTALERTS SET RESOLUTION_PROVIDED_BY = :resolutionProvideBy, RESOLUTION_PROVIDED_TIME = SYSDATE WHERE EVENTNAME = :eventname"; // added by Azeem phase2
		// $resDescription = $dbObj->insert($updatetResolutionProvidedBy);
		$params = [
			':resolutionProvideBy' => $resolutionProvideBy,
			':eventname' => $eventname
		];
		$resDescription = $dbObject->execInsertUpdate($updatetResolutionProvidedBy, $params);

		$EventIdquery = "SELECT EVENTID , TO_CHAR(RESOLUTION_PROVIDED_TIME, 'YYYY-MM-DD HH24:MI:SS') AS RESOLUTION_PROVIDED_TIME FROM eventalerts WHERE EVENTNAME = :eventname";
		// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
		$params = [
			':eventname' => $eventname
		];
		$eventIdResult = $dbObject->execSelect($EventIdquery, $params);
		// var_dump('eventIdResult: ', $eventIdResult);
		$event_id = $eventIdResult[0]['EVENTID'];
		$resolutionProvidedTime = $eventIdResult[0]['RESOLUTION_PROVIDED_TIME'];

		// var_dump('event_id: ', $eventIdResult);
		// var_dump('updateProvidedTime: ', $updateProvidedTime);

		// Initialize arrays to store PONs, cluster names, and user counts
		$pons_array = [];
		$cluster_name = '';
		$user_count = 0;

		// Loop through each row and extract the PON, cluster name, and user count
		foreach ($result as $row) {
			if (is_a($row['PONS_LIST'], 'OCI-Lob')) {
				$pon = $row['PONS_LIST']->load();  // Load the LOB data as a string if necessary
			} else {
				$pon = $row['PONS_LIST'];
			}

			$pons_array[] = $pon;  // Add PON to the array
			$cluster_name = $row['CLUSTERNAME'];  // Since cluster name and user count are the same for all rows
			$user_count = $row['USER_COUNT'];     // we can update them only once (if needed)
		}

		// Count the number of PONs
		$pon_count = count($pons_array);

		$emailTimeQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME
                            FROM NAYATELUSER.emaillogs_ponIntimation 
                            WHERE SUBJECT LIKE :eventname 
                            ORDER BY DATETIME DESC";
		// $emailTimeResult = $dbObject->execSelect($emailTimeQuery, []);
		$params = [
			':eventname' => '%' . $eventname . '%'
		];
		$emailTimeResult = $dbObject->execSelect($emailTimeQuery, $params);

		$emailTime = $emailTimeResult[0]['DATETIME'];

		// Format escalation time to HH:MM
		$resolutionProvidedTime_formatted = date('H:i', strtotime($resolutionProvidedTime));
		// var_dump('escalation_time_formatted:', $escalation_time_formatted);

		if ($user_count > 500) {
			// var_dump('USER COUNT IS GREATER THAN 500');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Resolved: $resolutionProvideBy / TX @ $resolutionProvidedTime_formatted\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Resolved: $resolutionProvideBy / TX @ $resolutionProvidedTime_formatted\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 500 Customers';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} elseif ($pon_count == 1 || $pon_count == 2) {
			// var_dump('PON IS LESS THAN 2');

			$pons_to_display = implode(", ", $pons_array);  // Join PONs for display
			// var_dump('pons_to_display: ', $pons_to_display);

			// Extract device names from PONs
			$device_names = array_map(function ($pon) {
				return explode('-', $pon)[0];
			}, $pons_array);
			// var_dump('device_names: ', $device_names);

			// Join unique device names for display
			$unique_device_names = implode(", ", array_unique($device_names));
			// var_dump('unique_device_names: ', $unique_device_names);

			$message .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$message .= "PONs: $pons_to_display\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Resolved: $resolutionProvideBy / TX @ $resolutionProvidedTime_formatted\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple $unique_device_names customers down in $cluster_name region\n";
			$pushMessage .= "PONs: $pons_to_display\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Resolved: $resolutionProvideBy / TX @ $resolutionProvidedTime_formatted\n";
			$pushMessage .= "NOC";

			if ($pon_count == 1) {
				$ecalation_level = '1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			} else {
				$ecalation_level = 'More than 1 PON Down';
				// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			}

			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		} else {
			// If more than 2 PONs are affected
			// var_dump('PON IS GREATER THAN 2');

			$message .= "Multiple customers down in $cluster_name region\n";
			$message .= "More than 2 PONs affected\n";
			$message .= "Scope: All services\n";
			$message .= "Customer count: $user_count\n";
			$message .= "Observed: $emailTime\n";
			$message .= "Resolved: $resolutionProvideBy / TX @ $resolutionProvidedTime_formatted\n";
			$message .= "NOC";

			$pushMessage = "$eventname\n";
			$pushMessage .= "Multiple customers down in $cluster_name region\n";
			$pushMessage .= "More than 2 PONs affected\n";
			$pushMessage .= "Scope: All services\n";
			$pushMessage .= "Customer count: $user_count\n";
			$pushMessage .= "Observed: $emailTime\n";
			$pushMessage .= "Resolved: $resolutionProvideBy / TX @ $resolutionProvidedTime_formatted\n";
			$pushMessage .= "NOC";

			$ecalation_level = 'More than 1 PON Down';
			// sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
			$response['smsDraft'] = $message;
			$response['pushDraft'] = $pushMessage;
			$response['ecalation_level'] = $ecalation_level;
		}

		$response['resolutionProvideBy'] = $resolutionProvideBy;
		$response['resolutionProvidedTime'] = $resolutionProvidedTime;
		$response['emailTime'] = $emailTime;
		$response['cluster_name'] = $cluster_name;
		$response['event_id'] = $event_id ?? 'N/A';
		$response['ecalation_level'] = $ecalation_level;
		$response['status'] = 'success';  // Set success status

		// Output the response as JSON
		echo json_encode($response);
	} else {
		echo json_encode(['status' => 'error', 'message' => 'Event not found']);
	}
} elseif ($type == 'resolutionUpdatedByNoc') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	$resolutionProvideBy = $_POST['resolutionProvideBy'];
	$resolutionProvidedTime = $_POST['resolutionProvidedTime'];
	$emailTime = $_POST['emailTime'];
	$cluster_name = $_POST['cluster_name'];
	$event_id = $_POST['event_id'];
	$ecalation_level = $_POST['ecalation_level'];
	$message = $_POST['smsDraft'];
	$pushMessage = $_POST['pushDraft'];
	$eventdescription = $_POST['eventdescription'];


	$updatetResolutionProvidedBy = "UPDATE EVENTALERTS SET STATUS = 'DISABLE' , DESCRIPTION = :eventdescription WHERE EVENTNAME = :eventname"; // added by Azeem phase2
	// $resDescription = $dbObj->insert($updatetResolutionProvidedBy);
	$params = [
		':eventdescription' => $eventdescription,
		':eventname' => $eventname
	];
	$resDescription = $dbObject->execInsertUpdate($updatetResolutionProvidedBy, $params);
	// var_dump('resDescription', $resDescription);

	$ResolutionProvidedByQuery = "SELECT id, EVENTNO FROM NAYATELUSER.EVENTLOGGERFORM WHERE EVENTDESCRIPTION LIKE :eventname ORDER BY ID DESC"; // added by Azeem phase2
	// $updatetResolutionProvidedByRes = $dbObject->execSelect($ResolutionProvidedByQuery, []);
	$params = [
		':eventname' => '%' . $eventname . '%'
	];

	$updatetResolutionProvidedByRes = $dbObject->execSelect($ResolutionProvidedByQuery, $params);

	$eventLoggerId = $updatetResolutionProvidedByRes[0]['ID'];
	$eventLoggerName = $updatetResolutionProvidedByRes[0]['EVENTNO'];
	// var_dump('eventLoggerId', $eventLoggerId);
	// var_dump('eventLoggerName', $eventLoggerName);

	// $totalTimeQuery = "SELECT TO_CHAR(ALARMDATE, 'YYYY-MM-DD HH24:MI:SS') AS ALARMDATE
	//     FROM (
	//         SELECT ALARMDATE
	//         FROM usereventalert
	//         WHERE EVENTID = '$event_id'
	//         ORDER BY ID ASC
	//     )
	//     WHERE ROWNUM = 1"; // added by Azeem phase2

	// $totalTimeRes = $dbObject->execSelect($totalTimeQuery, []);
	// $startTime = $totalTimeRes[0]['ALARMDATE'];

	// var_dump('startTime', $startTime);
	// var_dump('endTime', $resolutionProvidedTime);

	$updatetResolutionEventlogger = "UPDATE NAYATELUSER.EVENTLOGGERFORM SET RESOLUTIONTIME = TO_DATE(:resolutionProvidedTime, 'YYYY-MM-DD HH24:MI:SS') WHERE ID = :eventLoggerId"; // added by Azeem phase2
	// $resDescription = $dbObj->insert($updatetResolutionEventlogger);
	$params = [
		':resolutionProvidedTime' => $resolutionProvidedTime,
		':eventLoggerId' => $eventLoggerId
	];

	$resDescription = $dbObject->execInsertUpdate($updatetResolutionEventlogger, $params);

	// var_dump('updatetResolutionEventlogger', $updatetResolutionEventlogger);


	$ftttEscalationQuery = "SELECT id FROM SLA_FTTT_ESCLAIONS WHERE EMAIL_SUBJECT LIKE :eventname ORDER BY ID DESC"; // added by Azeem phase2
	// $ftttEscalationIdsRes = $dbObject->execSelect($ftttEscalationQuery, []);
	$params = [
		':eventname' => '%' . $eventname . '%'
	];

	$ftttEscalationIdsRes = $dbObject->execSelect($ftttEscalationQuery, $params);
	// var_dump('ftttEscalationQuery', $ftttEscalationQuery);
	// var_dump('ftttEscalationIdsRes', $ftttEscalationIdsRes);

	foreach ($ftttEscalationIdsRes as $record) {
		$id = $record['ID'];
		// echo "Processing ID: " . $id . "\n";

		$updateftttEscalation = "UPDATE SLA_FTTT_ESCLAIONS SET END_TIME = TO_DATE(:resolutionProvidedTime, 'YYYY-MM-DD HH24:MI:SS') WHERE ID = :id"; // added by Azeem phase2
		// $resDescription = $dbObj->insert($updateftttEscalation);
		$params = [
			':resolutionProvidedTime' => $resolutionProvidedTime,
			':id' => $id
		];

		$resDescription = $dbObj->execInsertUpdate($updateftttEscalation, $params);
	}

	$userListQuery = "SELECT USERID FROM usereventalert WHERE EVENTID = :event_id"; // added by Azeem phase2
	// $userListQueryRes = $dbObject->execSelect($userListQuery, []);
	$params = [
		':event_id' => $event_id
	];

	$userListQueryRes = $dbObject->execSelect($userListQuery, $params);

	// var_dump('userListQueryRes', $userListQueryRes);

	$userListQuery = $userListQueryRes[0]['USERID'];

	// var_dump('userListQuery', $userListQuery);

	$userCount = count($userListQueryRes);
	// var_dump('userCount', $userCount);

	if ($userCount > 500) {
		// Sending push to the customer for resolution
		foreach ($userListQueryRes as $user) {
			// var_dump('userListQueryRes', $userListQueryRes);

			$userId = $user['USERID'];
			// var_dump('userId :', $userId);

			sendRestorationPushNotificationToCustomer($userId);
			sendRestorationEmailNotificationToCustomer($userId);
		}

		// Sending email to the customer for resolution
		// foreach ($userListQueryRes as $user) {
		// 	sendRestorationEmailNotificationToCustomer($userId);
		// }
	}

	sendEscalationSMS($cluster_name, $event_id, $ecalation_level, $message, $pushMessage);
	echo json_encode("success");
} elseif ($type == 'ResolutionProvidedcancelation') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	// var_dump('eventname', $eventname);

	$insertEscalatedTo = "UPDATE EVENTALERTS SET RESOLUTION_PROVIDED_BY = NULL, RESOLUTION_PROVIDED_TIME = NULL WHERE EVENTNAME = :eventname"; // added by Azeem

	// $resEscalatedTo = $dbObj->insert($insertEscalatedTo);
	$params = [
		':eventname' => $eventname
	];

	$resEscalatedTo = $dbObject->execInsertUpdate($insertEscalatedTo, $params);
	var_dump('resEscalatedTo', $resEscalatedTo);

	echo json_encode(["status" => "success", "message" => "Event cancellation update successful"]);
} else if ($type == "compareEttr") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$eventname = $_POST['eventname'];
	// $previousDays = $_POST['previousDays'];
	$previousHours = $_POST['previousHours'];
	$previousMinutes = $_POST['previousMinutes'];
	// $currentDays = $_POST['currentDays'];
	$currentHours = $_POST['currentHours'];
	$currentMinutes = $_POST['currentMinutes'];

	// var_dump('Event Name:', $eventname);

	// var_dump('Previous Hours:', $previousHours);

	// var_dump('Previous Hours:', $previousMinutes);

	// var_dump('currentHours :', $currentHours);

	// var_dump('currentMinutes:', $currentMinutes);

	$EventIdquery = "SELECT EVENTID , to_char(DATETIME + ((HOURS / 24) + (MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS DATETIME FROM eventalerts WHERE EVENTNAME = :eventname";
	// $eventIdResult = $dbObject->execSelect($EventIdquery, []);

	$params = [
		':eventname' => $eventname
	];
	$eventIdResult = $dbObject->execSelect($EventIdquery, $params);

	// var_dump('eventIdResult: ', $eventIdResult);
	$event_id = $eventIdResult[0]['EVENTID'];
	$eventResolutionTime = $eventIdResult[0]['DATETIME'];
	// var_dump('event_id: ', $event_id);
	// var_dump('eventOccurenceTime: ', $eventOccurenceTime);

	$checkLogQuery = "SELECT TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME FROM NAYATELUSER.EVENTINTIMATIONLOGS 
	WHERE EVENTID = :eventid 
	AND KEY = :key
	AND SUBKEY = :subkey
	AND SUBVALUE = :subvalue
	AND STATUS = 'Active'
	ORDER BY datetime DESC
	";

	$checkLogParams = [
		':eventid' => $event_id,
		':key' => 'updateProvidedBy',
		':subkey' => 'ettrStatus',
		':subvalue' => 'YES',
		':status' => 'Active',
	];

	$updateProvidedResult = $dbObject->execSelect($checkLogQuery, $checkLogParams);
	// var_dump('checkLogQuery: ', $checkLogQuery);
	// var_dump('updateProvidedResult: ', $updateProvidedResult);

	if (empty($updateProvidedResult)) {
		// Get the current time
		$currentTime = new DateTime();

		// Add current hours and minutes to current time
		$interval = new DateInterval("PT{$currentHours}H{$currentMinutes}M");
		$currentTime->add($interval); // Add the interval to current time
		$updatedeventresolutiontime = $currentTime->format('d-m-Y H:i:s'); // Format as required

		// Parse eventResolutionTime for comparison
		$eventResolutionTimeObject = DateTime::createFromFormat('d-m-Y H:i:s', $eventResolutionTime);

		if (!$eventResolutionTimeObject) {
			echo json_encode(['status' => 'error', 'message' => 'Invalid event resolution time format']);
			exit;
		}

		// Step 4: Compare the two times
		if ($currentTime > $eventResolutionTimeObject) {
			echo json_encode([
				'status' => 'success',
				'message' => 'greater',
				'currentTime' => $currentTime->format('Y-m-d H:i:s') // Add current time to the response
			]);
		} else {
			echo json_encode(['status' => 'success', 'message' => 'lesser']);
		}
	} else {
		// var_dump('Ettr updated code start here...');

		$checkGreaterEttrQuery = "SELECT COMMENTS , DATETIME
		FROM (
			SELECT COMMENTS , TO_CHAR(DATETIME, 'YYYY-MM-DD HH24:MI:SS') AS DATETIME
			FROM NAYATELUSER.EVENTINTIMATIONLOGS
			WHERE EVENTID = :eventid
			AND KEY = 'updateProvidedBy'
			AND SUBVALUE = 'YES'
			AND LOWER(COMMENTS) LIKE '%greater ettr%'
			AND STATUS = 'Active'
			ORDER BY DATETIME DESC
		)
		WHERE ROWNUM = 1
		";

		$checkGreaterEttrParams = [
			':eventid' => $event_id,
		];

		$greaterEttrResult = $dbObject->execSelect($checkGreaterEttrQuery, $checkGreaterEttrParams);
		// var_dump('greaterEttrResult: ', $greaterEttrResult);

		if (!empty($greaterEttrResult)) {
			// var_dump('Greater Ettr updated code start here...');

			// Step 2: Handle COMMENTS (CLOB) and DATETIME
			$comments = $greaterEttrResult[0]['COMMENTS']->load(); // CLOB to string
			$lastGreaterDatetime = $greaterEttrResult[0]['DATETIME']; // Format: 2024-12-20 12:04:26
			// var_dump('comments: ', $comments);
			// var_dump('lastGreaterDatetime: ', $lastGreaterDatetime);

			// Step 3: Extract hours and minutes from COMMENTS
			preg_match('/The Updated Greater Ettr is:\s*(\d+)\s*Hour\s*:\s*(\d+)\s*Minutes/', $comments, $matches);
			if ($matches) {
				$lastGreaterHours = (int)$matches[1];
				// var_dump('lastGreaterHours: ', $lastGreaterHours);

				$lastGreaterMinutes = (int)$matches[2];
				// var_dump('lastGreaterMinutes: ', $lastGreaterMinutes);
			} else {
				echo json_encode(['status' => 'error', 'message' => 'Invalid comments format for greater ETTR']);
				exit;
			}

			try {
				$lastGreaterDatetimeObj = new DateTime($lastGreaterDatetime);
			} catch (Exception $e) {
				echo json_encode(['status' => 'error', 'message' => 'Invalid last greater datetime format']);
				exit;
			}

			// Add extracted hours and minutes to DATETIME
			$lastGreaterDatetimeObj->modify("+{$lastGreaterHours} hours");
			$lastGreaterDatetimeObj->modify("+{$lastGreaterMinutes} minutes");

			$lastGreaterResolutionTime = $lastGreaterDatetimeObj->format('Y-m-d H:i:s');
			// var_dump('lastGreaterResolutionTime: ', $lastGreaterResolutionTime);

			// Convert lastGreaterResolutionTime to DateTime for comparison
			$lastGreaterResolutionDateTime = $lastGreaterDatetimeObj; // Already a DateTime object
			// var_dump('lastGreaterResolutionDateTime: ', $lastGreaterResolutionDateTime);

			// Step 5: Calculate currentResolutionTime
			$currentTime = new DateTime(); // Current time
			$currentInterval = new DateInterval("PT{$currentHours}H{$currentMinutes}M");
			$currentTime->add($currentInterval); // Add current hours and minutes
			// var_dump('currentResolutionTime: ', $currentTime);

			// Step 6: Compare resolution times
			if ($currentTime > $lastGreaterResolutionDateTime) {
				echo json_encode([
					'status' => 'success',
					'message' => 'greater',
					'currentTime' => $currentTime->format('Y-m-d H:i:s') // Add current time to the response
				]);
			} else {
				echo json_encode(['status' => 'success', 'message' => 'lesser']);
			}
		} else {
			// var_dump('Less Ettr updated code start here...');

			// No greater ETTR record found now 
			$EventIdquery = "SELECT EVENTID, TO_CHAR(DATETIME, 'DD-MM-YYYY HH24:MI:SS') AS DATETIME FROM eventalerts WHERE EVENTNAME = :eventname";
			// $eventIdResult = $dbObject->execSelect($EventIdquery, []);
			$params = [
				':eventname' => $eventname
			];

			$eventIdResult = $dbObject->execSelect($EventIdquery, $params);
			// var_dump('eventIdResult: ', $eventIdResult);

			// Assuming eventIdResult contains the required event details
			if (empty($eventIdResult)) {
				echo json_encode(['status' => 'error', 'message' => 'Event not found']);
				exit;
			}

			$eventResolutionTime = $eventIdResult[0]['DATETIME']; // Format: 20-12-2024 12:02:26
			// var_dump('eventResolutionTime: ', $eventResolutionTime);

			// Step 3: Add 8 hours to event resolution time
			try {
				$eventResolutionTimeObj = new DateTime($eventResolutionTime);
				$eventResolutionTimeObj->modify("+8 hours");
				$eventResolutionTime = $eventResolutionTimeObj->format('Y-m-d H:i:s'); // New event resolution time
				// var_dump('added 8 hours in eventstartTime: ', $eventResolutionTime);
			} catch (Exception $e) {
				echo json_encode(['status' => 'error', 'message' => 'Invalid event resolution time format']);
				exit;
			}

			// Step 4: Get current time and add currentHours and currentMinutes
			$currentTime = new DateTime(); // Current time
			// var_dump('currentTime : ', $currentTime);
			$currentInterval = new DateInterval("PT{$currentHours}H{$currentMinutes}M");
			$currentTime->add($currentInterval); // Add current hours and minutes
			// var_dump('after adding current mints and hours currentTime : ', $currentTime);

			// Step 5: Compare current time with event resolution time
			$currentResolutionTime = $currentTime->format('Y-m-d H:i:s');
			// var_dump('currentResolutionTime : ', $currentResolutionTime);

			if ($currentResolutionTime > $eventResolutionTime) {
				// If current resolution time is greater than event resolution time
				echo json_encode([
					'status' => 'success',
					'message' => 'greater',
					'currentTime' => $currentTime->format('Y-m-d H:i:s') // Include current time in the response
				]);
			} else {
				// If current resolution time is lesser than event resolution time
				echo json_encode(['status' => 'success', 'message' => 'lesser']);
			}
		}
	}
} elseif ($type == 'updateEventManual') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$days = $_POST['days'];
	$hours = $_POST['hours'];
	$minutes = $_POST['minutes'];
	$eventname = $_POST['eventname'];
	$eventdescription = $_POST['eventdescription'];
	if (checkduplicateEventName($eventname, $dbObject, $eventid) == false) {
		echo json_encode("Error");
		exit();
	}

	$totalhours = 0;
	$duration = '';
	$d = '';
	$h = '';
	$m = '';

	if ($days != '') {
		$totalhours = $days * 24;
		$d = $days . " Day";
	}

	if ($hours != '') {
		$totalhours = $totalhours + $hours;
		$h = " : " . $hours . " Hour";
	}

	if ($minutes != '') {
		$m = " : " . $minutes . " Minutes";
	}

	$duration = $d . $h . $m;

	//  Changed by Hammad on 25 June 2024, Start:

	// $hours = $totalhours;

	// $query1 = "SELECT EVENT_TYPE,to_char(datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME FROM EVENTALERTS WHERE  EVENTID='$eventid'";
	// $stmt = OCIParse($conn, $query1);
	// $execute = OCIExecute($stmt, OCI_DEFAULT);
	// $eventtype = oci_fetch_array($stmt);

	$query1 = "SELECT EVENT_TYPE,to_char(datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME, DAYS, HOURS, MINUTES FROM EVENTALERTS WHERE EVENTID=:eventid";
	// $eventtype = $dbObj->Get_Array($query1);
	$params = [
		':eventid' => $eventid,
	];

	$eventtype = $dbObject->execSelect($query1, $params);

	$preDays = $eventtype[0]['DAYS'];
	$preHours = $eventtype[0]['HOURS'];
	$preMinutes = $eventtype[0]['MINUTES'];

	$preTotalHours = 0;
	$sendIntimation = false;

	if ($preDays != '') {
		$preTotalHours = $preDays * 24;
	}

	if ($preHours != '') {
		$preTotalHours = $preTotalHours + $preHours;
	}

	if ($totalhours > $preTotalHours) {
		// ETTR is greater than initially shared 8 hrs. Send Intimations:
		$sendIntimation = true;
	} else {
		// ETTR is not greater than initially shared 8 hrs. Don't Send Intimations:
		$sendIntimation = false;
	}

	//  Changed by Hammad on 25 June 2024, End.

	$event_SMS = '';
	if ($eventtype[0]['EVENT_TYPE'] == 'Fiber Cut') {
		if ($hours == '') {
			$event_SMS = "Your services are affected due to a fiber cut. Services will be restored in approximately 6-8 hrs. Inconvenience is regretted.";
		} else {
			$event_SMS = "Your services are affected due to a fiber cut. Services will be restored in approximately $totalhours hrs $minutes min . Inconvenience is regretted.";
		}
	}

	$queryinsert = "UPDATE EVENTALERTS SET EVENTNAME=:eventname,DESCRIPTION=:eventdescription,DURATION=:duration,DAYS='0',HOURS=:totalhours,MINUTES=:minutes,OPERATOR=:operator,EVENT_SMS = :event_sms , UPDATED_AT = SYSDATE , UPDATED_BY = :operator WHERE EVENTID=:eventid";
	// $dbObj->insert($queryinsert);
	$params = [
		':eventname' => $eventname,
		':eventdescription' => $eventdescription,
		':duration' => $duration,
		':totalhours' => $totalhours,
		':minutes' => $minutes,
		':operator' => $operator,
		':event_sms' => $event_SMS,
		':eventid' => $eventid,
	];

	$dbObject->execInsertUpdate($queryinsert, $params);

	// var_dump($eventtype['DATETIME']);exit;
	// by momna on 1stNov2023, converting days,hours,mins into outage_end_dateTime

	if ($eventtype[0]['EVENT_TYPE'] == 'Fiber Cut') {

		$date = new DateTime($eventtype[0]['DATETIME']);
		// $date->add(new DateInterval("PT{$hours}H{$minutes}M"));
		$date->add(new DateInterval("PT{$totalhours}H{$minutes}M"));
		$dateString = $date->format('d-M-Y H:i:s');
		$smsEndDate = $date->format('d-M-Y h:i A'); //  Added by Hammad on 27 June 2024.

		$querySelect = "SELECT * from USEREVENTALERT where eventid=:eventid AND STATUS = 'ACTIVE'";
		// $userids = $dbObj->Get_Array($querySelect);
		$params = [
			':eventid' => $eventid,
		];
		$userids = $dbObject->execSelect($querySelect, $params);

		// if this is the only event where user was involved, then simply update flag
		// otherwise replace flags with the other ongoing event

		for ($i = 0; $i < count($userids); $i++) {
			$query = "SELECT * from NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY where 
				userid = :userid  
				-- eventid = '$eventid' 
				and OUTAGE_END_TIME < to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS')
				";
			// $res = $dbObj->Get_Array($query);
			$params = [
				':userid' => $userids[$i]['USERID'],
				':dateString' => $dateString,
			];

			$res = $dbObject->execSelect($query, $params);

			if (count($res) >= 1) {
				$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_END_TIME=to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS') 
					WHERE USERID = :userid and eventid = :eventid";
				// $res = $dbObj->insert($query);
				$params = [
					':dateString' => $dateString,
					':userid' => $userids[$i]['USERID'],
					':eventid' => $eventid,
				];

				$res = $dbObject->execInsertUpdate($query, $params);
			} else {
				$querySelect = "SELECT to_char(a.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME,a.HOURS,a.MINUTES,a.EVENT_TYPE,a.EVENTID from
					EVENTALERTS a
					JOIN USEREVENTALERT b
					on a.eventid=b.eventid 
					where a.status='ACTIVE' 
					and a.event_type='Fiber Cut'
					and b.userid = :userid
					and a.eventid <> :currentEventId";

				// $resultSelect = $dbObj->Get_Array($querySelect);
				$params = [
					':userid' => $userids[$i]['USERID'],
					':currentEventId' => $eventid,
				];

				$resultSelect = $dbObject->execSelect($querySelect, $params);

				// if user is involved in another on going-fiber cut outage then update outage flags in multi IVR table, 
				// otherwise set flag to null
				if (count($resultSelect) > 0) {
					$hours = $resultSelect[0]['HOURS'];
					$mins = $resultSelect[0]['MINUTES'];
					$event_id = $resultSelect[0]['EVENTID'];

					$date = new DateTime($resultSelect[0]['DATETIME']);
					$date->add(new DateInterval("PT{$hours}H{$mins}M"));

					$dateString = $date->format('d-M-Y H:i:s');

					$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG=1,EVENTID=:event_id , OUTAGE_END_TIME=to_date(:dateString, 'DD-MON-YYYY HH24:MI:SS') WHERE USERID = :userid";

					// var_dump($query);exit;

					// $res = $dbObj->insert($query);
					$params = [
						':event_id' => $event_id,
						':dateString' => $dateString,
						':userid' => $userids[$i]['USERID'],
					];

					// Execute the query
					$res = $dbObject->execInsertUpdate($query, $params);
					// }
					// else
					// {
					// 	$query = "UPDATE NTLCRM.MULTI_IVR_OPTIMIZED_SUMMARY SET OUTAGE_FLAG=0 WHERE USERID = '".$userids[$i]['USERID']."'";
					// 	$res = $dbObj->insert($query);
					// }
				} else {
					continue;
				}
			}

			//  Sending SMS & Push Notification To Customer, Added by Hammad on 27 June 2024, Start:
			if ($sendIntimation) {
				sendSmsToCustomer($eventid, $userids[$i]['USERID'], $smsEndDate);
				sendPushNotificationToCustomer($userids[$i]['USERID'], $smsEndDate);
			}
			//  Sending SMS & Push Notification To Customer, Added by Hammad on 27 June 2024, End.

		}
	}

	echo json_encode("success");
} elseif ($type == 'getManualUserIDReport') {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventname = $_GET['eventname'];
	// var_dump('eventname: ', $eventname);
	//	Changed by Hammad on 1 July 2024, Start:

	// $query = "SELECT E.EVENTNAME,E.EVENTID,E.HOURS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS,UE.STATUS AS USTATUS,UE.USERID,to_char(e.datetime,'DD-MM-YYYY HH24:MI:SS') as DATETIME
	// ,to_char(e.datetime + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE FROM EVENTALERTS E
	// INNER JOIN USEREVENTALERT UE ON E.EVENTID=UE.EVENTID
	// WHERE trim(E.EVENTNAME)=trim('$eventname')";

	$query = "SELECT E.EVENTNAME,E.EVENTID,E.HOURS,E.MINUTES,E.DESCRIPTION,E.DURATION,E.STATUS,UE.STATUS AS USTATUS,UE.USERID,to_char(UE.DATETIME,'DD-MM-YYYY HH24:MI:SS') as DATETIME
	,to_char(e.DATETIME + E.DAYS + ((E.HOURS / 24) + (E.MINUTES/ 1440)), 'DD-MM-YYYY HH24:MI:SS') AS HOURDATE FROM EVENTALERTS E
	INNER JOIN USEREVENTALERT UE ON E.EVENTID=UE.EVENTID
	WHERE trim(E.EVENTNAME)=trim(:eventname)";

	//	Changed by Hammad on 1 July 2024, End.

	// $result = $dbObj->Get_Array($query);
	$params = [
		':eventname' => $eventname,
	];

	// Execute the query
	$result = $dbObject->execSelect($query, $params);
	// var_dump($result);

	$dataArray = array();
	for ($i = 0; $i < count($result); $i++) {
		$dataArray[$i][0] = $i + 1;
		$dataArray[$i][1] = $result[$i]['EVENTNAME'];
		$dataArray[$i][2] = $result[$i]['DESCRIPTION'];
		/**/
		$startdate = date("d-m-Y H:i:s");
		$hourdate = $result[$i]['HOURDATE'];

		// $startdate1=strtotime($startdate);
		// $hourdate1=strtotime($hourdate);
		// echo $diff= ($hourdate1 - $startdate1)/60/60/24; 
		// echo $days=$diff->format("%R%a days %H:%I:%S");exit();
		/**/
		$hours = $result[$i]['HOURS'];
		// $entryTime = $result[$i]['DATETIME'];

		// $actualTime=date('d-M-Y H:i:s',strtotime("+$hours hour",strtotime($entryTime)));
		$date1 = date_create($startdate);
		$date2 = date_create($hourdate);
		$diff = date_diff($date1, $date2);
		$days = $diff->format("%R%a days %H:%I:%S");



		$dataArray[$i][3] = $result[$i]['DURATION'];
		$dataArray[$i][4] = $days;
		$dataArray[$i][5] = $result[$i]['USERID'];

		// Changed by Hammad on 1 July 2024, Start:

		// $dataArray[$i][6] = $result[$i]['STATUS'];
		// $dataArray[$i][7] = $result[$i]['USTATUS'];
		$dataArray[$i][6] = $result[$i]['USTATUS'];
		$dataArray[$i][7] = $result[$i]['STATUS'];

		// Changed by Hammad on 1 July 2024, End.

		$dataArray[$i][8] = $result[$i]['DATETIME'];
		$userid = $result[$i]['USERID'];
		$eventid = $result[$i]['EVENTID'];
		$dataArray[$i][9] = "<div class='center'>
			<a class='btn btn-xs btn-red tooltips' title='DisableUser' data-placement='top' data-original-title='Edit' onclick='disableManualUser(\"$eventid\",\"$userid\")'>
					<span class='glyphicon glyphicon-remove'></span>
			</a>
			<a class='btn btn-xs btn-red tooltips' title='EnableUser' data-placement='top' data-original-title='Edit' onclick='enableManualUser(\"$eventid\",\"$userid\")'>
					<span class='glyphicon glyphicon-ok'></span>
			</a>
			<a class='btn btn-xs btn-red tooltips' title='DeleteUser' data-placement='top' data-original-title='Edit' onclick='deleteManualUser(\"$eventid\",\"$userid\")'>
					<span class='glyphicon glyphicon-trash'></span>
			</a>
			</div>";
	}
	//var_dump($dataArray);exit();
	$res = array('data' => $dataArray);
	echo json_encode($res);
} elseif ($type == "enableManualUser") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$userid = $_POST['userid'];
	$queryinsert = "UPDATE USEREVENTALERT SET STATUS='ACTIVE' WHERE USERID=:userid AND EVENTID=:eventid";
	// $res = $dbObj->insert($queryinsert);

	$params = [
		':userid' => $userid,
		':eventid' => $eventid
	];

	// Execute the query using execInsertUpdate
	$res = $dbObject->execInsertUpdate($queryinsert, $params);

	echo json_encode("success");
} elseif ($type == "disableManualUser") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$userid = $_POST['userid'];
	$queryinsert = "UPDATE USEREVENTALERT SET STATUS='DISABLE' WHERE USERID=:userid AND EVENTID=:eventid";
	// $res = $dbObj->insert($queryinsert);

	$params = [
		':userid' => $userid,
		':eventid' => $eventid
	];

	// Execute the query using execInsertUpdate
	$res = $dbObject->execInsertUpdate($queryinsert, $params);

	echo json_encode("success");
} elseif ($type == "deleteManualUser") {
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	// PHASE 2 WORKING
	$dbObject = new DbClass();

	$eventid = $_POST['eventid'];
	$userid = $_POST['userid'];
	// $queryinsert = "delete FROM USEREVENTALERT WHERE USERID='$userid' AND EVENTID='$eventid'";
	$queryinsert = "UPDATE USEREVENTALERT SET STATUS = 'DELETED' WHERE USERID=:userid AND EVENTID=:eventid";
	// $res = $dbObj->insert($queryinsert);

	$params = [
		':userid' => $userid,
		':eventid' => $eventid
	];
	// Execute the query using execInsertUpdate
	$res = $dbObject->execInsertUpdate($queryinsert, $params);

	echo json_encode("success");
}
//Azeem code Start here 09-18-2024

function checkduplicateEventName($eventname, $dbObject, $eventid)
{
	$query1 = "SELECT * FROM EVENTALERTS WHERE EVENTNAME=:eventname and EVENTID NOT IN (SELECT EVENTID FROM EVENTALERTS WHERE EVENTID=:eventid)";
	// $stmt = OCIParse($conn, $query1);
	// $execute = OCIExecute($stmt, OCI_DEFAULT);
	// $useridexists = oci_fetch_array($stmt);

	// Prepare parameters for binding
	$params = [
		':eventname' => $eventname,
		':eventid' => $eventid
	];

	// Execute the query
	$useridexists = $dbObject->execSelect($query1, $params);

	if (empty($useridexists)) {
		return true;
	} else {
		return false;
	}
}

//  Sending SMS & Push Notification To Customer, Added by Hammad on 27 June 2024, Start:     

function sendSmsToCustomer($eventid, $userId, $newDateString)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	// Fetching phone numbers of the Customer:
	$phoneQuery = "SELECT LISTAGG(y.areacode || y.phoneno, ',') WITHIN GROUP (ORDER BY y.areacode, y.phoneno) AS phone_numbers
                            FROM mbluser x
                            JOIN customercontact y ON x.accountid = y.accountid
                            WHERE x.userid = :userId AND y.phonetype LIKE '%MOBILE_IT_%'";

	$phoneBindParameters = array(":userId" => $userId);

	$phoneResult = $dbObject->execSelect($phoneQuery,  $phoneBindParameters);

	if (!empty($phoneResult) && isset($phoneResult[0]['PHONE_NUMBERS'])) {
		$phoneNumbersString = $phoneResult[0]['PHONE_NUMBERS'];
		$phoneNumbers = explode(',', $phoneNumbersString);

		$smsMsg = "Restoration work for main cable, against User ID $userId, is in progress. Services are expected to be fully restored by approximately $newDateString. We appreciate your understanding.";
		$module = 'ajax_files/ajaxEventAlertReport.php';
		$op = 'system';
		$opDept = '';
		$status = 'PENDING';
		$category = '';

		foreach ($phoneNumbers as $phoneNumber) {

			$smsQuery = "INSERT INTO NAYATELUSER.SMS_INTIMATION(ID, CELLNUMBER, MESSAGE, MODULE, OPERATOR, OPERATOR_DEPT, STATUS, DATETIME, CATEGORY, EVENT_ID)
                            VALUES(NAYATELUSER.SMS_INTIMATION_ID_SEQ.nextval, :phoneNumber, :smsMsg, :module, :op, :opDept, :status, sysdate, :category, :eventid)";

			$smsBindParameters = array(":phoneNumber" => $phoneNumber, ":smsMsg" => $smsMsg, ":module" => $module, ":op" => $op, ":opDept" => $opDept, ":status" => $status, ":category" => $category, ":eventid" => $eventid);

			$smsResult = $dbObject->execInsertUpdate($smsQuery, $smsBindParameters);
		}
	} else {
		// echo "No phone numbers found for User Id $userId\n";
	}
}

function sendPushNotificationToCustomer($userId, $newDateString)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$notificationCategory = "";
	$notificationTitle = "Fiber Cut Alert";
	$notificationText = "Restoration work for main cable, against User ID $userId, is in progress. Services are expected to be fully restored by approximately $newDateString. We appreciate your understanding.";
	$op = 'system';
	$status = 'pending';
	$notificationType = 'INTIMATION';

	$notificationQuery = "INSERT INTO NAYATELUSER.PUSHNOTIFICATIONHISTORY (ID, USERID, NOTIFICATIONCATEGORY, NOTFICATIONTITLE, NOTFICATIONTEXT, NOTIFICATIONTIME, STATUS, DATETIME, OPERATOR, NOTIFICATIONTYPE)
           VALUES (NAYATELUSER.PUSHNOTIFICATIONHISTORY_id_seq.nextval ,:userid, :category, :title, :text, SYSDATE, :status, SYSDATE, :operator, :notificationType)";

	$notificationBindParameters = [
		':userid' => $userId,
		':category' => $notificationCategory,
		':title' => $notificationTitle,
		':text' => $notificationText,
		':operator' => $op,
		':status' => $status,
		':notificationType' => $notificationType
	];

	$notificationResult = $dbObject->execInsertUpdate($notificationQuery, $notificationBindParameters);
}

//  Sending SMS & Push Notification To Customer, Added by Hammad on 27 June 2024, End.

//  Getting employees for dropdown, Added by Azeem on 11 SEP 2024, Start:

if ($key == "GetEmployees") {
	$query = "SELECT p.EMPID, p.NAME, pr.EMPTYPE 
				  FROM personalinfo p 
				  INNER JOIN professionalinfo pr 
				  ON p.EMPID = pr.EMPID 
				  WHERE pr.EMPTYPE IN ('Interns', 'Part Time', 'Permanent', 'Contractual')";

	$employeesResult = $dbObj->Get_Array($query);
	// var_dump($employeesResult);
	// exit;
	echo json_encode($employeesResult);
}

function sendSmsToSLATelcoCustomer($userId)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	// Fetching phone numbers of the Customer:
	$phoneQuery = "SELECT LISTAGG(y.areacode || y.phoneno, ',') WITHIN GROUP (ORDER BY y.areacode, y.phoneno) AS phone_numbers
								FROM mbluser x
								JOIN customercontact y ON x.accountid = y.accountid
								WHERE x.userid = :userId AND y.phonetype LIKE '%MOBILE_IT_%'";

	$phoneBindParameters = array(":userId" => $userId);

	$phoneResult = $dbObject->execSelect($phoneQuery,  $phoneBindParameters);

	if (!empty($phoneResult) && isset($phoneResult[0]['PHONE_NUMBERS'])) {
		$phoneNumbersString = $phoneResult[0]['PHONE_NUMBERS'];
		$phoneNumbers = explode(',', $phoneNumbersString);

		$smsMsg = "Restoration work for main cable, against User ID $userId, is in progress. Services are expected to be fully restored by approximately $newDateString. We appreciate your understanding.";
		$module = 'ajax_files/ajaxEventAlertReport.php';
		$op = 'system';
		$opDept = '';
		$status = 'PENDING';
		$category = '';

		foreach ($phoneNumbers as $phoneNumber) {

			$smsQuery = "INSERT INTO NAYATELUSER.SMS_INTIMATION(ID, CELLNUMBER, MESSAGE, MODULE, OPERATOR, OPERATOR_DEPT, STATUS, DATETIME, CATEGORY, EVENT_ID)
								VALUES(NAYATELUSER.SMS_INTIMATION_ID_SEQ.nextval, :phoneNumber, :smsMsg, :module, :op, :opDept, :status, sysdate, :category, :eventid)";

			$smsBindParameters = array(":phoneNumber" => $phoneNumber, ":smsMsg" => $smsMsg, ":module" => $module, ":op" => $op, ":opDept" => $opDept, ":status" => $status, ":category" => $category, ":eventid" => $eventid);

			$smsResult = $dbObject->execInsertUpdate($smsQuery, $smsBindParameters);
		}
	} else {
		// echo "No phone numbers found for User Id $userId\n";
	}
}

function sendEscalationSMS($cluster, $seq, $ecalation_level, $message, $pushMessage)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObj = new DbClass();

	// echo '<br/>' . "Sending SMS to the employees, $ecalation_level";
	$status = 'Active';

	// Query to fetch the clusterId based on the cluster_name
	$clusterIdQuery = "SELECT ID
			FROM nayateluser.clusters
			WHERE CLUSTER_NAME = :clusterName
			AND STATUS = :status
		";

	// Bind parameters for the clusterId query
	$clusterIdParams = [
		':clusterName' => $cluster,
		':status' => $status
	];

	// Execute the query to fetch the clusterId
	$clusterIdResult = $dbObj->execSelect($clusterIdQuery, $clusterIdParams);
	// var_dump('ClusterId Result :', $clusterIdResult);

	// Check if clusterId is found
	if (!empty($clusterIdResult)) {
		$clusterId = $clusterIdResult[0]['ID'];
		// var_dump($clusterId);

		// Query the cluster_phones table to get phone numbers and POC names for escalation level "More than 500 Customers"
		$smsQuery = "SELECT PHONE_NO, POC_NAME
				FROM nayateluser.cluster_phones
				WHERE CLUSTER_ID = :clusterId
				AND ESCALATION_LEVEL = :ecalation_level
				AND STATUS = :status
			";

		// Bind parameters for the phone query
		$smsParams = [
			':clusterId' => $clusterId,
			':status' => $status,
			':ecalation_level' => $ecalation_level
		];

		// Execute the query to get phone numbers and POC names
		$smsResult = $dbObj->execSelect($smsQuery, $smsParams);

		// Check if there are any phone numbers and POC names
		if (!empty($smsResult)) {
			// var_dump('sms result: ', $smsResult);

			foreach ($smsResult as $row) {
				$phoneNumber = $row['PHONE_NO'];
				$pocName = $row['POC_NAME'];
				// var_dump('phoneNumber: ', $phoneNumber);
				// var_dump('pocName: ', $pocName);

				// Construct the SMS message
				$smsMsg = $message;
				$MODULE = 'PONDownPhase2';
				$OPERATOR_DEPT = '';
				$CATEGORY = '';

				// Prepare the SMS insert query
				$smsInsertQuery = "INSERT INTO NAYATELUSER.SMS_INTIMATION(ID, CELLNUMBER, MESSAGE, MODULE, OPERATOR, OPERATOR_DEPT, STATUS, DATETIME, CATEGORY, EVENT_ID)
					VALUES(NAYATELUSER.SMS_INTIMATION_ID_SEQ.nextval, :cellnumber, :smsMsg, :MODULE, 'system', :OPERATOR_DEPT, 'PENDING', sysdate, :CATEGORY, :EVENT_ID)";

				// Bind parameters for the SMS query
				$smsParameters = [
					':cellnumber' => $phoneNumber,
					':smsMsg' => $smsMsg,
					':MODULE' => $MODULE,
					':OPERATOR_DEPT' => $OPERATOR_DEPT,
					':CATEGORY' => $CATEGORY,
					':EVENT_ID' => $seq,
				];

				// Execute the SMS insert query
				$smsInsertResult = $dbObj->execInsertUpdate($smsInsertQuery, $smsParameters);

				// var_dump('SMSseq: ', $seq);

				// Check the response of the SMS sending
				if ($smsInsertResult) {
					// echo "</br> SMS sent successfully to $phoneNumber";
				} else {
					// echo "Failed to send SMS to $phoneNumber\n";
				}

				// Fetch the employee ID based on POC_NAME
				$employeeIdQuery = "SELECT *
						FROM professionalinfo 
						WHERE emptype='Permanent' AND (OFFICIALMOBILE1 = :phoneNumber OR OFFICIALMOBILE2 = :phoneNumber)
					";
				$employeeIdParams = [':phoneNumber' => $phoneNumber];
				$employeeIdResult = $dbObj->execSelect($employeeIdQuery, $employeeIdParams);

				// Check if employee ID is found
				if (!empty($employeeIdResult)) {
					$employeeId = $employeeIdResult[0]['EMPID'];
					// var_dump('employeeId: ', $employeeId);

					// // Send push notification
					// $pushNotificationMessage = $pushMessage;
					// $pushNotificationMessage .= '\n Fiber Cut Alert';

					$empIdsArr = array(
						// array('txt_employee_id' => "hammad.yousaf"),
						array('txt_employee_id' => "areeba.qadeer"),
						array('txt_employee_id' => "muhammad.azeem"),
						array('txt_employee_id' => "$employeeId"),
					);

					sendPushNotificationToEmployees($empIdsArr, $pushMessage, $seq);

					logPushNotification($dbObj, $employeeId, $pushMessage, $seq);
					// var_dump('Pushseq: ', $seq);
					// sendPushNotification('areeba.qadeer', $pushNotificationMessage);

					// exit;
				} else {
					// echo "</br> No employee ID found for POC name $pocName\n";
				}
			}
			// logSMSNotification($dbObj, $smsMsg, $seq);
		} else {
			// echo '</br>' . "No phone number found for escalation level: $ecalation_level, in cluster $cluster.";
		}
	} else {
		// echo "No cluster ID found for cluster $cluster\n";
	}
}

function sendEscalationSMSLog($cluster, $seq, $ecalation_level, $message, $pushMessage)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObj = new DbClass();

	// echo '<br/>' . "Sending SMS to the employees, $ecalation_level";
	$status = 'Active';

	// Query to fetch the clusterId based on the cluster_name
	$clusterIdQuery = "
        SELECT ID
        FROM nayateluser.clusters
        WHERE CLUSTER_NAME = :clusterName
        AND STATUS = :status
    ";

	// Bind parameters for the clusterId query
	$clusterIdParams = [
		':clusterName' => $cluster,
		':status' => $status
	];

	// Execute the query to fetch the clusterId
	$clusterIdResult = $dbObj->execSelect($clusterIdQuery, $clusterIdParams);
	// var_dump('ClusterId Result :', $clusterIdResult);

	// Check if clusterId is found
	if (!empty($clusterIdResult)) {
		$clusterId = $clusterIdResult[0]['ID'];
		// var_dump($clusterId);

		// Query the cluster_phones table to get phone numbers and POC names for escalation level "More than 500 Customers"
		$smsQuery = "
            SELECT PHONE_NO, POC_NAME
            FROM nayateluser.cluster_phones
            WHERE CLUSTER_ID = :clusterId
            AND ESCALATION_LEVEL = :ecalation_level
            AND STATUS = :status
        ";

		// Bind parameters for the phone query
		$smsParams = [
			':clusterId' => $clusterId,
			':status' => $status,
			':ecalation_level' => $ecalation_level
		];

		// Execute the query to get phone numbers and POC names
		$smsResult = $dbObj->execSelect($smsQuery, $smsParams);

		// Check if there are any phone numbers and POC names
		if (!empty($smsResult)) {
			// var_dump('sms result: ', $smsResult);

			foreach ($smsResult as $row) {
				$phoneNumber = $row['PHONE_NO'];
				$pocName = $row['POC_NAME'];
				// var_dump('phoneNumber: ', $phoneNumber);
				// var_dump('pocName: ', $pocName);

				// Construct the SMS message
				$smsMsg = $message;
				$MODULE = 'PONDownPhase2';
				$OPERATOR_DEPT = '';
				$CATEGORY = '';
				// var_dump('message :', $message);
				// var_dump('message :', $message);

				// Prepare the SMS insert query
				$smsInsertQuery = "INSERT INTO NAYATELUSER.SMS_INTIMATION(ID, CELLNUMBER, MESSAGE, MODULE, OPERATOR, OPERATOR_DEPT, STATUS, DATETIME, CATEGORY, EVENT_ID)
                VALUES(NAYATELUSER.SMS_INTIMATION_ID_SEQ.nextval, :cellnumber, :smsMsg, :MODULE, 'system', :OPERATOR_DEPT, 'PENDING', sysdate, :CATEGORY, :EVENT_ID)";

				// Bind parameters for the SMS query
				$smsParameters = [
					':cellnumber' => $phoneNumber,
					':smsMsg' => $smsMsg,
					':MODULE' => $MODULE,
					':OPERATOR_DEPT' => $OPERATOR_DEPT,
					':CATEGORY' => $CATEGORY,
					':EVENT_ID' => $seq,
				];

				// Execute the SMS insert query
				$smsInsertResult = $dbObj->execInsertUpdate($smsInsertQuery, $smsParameters);

				// var_dump('SMSseq: ', $seq);

				// Check the response of the SMS sending
				if ($smsInsertResult) {
					// echo "</br> SMS sent successfully to $phoneNumber";
				} else {
					// echo "Failed to send SMS to $phoneNumber\n";
				}

				// Fetch the employee ID based on POC_NAME
				$employeeIdQuery = "SELECT *
                    FROM professionalinfo 
                    WHERE emptype='Permanent' AND (OFFICIALMOBILE1 = :phoneNumber OR OFFICIALMOBILE2 = :phoneNumber)
                ";
				$employeeIdParams = [':phoneNumber' => $phoneNumber];
				$employeeIdResult = $dbObj->execSelect($employeeIdQuery, $employeeIdParams);

				// Check if employee ID is found
				if (!empty($employeeIdResult)) {
					$employeeId = $employeeIdResult[0]['EMPID'];
					// var_dump('employeeId: ', $employeeId);

					// // Send push notification
					// $pushNotificationMessage = $pushMessage;
					// $pushNotificationMessage .= '\n Fiber Cut Alert';

					$empIdsArr = array(
						// array('txt_employee_id' => "hammad.yousaf"),
						array('txt_employee_id' => "areeba.qadeer"),
						array('txt_employee_id' => "muhammad.azeem"),
						array('txt_employee_id' => "$employeeId"),
					);

					sendPushNotificationToEmployees($empIdsArr, $pushMessage, $seq);

					logPushNotification($dbObj, $employeeId, $pushMessage, $seq);
					// var_dump('Pushseq: ', $seq);
					// sendPushNotification('areeba.qadeer', $pushNotificationMessage);

					// exit;
				} else {
					// echo "</br> No employee ID found for POC name $pocName\n";
				}
			}
			logSMSNotification($dbObj, $smsMsg, $seq);
		} else {
			// echo '</br>' . "No phone number found for escalation level: $ecalation_level, in cluster $cluster.";
		}
	} else {
		// echo "No cluster ID found for cluster $cluster\n";
	}
}

function sendPushNotificationToEmployees($empIdsArr, $message, $eventid)
{
	$curl = curl_init();

	$payload = array(
		'lst_employees' => $empIdsArr,
		'ser_service_id' => '4',
		'txt_notification_title' => "Fiber Cut",
		'is_scheduled' => 'true',
		'txt_notification_description' => $message,
		'dte_scheduled_date' => date('Y-m-d H:i:s'),
		'txt_sender_id' => 'NAYAtel',
		'secret_code' => 'dVHG2YZQTw9RAt9dNeSrLzxWzPAoFgc6J5Jnr3eWzHj4kiM79xYz73FiCm5Zvdj7kqFF923vNBwCffKawGMmhNSVcbjcctfzoeocVk5fDJxsFooYiz3PQt7JZj9ZB6cz',
		'txt_notification_type' => 'Outage',
		'event_id' => $eventid,
	);

	$data = array(
		'call_type' => 'PORTAL',
		'information' => json_encode($payload),
		'operation' => 'PORTAL_SEND_NOTIFICATIONS_TO_EMPLOYESS_FROM_NAYATEL_INTERFACE'
	);

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://devapi.nayatel.com/creatives/NayatelTeam/NayatelTeamV2/NtlTeams_dev.php',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => $data,
		CURLOPT_HTTPHEADER => array(
			'Cookie: cookiesession1=678A3E19F30473980C940DE825103F5A'
		),
	));

	$response = curl_exec($curl);

	if (curl_errno($curl)) {
		$error_msg = curl_error($curl);
		// echo "\n <br> Curl error: " . $error_msg;
	}

	// echo "\n <br> Push Notification Response: " . $response;
	// var_dump('message', $message);

	curl_close($curl);
}

function generateEventId($dbObject)
{
	// Get today's date in 'Y-m-d' format
	$today = date('Y-m-d');

	// Format today's date as 'Ymd' for the event ID
	$formattedDate = date('Ymd');

	// Query to count the number of events created today based on EVENTNO
	$eventCountQuery = "
			SELECT COUNT(*) AS EVENT_COUNT	 
			FROM NAYATELUSER.EVENTLOGGERFORM 
			WHERE EVENTNO LIKE :eventNoPattern
		";

	// Fetch the count of events
	// $eventCountData = $dbObj->Get_Row($eventCountQuery);
	$eventNoPattern = "NTL-$formattedDate-%";

	// Fetch the count of events
	$paramsEventCount = [':eventNoPattern' => $eventNoPattern];
	$eventCountData = $dbObject->execSelect($eventCountQuery, $paramsEventCount);

	$eventCount = $eventCountData[0]['EVENT_COUNT'];

	// Create a new event number based on the count
	$newEventNumber = $eventCount + 1;

	// Construct the new EVENTID
	$eventId = "NTL-" . $formattedDate . "-" . $newEventNumber;

	return $eventId;
}

function logSMSNotification($dbObj, $smsMsg, $seq)
{
	$INTIMATION_STATUS = 'Sent';
	// Prepare the SMS insert query
	$smsInsertQuery = "UPDATE eventalerts SET INTIMATION_TEXT = :smsMsg , INTIMATION_STATUS = :INTIMATION_STATUS , INTIMATION_TIME = SYSDATE WHERE EVENTID = :EVENT_ID ";

	// Bind parameters for the SMS query
	$smsParameters = [
		':smsMsg' => $smsMsg,
		':INTIMATION_STATUS' => $INTIMATION_STATUS,
		':EVENT_ID' => $seq,
	];

	// Execute the SMS insert query
	$smsInsertResult = $dbObj->execInsertUpdate($smsInsertQuery, $smsParameters);
}

function logPushNotification($dbObj, $recipient, $pushMessage, $seq)
{
	$title = "PONDownPhase2";
	$status = 'Sent';
	$operator = 'system';
	// Prepare the SMS insert query
	$pushInsertQuery = "INSERT INTO nayateluser.NTLTeamsNotifications(ID,USERID, TITLE, TEXT , DATETIME , STATUS , OPERATOR , COMMENTS)
		VALUES(nayateluser.NTLTeamsNotifications_ID_SEQ.nextval, :recipient ,:title, :pushMessage, SYSDATE, :status , :operator , NULL)";

	// Bind parameters for the SMS query
	$pushParameters = [
		':recipient' => $recipient,
		':pushMessage' => $pushMessage,
		':EVENT_ID' => $seq,
		':title' => $title,
		':status' => $status,
		':operator' => $operator,
	];

	// Execute the Push insert query
	$pushInsertResult = $dbObj->execInsertUpdate($pushInsertQuery, $pushParameters);
}

function sendRestorationPushNotificationToCustomer($userId)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	$notificationCategory = "";
	$notificationTitle = "Fiber Cut Alert";
	$notificationText = "Services for User ID $userId have been restored. Please verify your services. We are sorry for the trouble";
	$op = 'system';
	$status = 'pending';
	$notificationType = 'INTIMATION';

	$notificationQuery = "INSERT INTO NAYATELUSER.PUSHNOTIFICATIONHISTORY (ID, USERID, NOTIFICATIONCATEGORY, NOTFICATIONTITLE, NOTFICATIONTEXT, NOTIFICATIONTIME, STATUS, DATETIME, OPERATOR, NOTIFICATIONTYPE)
           VALUES (NAYATELUSER.PUSHNOTIFICATIONHISTORY_id_seq.nextval ,:userid, :category, :title, :text, SYSDATE, :status, SYSDATE, :operator, :notificationType)";

	$notificationBindParameters = [
		':userid' => $userId,
		':category' => $notificationCategory,
		':title' => $notificationTitle,
		':text' => $notificationText,
		':operator' => $op,
		':status' => $status,
		':notificationType' => $notificationType
	];

	$notificationResult = $dbObject->execInsertUpdate($notificationQuery, $notificationBindParameters);
	var_dump('notificationResult', $notificationResult);
	if ($notificationResult) {
		echo "Success: Notification sent successfully to the user $userId.";
	} else {
		echo "Failed: Notification could not be sent.";
	}
}

function  sendRestorationEmailNotificationToCustomer($userId)
{
	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/DbClassBind.php";
	$dbObject = new DbClass();

	include_once "/var/www/html/ncrm/views/crmViews/nayatelCrm/include_files/sendMailGeneric.php";
	$mailSender = new mailSender();

	// var_dump('userId :', $userId);

	$getEmailQuery = "SELECT wm_concat(y.emailaddress) AS email_addresses
    FROM mbluser x
    JOIN customeremailaddress y ON x.accountid = y.accountid
    WHERE x.userid = :user_id AND y.emailtype LIKE '%_IT_%'";

	$getEmailParameters = [
		':user_id' => $userId
	];

	$emailResult = $dbObject->execSelect($getEmailQuery, $getEmailParameters);
	$emailAddresses = $emailResult[0]['EMAIL_ADDRESSES'];
	// var_dump('emailAddresses :', $emailAddresses);

	// Check if there are any email addresses
	if (!empty($emailAddresses)) {
		// Split the concatenated email addresses into an array
		$emailArray = explode(',', $emailAddresses);
		// var_dump('emailArray :', $emailArray);

		// Send email to each retrieved email address
		$cc = '';
		$subject = 'Services Unavailable Due To Main Cable Damage';
		// Construct the email message using the required format
		$emailMessage = "<b>Dear Customer,</b><br><br>";
		$emailMessage .= "Services for User ID $userId have been restored. Please verify your services. We are sorry for the trouble\n\n";
		// $emailMessage .= "<br><br><b>Manager</b><br>NAYAtel Technical Assistance Center<br>Phone: 051-111 11 44 44<br>www.nayatel.com";
		$moduleName = 'PONDownIntimationsAndEventLogging';
		// Iterate over each email address and send the email
		foreach ($emailArray as $mailTo) {
			// Trim any whitespace from the email address
			$mailTo = trim($mailTo);
			// $mailTo= "momna.hassan@nayatel.com,muhammad.azeem@nayatel.com,areeba.qadeer@nayatel.com";

			// Send email using the sendMailGeneral function
			$mailResp = $mailSender->sendMailGeneral(
				'do-not-reply@nayatel.com',
				$mailTo,
				$cc,
				$subject,
				$emailMessage,
				'Nayatel',
				'', // Attachment
				'', // BCC
				'', // ReplyTo
				'', // SendImage
				'', // ImagePlacement
				$moduleName, // ModuleName
				'' // AttachmentName
			);
			echo "</br>" . 'Mail Response for ' . $mailTo . ":" . $mailResp;
		}
	} else {
		echo "</br>" . "No email addresses found for user ID: $userId";
	}
}
//  Getting employees for dropdown, Added by Azeem on 11 SEP 2024, End:
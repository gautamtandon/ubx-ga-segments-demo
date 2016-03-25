<?php

$ga_appname = "";
$ga_p12_filepath = "";
$ga_devacct = "";

$ubx_es_endpoint_id = 0;
$ubx_es_endpoint_key = "";
$ubx_shiro_key = ""

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/google-api-php-client/src/');
include_once "Google/Client.php";

$db = new SQLite3('es_ga_sqlitedb.db');
$db->query('create table if not exists ga_segments(id integer primary key, name varchar(1024), definition varchar(1024))');

function get_ga_segments() {
	global $ga_appname, $ga_p12_filepath, $ga_devacct;

	$gclient = new Google_Client();
	$gclient->setApplicationName($ga_appname);
	$gkey = file_get_contents($ga_p12_filepath);
	$gcred = new Google_Auth_AssertionCredentials(
		"$ga_devacct",
		array('https://www.googleapis.com/auth/analytics', 'https://www.googleapis.com/auth/analytics.edit', 'https://www.googleapis.com/auth/analytics.readonly'),
		$gkey
	);
	$gclient->setAssertionCredentials($gcred);
	if($gclient->getAuth()->isAccessTokenExpired()) {
		$gclient->getAuth()->refreshTokenWithAssertion($gcred);
	}
	$ga_anal_svc = new Google_Service_Analytics($gclient);

	return $ga_anal_svc->management_segments->listManagementSegments();
}

function ubx_ellipsis_ga_segment_exists($id) {
	global $ubx_es_endpoint_id, $ubx_es_endpoint_key;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/endpoint/".$ubx_es_endpoint_id."/segments/".$id);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$ubx_es_endpoint_key));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$resp = curl_exec($ch);
	return json_decode($resp, true);
}

function ubx_ellipsis_ga_del_segment($id) {
	global $db, $ubx_es_endpoint_id, $ubx_shiro_key;
	$db->query("delete from ga_segments where id = " . $id);
        
	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/endpoint/".$ubx_es_endpoint_id."/segments/".$id);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$ubx_shiro_key));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
}

function ubx_ellipsis_ga_add_segment($name, $definition) {
	global $db, $ubx_es_endpoint_id, $ubx_es_endpoint_key;

	$db->query("insert into ga_segments (name, definition) values ('".$name."', '".$definition."')");
	$res = $db->query("select * from ga_segments where name = '".$name."'");
	$id = -1;
	while ($row = $res->fetchArray()) {
		$id = $row['id'];
	}

	$data = array(array(
		"id" => $id, 
		"name" => $name, 
		"description" => $definition, 
		"segment_attributes" => array(
						array("name" => "email", "type" => "string")
					)
	));
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/endpoint/".$ubx_es_endpoint_id."/segments");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$ubx_es_endpoint_key, "Content-Type: application/json"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	$resp = curl_exec($ch);
}

function ubx_ellipsis_get_jobs() {
	global $ubx_es_endpoint_id, $ubx_es_endpoint_key;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/jobs/SEGMENT_EXPORT?endpointId=".$ubx_es_endpoint_id."&status=WAITING_TO_RECIEVE_DATA");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$ubx_es_endpoint_key, "Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$resp = curl_exec($ch);
	return json_decode($resp, true);
}

function ubx_ellipsis_push_data($job) {
	global $ubx_shiro_key;
	//echo json_encode($job) . "\n\n";
	$data = array(
		"endpointID" => $job['producerEndpointID'],
		"segmentID" => $job['producerSegmentID'],
		"destinationEndpointID" => $job['destinationEndpointID'],
		"destinationSegmentName" => $job['destinationSegmentName'],
		"attributeMappings" => $job['attributeMappings'],
		"identityMappings" => $job['identityMappings']
	);
	//echo json_encode($data) . "\n\n";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/jobs/".$job['jobId']."/data");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$ubx_shiro_key, "Content-Type: application/json"));
	//curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer c950658d-6c2e-4c71-8585-f0b87c75ddb6", "Content-Type: application/json"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	//curl_setopt($ch, CURLOPT_VERBOSE, 1);
	//curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	$resp = curl_exec($ch);

//$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//$header = substr($resp, 0, $header_size);
//$resp = substr($resp, $header_size);

	//echo $resp;
}

echo "Getting latest segments from Google Analytics...\n";
$ga_segments = get_ga_segments();

$res = $db->query("select * from ga_segments");
$ubx_seg_to_del = array();
while ($row = $res->fetchArray()) {
	$found = false;
	foreach ($ga_segments as $seg) {
		if ($seg['name'] == $row['name']) {
			$found = true;
		}
	}
	if (!$found) {
		// this segment does not exist in GA anymore
		// so I should delete it from UBX
		array_push($ubx_seg_to_del, $row['id']);
	}
}
foreach ($ubx_seg_to_del as $seg_id) {
	echo "Removing segment id '".$seg_id."' from UBX...\n";
	ubx_ellipsis_ga_del_segment($seg_id);
}

foreach ($ga_segments as $ga_seg) {
	if ($ga_seg['type'] == "CUSTOM") {
		$res = $db->query("select * from ga_segments where name = '".$ga_seg['name']."'");
		$exists = false;
		while ($row = $res->fetchArray()) {
			$exists = true;
		}
		if (!$exists) {
			// this is a newly added segment in GA
			// so I should add it to UBX
			echo "Adding segment '".$ga_seg['name']."' into UBX...\n";
			ubx_ellipsis_ga_add_segment($ga_seg['name'], $ga_seg['definition']);
		}
	}
}

echo "Getting pending jobs...";
$jobs_json = ubx_ellipsis_get_jobs();
foreach ($jobs_json as $job) {
	ubx_ellipsis_push_data($job);
}
?>

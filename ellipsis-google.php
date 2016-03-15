<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/google-api-php-client/src/');
include_once "Google/Client.php";

$db = new SQLite3('es_ga_sqlitedb.db');
$db->query('create table if not exists ga_segments(id integer primary key, name varchar(1024), definition varchar(1024))');

function get_ga_segments() {
	$gclient = new Google_Client();
	$gclient->setApplicationName("My Application");
	$gkey = file_get_contents("ellipsis-4777e0661927.p12");
	$gcred = new Google_Auth_AssertionCredentials(
		"268295878612-lock95d09vrpkd5ehffpb16rusa7v7jv@developer.gserviceaccount.com",
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
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/endpoint/12481/segments/".$id);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer c950658d-6c2e-4c71-8585-f0b87c75ddb6"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	$resp = curl_exec($ch);
	return json_decode($resp, true);
}

function ubx_ellipsis_ga_del_segment($id) {
	global $db;
	$db->query("delete from ga_segments where id = " . $id);
        
	$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/endpoint/12481/segments/".$id);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer shiro-062c516b-1bce-4503-a4ef-80e37696bf25"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
}

function ubx_ellipsis_ga_add_segment($name, $definition) {
	global $db;
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
	curl_setopt($ch, CURLOPT_URL, "https://api-01.ubx.ibmmarketingcloud.com/v1/endpoint/12481/segments");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer c950658d-6c2e-4c71-8585-f0b87c75ddb6", "Content-Type: application/json"));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	$resp = curl_exec($ch);
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
?>

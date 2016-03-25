## IBM UBX Google Analytics Segments Connector
This connectors picks up custom segments created in Google Analytics and pushes them over to UBX. The segments can then be mapped to the already existing segments in IBM Engage to share segments data between Google Analytics and IBM Engage. A typical use case would be to do targeted email marketing based on website activity of logged in (or known) users.

### How it works
In "IBM language", this is a "push type segment" connector. Which means, it pushes data to UBX instead of waiting for UBX to make a call. Because of that, you can simply run this connector via command line instead of having to setup a HTTP endpoint where IBM UBX could call. Having said that, based on your particular situation, you might need a "pull type" connector, which is going to be pretty similar except for the fact that instead of you running the connector every so often, you'd wait UBX to make a call and do the same stuff.

#### This is how this connector works at a high level:
1. Pulls custom segments from Google Analytics.
2. Figures out which segments need to be pushed to UBX (i.e. ignore the ones that have already been pushed).
3. Pushes the new segments to IBM UBX.
4. Deletes the segments from IBM UBX that don't exist in Google Analytics anymore.

### Setup

#### Pre-requisites
1. Clear understanding of IBM UBX
2. Clear understanding of Google Analytics APIs

#### Steps
1. Create a Google Analytics App on Google Developer Console.
2. Create a 'Segments Push Type' Custom Endpoint in UBX. Keep in mind if you are creating a custom endpoint for the first time, you might need to get your company provisioned into the IBM UBX system.
3. Edit the "ellipsis-google.php" file and set the following global variables with the right values.

	$ga_appname = "";
	$ga_p12_filepath = "";
	$ga_devacct = "";

	$ubx_es_endpoint_id = 0;
	$ubx_es_endpoint_key = "";
	$ubx_shiro_key = ""

### Usage
Simply configure the script 'ellipsis-google.php' to be called every so often - perhaps as a cron job, and you are all set! Now create a segment in GA and go to your UBX console and refresh the segments, and you'll see it there!

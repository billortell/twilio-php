<?php
    // Include the PHP TwilioRest library 
    require "twilio-async.php";
    
    // Twilio REST API version 
    $ApiVersion = "2008-08-01";
    
    // Set our AccountSid and AuthToken 
    $AccountSid = "ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
    $AuthToken = "YYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY";
    
    // Outgoing Caller ID you have previously validated with Twilio 
    $CallerID = 'NNNNNNNNNN';
    
    // Instantiate a new Twilio Rest Client 
    $client = new TwilioRestClientAsync($AccountSid, $AuthToken);
    $response = array();
    
    // ========================================================================
    // 1. Initiate a new outbound call to 415-555-1212
    //    uses a HTTP POST
    $data = array(
    	"Caller" => $CallerID, 	      // Outgoing Caller ID
    	"Called" => "415-555-1212",	  // The phone number you wish to dial
    	"Url" => "http://demo.twilio.com/welcome"
    );
    
    $response[] = $client->request("/$ApiVersion/Accounts/$AccountSid/Calls", 
       "POST", $data); 
    
    // ========================================================================
    // 2. Get a list of recent calls 
    // uses a HTTP GET
    $response[] = $client->request("/$ApiVersion/Accounts/$AccountSid/Calls", 
        "GET");
    
    // ========================================================================
    // 3. Get Recent Developer Notifications
    // uses a HTTP GET
    $response[] = $client->request("/$ApiVersion/Accounts/$AccountSid/Notifications");
    
    // ========================================================================
    // 4. Get Recordings for a certain Call
    // uses a HTTP GET
    
    $callSid = "CA123456789123456789";
    $response[] = $client->request("/$ApiVersion/Accounts/$AccountSid/Recordings",
        "GET", array("CallSid" => $callSid));
    
    // ========================================================================
    // 5. Delete a Recording 
    // uses a HTTP DELETE
    $recordingSid = "RE12345678901234567890";
    $response[] = $client->request("/$ApiVersion/Accounts/$AccountSid/Recordings/$recordingSid", "DELETE");

    // ========================================================================
    // Asynchronously fetch the results
    // check response for success or error
    
    // 1
    if($response[0]->IsError)
    	echo "Error starting phone call: {$response[0]->ErrorMessage}\n";
    else
    	echo "Started call: {$response[0]->ResponseXml->Call->Sid}\n";

    // 2
    if($response[1]->IsError)
    	echo "Error fetching recent calls: {$response[1]->ErrorMessage}";
    else {
    	foreach($response[1]->ResponseXml->Calls->Call AS $call) {
    		echo "Call from {$call->Caller} to {$call->Called}";
    		echo " at {$call->StartTime} of length: {$call->Duration}\n";
      }
    }

    // 3
    if($response[2]->IsError)
    	echo "Error fetching recent notifications: {$response[2]->ErrorMessage}";
    else {
    	foreach($response[2]->ResponseXml->Notifications->Notification AS $notification) {
    		echo "Log entry (level {$notification->Log}) on ";
    		echo "{$notification->MessageDate}: {$notification->MessageText}\n";
      }
    }

    // 4
    if($response[3]->IsError){
    	echo "Error fetching recordings for call $callSid:";
    	echo " {$response->ErrorMessage}";
    } else {
    	
    	// iterate over recordings found
    	foreach($response[3]->ResponseXml->Recordings->Recording AS $recording) {
    		echo "Recording of duration {$recording->Duration} seconds made ";
    		echo "on:{$recording->DateCreated} at URL: ";
    		echo "/Accounts/$AccountSid/Recordings/{$recording->Sid}\n";
      }
    }

    // 5
    if($response[4]->IsError)
    	echo "Error deleting recording $recordingSid: {$response[4]->ErrorMessage}\n";
    else
    	echo "Successfully deleted recording $recordingSid\n";
    ?>

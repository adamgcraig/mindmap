<?php
//SUMMARY: stores edit history to a file, returns it to the client
//reminder: @ suppresses error output from an expression.
//designed to work with EventSource JavaScript API based on 
//http://www.htmlgoodies.com/beyond/reference/receive-updates-from-the-server-using-the-eventsource.html
require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/exit_on_bad_node_name.php');

//Check for the view name.
if( isset($_REQUEST[VIEW]) ) {
    $view = $_REQUEST[VIEW];
}
else {
    exit_with_error_json("The request did not contain a view name.");
}

//We hold views to the same naming conventions as we do nodes.
exit_on_bad_node_name($view);

//Check for the edit count.
if( isset($_REQUEST[UPDATE_COUNT]) ) {
    $update_count = $_REQUEST[UPDATE_COUNT];
}
else {
    $update_count = 0;
}

//Check for updates from the user.
if( isset($_REQUEST[UPDATES]) ) {
    echo("updates: ".$_REQUEST[UPDATES]."\n");
    $updates = @json_decode($_REQUEST[UPDATES],TRUE);//TRUE->force to associative array
    if($updates == FALSE) {
        exit_with_error_json( "Attempt to parse updates ".$_REQUEST[UPDATES]." failed: ".json_last_error() );
    }
    //For some reason, json_decode would not work unless we wrapped the numeric array inside an associative one.
    if( array_key_exists(UPDATES, $updates) ) {
        $updates = $updates[UPDATES];
    }
    else {
        exit_with_error_json( "Updates is missing key ".UPDATES.": ".$_REQUEST[UPDATES] );
    }
}
else {
    $updates = array();
}

//Make certain both edits and updates have numeric keys so that the new array will have the proper numbering.
//json_parse should do this if both were formatted correctly.
//$updates_keys = array_keys($updates);
foreach($updates as $u_key => $u_value) {
    if( !is_int($u_key) ) {
        exit_with_error_json( "Updates array has non-numeric key ".$u_key."." );
    }
}//end of check that updates has numeric keys.

//add the current timestamp
$current_timestamp = time();
for($update_index = 0; $update_index < count($updates); $update_index++) {
    $updates[$update_index][TIMESTAMP] = $current_timestamp;
}

clearstatcache();//Do not let it cache the existence, length, or last modified time.
$edit_history_path = EDIT_HISTORY_PATH.$view;
$edit_file_exists = is_file($edit_history_path);

//c (open for reading, create if not exists) 
//+(open for writing too) 
//b(Do not translate new line characters between Windows and Unix conventions.)
$edit_history_file = @fopen($edit_history_path,'c+b');
if($edit_history_file === FALSE) {
    $fopen_error = error_get_last();
    exit_with_error_json( "Attempt to open file ".$edit_history_path." failed: ".$fopen_error['message'] );
}

//If we did not lock the file and another client made the request between the time we read in the old updates
//and the time we wrote in the new updates, we would probably write our updates first, and then the other
//client would write its updates, overwriting the ones we wrote.
//Locking forces the other client to wait until we have both read the previous edits and written our own.
//We can then get the other client's edits on the next update request.
if( @flock($edit_history_file, LOCK_EX) === FALSE ) {
    $flock_error = error_get_last();
    exit_with_error_json( "Attempt to lock file ".$edit_history_path." failed: ".$flock_error['message'] );
}


//If the view exists, the file length should not be 0, as it should contain an empty JSON-formatted updates array.
//On the other hand, for some reason, file-size sometimes erroneously reports 0-size files.
//$old_file_length = filesize($edit_history_path);
//if( $old_file_length <= 0 ) {
//    exit_with_error_json( "Edit history file ".$edit_history_path." should not be empty." );
//}

//$edits_string = @fread( $edit_history_file, $old_file_length );
$edits_string = file_get_contents($edit_history_path);
if($edits_string === FALSE) {
    $fread_error = error_get_last();
    exit_with_error_json( "Attempt to read file ".$edit_history_path." failed: ".$fread_error['message'] );
}
$edits = json_decode($edits_string,TRUE);//TRUE->force to associative array
if($edits === FALSE) {
    exit_with_error_json( "Attempt to parse file ".$edit_history_path." failed: ".json_last_error() );
}
//For some reason, json_decode would not work unless we wrapped the numeric array inside an associative one.
if( array_key_exists(UPDATES, $edits) ) {
    $edits = $edits[UPDATES];
}
else {
    exit_with_error_json( "Edit history file is incorrectly formatted, missing key ".UPDATES."." );
}
//echo("old edits: ".$edits_string."\n");

$edits = array_merge($edits,$updates);

//Assign each edit its index+1 in the complete array as a unique identifier.
//Use +1 so that, if the client has editCount == 0, it will know to get edit 1.
for($edit_index = 0; $edit_index < count($edits); $edit_index++) {
    $edits[$edit_index][HISTORY_INDEX] = $edit_index+1;
}

$edits_assoc = array();
$edits_assoc[UPDATES] = $edits;
$edits_string = json_encode($edits_assoc);
if($edits_string === FALSE) {
    exit_with_error_json( "Attempt to serialize updated edit history failed." );
}
//echo("merged edits: ".$edits_string."\n");

//We have to rewind to the beginning of the file before we can erase the old file contents with ftruncate.
if( rewind($edit_history_file) === FALSE ) {
    $rewind_error = error_get_last();
    exit_with_error_json( "Attempt to rewind to beginning of file ".$edit_history_path." failed: ".$rewind_error['message'] );
}

if( ftruncate($edit_history_file, 0) === FALSE ) {
    $ftrunc_error = error_get_last();
    exit_with_error_json( "Attempt to erase old contents of file ".$edit_history_path." failed: ".$ftrunc_error['message'] );
}

if( fwrite($edit_history_file,$edits_string) == FALSE ) {
    $fwrite_error = error_get_last();
    exit_with_error_json( "Attempt to write to file ".$edit_history_path." failed: ".$fwrite_error['message'] );
}

if( flock($edit_history_file, LOCK_UN) === FALSE ) {
    $funlock_error = error_get_last();
    exit_with_error_json( "Attempt to unlock file ".$edit_history_path." failed: ".$funlock_error['message'] );
}

if( fclose($edit_history_file) === FALSE) {
    $fclose_error = error_get_last();
    exit_with_error_json( "Attempt to close file ".$edit_history_path." failed: ".$fclose_error['message'] );
}

//If the client has received 0 edits, send all of them.
//                        ...1 edits, send all but the first (index 0).
//                        ...2 edits, send all but the first, and second (index 0 and index 1).
//                        ...3 edits, send all but the first, second, and third (index 0, index 1, index 2).
$new_edits_numeric = array_slice($edits,$update_count);
$new_edits_assoc = array();
$new_edits_assoc[UPDATES] = $new_edits_numeric;
$new_edits_string = json_encode( $new_edits_assoc );
if($new_edits_string === FALSE) {
    exit_with_error_json( "Attempt to serialize new edits failed: ".json_last_error() );
}
//echo("new edits: ".$new_edits_string."\n");
echo($new_edits_string);

//This code was originally meant to alert any sendmessageonupdate.php instances that this instance has made changes.
//However, msg_receive failes with error code 7.
//For now, we will just have each sendmessageonupdate.php instance, 
//1. check the last modification time,
//2. if the last modification time is more recent than the previously recorded value,
//3. a. Read in the file and send any updates.
//   or, if not,
//3. b. return to step 1.
//$new_update_count = count($edits);
////Post a message so that any scripts streaming updates know to get the new updates.
//$listener_id_queue_key = ftok(UPDATE_QUEUE_PATH.$view.UPDATE_QUEUE_SUFFIX, UPDATE_LISTENER_PROJECT_IDENTIFIER);
//$listener_id_queue = msg_get_queue($listener_id_queue_key);
//$update_count_queue_key = ftok(UPDATE_QUEUE_PATH.$view.UPDATE_QUEUE_SUFFIX, UPDATE_COUNT_PROJECT_IDENTIFIER);
//$update_count_queue = msg_get_queue($update_count_queue_key);
////Use this ID as the message type for which to listen so we only get messages intended for this instance:
//$message_listener_id = NULL;
//$error_code = 0;
//$desiredmsgtype = -1*($new_update_count-1);
//
//do {//start of loop to send new update count to all instances of sendmessageonupdate.php currently listening
//    
//    //Get any messages from listeners with current update counts less than our new update count.
//    //Each instance of sendmessageonupdate.php posts a message before listening for new update counts.
//    //Its own update_count is the message type, so we only look for messages of with type less than the new update count.
//    //The content of the message is the message type by which to reply to the instance.
//    //It should not need unserializing.
//    //If there are no listening instances with lower update counts, break from the loop and exit.
//    //                          queue               desiredmsgtype   msgtype                 maxsize                  message              unserialize flags      errorcode
//    
//    $msg_return = @msg_receive($listener_id_queue, $desiredmsgtype, $message_type_received, UPDATE_MESSAGE_MAX_SIZE, $message_listener_id, FALSE, MSG_IPC_NOWAIT, $error_code);
//    if( $msg_return === MSG_ENOMSG) {
//        break;
//    }
//    elseif( $msg_return === FALSE ) {
//        exit_with_error_json( "Attempt to receive message of ID of sendmessageonupdate.php instance failed: code ".$error_code );
//    }
//    
//    //Send the current update count to all the listening instances of sendmessageonupdate.php.
//    //We should not need to serialize an integer.
//    //It is unlikely we would need to block, but block just to make certain it gets sent.
//    //            queue                msgtype               message           serialize blocking errorcode
//    if( msg_send($update_count_queue, $message_listener_id, $new_update_count, FALSE, TRUE, $error_code) === FALSE ) {
//        exit_with_error_json( "Attempt to post message listener ID ".$message_listener_id." failed: code ".$error_code );
//    }
//    
//}while($msg_return !== MSG_ENOMSG);// end of loop through messages
?>
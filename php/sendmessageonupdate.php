<?php
//SUMMARY: stores edit history to a file, returns it to the client
//reminder: @ suppresses error output from an expression.
//designed to work with EventSource JavaScript API based on 
//https://developer.mozilla.org/en-US/docs/Server-sent_events/Using_server-sent_events

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/exit_on_bad_node_name.php');

header("Content-Type: text/event-stream\n\n");
header("Cache-Control: no-cache\n\n");

//Check for the view name.
if( isset($_REQUEST[VIEW]) ) {
    $view = $_REQUEST[VIEW];
}
else {
    exit_with_error_json("The request did not contain a view name.");
}

//We hold views to the same naming conventions as we do nodes.
exit_on_bad_node_name($view);
$edit_history_path = EDIT_HISTORY_PATH.$view;

//Check for the edit count.
if( isset($_REQUEST[UPDATE_COUNT]) ) {
    $update_count = $_REQUEST[UPDATE_COUNT];
}
else {
    $update_count = 0;
}

//receiveupdate.php does not seem to be able to read messages from the queue for some reason.
//$listener_id_queue_key = ftok(UPDATE_QUEUE_PATH.$view.UPDATE_QUEUE_SUFFIX, UPDATE_LISTENER_PROJECT_IDENTIFIER);
//$listener_id_queue = msg_get_queue($listener_id_queue_key);
//$update_count_queue_key = ftok(UPDATE_QUEUE_PATH.$view.UPDATE_QUEUE_SUFFIX, UPDATE_COUNT_PROJECT_IDENTIFIER);
//$update_count_queue = msg_get_queue($update_count_queue_key);
////Use this ID as the message type for which to listen so we only get messages intended for this instance:
//$message_listener_id = mt_rand( UPDATE_LISTENER_ID_MESSAGE_TYPE+1, mt_getrandmax() );
//$message_type_received = NULL;
//$error_code = 0;
//$new_update_count = NULL;

//For now, we will just have each sendmessageonupdate.php instance, 
//1. check the last modification time,
//2. if the last modification time is more recent than the previously recorded value,
//3. a. Read in the file and send any updates.
//   or, if not,
//3. b. return to step 1.
$old_last_modified_time = 0;//On the first iteration, send any pre-existing edits.
$last_message_sent_time = 0;//Use this to keep track of when we should send keepalives.
while(1) {
    
    $should_send = FALSE;//Assume we should not send until we find that one of the conditions where we should send is true.
    /*
    //c (open for reading, create if not exists) 
    //+(open for writing too) 
    //b(Do not translate new line characters between Windows and Unix conventions.)
    $edit_history_file = @fopen($edit_history_path,'c+b');
    if($edit_history_file === FALSE) {
        $fopen_error = error_get_last();
        exit_with_error_json( "Attempt to open file ".$edit_history_path." failed: ".$fopen_error['message'] );
    }

    //We obtain a lock first just to make sure no other script tries to write to the file while we are reading from it.
    //In particular, we want to make sure that nothing modifies the file between when we get the last-modified date 
    //and when we get the file contents. 
    if( @flock($edit_history_file, LOCK_EX) === FALSE ) {
        $flock_error = error_get_last();
        exit_with_error_json( "Attempt to lock file ".$edit_history_path." failed: ".$flock_error['message'] );
    }
    */    
    clearstatcache();//Do not let it cache the existence, length, or last modified time.
    $edit_file_exists = is_file($edit_history_path);
    if( $edit_file_exists ) {
        $file_length = filesize($edit_history_path);
    }
    else {
        $file_length = 0;
    }
    $has_edits = $file_length > 0;
    if($has_edits) {
        $new_last_modified_time = filemtime($edit_history_path);
    }
    else {
        $new_last_modified_time = 0;
    }
    $has_new_edits = $new_last_modified_time > $old_last_modified_time;
    
    if($has_new_edits) {
        
        $edits_string = @file_get_contents( $edit_history_path );
        //$edits_string = @fread( $edit_history_file, $file_length );
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
        
        $new_update_count = count($edits);
        //If there are more updates we have not yet sent, send them.
        //There should be, since the file was modified, but check just in case some other process somehow modified the file.
        if($new_update_count > $update_count) {
            
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
            
            //Set the retry "The reconnection time to use when attempting to send the event."
            echo("retry: 1000\n");//milliseconds 
            //A stream broadcast must begin with "data:".
            echo("data: ".$new_edits_string);
            //A stream broadcast must end with an empty line.
            $should_send = TRUE;//We now have content to send.
            
            //Set update_count so we do not send the same updates again.
            $update_count = $new_update_count;
            
        }//end of if we have updates to send
    
    }//end of if the file has been modified since we last checked
    
    //If we still have no content to send, check whether it is time to send a keep-alive.
    if(!$should_send) {
        $should_send = ( time() - $last_message_sent_time ) >= KEEPALIVE_INTERVAL;
        if($should_send) {
            echo(":");//colon stands for "keepalive"
            //sleep(1);//Wait a second so that instances where the file is not being modified do not use up too many processor cycles.
        }
    }
    
    if($should_send) {
        echo("\n\n");
        ob_flush();
        flush();
        $last_message_sent_time = time();
    }
    /*
    if( flock($edit_history_file, LOCK_UN) === FALSE ) {
        $funlock_error = error_get_last();
        exit_with_error_json( "Attempt to unlock file ".$edit_history_path." failed: ".$funlock_error['message'] );
    }
    
    if( fclose($edit_history_file) === FALSE) {
        $fclose_error = error_get_last();
        exit_with_error_json( "Attempt to close file ".$edit_history_path." failed: ".$fclose_error['message'] );
    }
    */
    //Store the last modified time for the next iteration.
    $old_last_modified_time = $new_last_modified_time;
    
    ////Put this instance's listener ID in the queue so that any receiveupdates.php instance will know to put in a message for it.
    ////Set the message type to the current update count so that only instances of receiveupdate.php with higher new update counts will receive it.
    ////We should not need to serialize an integer.
    ////It is unlikely we would need to block, but block just to make certain it gets sent.
    ////            queue               msgtype       message               serialize blocking errorcode
    //if( msg_send($listener_id_queue, $update_count, $message_listener_id, FALSE, TRUE, $error_code) === FALSE ) {
    //    exit_with_error_json( "Attempt to post message listener ID ".$message_listener_id." failed: code ".$error_code );
    //}
    
    ////Listen for any updates sent to this instance.
    ////We should receive an integer representing the new update count for this view.
    ////It should not need unserializing.
    ////               queue                desiredmsgtype        msgtype                maxsize                  message            unserialize flags errorcode
    //if( msg_receive($update_count_queue, $message_listener_id, $message_type_received, UPDATE_MESSAGE_MAX_SIZE, $new_update_count, FALSE, 0, $error_code) === FALSE ) {
    //    exit_with_error_json( "Attempt to receive message by listener ID ".$message_listener_id." failed: code ".$error_code );
    //}
    
}//end of main loop to check for new updates and send them

?>
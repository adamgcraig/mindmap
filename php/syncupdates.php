<?php
//SUMMARY: stores edit history to a file, returns it to the client
//reminder: @ suppresses error output from an expression.
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
    $updates = @json_decode($_REQUEST[UPDATES],TRUE);//TRUE->force to associative array
    if($updates == FALSE) {
        exit_with_error_json( "Attempt to parse updates ".$_REQUEST[UPDATES]." failed: ".json_last_error() );
    }
}
else {
    $updates = array();
}

//For some reason, json_decode would not work unless we wrapped the numeric array inside an associative one.
if( array_key_exists(UPDATES, $updates) ) {
    $updates = $updates[UPDATES];
}
else {
    exit_with_error_json( "Updates is missing key ".UPDATES."." );
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

$edit_history_path = EDIT_HISTORY_PATH.$view;
$edit_file_exists = is_file($edit_history_path);
if( $edit_file_exists ) {
    $old_file_length = filesize($edit_history_path);
}
else {
    $old_file_length = 0;
}
$has_preexisting_edits = $edit_file_exists && ($old_file_length > 0);

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

if( $has_preexisting_edits ) {
    
    $edits_string = @fread( $edit_history_file, $old_file_length );
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
    
}
else {
    
    $edits = array();
    //Give it permissions that will allow the script to access the file in the future.
    if( chmod($edit_history_path, FILE_PERMISSIONS) === FALSE ) {
        $chmod_error = error_get_last();
        exit_with_error_json("Setting of permissions of \"".$edit_history_path."\" failed: ".$chmod_error['message']);
    }
    
}

$edits = array_merge($edits,$updates);

$edits_assoc = array();
$edits_assoc[UPDATES] = $edits;
$edits_string = json_encode($edits_assoc);
if($edits_string === FALSE) {
    exit_with_error_json( "Attempt to serialize updated edit history failed." );
}

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

exit($new_edits_string);
?>
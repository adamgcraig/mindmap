<?php

//SUMMARY: Given an HTTP request with the name of a view, deletes that view.

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/exit_on_bad_node_name.php');

//Check that the request includes a name.
if( !isset($_REQUEST[NAME]) ) {
    exit_with_error_json("The request did not contain a view name.");
}//end of if no name provided

$name = $_REQUEST[NAME];

//We hold views to the same naming conventions as we do nodes.
exit_on_bad_node_name($name);

$view_path = VIEW_PATH.$name.VIEW_FILE_TYPE;

if( !unlink($view_path) ) {
    $unlink_error = error_get_last();
    exit_with_error_json("Deletion of file \"".$view_path."\" failed: ".$unlink_error['message']);
}

exit(  json_encode( array('success' => TRUE) )  );
?>
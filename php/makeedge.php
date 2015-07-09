<?php

//SUMMARY: Given an HTTP request with the names of the source, label, and destination nodes,
//this PHP script creates a directory with the same name as label
//and a symbolic link to the destination directory in that directory.
//It checks beforehand that all three directories exist as subdirectories of the nodes directory,
//and that the link does not already exist.

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/get_node_or_exit.php');

$source = get_node_or_exit(SOURCE);
$label = get_node_or_exit(LABEL);
$destination = get_node_or_exit(DESTINATION);

//Since this is a relative symbolic link, we have to specify the destination relative to the location of the link.
//In this case, that means going up two levels, 
//out of the label directory and out of the directory folder, 
//to the nodes directory then down to the destination directory.
$target_path = '../../'.$destination;
$label_path = NODE_PATH.$source.PATH_SEPARATOR.$label;
$link_path = $label_path.PATH_SEPARATOR.$destination;

if( is_link($link_path) ) {
    exit_with_error_json("The link \"".$link_path."\"->\"".$target_path."\" already exists.");
}

if( !is_dir($label_path) ) {
    if( mkdir($label_path,DIRECTORY_PERMISSIONS,true) === FALSE ) {
        $mkdir_error = error_get_last();
        exit_with_error_json("Creation of directory \"".$label_path."\" failed:".$mkdir_error['message']);
    }//end of if we failed to make the label sub-directory
}//end of if the label sub-directory does not exist

if( !symlink($target_path,$link_path) ) {
    $link_error = error_get_last();
    exit_with_error_json("Creation of link \"".$link_path."\"->\"".$target_path."\" failed: ".$link_error['message']);
}

exit(  json_encode( array('edge_href' => $link_path) )  );
?>
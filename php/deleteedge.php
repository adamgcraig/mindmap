<?php

//SUMMARY: Given an HTTP request with the names of the source, label, and destination nodes,
//this PHP script checks that a directory with the same name as label
//and a symbolic link to the destination directory in that directory exists
//and, if so, removes the link. If the directory is then empty, it removes the directory.

require_once('includes/consts.php');
require_once('includes/get_node_or_exit.php');
require_once('includes/delete_edges_with.php');

$source = get_node_or_exit(SOURCE);
$label = get_node_or_exit(LABEL);
$destination = get_node_or_exit(DESTINATION);

delete_edges_with($source,$label,$destination);

exit(  json_encode( array('success' => TRUE) )  );

?>
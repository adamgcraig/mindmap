<?php

//SUMMARY: Given an HTTP request with the names of the source, label, and destination nodes,
//this PHP script checks that a directory with the same name as label
//and a symbolic link to the destination directory in that directory exists
//and, if so, removes the link. If the directory is then empty, it removes the directory.

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/get_node_or_exit.php');
require_once('includes/delete_edges_with.php');

$name = get_node_or_exit(NAME);

//Delete all edges in which this node has any role.
delete_edges_with($name,'*','*');
delete_edges_with('*',$name,'*');
delete_edges_with('*','*',$name);

//Delete the contents of the node directory.
$node_path = NODE_PATH.$name;
$files = scandir($node_path);
foreach( $files as $file ) {
    //Skip over the loopback link to the directory itself and the parent directory backlink.
    if( ($file == '.') || ($file == '..') ) {
        continue;
    }
    //Create the path to the file relative to our current directory.
    $file_path = $node_path.'/'.$file;
    //For now, we only try to delete files instead of looking for non-label directories and trying to delete them recursively.
    if( is_file($file_path) ) {
            if( !unlink($file_path) ) {
                $unlink_error = error_get_last();
                exit_with_error_json("Deletion of file \"".$file_path."\" failed: ".$unlink_error['message']);
            }//end of if file deletion failed
    }//end of if is a file
}//end of deleting any files in the node directory

//reminder: scandir includes '.' and '..' even when the directory is empty, so a directory with 1 item has 3 scandir results
if(  count( scandir($node_path) ) >= 3  ) {
    exit_with_error_json("Directory \"".$node_path."\" contains something other than label directories and regular files, so we cannot delete it.");
}//end of if the directory is still not empty

if( !rmdir($node_path) ) {
    $rmdir_error = error_get_last();
    exit_with_error_json("Deletion of empty node directory \"".$node_path."\" failed: ".$rmdir_error['message']);
}//end of if we failed to delete the empty node directory

exit(  json_encode( array('success' => TRUE) )  );

?>
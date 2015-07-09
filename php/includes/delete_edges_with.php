<?php

require_once('exit_with_error_json.php');
require_once('edges_with.php');

//finds and deletes any edge links with specified source, label, and destination
//treating '*' as 'any' (Unfortunately, we cannot use declared constants, such as NODE_NAME_WILDCARD, as function default values.)
//and removing any label directories that become empty
function delete_edges_with($source='*',$label='*',$destination='*') {
    $paths = edges_with($source, $label, $destination);
    foreach( $paths as $link_path ) {
        if( is_link($link_path) ) {
            
            //Get the name of the parent label directory so that we can check whether it is empty after we delete the destination link.
            $label_path=dirname($link_path);
            
            //Remove the link itself.
            if( !unlink($link_path) ) {
                $unlink_error = error_get_last();
                exit_with_error_json("Deletion of link \"".$link_path."\" failed: ".$unlink_error['message']);
            }
            
            //If the label directory is now empty, delete it.
            $remaining_destinations = glob($label_path.PATH_SEPARATOR.NODE_NAME_WILDCARD,GLOB_ONLYDIR);
            if($remaining_destinations === FALSE) {
                $glob_error = error_get_last();
                exit_with_error_json("Check for destination symlinks in \"".$label_path."\" failed: ".$glob_error['message']);
            }
            if( count($remaining_destinations) < 1 ) {
                if( !rmdir($label_path) ) {
                    $rmdir_error = error_get_last();
                    exit_with_error_json("Deletion of empty label directory \"".$label_path."\" failed: ".$rmdir_error['message']);
                }//end of if we failed to delete the empty label directory
            }//end of if the label folder is now empty.
            
        }//end of if the link exists and is a symbolic link
    }//end of loop through all edges found
}//end of function delete_edges_with

?>
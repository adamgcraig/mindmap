<?php

require_once('consts.php');
require_once('exit_with_error_json.php');

//takes in the names of a source node, label node, and destination node,
//returns paths to all the symbolic link edges fitting the description
//or exits the script with error message on failure,
//treats '*' as 'any' (Unfortunately, we cannot use declared constants, such as NODE_NAME_WILDCARD, in default values.)
function edges_with($source='*',$label='*',$destination='*') {
    $link_path_pattern = NODE_PATH.$source.PATH_SEPARATOR.$label.PATH_SEPARATOR.$destination;
    $paths = glob($link_path_pattern);
    if($paths === FALSE) {
        $glob_error = error_get_last();
        exit_with_error_json("Search for paths matching pattern \"".$link_path_pattern."\" failed: ".$glob_error['message']);
    }//end of if we failed to search for edge links
    return $paths;
}//end of function edges_with

?>
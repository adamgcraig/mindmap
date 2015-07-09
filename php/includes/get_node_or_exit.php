<?php
//If the string passed in names a variable in the _REQUEST 
//that has a value that is the name of a node in the node directory,
//it returns that node name.
//Otherwise, it exits the script with an error message.

require_once('consts.php');
require_once('exit_with_error_json.php');
require_once('exit_on_bad_node_name.php');

function get_node_or_exit($node_role) {
    if( !isset($_REQUEST[$node_role]) ) {
        exit_with_error_json("The request did not include a ".$node_role." node.");
    }
    $node = $_REQUEST[$node_role];
    exit_on_bad_node_name($node);
    if( !is_dir(NODE_PATH.$node) ) {
        exit_with_error_json("The requested ".$node_role." node, ".$node.", does not exist.");
    }
    return $node;
}//end of get_node_or_exit
?>
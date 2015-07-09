<?php

require_once('edges_with.php');

//takes in the name of a node, 
//returns paths to all the symbolic link edges from it
//or exits the script with error message on failure
function edges_from($source) {
    return edges_with($source,NODE_NAME_WILDCARD,NODE_NAME_WILDCARD);
}//end of function edges_from

?>
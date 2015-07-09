<?php

require_once('edges_with.php');

//takes in the name of a node, 
//returns paths to all the symbolic link edges to it
//or exits the script with error message on failure
function edges_to($destination) {
    return edges_with(NODE_NAME_WILDCARD,NODE_NAME_WILDCARD,$destination);
}//end of function edges_to

?>
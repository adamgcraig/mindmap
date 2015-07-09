<?php

require_once('edges_with.php');

//takes in the name of a node, 
//returns paths to all the symbolic link edges labeled with its name
//or exits the script with error message on failure
function edges_labeled($label) {
    return edges_with(NODE_NAME_WILDCARD,$label,NODE_NAME_WILDCARD);
}//end of function edges_labeled

?>
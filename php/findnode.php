<?php
//SUMMARY: outputs a list of existing nodes   
//If none of the _REQUEST keys mentioned below have values, it outputs a list of all nodes in alphabetical order.
//If _REQUEST key NAME has a value, it sorts nodes by similarity of name to the target name.
//If _REQUEST key TEXT has a value, it sorts nodes by similarity of text to the target text.
//If _REQUEST key SOURCE has a value, it only outputs nodes that have edges from the source node to them or nodes that label edges from the source node.
//If _REQUEST key LABEL has a value, it only outputs nodes that have edges labeled with the label node from or to them.
//If _REQUEST key DESTINATION has a value, it only outputs nodes that have edges to that destination node or that label edges to that destination node.
//If _REQUEST has both SOURCE and LABEL values, it only outputs nodes that are destinations of edges from the source with the label. 
//If _REQUEST has both LABEL and DESTINATION values, it only outputs nodes that are sources of edges to the destination with the label.
//If _REQUEST has both SOURCE and DESTINATION values, it only outputs nodes that are labels of edges from the source to the destination.
//If _REQUEST has SOURCE, LABEL, and DESTINATION values, it outputs TRUE if the edge exists, false if it does not.
//Note on levenshtein vs. levenshtein_long: The built-in PHP function, levenshtein, imposes a 255-character limit.
//I have implemented a function, levenshtein_long, that accepts longer strings.
//We already restrict node names to 255 characters, but this code truncates node names to 255 characters just to be safe.
//It then uses levenshtein for name comparissons, since it is probably faster.
//Since text content may be longer than 255 characters, it uses levenshtein_long to compare text.

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/get_node_or_exit.php');
require_once('includes/edge_exists.php');
require_once('includes/edges_with.php');
require_once('includes/node_path_with_name.php');
require_once('includes/node_name_with_path.php');
require_once('includes/node_text_with_path.php');
require_once('includes/levenshtein_long.php');

//Check whether the request includes a name.
//We do not require it to be a valid node name, because we will not use it directly in a search.
$sort_by_name_similarity = isset($_REQUEST[NAME]);
if($sort_by_name_similarity) {
    $target_name = substr( $_REQUEST[NAME], 0, MAX_LEVENSHTEIN_LENGTH);
    $target_name_lc = strtolower($target_name);
}

//Check whether the request includes a name.
//We do not require it to be a valid node name, because we will not use it directly in a search.
$sort_by_text_similarity = isset($_REQUEST[TEXT]);
if($sort_by_text_similarity) {
    $target_text = $_REQUEST[TEXT];
    $target_text_lc = strtolower($target_text);
}

//Check whether the request includes a name for each role in an edge.
//If one of the _REQUEST variables is empty, we use a wildcard,
//but, if it is an invalid node name or non-existent node, 
//we should quit with an error.

$limit_by_source = isset($_REQUEST[SOURCE]);
if( $limit_by_source ) {
    $source = get_node_or_exit(SOURCE);
}
else {
    $source = NODE_NAME_WILDCARD;
}

$limit_by_label = isset($_REQUEST[LABEL]);
if( $limit_by_label ) {
    $label = get_node_or_exit(LABEL);
}
else {
    $label = NODE_NAME_WILDCARD;
}

$limit_by_destination = isset($_REQUEST[DESTINATION]);
if( $limit_by_destination ) {
    $destination = get_node_or_exit(DESTINATION);
}
else {
    $destination = NODE_NAME_WILDCARD;
}

$node_array = array();

function node_info_with_edge($edge,$back_index) {
    $node = array();
    $node[EDGE] = $edge;
    $path_elements = explode(PATH_SEPARATOR,$edge);
    $name_index = count($path_elements) + $back_index;
    $name = $path_elements[$name_index];
    $node[NAME] = $path_elements[$name_index];
    $node[PATH] = node_path_with_name($name);
    return $node;
}//end of function node_info_with_edge

//If the request specified all three, we do not have multiple possible matches.
//The edge either exists or does not exist.
if( $limit_by_source && $limit_by_label && $limit_by_destination ) {
    $exists = edge_exists($source,$label,$destination);
    exit( json_encode( array('exists' => $exists) ) );
}//end of if we have three specified edge parts; In all other cases, we output a list of possible matches.
elseif( $limit_by_source || $limit_by_label || $limit_by_destination ) {
    
    $edges = edges_with($source,$label,$destination);
    if( ($limit_by_source && $limit_by_label) || ($limit_by_source && $limit_by_destination) || ($limit_by_label && $limit_by_destination) ) {
        
        //In this case, we have only one wildcard in the edge, so get that as the name.
        //Recall that the last elements in the path describing an edge will be source/label/destination.
        //We will add each offset to the length of the array to get the index of the path element that is the name of the unspecified node.
        if( $limit_by_label && $limit_by_destination ) {
            $back_index = -1;//The wildcard is the destination.
        }
        elseif( $limit_by_source && limit_by_destination ) {
            $back_index = -2;//The wildcard is the label.
        }
        else {
            $back_index = -3;//The wildcard is the source.
        }
        foreach($edges as $edge) {
            array_push( $node_array, node_info_with_edge($edge,$back_index) );
        }//end of loop through edges
        
    }//end of if we have two specified edge parts.
    else {
        
        //In this case, we have two unspecified nodes for each edge.
        if($limit_by_source) {
            $back_index1 = -2;//The first wildcard is the label.
            $back_index2 = -1;//The second wildcard is the destination.
        }
        elseif($limit_by_label) {
            $back_index1 = -3;//The first wildcard is the source.
            $back_index2 = -1;//The second wildcard is the destination.
        }
        else {
            $back_index1 = -2;//The first wildcard is the label.
            $back_index2 = -3;//The second wildcard is the source.
        }
        foreach($edges as $edge) {
            //Add a node entry for each undetermined participant in the edge.
            array_push( $node_array, node_info_with_edge($edge,$back_index1) );
            array_push( $node_array, node_info_with_edge($edge,$back_index2) );
        }//end of loop through edges
        
    }//end of if we have one specified edge part.
    
}//If we do not need to consider edges, just get all nodes.
else {
    $node_wildcard_path = NODE_PATH.NODE_NAME_WILDCARD;
    $paths = glob($node_wildcard_path);
    if($node_paths === FALSE) {
        $glob_error = error_get_last();
        exit_with_error_json("Search for nodes with pattern \"".$node_wildcard_path."\" failed: ".$glob_error['message']);
    }//end of if we failed to search for nodes
    foreach($paths as $path) {
        $node = array();
        $node[PATH] = $path;
        $node[NAME] = node_name_with_path($path);
        array_push( $node_array, $node );
    }
}//end of if we have no specified edge parts.

//Add more information to the node descriptions.
//For assignment, we have to use the original node array in the array.
//Assigning it to another variable makes a copy.
for($node_index = 0; $node_index < count($node_array); $node_index++) {
    //Always add text.
    $node = $node_array[$node_index];
    $node_array[$node_index][TEXT] = node_text_with_path($node[PATH]);
    //Reminder: PHP's built-in Levenshtein function only takes words up to 50 characters.
    if($sort_by_name_similarity) {
        $comparisson_name = substr($node[NAME], 0, MAX_LEVENSHTEIN_LENGTH);
        $comparisson_name_lc = strtolower($comparisson_name);
        $node_array[$node_index][DISTANCE_BY_NAME] = levenshtein($comparisson_name,$target_name);
        $node_array[$node_index][DISTANCE_BY_NAME_CASE_INSENSITIVE] = levenshtein($comparisson_name_lc,$target_name_lc);
    }
    if($sort_by_text_similarity) {
        $text_lc = strtolower($text);
        $node_array[$node_index][DISTANCE_BY_TEXT] = levenshtein_long($text,$target_text);
        $node_array[$node_index][DISTANCE_BY_TEXT_CASE_INSENSITIVE] = levenshtein($text_lc,$target_text_lc);
    }
}//end of loop through paths

function compare_closeness($node1, $node2) {
    $output = 0;
    //If we had a target text, first try case-insensitive text similarity.
    if( isset($node1[DISTANCE_BY_TEXT_CASE_INSENSITIVE]) ) {
        $output = $node1[DISTANCE_BY_TEXT_CASE_INSENSITIVE] - $node2[DISTANCE_BY_TEXT_CASE_INSENSITIVE];
        //Then try case-sensitive text similarity.
        if(!$output) {
            $output = $node1[DISTANCE_BY_TEXT] - $node2[DISTANCE_BY_TEXT];
        }
    }
    //If we had a target name but no target text or a tie when comparing by text, try case-insensitive name similarity.
    if(  (!$output) && ( isset($node1[DISTANCE_BY_NAME_CASE_INSENSITIVE]) )  ) {
        $output = $node1[DISTANCE_BY_NAME_CASE_INSENSITIVE] - $node2[DISTANCE_BY_NAME_CASE_INSENSITIVE];
        //Then try case-sensitive name similarity.
        if(!$output) {
            $output = $node1[DISTANCE_BY_NAME] - $node2[DISTANCE_BY_NAME];
        }
    }
    //If we did not receive either kind of target or got ties on both targets, sort lexicographically by text.
    if(!$output) {
        $output = strcmp($node1[TEXT],$node2[TEXT]);
    }
    //If we are still tied, sort lexicographically by name. Recall that names must be unique.
    if(!$output) {
        $output = strcmp($node1[NAME],$node2[NAME]);
    }
    return $output;
}//end of function compare_closeness

$result = usort($node_array, 'compare_closeness');
if($result === FALSE) {
    exit_with_error_json("Attempt to sort nodes failed.");
}

exit( json_encode( array('nodes' => $node_array) ) );

?>
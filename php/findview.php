<?php
//SUMMARY: outputs a list of existing views 
//If the request includes a 'name' key and value, it sorts the results by ascending Levenshtein distance from the target name.
 
require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/view_name_with_path.php');
require_once('includes/damerau_levenshtein.php');

//Check whether the request includes a name.
//We do not require it to be a valid node name, because we will not use it directly in a search.
$sort_by_name_similarity = isset($_REQUEST[NAME]);
if($sort_by_name_similarity) {
    $target_name = $_REQUEST[NAME];
    $target_name_lc = strtolower($target_name);
}

$view_array = array();

$view_wildcard_path = VIEW_PATH.NODE_NAME_WILDCARD.VIEW_FILE_TYPE;
//echo("view path: ".$view_wildcard_path);
$paths = glob($view_wildcard_path);
if($paths === FALSE) {
    $glob_error = error_get_last();
    exit_with_error_json("Search for views with pattern \"".$view_wildcard_path."\" failed: ".$glob_error['message']);
}//end of if we failed to search for views
//echo("initial list of paths:");
//print_r($paths);

foreach($paths as $path) {
    $view = array();
    $view[PATH] = $path;
    $view[NAME] = view_name_with_path($path);
    //Get the timestamp of the first and last edit.
    $edit_history_path = EDIT_HISTORY_PATH.$view[NAME];
    $edits_string = file_get_contents($edit_history_path);
    if($edits_string === FALSE) {
        $fgc_error = error_get_last();
        exit_with_error_json( "Attempt to read file ".$edit_history_path." failed: ".$fgc_error['message'] );
    }
    $edits = json_decode($edits_string,TRUE);//TRUE->force to associative array
    if($edits === FALSE) {
        exit_with_error_json( "Attempt to parse file ".$edit_history_path." failed: ".json_last_error() );
    }
    //For some reason, json_decode would not work unless we wrapped the numeric array inside an associative one.
    if( array_key_exists(UPDATES, $edits) ) {
        $edits = $edits[UPDATES];
    }
    else {
        exit_with_error_json( "Edit history file is incorrectly formatted, missing key ".UPDATES."." );
    }
    $min_edit_timestamp = time();
    $max_edit_timestamp = 0;
    //We search through all of them and check that it exists first in case some timestamps are missing or out of order.
    for($edit_index = 0; $edit_index < count($edits); $edit_index++) {
        if( !isset($edits[$edit_index][TIMESTAMP]) ) {
            continue;
        }
        $timestamp = $edits[$edit_index][TIMESTAMP];
        if($timestamp < $min_edit_timestamp) {
            $min_edit_timestamp = $timestamp;
        }
        if($timestamp > $max_edit_timestamp) {
            $max_edit_timestamp = $timestamp;
        }
    }//end of loop through edits looking for min and max timestamps
    $view[MIN_EDIT_TIMESTAMP] = $min_edit_timestamp;
    $view[MAX_EDIT_TIMESTAMP] = $max_edit_timestamp;
    array_push( $view_array, $view );
}
//echo("array of view info arrays:");
//print_r($view_array);

//If we have a target name, add case-sensitive and non-case-sensitive distances from it.
//For assignment, we have to use the original node array in the array.
//Assigning it to another variable makes a copy.
if($sort_by_name_similarity) {
    for($view_index = 0; $view_index < count($view_array); $view_index++) {
        $view = $view_array[$view_index];
        $comparisson_name = $view[NAME];
        $comparisson_name_lc = strtolower($comparisson_name);
        $view_array[$view_index][DISTANCE_BY_NAME] = damerau_levenshtein($comparisson_name,$target_name);
        $view_array[$view_index][DISTANCE_BY_NAME_CASE_INSENSITIVE] = damerau_levenshtein($comparisson_name_lc,$target_name_lc);
    }//end of loop through paths
}//end of if we have a target name
//echo("array of view info arrays with distances:");
//print_r($view_array);

function compare_closeness($view1, $view2) {
    $output = 0;
    //Try case-insensitive name similarity.
    if(  (!$output) && ( isset($view1[DISTANCE_BY_NAME_CASE_INSENSITIVE]) )  ) {
        $output = $view1[DISTANCE_BY_NAME_CASE_INSENSITIVE] - $view2[DISTANCE_BY_NAME_CASE_INSENSITIVE];
        //Then try case-sensitive name similarity.
        if(!$output) {
            $output = $view1[DISTANCE_BY_NAME] - $view2[DISTANCE_BY_NAME];
        }
    }
    //If we did not receive a target name or got ties on both case-insensitive and case-sensitive comparisons, sort lexicographically.
    //Recall that names must be unique.
    if(!$output) {
        $output = strcmp($view1[NAME],$view2[NAME]);
    }
    return $output;
}//end of function compare_closeness

$result = usort($view_array, 'compare_closeness');
if($result === FALSE) {
    exit_with_error_json("Attempt to sort views failed.");
}
//echo("array of view info arrays sorted:");
//print_r($view_array);

exit( json_encode( array('results' => $view_array) ) );

?>
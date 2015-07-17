<?php

//SUMMARY: Given an HTTP request with the name of a view, 
//on success, creates an SVG file for that view based on the template and returns the relative path to it,
//on failure, returns an error message,
//checks that the view name contains only word characters and that creating it would not take up too much memory.

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/exit_on_bad_node_name.php');

//Check that the request includes a name.
if( !isset($_REQUEST[NAME]) ) {
    exit_with_error_json("The request did not contain a view name.");
}//end of if no name provided

$name = $_REQUEST[NAME];

//We hold views to the same naming conventions as we do nodes.
exit_on_bad_node_name($name);

function is_pre_clone($edit) {
    return $edit[IS_PRE_CLONE];
}//end of function is_old_enough

$dest_edits = array();//an empty array

//http://php.net/manual/en/function.usort.php
//The comparison function must return an integer less than, equal to, or greater than zero 
//if the first argument is considered to be respectively less than, equal to, or greater than the second.
//less than (-), equal to (0), or greater than (+) 
//edit1 is less than (<), equal to (==), or greater than (>) edit2.
//- => edit1 <  edit2 => edit1 - edit2 < 0, 
//0 => edit1 == edit2 => edit1 - edit2 == 0,
//+ => edit1 >  edit2 => edit1 - edit2 > 0
function compare_timestamps($edit1,$edit2) {
    if(  !( isset($edit1[TIMESTAMP]) || isset($edit2[TIMESTAMP]) )  ) {
        return 0;//Neither one has a timestamp, so we have no basis on which to compare.
    }
    elseif( !isset($edit1[TIMESTAMP]) ) {
        return -1;//edit1 lacks a timestamp, so assume it precedes edit2.
    }
    elseif( !isset($edit2[TIMESTAMP]) ) {
        return 1;//edit2 lacks a timestamp, so assume it precedes edit1.
    }
    else {
        return $edit1[TIMESTAMP] - $edit2[TIMESTAMP];//Both have timestamps, so we can compare directly.
    }
}//end of function compare_timestamps

//Check whether the request includes a source from which to copy.
if( isset($_REQUEST[SOURCES]) ) {
    
    $sources_string = $_REQUEST[SOURCES];
    $sources = json_decode($sources_string,TRUE);//TRUE->force to associative array
    if($sources === FALSE) {
        exit_with_error_json( "Attempt to parse sources ".$sources_string." failed: ".json_last_error() );
    }
    //echo("sources:<br/>");
    //print_r($sources);
    //echo("<br/>");
    foreach( $sources as $source_name => $cutoff ) {
        
        exit_on_bad_node_name($source_name);
        
        $source_edit_history_path = EDIT_HISTORY_PATH.$source_name;
        
        //Check that a view with this name exists.
        if( !is_file($source_edit_history_path) ) {
            exit_with_error_json("The edit history file \"".$source_edit_history_path."\" does not exist.");
        }
        
        //Read in the contents.
        $source_edits_string = file_get_contents($source_edit_history_path);
        if($source_edits_string === FALSE) {
            $fgc_error = error_get_last();
            exit_with_error_json( "Attempt to read file ".$source_edit_history_path." failed: ".$fgc_error['message'] );
        }
        
        //Parse the JSON.
        $source_edits = json_decode($source_edits_string,TRUE);//TRUE->force to associative array
        if($source_edits === FALSE) {
            exit_with_error_json( "Attempt to parse file ".$source_edit_history_path." failed: ".json_last_error() );
        }
        //For some reason, json_decode would not work unless we wrapped the numeric array inside an associative one.
        if( array_key_exists(UPDATES, $source_edits) ) {
            $source_edits = $source_edits[UPDATES];
        }
        else {
            exit_with_error_json( "Edit history file ".$source_edit_history_path." is incorrectly formatted, top-level key ".UPDATES."." );
        }
        
        //echo($source_name." edits:<br/>");
        //print_r($source_edits);
        //echo("<br/>");
        
        if( !is_numeric($cutoff) ) {
            exit_with_error_json( "Cutoff timestamp ".$cutoff." is non-numeric." );
        }
        $cutoff = floatval($cutoff);//Just convert to float once here rather than on every iteration.
        
        for($edit_index = 0; $edit_index < count($source_edits); $edit_index++) {
            //If the edit is missing a timestamp, assume it predates timestamping of edits and should be included.
            $source_edits[$edit_index][IS_PRE_CLONE] = ( !isset($source_edits[$edit_index][TIMESTAMP]) )||( $source_edits[$edit_index][TIMESTAMP] <= $cutoff );
            //echo("edit at time ".$source_edits[$edit_index][TIMESTAMP]." is before cutoff ".$cutoff.": ".$source_edits[$edit_index][IS_PRE_CLONE]."<br />\n");
        }//end of loop through edits to check whether they are before cutoff
        
        //Filter out all edits that occurred after the cutoff timestamp.
        $source_edits = array_filter($source_edits,'is_pre_clone');
        $dest_edits = array_merge($dest_edits,$source_edits);
        
    }//end of loop through sources
    
    //For now, do not bother sorting them by time.
    //The timestamps have only 1 second precision, 
    //so sorting by timestamp ometimes puts updates that are less than 1 second apart out of order.
    //Out-of-order updates can cause problems.
    //After compiling all the edits into one array, sort them by timestamp.
    //if( usort($dest_edits,'compare_timestamps') == FALSE ) {
    //    exit_with_error_json("Attempt to sort edits failed.");
    //}
    
}//end of if source provided

//echo("source edits:<br/>");
//print_r($dest_edits);
//echo("<br/>");

$name = $_REQUEST[NAME];

$view_path = VIEW_PATH.$name.VIEW_FILE_TYPE;

//Check that a view with this name does not already exist.
if( is_file($view_path) ) {
    exit_with_error_json("The view \"".$view_path."\" already exists.");
}

//Load the template.
$view_doc = new DOMDocument();
$result = $view_doc->load(VIEW_TEMPLATE_PATH);
if($result === FALSE) {
    $load_error = error_get_last();
    exit_with_error_json("Retrieval of view file template \"".VIEW_TEMPLATE_PATH."\" failed: ".$load_error['message']);
}

//Set the title of the top-level SVG element to the name.
$title = $view_doc->getElementsByTagName('title');
if(!$title) {
    exit_with_error_json("Retrieval of title element from document template failed.");
}
$title->item(0)->appendChild( new DOMText($name) );

//Check that the new file will leave a safe amount of free disk space.
$xml_string = $view_doc->saveXML();
if($xml_string === FALSE) {
    exit_with_error_json("Conversion of the view XML template to a string failed.");
}
if( disk_free_space(".") - strlen($xml_string) <  MEMORY_SAFETY_MARGIN) {
    exit_with_error_json("Creating the new view \"".$view_path."\" would leave fewer than ".MEMORY_SAFETY_MARGIN." bytes of disk space.");
}

//Save it to the new location.
//using XMLDoc->save would accomplish the same thing, 
//but we would be serializing the same document twice,
//since we already serialize it above to check the file size.
//$result = $view_doc->save($view_path);
//if($result === FALSE) {
//    $save_error = error_get_last();
//    exit_with_error_json("Creation of view \"".$view_path."\" failed: ".$save_error['message']);
//}
if( file_put_contents($view_path,$xml_string,LOCK_EX) == FALSE ) {
    $fpc_error = error_get_last();
    exit_with_error_json( "Attempt to write to file ".$view_path." failed: ".$fpc_error['message'] );
}

//Give it permissions that will allow the scripts to work.
if( chmod($view_path, FILE_PERMISSIONS) === FALSE ) {
    $chmod_error = error_get_last();
    exit_with_error_json("Setting of permissions of \"".$view_path."\" failed: ".$chmod_error['message']);
}

$dest_edit_history_path = EDIT_HISTORY_PATH.$name;

//Serialize the edits.
//For this to work, the top-level array must be associative.
$dest_edits_assoc = array();
$dest_edits_assoc[UPDATES] = $dest_edits;
$dest_edits_string = json_encode($dest_edits_assoc);
if($dest_edits_string === FALSE) {
    exit_with_error_json( "Attempt to serialize edit history failed." );
}

//Check that we have enough free disk space.
//This check produces false positives on Linux servers, because they tend to use free memory for temporary files and clear it as needed.
//if( disk_free_space(".") - strlen($dest_edits_string) <  MEMORY_SAFETY_MARGIN) {
//    exit_with_error_json("Creating the new edit history \"".$dest_edit_history_path."\" would leave fewer than ".MEMORY_SAFETY_MARGIN." bytes of disk space.");
//}

//Write the edits to the file.
if( file_put_contents($dest_edit_history_path,$dest_edits_string,LOCK_EX) == FALSE ) {
    $fpc_error = error_get_last();
    exit_with_error_json( "Attempt to write to file ".$dest_edit_history_path." failed: ".$fpc_error['message'] );
}

//Give it permissions that will allow the script to access the file in the future.
if( chmod($dest_edit_history_path, FILE_PERMISSIONS) === FALSE ) {
    $chmod_error = error_get_last();
    exit_with_error_json("Setting of permissions of \"".$dest_edit_history_path."\" failed: ".$chmod_error['message']);
}

exit(  json_encode( array('view_path' => $view_path) )  );
?>
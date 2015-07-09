<?php

//SUMMARY: Given an HTTP request with a name consisting of alphanumeric characters and '_'s and a text string,
//this PHP script creates a directory with the requested name if it does not already exist
//and writes the text to a file in that directory named text.txt.
//It checks beforehand that the name is not too long,
//that the changes will leave a safe amount of disk space,
//and that the text content is not a PHP script.
//On failure, it outputs a JSON string with key 'error' and value an appropriate error message.
//On success, it returns the path to the text content file.

require_once('includes/consts.php');
require_once('includes/exit_with_error_json.php');
require_once('includes/exit_on_bad_node_name.php');

//In this case, we check that the request includes a name and that it is valid,
//but, when the node directory does not exist,
//we create it instead of throwing and error.
//Hence, we do not use get_node_or_exit here.

//Check that the request includes a name.
if( !isset($_REQUEST[NAME]) ) {
    exit_with_error_json("The request did not contain a node name.");
}//end of if no name provided

$name = $_REQUEST[NAME];

exit_on_bad_node_name($name);

$node_path = NODE_PATH.$name;

//Create the node directory if it does not exist.
if( !is_dir($node_path) ) {
    
    //Try to make the directory.
    if( mkdir($node_path,DIRECTORY_PERMISSIONS,true) === FALSE ) {
        $mkdir_error = error_get_last();
        exit_with_error_json("Creation of directory \"".$node_path."\" failed: ".$mkdir_error['message']);
    }//end of if directory creation failed
    
}//end of if directory does not exist

//If the request does not include a separate text string, use the directory name for the text content.
if( isset($_REQUEST[TEXT]) ) {
    $text = $_REQUEST[TEXT];
}//end of if text provided
else {
    $text = $name;
}

//Do not allow PHP scripts in the text file.
$safe_text = preg_replace('/<\?php/', "<!notphp", $text);

$text_file_path = $node_path.PATH_SEPARATOR.TEXT_FILE_NAME;

//Take into account that, since we overwrite any old content, take that into account when we consider memory use.
if( is_file($text_file_path) ) {
    $existing_file_size = filesize($text_file_path);
}
else {
    $existing_file_size = 0;
}

//Check that the text will leave a safe amount of free disk space.
if( disk_free_space(".") - strlen($safe_text) + $existing_file_size <  MEMORY_SAFETY_MARGIN) {
    exit_with_error_json("Writing text \"".$safe_text."\" to file \"".$text_file_path."\" would leave fewer than ".MEMORY_SAFETY_MARGIN." bytes of disk space.");
}

//Try to save the text to a file.
$text_write_outcome = file_put_contents($text_file_path, $safe_text, LOCK_EX);
if($text_write_outcome === FALSE) {
    $fpc_error = error_get_last();
    exit_with_error_json("Attempt to write text \"".$safe_text."\" to file \"".$text_file_path."\" failed: ".$fpc_error['message']);
}

exit(  json_encode( array('text_file_path' => $text_file_path) )  );

?>
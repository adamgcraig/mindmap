<?php
require_once('consts.php');
//This function reads in the nodes text content.
function node_text_with_path($path) {
    $text_file_path = $path.FILE_PATH_SEPARATOR.TEXT_FILE_NAME;
    $text = file_get_contents($text_file_path);
    if($text === FALSE) {
        $fgc_error = error_get_last();
        exit_with_error_json("Attempt to read text from file \"".$text_file_path."\" failed: ".$fgc_error['message']);
    }
    return $text;
}
?>
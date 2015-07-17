<?php
require_once('consts.php');

function exit_on_bad_node_name($name) {
    
    //Check that the name includes only Perl word characters, i.e., alphanumeric characters and '_'s.
    //This is more strict than we need to be but safer than letting the user use every character other than '/'
    //and still flexible enough that the user can devise a wide variety of descriptive node names.
    $name_has_bad_char = preg_match('/[\W]+/', $name);
    //Check that preg_match has not failed.
    if($name_has_bad_char === FALSE) {
        switch( preg_last_error() ) {
            case PREG_NO_ERROR:
                $error_message = "no error";
            break;
            case PREG_INTERNAL_ERROR:
                $error_message = "internal error";
            break;
            case PREG_BACKTRACK_LIMIT_ERROR:
                $error_message = "too many backtracks";
            break;
            case PREG_RECURSION_LIMIT_ERROR:
                $error_message = "too many recursions";
            break;
            case PREG_BAD_UTF8_ERROR:
                $error_message = "bad UTF8 character";
            break;
            case PREG_BAD_UTF8_OFFSET_ERROR:
                $error_message = "bad UTF8 offset";
            break;
            default:
                $error_message = "unknown error";
        }//end of switch over different error codes for regular expression matcher
        exit_with_error_json( "An error occurred during checking of the filename for bad characters: ".$error_message );
    }//end of if preg_match failed
    if($name_has_bad_char) {
        exit_with_error_json( "The name \"".$name."\" contains a character that was not a letter, number, or underscore." );
    }
    
    //Check that the name is not too long.
    if( strlen($name) > MAX_NAME_LENGTH ) {
        exit_with_error_json( "The name \"".$name."\" is longer than ".MAX_NAME_LENGTH." bytes." );
    }

    //Check that the name is not the empty string.
    if( strlen($name) < 1 ) {
        exit_with_error_json( "The name is an empty string." );
    }
    
    //Check that creating the directory will leave a safe amount of free disk space.
    //Since Linux keeps various temporary files around until it needs to clear them, this may not reflect all the memory potentially available.
    //if( disk_free_space('.') - strlen($name) <  MEMORY_SAFETY_MARGIN) {
    //    exit_with_error_json("Creating a file or directory named \"".$name."\" would leave fewer than ".MEMORY_SAFETY_MARGIN." bytes of disk space. ".disk_free_space('.')." bytes currently free.");
    //}
    
}//end of function exit_on_bad_node_name
?>
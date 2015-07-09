<?php
//relative path to directory containing node subdirectories:
define('NODE_PATH','../nodes/');
//relative path to directory containing view files:
define('VIEW_PATH','../views/');
//minimum amount of memory to keep free, 2GB
define('MEMORY_SAFETY_MARGIN', 2000000000);
//maximum length of node name:
define('MAX_NAME_LENGTH',255);
//PHP's built-in Levenshtein function limits string arguments to a maximum length of 255 characters:
define('MAX_LEVENSHTEIN_LENGTH',255);
//permissions to assign to newly created directories:
//For now, make them maximally permissive to avoid confusion between
//permission-related errors and other sorts of errors while testing.
define('DIRECTORY_PERMISSIONS',0777);
define('FILE_PERMISSIONS',0777);
//_REQUEST key at which to look for the node/view name when the script only takes one node or view name:
define('NAME','name');
//_REQUEST key at which to look for the node text content:
define('TEXT','text');
//roles of nodes in an edge, used as keys in _REQUEST:
define('SOURCE','source');
define('LABEL','label');
define('DESTINATION','destination');
//_REQUEST key at which to look for a single node in a script that also involves a view:
define('NODE','node');
//name of file in node directory in which to store text content:
define('TEXT_FILE_NAME','text.txt');
//_REQUEST key at which to look for a view in a script that also involves a node:
define('VIEW','view');
//filetype suffix to append to view files:
define('VIEW_FILE_TYPE','.svg');
//relative path to the template to use for a new, empty view file:
define('VIEW_TEMPLATE_PATH','../templates/view.svg');
//relative path to the directory containing edit history files:
define('EDIT_HISTORY_PATH','../edit_histories/');
//string to use to separate items in a file path:
//define('PATH_SEPARATOR','/');
define('FILE_PATH_SEPARATOR','/');
//string to use in place of a node name to indicate "any node" in a search:
define('NODE_NAME_WILDCARD','*');
//In findnode, we sort existing nodes by similarity to some target node name and/or text content.
//To do this, we first create an array of associative arrays with the node names, paths, texts, relevant edges, and distances.
//We use NAME, TEXT, and the following Strings as the keys in the associative array:
define('PATH','path');
define('EDGE','edge');
define('DISTANCE_BY_NAME','distance_by_name');
define('DISTANCE_BY_NAME_CASE_INSENSITIVE','distance_by_name_case_insensitive');
define('DISTANCE_BY_TEXT','distance_by_text');
define('DISTANCE_BY_TEXT_CASE_INSENSITIVE','distance_by_text_case_insensitive');
//_REQUEST keys passed to syncupdates:
//VIEW (defined above)
define('UPDATE_COUNT','update_count');
define('UPDATES','updates');
//key in update associatve arrays in the edit history of a view, indicates when we logged this update, used for backtracking:
define('TIMESTAMP','timestamp');
//properties of view included in output of findview, indicate timestamps first and most recent edits:
define('MIN_EDIT_TIMESTAMP','min_edit_timestamp');
define('MAX_EDIT_TIMESTAMP','max_edit_timestamp');
//_REQUEST key at which makeview looks for an associative array mapping source views from which 
//to copy edits to cutoff times before which to accept edits:
define('SOURCES','sources');
//key in update associative arrays in the edit history of a view; 
//if present, indicates the edit was part of the view from which this view was cloned:
define('IS_PRE_CLONE','is_pre_clone');
?>
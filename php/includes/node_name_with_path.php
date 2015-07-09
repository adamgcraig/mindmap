<?php
require_once('consts.php');
//This function just returns the last element of a file/directory path.
function node_name_with_path($path) {
    $path_components = explode(PATH_SEPARATOR, $path);
    $last_index = count($path_components) - 1;
    return $path_components[$last_index];
}
?>
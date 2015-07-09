<?php
require_once('consts.php');
//This function just returns the last element of a file/directory path.
function view_name_with_path($path) {
    $path_components = explode('/', $path);
    $last_index = count($path_components) - 1;
    $file_name = $path_components[$last_index];
    $file_name_components = explode('.', $file_name);
    return $file_name_components[0];
}
?>
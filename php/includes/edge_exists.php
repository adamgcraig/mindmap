<?php
require_once('consts.php');
function edge_exists($source, $label, $destination) {
    return is_link(NODE_PATH.$source.PATH_SEPARATOR.$label.PATH_SEPARATOR.$destination);
}//end of function edge_exists
?>
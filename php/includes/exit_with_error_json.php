<?php
function exit_with_error_json($message) {
    exit(  json_encode( array('error' => $message) )  );
}
?>
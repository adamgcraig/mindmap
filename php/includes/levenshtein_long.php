<?php
//PHP's built-in Levenshtein distance function only allows strings up to 255 characters.
//algorithm from http://en.wikipedia.org/wiki/Levenshtein_distance
function levenshtein_long($string1, $string2) {
    $distances = array();
    $length1 = strlen($string1);
    //echo($string1." has length ".$length1."<br />\n");
    $length2 = strlen($string2);
    //echo($string2." has length ".$length2."<br />\n");
    for($i = 0; $i <= $length1; $i++) {
        $distances[$i] = array( 0 => $i );
    }
    for($j = 1; $j <= $length2; $j++) {
        $distances[0][$j] = $j;
    }
    for($i = 1; $i <= $length1; $i++) {
        for($j = 1; $j <= $length2; $j++) {
            if($string1[$i] == $string2[$j]) {
                //echo($string1[$i]." == ".$string2[$j]."<br />\n");
                $distances[$i][$j] = $distances[$i-1][$j-1];
            }
            else {
                //echo($string1[$i]." != ".$string2[$j]."<br />\n");
                //echo("add-a-character distance: ".$distances[$i-1][$j]." + 1<br />\n");
                $add_dist = $distances[$i-1][$j]+1;
                //echo("remove-a-character distance: ".$distances[$i][$j-1]." + 1<br />\n");
                $remove_dist = $distances[$i][$j-1]+1;
                //echo("replace-a-character distance: ".$distances[$i-1][$j-1]." + 1<br />\n");
                $replace_dist = $distances[$i-1][$j-1]+1;
                $alternatives = array($add_dist, $remove_dist, $replace_dist);
                $distances[$i][$j] = min($alternatives);
            }
        }
    }
    //print_r($distances);
    //echo("<br />\n");
    return $distances[$length1][$length2];
}//end of levenshtein_long
?>
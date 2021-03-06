<?php
//Damerau-Levenshtein distance is like Levenshtein distance 
//but allows for transpostions in addition to insertions, deletions, and substitutions.
//The current version uses unit costs for all changes.
//algorithm from https://en.wikipedia.org/wiki/Damerau%E2%80%93Levenshtein_distance
function damerau_levenshtein($string1, $string2) {
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
                $cost = ($string1[$i] == $string2[$j]) ? 0:1;
                //echo($string1[$i]." != ".$string2[$j]."<br />\n");
                //echo("add-a-character distance: ".$distances[$i-1][$j]." + 1<br />\n");
                $add_dist = $distances[$i-1][$j]+1;
                //echo("remove-a-character distance: ".$distances[$i][$j-1]." + 1<br />\n");
                $remove_dist = $distances[$i][$j-1]+1;
                //echo("replace-a-character distance: ".$distances[$i-1][$j-1]." + 1<br />\n");
                $replace_dist = $distances[$i-1][$j-1]+$cost;
                $alternatives = array($add_dist, $remove_dist, $replace_dist);
                if( ($i > 1) && ($j > 1) && ($string1[$i] == $string2[$j-1]) && ($string1[$i-1] == $string2[$j]) ) {
                    $transpose_dist = $distances[$i-2][$j-2] + $cost;
                    array_push($alternatives, $transpose_dist);
                }
                $distances[$i][$j] = min($alternatives);
            }
        }
    }
    //print_r($distances);
    //echo("<br />\n");
    return $distances[$length1][$length2];
}//end of levenshtein_long
?>
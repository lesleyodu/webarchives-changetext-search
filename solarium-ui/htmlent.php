<?php
//$text = " &#40;New York&#44; N.Y.&#41; ";
//$text2 = html_entity_decode($text);
//echo $text2;


//$myfile = fopen("htmlent.txt", "r") or die("Unable to open file!");
$text = file_get_contents("htmlent.txt");

$text1b = str_replace("&quot;", "\\\"", $text);
$text1c = str_replace("&#34;", "\\\"", $text1b);

$text2 = html_entity_decode($text1c);

$text4 = str_replace( array('&#146;', '&#147;', '&#148;', '&#150;', '&#151;'),
                      array("'", '\"', '\"', '-', '-'), $text2);


$myfile2 = fopen("htmlent2.txt", "w") or die("Unable to open file2!");
fwrite($myfile2, $text4);
fclose($myfile2);
//fclose($myfile);

echo 'done';

?>
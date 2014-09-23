<?php
/**
 * Created by PhpStorm.
 * User: marko
 * Date: 18.4.2014
 * Time: 12:49
 */

set_time_limit(0);

header('Content-Type: text/html; charset=utf-8');

include('include/config.php');

function __autoload($class_name)
{
    include 'include/class.' . $class_name . '.php';
}

$html = new html(
    'Flood events',
    'utf8',
    array('include/template.css'),
    array()
);

$flood = new flood();
$timer_start = $flood->microtime_float();

echo "<div id='version' style='position: absolute; top: 10px; right: 10px;'>v01 (23.9.2014)</div>";
flush();
ob_flush();

if(!isset($_GET['cc'])) {
    $_GET['cc'] = 'SI';
}

// alter orig. tabele; doda na koncu atribut timeLineEnd



// gre čez vse evente v državi v osnovni tabeli
$result = $flood->getAllFloodEventsForCountry($_GET['cc']);

$stevec = 0;


while ($myrow = $result->fetch_array()) {

    // izpiše vsak event
    echo $stevec." ".$myrow['FloodEventCode'] . ": " . $myrow['StartDate'] . " -  " . $myrow['EndDate'] . "\t\t";
    flush();
    ob_flush();

    // rekurzija poišče vse evente vezane na ta event
    $flood->recursiveSearchOverlappedEvents($_GET['cc'], $myrow['StartDate'], $myrow['EndDate'], array($myrow['FloodEventCode']));

    echo "<br>";

    $stevec++;
}

$timer_end = $flood->microtime_float();
$execution_time = round($timer_end - $timer_start, 2);

echo "<br><br>execution time: ".$execution_time." secs<br><br>";



//var_dump($_GET);


?>
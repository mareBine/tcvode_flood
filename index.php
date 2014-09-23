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

echo "<div id='version' style='position: absolute; top: 10px; right: 10px;'>v02 (23.9.2014)</div>";
flush();
ob_flush();

if(!isset($_GET['cc'])) {
    $_GET['cc'] = 'SI';
}

echo "V tabelo <strong>" . $flood->tableName ."</strong> doda na začetku polja:<br>";
echo "* " . $flood->columnNameTL ." - konec časovnega obdobja za prekrit event (dobi rekurzija)<br>";
echo "* " . $flood->columnNameId ." - unikaten id eventa<br>";
echo "<br>";

// alter orig. tabele; doda na koncu atribut timeLineEnd
if(!!!$flood->checkIfColumnExists($columnNameTL)) {
    $flood->createTableColumn($columnNameTL, 'DATE NULL FIRST');
}

$flood->setTimeLineEndColumn($columnNameTL);

// doda še id
if(!!!$flood->checkIfColumnExists($columnNameId)) {
    $flood->createTableColumn($columnNameId, 'INT(10) NOT NULL AUTO_INCREMENT FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY(' . $columnNameId . ')');
}

// gre čez vse evente v državi v osnovni tabeli
$result = $flood->getAllFloodEventsForCountry($_GET['cc']);
$stevec = 1;

echo "<br>Gre čez vse evente v izvorni tabeli <strong>" . $flood->tableName ."</strong> in rekurzivno išče prekrite evente:<br><br>";

while ($myrow = $result->fetch_array()) {

    $updateSql = "";

    // izpiše vsak event
    echo ($stevec++)." ".$myrow['FloodEventCode'] . ": " . $myrow['StartDate'] . " -  " . $myrow['EndDate'] . "\t\t";
    flush();
    ob_flush();

    // rekurzija poišče vse evente vezane na ta event
    $flood->recursiveSearchOverlappedEvents($_GET['cc'], $myrow['StartDate'], $myrow['EndDate'], array($myrow['FloodEventCode']), $updateSql);

    // sestavi do konca stavek za update in ga izvede; updata polje timeLineEnd
    if($updateSql != "")    {
        $updateSql .= " WHERE id = " . $myrow['id'];
        $flood->executeMySQLQuery($updateSql);
    }

    echo "<br>";
}

$timer_end = $flood->microtime_float();
$execution_time = round($timer_end - $timer_start, 2);
echo "<br><br>execution time: ".$execution_time." secs<br><br>";

echo "<br>Končna tabela z rezultati: ";
$html->drawTableFromArray($flood->getMultipleEventsForCountry($_GET['cc']), 'table1', '');




?>
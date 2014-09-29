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

echo "<div id='version' style='position: absolute; top: 10px; right: 10px;'>v04 (29.9.2014)</div>";
flush();
ob_flush();

if (!isset($_GET['cc'])) {
    $_GET['cc'] = 'SI';
}

if (!isset($_GET['type'])) {
    $_GET['type'] = 'view';
}

echo "V tabelo <strong>" . $flood->tableName . "</strong> doda na začetku polja:<br>";
echo "* " . $flood->columnNameTL . " - konec časovnega obdobja za prekrit event (dobi rekurzija)<br>";
echo "* " . $flood->columnNameId . " - unikaten id eventa<br>";
echo "<br>";

// alter orig. tabele; doda na koncu atribut timeLineEnd
if (!!!$flood->checkIfColumnExists($columnNameTL)) {
    $flood->createTableColumn($columnNameTL, 'DATE NULL FIRST');
}

$flood->setTimeLineEndColumn($columnNameTL);

// doda še id
if (!!!$flood->checkIfColumnExists($columnNameId)) {
    $flood->createTableColumn($columnNameId, 'INT(10) NOT NULL AUTO_INCREMENT FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY(' . $columnNameId . ')');
}

// gre čez vse evente v državi v osnovni tabeli
$result = $flood->getAllFloodEventsForCountry($_GET['cc']);
$stevec = 1;

if(isset($_GET['prm']) AND $_GET['prm'] == 'recursive') {
    echo "<br>-> Parameter type=create kreira tabelo v bazi namesto da jo izpiše: ";
    echo "<a href='" . $_SERVER['PHP_SELF'] . "?cc=" . $_GET['cc'] . "&type=create&prm=recursive'>" . $_SERVER['PHP_SELF'] . "?cc=" . $_GET['cc'] . "&type=create&prm=recursive</a><br>";
} else {
    echo "<br>-> Parameter prm=recursive kliče še rekurzivno proceduro: ";
    echo "<a href='" . $_SERVER['PHP_SELF'] . "?cc=" . $_GET['cc'] . "&prm=recursive'>" . $_SERVER['PHP_SELF'] . "?cc=" . $_GET['cc'] . "&prm=recursive</a><br>";

}

echo "<br>Gre čez vse evente v izvorni tabeli <strong>" . $flood->tableName . "</strong> in <strong><em>";
echo (isset($_GET['prm']) AND $_GET['prm'] == 'recursive') ? 'rekurzivno' : 'iterativno';
echo "</strong></em> išče prekrite evente:<br><br>";

$startDate_currentEvent = "";
$endDate_currentEvent = "";
$noOfEvents = $result->num_rows;

$internalEventsCounter = 0; // števec dogodkov znotraj večjega eventa
$eventCount = 1; // števec eventov

while ($myrow = $result->fetch_array()) {

    // recursion; might be very slow for large dataset
    if (isset($_GET['prm']) AND $_GET['prm'] == 'recursive') {
        $updateSql = "";

        // izpiše vsak event
        echo ($stevec++) . " " . $myrow['FloodEventCode'] . ": " . $myrow['StartDate'] . " -  " . $myrow['EndDate'] . "\t\t";
        flush();
        ob_flush();

        // rekurzija poišče vse evente vezane na ta event
        $flood->recursiveSearchOverlappedEvents($_GET['cc'], $myrow['StartDate'], $myrow['EndDate'], array($myrow['FloodEventCode']), $updateSql);

        // sestavi do konca stavek za update in ga izvede; updata polje timeLineEnd
        if ($updateSql != "") {
            $updateSql .= " WHERE id = " . $myrow['id'];
            $flood->executeMySQLQuery($updateSql);
        }

        echo "<br>";
    }

    // ITERATION: very fast
    else {

        //$flood->iterativeSearchOverlappedEvents($stevec, $myrow['StartDate'], $myrow['EndDate']);

        // iteration: search if next event
        // a) if startDate > endDate_current -> this is new event
        //echo ($stevec++) . " " . $myrow['FloodEventCode'] . ": " . $myrow['StartDate'] ;

        // ob prvem prehodu
        if ($stevec == 1) {
            echo "<br>event #" . $eventCount . " : " . $myrow['StartDate'];
            $startDate_currentEvent = strtotime($myrow['StartDate']);
            $endDate_currentEvent = strtotime($myrow['EndDate']);
            $tableName = $flood->createTableIterative($_GET['cc']);
            $insertSQL = "INSERT INTO " . $tableName . " VALUES (" . $eventCount . ", '" . $myrow['StartDate'] . "', ";
            $eventCount++;

        }

//    echo "<br>" . date("Y-m-d",$startDate_currentEvent);
//    echo "<br>" . date("Y-m-d",$endDate_currentEvent);
//    echo "<br>" . $myrow['StartDate'];

        // če je našel gap: potem izpiše endDate trenutnega eventa in pa startDate naslednjega
        if ($startDate_currentEvent != "" AND $endDate_currentEvent != "" AND strtotime($myrow['StartDate']) > $endDate_currentEvent) {
            echo " -------iter------> " . date("Y-m-d", $endDate_currentEvent) . " (" . $internalEventsCounter . ")";
            echo "<br>event #" . $eventCount . " : " . $myrow['StartDate'];

            $insertSQL .= "'" . date("Y-m-d", $endDate_currentEvent) . "', " . $internalEventsCounter . ")";
            $flood->executeMySQLQuery($insertSQL);

            $insertSQL = "INSERT INTO " . $tableName . " VALUES (" . $eventCount . ", '" . $myrow['StartDate'] . "', ";

            $internalEventsCounter = 1;
            $eventCount++;
            $startDate_currentEvent = strtotime($myrow['StartDate']);
            $endDate_currentEvent = strtotime($myrow['EndDate']);

        } // drugače pa gleda naprej
        else {
            //echo "else: " . $myrow['EndDate'] . " " . $endDate_currentEvent;

            // če je endDate večji kot trenuten; naredi tega kot trenutnega
            if ($endDate_currentEvent != "" AND strtotime($myrow['EndDate']) > $endDate_currentEvent) {
                $endDate_currentEvent = strtotime($myrow['EndDate']);
                //$gapFound = 0;
            }

            $internalEventsCounter++;
        }

        // pri zadnjem eventu izpiše EndDate
        if ($stevec == $noOfEvents) {
            echo " -----------------> " . $myrow['EndDate'] . " (" . $internalEventsCounter . ")";

            $insertSQL .= "'" . date("Y-m-d", $endDate_currentEvent) . "', " . $internalEventsCounter . ")";
            $flood->executeMySQLQuery($insertSQL);
        }

    }

    $stevec++;
}

$timer_end = $flood->microtime_float();
$execution_time = round($timer_end - $timer_start, 2);
echo "<br><br>execution time: " . $execution_time . " secs<br><br>";

// ali kreira tabelo ali izpiše na ekran
if (isset($_GET['prm']) AND $_GET['prm'] === 'recursive') {
    if ($_GET['type'] === 'create') {
        echo "<br><strong>Kreiranje</strong> končne tabele z rezultati: " . $flood->recursiveGetMultipleEventsForCountry($_GET['cc'], 'create');

    } else {
        echo "<br>Končna tabela z rezultati: ";
        $html->drawTableFromArray($flood->recursiveGetMultipleEventsForCountry($_GET['cc']), 'table1', '');

    }
} else {
    echo "<br><strong>Kreiranje</strong> končne tabele z rezultati: ";
    $html->drawTableFromArray($flood->iterativeGetAllEvents($tableName), 'table1', '');

}

?>
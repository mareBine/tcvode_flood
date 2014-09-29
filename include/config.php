<?php

// povezava na bazo
$host = "localhost";
$dbuser = "root";
$dbpass = "redhat";
$database = "tcvode_flood";

$port = 3307;

// nastavitve za ime tabele in atribute, pustiš kot je
$table = "floodevent_indicator_past1980_timeperiods";
//$table = "floodevent_pl";

$FloodEventCode = 'FloodEventCode';
// ta 2 atributa potem kreira
$columnNameTL = 'timeLineEnd';
$columnNameId = 'id';

$online = 0;
?>
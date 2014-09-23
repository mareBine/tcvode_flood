<?php
/**
 * queriji na national reporte na osnovi OpenTBS
 * @see OPENTBS_national_report.php
 *
 * User: marko
 * Date: 14.4.2014
 * Time: 16:01
 *
 * @note    tabele iz katerih bere, baza je bwd_<currYear>
 *  tb_countries
 *  bwd_trend
 *  bwd_newd_trend
 *  new_monitoringresults
 *  new_seasonalinfo
 *  new_identifiedbw
 *
 */

class country_fiches extends db_common
{

    /**
     * pokaže gumb za prikaz sqla
     *
     * @param $sql
     */
    private function showSQLforDebug($sql)
    {

        global $online;

        //if (!$online) {
        echo "&nbsp;<a href='#' class='showhide_button'>Show/Hide SQL</a>";
        echo "<div style='display: none;'><pre>" . $sql . "</pre></div>";
        //}
    }

    /**
     * excluded sql za nutrients
     * @var string
     */
    private $sql_excluded_nutrients_hazsubs = "
        # nutrients/hazsubs - only not deleted records
        AND (n.RecordDelete IS NULL OR n.RecordDelete != 1)

        # nutrients/hazsubs - only records w/out QA errors
        AND n.QA_LRviolations IS NULL
        AND n.QA_MVissues IS NULL
        AND (n.QA_station_issues IS NULL OR n.QA_station_issues NOT IN ('501','502','599'))
    ";

    /**
     * subquery za dobit QA problematične recorde v stations tabeli
     * @var string
     */
    private $sql_excluded_stations = "
        # stations - only not deleted records
        AND (s.RecordDelete IS NULL OR s.RecordDelete != 1)

        # stations - only records w/out QA errors
        AND (s.QA_station_issues IS NULL OR s.QA_station_issues NOT IN ('501','502'))
    ";

    /**
     * ime tabele, ki jo nastavi setter
     *
     * @var string
     * @see setTableDeterminand
     */
    private $table = '';
    private $tableName = '';

    /**
     * ime polja za determinand v tabeli, nastavi setter
     *
     * @var string
     * @see setTableDeterminand
     */
    private $determinandName = '';

    /**
     * subquery za dobit aggregationPeriod recorde
     *
     * @var string
     * @see setTableDeterminand
     */
    private $aggregationSubQuery = '';


    /**
     * te subqueriji so za povezavo na waterbase_common.tb_fiche_* tabeli
     * - vrstni red determinandov
     *
     * @var string
     */
    private $determinandJoinSubQuery = '';
    private $determinandConditionSubQuery = '';
    private $determinandOrder = '';

    /**
     * za setat tabelo in determinand
     *
     * @param $table
     */
    public function setTableDeterminand($table)
    {
        global $tabela_dataset;

        $this->table = $table;

        switch ($table) {
            case 'hazsubs':
                $this->tableName = $tabela_dataset['hazsubs_agg'];
                $this->determinandName = 'Determinand_HazSubs';
                $this->aggregationSubQuery = '';
                $this->determinandJoinSubQuery = ' INNER JOIN waterbase_common.tb_fiche_hazsubs_determinand_order AS t ON n.Determinand_HazSubs = t.`Name` ';
                $this->determinandConditionSubQuery = ' AND t.PrefSubst_RiLa = "Yes" ';
                $this->determinandOrder = ' ORDER BY t.Group ';
                break;
            case 'nutrients':
                $this->tableName = $tabela_dataset['nutrients'];
                $this->determinandName = 'Determinand_Nutrients';
                $this->aggregationSubQuery = '
                    AND LOWER(AggregationPeriod) = "annual"
                ';
                $this->sql_excluded_nutrients_hazsubs .= "
                    AND (n.QA_outlier IS NULL OR n.QA_outlier NOT IN ('401','402','403','411','412','413'))
                ";
                $this->determinandJoinSubQuery = ' INNER JOIN waterbase_common.tb_fiche_determinand_order AS t USING(Determinand_Nutrients) ';
                $this->determinandConditionSubQuery = '';
                $this->determinandOrder = ' ORDER BY t.Order ';
                break;
            default:
                break;
        }
    }

    /**
     * kreira temporary tabelo in vrne njeno ime
     *
     * @param $cc
     * @param $determinand
     * @param $period_from
     * @param $period_to
     * @return string
     */
    private function createTemporaryTable($cc, $determinand, $period_from, $period_to)
    {

        global $tabela_dataset;

        // kreira tmp tabelo
        $tmp_table_name = "tmp_fiche_" . strtolower($cc) . "_"
            . strtolower(str_replace(array(' ', '%20', '(', ')', ',', '-',"'"), '', $determinand)) . "_"
            . $period_from . "_" . $period_to;

        // treba klicat samo še tabela še ne obstaja
        $sql_check = "SHOW TABLES LIKE '" . $tmp_table_name . "'";
        $result_check = $this->mysqli->query($sql_check) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql_check));
        $table_exists = $result_check->num_rows > 0;

        if (!$table_exists) {
            $sql_create = '
                CREATE TABLE /*IF NOT EXISTS*/ ' . $tmp_table_name . ' /*ENGINE=MEMORY*/
                SELECT CountryCode, RBDcode, NationalStationID, ' . $this->determinandName . ', count(distinct Year) as ts_length
                    # tole je samo testno, zato da dobim leta in vidim če prav dela !!! z MEMORY TABELO ne dela ta type
                    , GROUP_CONCAT(DISTINCT Year ORDER BY Year)
                FROM ' . $this->tableName . ' AS n
                INNER JOIN ' . $tabela_dataset['stations'] . ' AS s USING(CountryCode, NationalStationID)

                WHERE 1
                AND CountryCode = "' . $cc . '"
                AND ' . $this->determinandName . ' = "' . $determinand . '"
                AND Year BETWEEN "' . $period_from . '" AND "' . $period_to . '"
                AND RBDcode IS NOT NULL
            ';

            $sql_create .= $this->aggregationSubQuery;
            $sql_create .= $this->sql_excluded_nutrients_hazsubs;
            $sql_create .= $this->sql_excluded_stations;

            $sql_create .= "
                GROUP BY NationalStationID
            ";
            // tega SQLa ne kažem, ker ni relevanten
            $this->showSQLforDebug($sql_create);

            $result = $this->mysqli->query($sql_create) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql_create));

            // naredi indekse če še ne obstajajo / ne rabimo več, ker že zgoraj preverja če tabela obstaja
            //$sql_check = "SHOW INDEX FROM " . $tmp_table_name . " WHERE KEY_NAME = 'CountryCode' OR KEY_NAME = 'NationalStationID'";
            //$result_check = $this->mysqli->query($sql_check) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql_check));

            //if ($result_check->num_rows == 0) {
            $sql_index = "
                    ALTER TABLE " . $tmp_table_name . "
                    ADD INDEX(CountryCode),
                    ADD INDEX(NationalStationID),
                    ADD INDEX(" . $this->determinandName . "),
                    ADD COLUMN const_ts_length INT(10) NOT NULL DEFAULT 1,
                    ADD COLUMN allowable_gap INT(10) NOT NULL DEFAULT 1
                ";
            $result_index = $this->mysqli->query($sql_index) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql_index));
            // }

        }


        return $tmp_table_name;

    }

    /**
     * izračuna dolžino največje čas. serije glede na allowable gap, primer:
     * 2000, 2001, 2003, 2004, 2005
     * allowable_gap = 1, potem const_ts_length = 3 (2003, 2004, 2005)
     * allowable_gap = 2, potem const_ts_length = 5
     *
     * 2000, 2003, 2004, 2005, 2007, 2008, 2011
     * allowable_gap = 1, potem const_ts_length = 3 (2003, 2004, 2005)
     * allowable_gap = 2, potem const_ts_length = 5 (2003, 2004, 2005, 2007, 2008)
     * allowable_gap = 3, potem const_ts_length = 7 (2000, 2003, 2004, 2005, 2007, 2008, 2011)
     *
     * @param $cc
     * @param $determinand
     * @param $period_from
     * @param $period_to
     * @param int $allowable_gap
     * @return array
     */
    private function calculateCorrectLengthOfTimeSeries($table_name, $period_from, $period_to, $allowable_gap)
    {
        global $tabela_dataset;

        // 1. izbere vse iz temp tabele
        $sql = "SELECT * FROM " . $table_name . " WHERE ts_length > 1";
        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));

        while ($myrow = $result->fetch_assoc()) {
            // 1.1. za vsak zapis gre čez vse time serije
            $sql_ts = "
                SELECT Year
                FROM " . $this->tableName . " AS n
                WHERE 1
                AND CountryCode = '" . $myrow['CountryCode'] . "'
                AND NationalStationID =  '" . $myrow['NationalStationID'] . "'
                AND " . $this->determinandName . " = '" . addslashes($myrow[$this->determinandName]) . "'
                AND Year BETWEEN '" . $period_from . "' AND '" . $period_to . "'
            ";

            $sql_ts .= $this->aggregationSubQuery;
            $sql_ts .= $this->sql_excluded_nutrients_hazsubs;

            $sql_ts .= "
                ORDER BY Year
            ";
            $result_ts = $this->mysqli->query($sql_ts) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql_ts));

            // logika za izračunat dolžino TS glede na allowable gap
            $years = array();
            $temp_ts_length = 1;
            $max_ts_length = 1;


            // 1.1.a. da vsa leta v array years
            while ($myrow_ts = $result_ts->fetch_assoc()) {
                $years[] = $myrow_ts['Year'];
            }

            // 1.1.b. gre čez vsa ta leta in računa
            for ($ii = 0; $ii < sizeof($years); $ii++) {
                if (isset($years[$ii + 1]) AND (($years[$ii + 1] - $years[$ii]) <= $allowable_gap)) {
                    $temp_ts_length++;
                } else {
                    if ($temp_ts_length > $max_ts_length) {
                        $max_ts_length = $temp_ts_length;
                        $temp_ts_length = 1;
                    }
                }

                //if($myrow['NationalStationID'] == '09-MAS-014')     echo "year: ".$years[$ii] . ", max_ts_length: " .  $max_ts_length . ", temp_ts_length: " .  $temp_ts_length . "<br>";
            }

            //if($myrow['NationalStationID'] == '09-MAS-014')     echo "<pre>" . print_r($years, true) . "</pre>, max_ts_length: " .  $max_ts_length . ", allowable_gap: " .  $allowable_gap . ", temp_ts_length: " .  $temp_ts_length;

            // 1.1.c. naredi UPDATE v $table_name
            $sql_update = "
                UPDATE " . $table_name . "
                SET const_ts_length = " . $max_ts_length . ",
                    allowable_gap = " . $allowable_gap . "
                WHERE 1
                AND CountryCode = '" . $myrow['CountryCode'] . "'
                AND NationalStationID =  '" . $myrow['NationalStationID'] . "'
                AND " . $this->determinandName . " = '" . addslashes($myrow[$this->determinandName]) . "'
            ";
            $result_update = $this->mysqli->query($sql_update) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql_update));

        }

    }


    /**
     * kliče parent constructor - povezava na mysql bazo
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * dobi vse države iz stations tabele
     */
    public function getCountries()
    {
        global $tabela_dataset;

        $sql = "
            SELECT DISTINCT CountryCode
            FROM " . $tabela_dataset['stations'] . "
            ORDER BY CountryCode
        ";
        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow['CountryCode'];
        }
        return $output;
    }


    /**
     * Number of river stations reported by River Basin Districts - for selected determinand
     *
     * @param string $cc countryCode
     * @param int $period_from
     * @param int $period_to
     * @param $determinand
     *
     * @return mixed
     */
    public function getNoOfRiverStationsByRBD($cc, $period_from, $period_to, $determinand)
    {

        global $tabela_dataset;

        $sql = "
            SELECT s.RBDcode AS RBDcode,
        ";

        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " COUNT(DISTINCT IF(n.`Year` = " . $ii . ", NationalStationID, null)) AS '" . $ii . "',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql .= "
            , COUNT(DISTINCT NationalStationID) AS 'Total'

            FROM " . $this->tableName . " AS n
            INNER JOIN " . $tabela_dataset['stations'] . " AS s USING(CountryCode, NationalStationID)

            WHERE 1
            AND s.CountryCode = '" . $cc . "'
            AND n.Year BETWEEN '" . $period_from . "' AND '" . $period_to . "'
            AND n." . $this->determinandName . " = \"" . addslashes($determinand) . "\"
            AND s.RBDcode IS NOT NULL
        ";

        $sql .= $this->aggregationSubQuery;
        $sql .= $this->sql_excluded_nutrients_hazsubs;
        $sql .= $this->sql_excluded_stations;

        $sql .= "
            GROUP BY s.RBDcode
        ";

        $this->showSQLforDebug($sql);

        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow;
        }
        return $output;
    }

    /**
     * naredi string iz preffered determinandov (za uporabi v SQLu ... IN)
     *
     * @return string
     */
    private function getPreferredDEterminandString() {

        $sql = "
            SELECT `Name`
            FROM waterbase_common.tb_fiche_hazsubs_determinand_order
            WHERE PrefSubst_RiLa = 'Yes'
        ";

        return implode('","', $this->createOneDimensionalArrayFromSQL($sql));

    }

    /**
     * Number of river stations reported by River Basin Districts  -for all determinands
     *
     * @param string $cc countryCode
     * @param int $period_from
     * @param int $period_to
     *
     * @return mixed
     */
    public function getNoOfRiverStationsByRBDAllDeterminands($cc, $period_from, $period_to)
    {

        global $tabela_dataset;

        $sql = "
            SELECT s.RBDcode AS RBDcode,
        ";

        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " COUNT(DISTINCT IF(n.`Year` = " . $ii . ", NationalStationID, null)) AS '" . $ii . "',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql .= "
            , COUNT(DISTINCT NationalStationID) AS 'Total'

            FROM " . $this->tableName . " AS n
            INNER JOIN " . $tabela_dataset['stations'] . " AS s USING(CountryCode, NationalStationID)

            WHERE 1
            AND s.CountryCode = '" . $cc . "'
            AND n.Year BETWEEN '" . $period_from . "' AND '" . $period_to . "'
            AND s.RBDcode IS NOT NULL
        ";

        // for hazsubs only preferred determinands are counted
        if($this->table == 'hazsubs')    {
            $sql .= "AND n." . $this->determinandName . " IN ( \"" . $this->getPreferredDEterminandString() . "\" )";
        }

        $sql .= $this->aggregationSubQuery;
        $sql .= $this->sql_excluded_nutrients_hazsubs;
        $sql .= $this->sql_excluded_stations;

        $sql .= "
            GROUP BY s.RBDcode
        ";

        $this->showSQLforDebug($sql);

        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow;
        }
        return $output;
    }


    /**
     * dobi vse različne determinande za izbrano državo
     *
     * @param $cc
     * @return array
     */
    public function getDeterminandsForCountry($cc = '')
    {
        global $tabela_dataset;

        $sql = "
            SELECT DISTINCT " . $this->determinandName . "
            FROM " . $this->tableName . " AS n
        ";
        $sql .= $this->determinandJoinSubQuery;

        if ($cc != '') {
            $sql .= " WHERE n.CountryCode = '" . $cc . "'";
        }

        $sql .= $this->determinandConditionSubQuery;

        $sql .= "
            ORDER BY n." . $this->determinandName . "
        ";

        $output = array();

        //echo $sql;

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_row()) {
            $output[] = $myrow[0];
        }
        return $output;
    }


    /**
     * dobi dolžine časovnih serij
     *
     * @param $cc
     * @param $determinand
     * @param $period_from
     * @param $period_to
     * @return array
     */
    public function getLengthOfTimeSeries($cc, $determinand, $period_from, $period_to, $allowable_gap)
    {

        $tmp_table_name = $this->createTemporaryTable($cc, $determinand, $period_from, $period_to);
        // f. updata polje const_ts_length in izračuna prave dolžine čas.serij glede na podani gap
        $this->calculateCorrectLengthOfTimeSeries($tmp_table_name, $period_from, $period_to, $allowable_gap);

        // sestavi SQL
        $sql = "
            SELECT RBDcode,
        ";

        $counter = 1;
        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " COUNT(DISTINCT IF(const_ts_length = " . $counter . ", NationalStationID, NULL)) AS '" . $counter++ . " yr',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql .= "
            FROM " . $tmp_table_name . "
            GROUP BY RBDcode
        ";

        $this->showSQLforDebug($sql);

        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow;
        }
        return $output;

    }


    /**
     * dobi povprečja za determinand, državo in periodo
     *
     * @param $cc
     * @param $determinand
     * @param $period_from
     * @param $period_to
     * @param $ts_length        dolžina čas.serije
     *
     * @return array
     */
    public function getAveragesForDeterminand($cc, $determinand, $period_from, $period_to, $ts_length, $allowable_gap)
    {

        global $tabela_dataset;

        $tmp_table_name = $this->createTemporaryTable($cc, $determinand, $period_from, $period_to);
        // f. updata polje const_ts_length in izračuna prave dolžine čas.serij glede na podani gap
        $this->calculateCorrectLengthOfTimeSeries($tmp_table_name, $period_from, $period_to, $allowable_gap);

        // sestavi SQL
        $sql = "
            SELECT 'No of stations' AS '',
        ";
        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " COUNT(DISTINCT IF(n.Year = " . $ii . ", NationalStationID, NULL)) AS '" . $ii . "',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql_common = "
            FROM " . $this->tableName . " AS n

            WHERE 1
            AND CountryCode = '" . $cc . "'
            AND " . $this->determinandName . " = '" . addslashes($determinand) . "'
            AND `Year` BETWEEN '" . $period_from . "' AND '" . $period_to . "'
        ";

        $sql_common .= $this->aggregationSubQuery;
        $sql_common .= $this->sql_excluded_nutrients_hazsubs;

        $sql_common .= "
            # only stations with timeseries longer than N
            AND NationalStationID IN (SELECT NationalStationID FROM " . $tmp_table_name . " WHERE const_ts_length >= " . $ts_length . ")
        ";

        $sql .= $sql_common;

        $sql .= "
            UNION
            SELECT 'Average' AS '',
        ";

        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " ROUND(AVG(IF(n.`Year` = " . $ii . ", Mean, null)), 4)  AS '" . $ii . "',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql .= $sql_common;

        $this->showSQLforDebug($sql);

        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow;
        }
        return $output;

    }

    /**
     * Number of river stations per determinand/year for selected country
     *
     * @param $cc
     * @param $period_from
     * @param $period_to
     * @return array
     */
    public function getNoOfStationsForDeterminandYear($cc, $period_from, $period_to)
    {
        global $tabela_dataset;

        $sql = "
            SELECT t.Group, n." . $this->determinandName . ",
        ";

        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " COUNT(DISTINCT IF(n.`Year` = " . $ii . ", NationalStationID, null)) AS '" . $ii . "',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql .= "
            , COUNT(DISTINCT NationalStationID) AS 'Total'
            FROM " . $this->tableName . " AS n
        ";

        $sql .= $this->determinandJoinSubQuery;

        $sql .= "
            WHERE 1
            AND n.CountryCode = '" . $cc . "'
            AND n.Year BETWEEN '" . $period_from . "' AND '" . $period_to . "'
        ";

        $sql .= $this->aggregationSubQuery;

        $sql .= $this->sql_excluded_nutrients_hazsubs;

        $sql .= "
            GROUP BY n." . $this->determinandName . "
        ";

        $sql .= $this->determinandOrder;

        $this->showSQLforDebug($sql);

        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow;
        }
        return $output;
    }


    /**
     *  Number of measurements per river station/year for selected country
     *
     * @param $cc
     * @param $period_from
     * @param $period_to
     * @return array
     */
    public function getNoOfMeasurementsForStationYear($cc, $period_from, $period_to)
    {
        global $tabela_dataset;
        global $wise_soe_category;

        switch ($wise_soe_category) {
            case 'lakes':
                $name_attribute = 'LakeName';
                break;
            default:
                $name_attribute = 'RiverName';
                break;
        }

        $sql = "
            SELECT s.RBDcode, s.RBDname, s." . $name_attribute . ", s.WaterBodyID, s.WaterBodyName, s.NationalStationID, s.NationalStationName,
        ";

        for ($ii = $period_from; $ii <= $period_to; $ii++) {
            $sql .= " COUNT(IF(n.`Year` = " . $ii . ", n.Mean, NULL)) AS '" . $ii . "',\n";
        }
        $sql = substr($sql, 0, -2);

        $sql .= "
            , COUNT(n.Mean) AS 'Total " . $period_from . "-" . $period_to . "'
            FROM " . $tabela_dataset['stations'] . " AS s
            INNER JOIN " . $this->tableName . " AS n USING(CountryCode, NationalStationID)

            WHERE 1
            AND n.CountryCode = '" . $cc . "'
            AND n.Year BETWEEN '" . $period_from . "' AND '" . $period_to . "'
            # TODO: tukaj mogoče omejit na determinand
            # AND " . $this->determinandName . " = 'Nitrate'
        ";

        $sql .= $this->aggregationSubQuery;
        $sql .= $this->sql_excluded_nutrients_hazsubs;
        $sql .= $this->sql_excluded_stations;

        $sql .= "
            GROUP BY n.NationalStationID
        ";

        $this->showSQLforDebug($sql);

        $output = array();

        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        while ($myrow = $result->fetch_assoc()) {
            $output[] = $myrow;
        }
        return $output;
    }

} 
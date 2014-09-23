<?php

/**
 * skupni class za vse kar se tiče baze, povezava, error reporting, ...
 *
 * @author marko
 *
 */
class db_common
{

    protected $mysqli;

    public $tableName;

    /**
     * @brief   konstruktor se poveže na mysql bazo
     *
     */
    public function __construct()
    {
        $this->mysqli = new mysqli($GLOBALS['host'], $GLOBALS['dbuser'], $GLOBALS['dbpass'], $GLOBALS['database'], $GLOBALS['port']);

        if ($this->mysqli->connect_errno) {
            printf("Connect failed: %s\n", $this->mysqli->connect_error);
            exit();
        }

        $this->mysqli->query("SET NAMES 'utf8'");

        $this->tableName = $GLOBALS['table'];

        //echo "<pre>". $GLOBALS['database'] ."</pre>";
    }

    /**
     * izpiše mysql error na ekran
     *
     * @param $file
     * @param $class
     * @param $line
     * @param $mysql_error
     * @param $sql
     */
    public function error_output($file, $class, $line, $mysql_error, $sql)
    {

        // pokličemo globalno spr. online
        global $online;

        // če je offline izpiše poln error, online pa samo številko vrstice
        if (!$online) {
            echo "<br>";
            echo "<span class='mismatch'>";
            echo "File: ".$file."<br>Class: ".$class."<br>Line: " . $line;
            echo "<br>";
            echo $mysql_error;
            echo "<br>";
            echo "<pre>";
            echo $sql;
            echo "</pre>";
            echo "</span>";
        } else {
            echo "<span class='mismatch'>ERROR: " . $line . "</span>";
        }
    }

    /**
     * za timer v sekundah, pobrano iz: http://php.net/manual/en/function.microtime.php
     *
     * Uporaba:
     * $timer_start = microtime_float();
     *      // delovanje
     * $timer_end = microtime_float();
     * $execution_time = round($timer_end - $timer_start, 4);
     *
     *
     * @return float
     */
    public function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * samo izvede query (svoja f. zato da lahko merim hitrost izvajanja)
     *
     * @param $sql
     *
     * @return bool|\mysqli_result
     */
    protected function executeMySQLQuery($sql) {

        global $sql_debug_mode;

        if($sql_debug_mode)     $timer_start = $this->microtime_float();
        $result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));

        if($sql_debug_mode) {
            $timer_end = $this->microtime_float();
            $execution_time = round($timer_end - $timer_start, 4);
        }

        if($sql_debug_mode)      echo $sql."<br>time: ".$execution_time."<br><br>";

        return $result;
    }

    /**
     * vrne array vrednosti iz vstopnega sql stavka
     *
     * @param $sql
     * @return array
     */
    protected function createArrayFromSQL($sql) {
        //$result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        $result = $this->executeMySQLQuery($sql);

        //$rows = $result->num_rows;
        $rows = 0;
        $fields = $result->field_count;

        while ($myrow = $result->fetch_row()) {
            for($ii = 0; $ii < $fields; $ii++) {
                $output[$rows][$result->fetch_field_direct($ii)->name] = $myrow[$ii];
            }
            $rows++;
        }
        return $output;
    }


    /**
     * vrne enodimenzionalni array vrednosti iz vstopnega sql stavka
     *
     * @param $sql
     * @return array
     */
    protected function createOneDimensionalArrayFromSQL($sql) {
        //$result = $this->mysqli->query($sql) or die($this->error_output(__FILE__, __CLASS__, __LINE__, $this->mysqli->error, $sql));
        $result = $this->executeMySQLQuery($sql);

        //$rows = $result->num_rows;
        $rows = 0;
        $fields = $result->field_count;

        while ($myrow = $result->fetch_row()) {
            for($ii = 0; $ii < $fields; $ii++) {
                $output[$rows] = $myrow[$ii];
            }
            $rows++;
        }
        return $output;
    }

    /**
     * dobi eno vrednosti iz SQLa
     *
     * @param $sql
     * @return mixed
     */
    protected function getOneValueFromSQL($sql) {
        $result = $this->executeMySQLQuery($sql);
        $myrow = $result->fetch_row();

        return $myrow[0];
    }

    /**
     * @brief   destruktor prekine povezave z bazo
     */
    public function __destruct()
    {
        $thread = $this->mysqli->thread_id;
        $this->mysqli->kill($thread);
        $this->mysqli->close();
    }

}

?>

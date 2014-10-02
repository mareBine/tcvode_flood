<?php
/**
 * Created by PhpStorm.
 * User: marko
 * Date: 23.9.2014
 * Time: 10:11
 */

class flood extends db_common
{

    public function getAllFloodEventsForCountry($cc)
    {

        $sql = "
            SELECT *
            from " . $this->tableName . "
            where cc = '" . $cc . "'
            order by StartDate
        ";

        return $this->executeMySQLQuery($sql);

    }

    public function recursiveSearchOverlappedEvents($cc, $startDate, $endDate, $eventCode, &$updateSql)
    {
        $sql = "
            select *
            from " . $this->tableName . "
            where cc = '" . $cc . "'
            and (
                # prekrivanje
                StartDate between '" . $startDate . "' and '" . $endDate . "'
                OR
                EndDate between '" . $startDate . "' and '" . $endDate . "'
                OR
                # čista vsebovanost (manjši v večjem)
                (StartDate < '" . $endDate . "' and EndDate > '" . $startDate . "')
                OR
                # čista pokritost (večji nad manjšim)
                (StartDate > '" . $startDate . "' and EndDate < '" . $endDate . "')
            )
            and FloodEventCode NOT IN ('" . implode("','", $eventCode) . "')
        ";

        //if($eventCode[0] == 'PL_2000_P_2010_0098_0713')            echo "<pre>".$sql."</pre>";
        //var_dump($eventCode);

        $result = $this->executeMySQLQuery($sql);
        $myrow = $result->fetch_array();

        //echo "<br>numrows: " . $result->num_rows;

        if ($result->num_rows > 0) {
            //echo " ---------> " . $myrow['id']  . " ";
            $eventCode[] = $myrow['FloodEventCode'];
            $eventCode = array_unique($eventCode);
            $this->recursiveSearchOverlappedEvents($cc, $myrow['StartDate'], ($endDate > $myrow['EndDate'] ? $endDate : $myrow['EndDate']), $eventCode, $updateSql);
        } else {
            if (sizeof($eventCode) > 1) {
                echo " -----------------> " . $endDate . " ";
                echo "(" . sizeof($eventCode) . ")";

                $updateSql = "UPDATE " . $this->tableName . " SET " . $this->columnNameTL . " = '" . $endDate . "' ";
            }

        }

        return $result;

    }

    /**
     * iterativna procedura, ki poišče vse overlapped evente
     *
     * @param $cc
     * @param $startDate
     * @param $endDate
     * @param $eventCode
     */
    public function iterativeSearchOverlappedEvents($cc, $startDate, $endDate, $eventCode)
    {

    }

    public function createTableIterative($cc)
    {

        // type = 'create', da kreira tabelo
        $tableName = "tmp_fp_periods_" . $cc;
        $sql_drop = "DROP TABLE IF EXISTS " . $tableName;
        $this->executeMySQLQuery($sql_drop);

        $sql = "
            CREATE TABLE IF NOT EXISTS " . $tableName . " (
                event_id INT(10),
                per_start DATE,
                per_end DATE,
                noOfEvents INT(10)
            );
        ";
        $this->executeMySQLQuery($sql);

        return $tableName;

    }

    public function iterativeGetAllEvents($tableName)
    {
        $sql = "SELECT * FROM " . $tableName;
        return $this->createArrayFromSQL($sql);

    }

    /**
     * končna poizvedba, ki dobi vse prekrite evente
     *
     * @param $cc
     * @param string $type
     * @return bool
     *
     */
    public function recursiveGetMultipleEventsForCountry($cc, $type = 'view')
    {

        $sql = "";

        // type = 'create', da kreira tabelo
        if ($type === 'create') {
            $tableName = "tmp_recursive_" . $cc . "_floodPeriods";
            $sql_drop = "DROP TABLE IF EXISTS " . $tableName;
            $this->executeMySQLQuery($sql_drop);

            $sql .= "CREATE TABLE IF NOT EXISTS " . $tableName;
        }

        $sql .= "
            SELECT
                MIN(StartDate) AS timeLineStart,
                " . $this->columnNameTL . ",
                COUNT(*) AS NoOfEvents,
                GROUP_CONCAT(DISTINCT " . $this->FloodEventCode . ") AS eventCodes
            FROM " . $this->tableName . "
            WHERE cc = '" . $cc . "'
            GROUP BY " . $this->columnNameTL . "
        ";

        if ($type === 'create') {
            $this->executeMySQLQuery($sql);
            return $tableName;
        } else {
            return $this->createArrayFromSQL($sql);
        }

    }

    /**
     * preveri če v tabeli obstaja iskani column
     *
     * @param $columnName
     * @return bool
     */
    public function checkIfColumnExists($columnName)
    {
        $sql = "
            SELECT *
            FROM information_schema.columns
            WHERE table_schema = database()
            and COLUMN_NAME = '" . $columnName . "'
            AND table_name = '" . $this->tableName . "'
        ";

        $result = $this->executeMySQLQuery($sql);
        if ($result->num_rows > 0) return true;
        else                        return false;
    }


    /**
     * kreira column v tabeli
     *
     * @param $columnName
     * @param $columnDesc
     * @return bool|mysqli_result
     */
    public function createTableColumn($columnName, $columnDesc)
    {

        echo "- creating " . $columnName . " column ...<br>";

        $sql = "
            ALTER TABLE " . $this->tableName . "
            ADD COLUMN " . $columnName . " " . $columnDesc . ";
        ";

        return $this->executeMySQLQuery($sql);

    }

    /**
     * naredi update timeLineEnd columns (na EndDate)
     *
     * @param $columnName
     * @return bool|mysqli_result
     */
    public function setTimeLineEndColumn($columnName)
    {

        echo "- updating " . $columnName . " column ...<br>";

        $sql = "
            UPDATE " . $this->tableName . "
            SET " . $columnName . " = EndDate
        ";

        return $this->executeMySQLQuery($sql);
    }

    public function updateTimeLineEndColumn($cc, $startDate, $endDate)
    {

        echo "- updating " . $cc . " [" . $startDate . "] [" . $endDate . "] column ...<br>";

//        $sql = "
//            UPDATE ".$this->tableName."
//            SET ".$columnName." = EndDate
//        ";

        //return $this->executeMySQLQuery($sql);
    }

}
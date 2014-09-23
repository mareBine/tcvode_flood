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
            select StartDate, EndDate, FloodEventCode
            from " . $this->tableName . "
            where cc = '" . $cc . "'
            order by StartDate
        ";

        return $this->executeMySQLQuery($sql);

    }

    public function recursiveSearchOverlappedEvents($cc, $startDate, $endDate, $eventCode)
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

        //if($eventCode[0] == 'SI3_198907B')            echo "<pre>".$sql."</pre>";
        //var_dump($eventCode);

        $result = $this->executeMySQLQuery($sql);
        $myrow = $result->fetch_array();

        //echo $result->num_rows;

        if ($result->num_rows > 0) {
            //echo " -----------------> " . $startDate  . " ";
            $eventCode[] = $myrow['FloodEventCode'];
            $eventCode = array_unique($eventCode);
            $this->recursiveSearchOverlappedEvents($cc, $myrow['StartDate'], ($endDate > $myrow['EndDate'] ? $endDate : $myrow['EndDate']), $eventCode);
        } else {
            if (sizeof($eventCode) > 1) {
                //echo "STOP ";
                //echo $startDate . " - ";
                //echo "end: " . $myrow['EndDate'];
                echo " -----------------> " . $endDate  . " ";
                echo "(" . sizeof($eventCode) . ")";
                //print_r($eventCode);
                //echo "<br>";

                // UPDATE atributa timeLineEnd
            }

        }


        return $result;


    }

}
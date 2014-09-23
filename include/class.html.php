<?php
/**
 * helper class za HTML
 *
 * User: marko
 * Date: 18.4.2014
 * Time: 16:20
 */

/** @noinspection PhpUndefinedClassInspection */
class html
{

    /**
     * odpre html file in vse kar je v headu
     *
     * @param $title    string
     * @param $meta_charset string
     * @param $css      array
     * @param $script   array
     */
    public function __construct($title, $meta_charset, $css, $script)
    {
        echo "<html>";
        echo "<head>";
        echo "<title>" . $title . "</title>";
        echo "<META http-equiv='Content-Type' content='text/html; charset=" . $meta_charset . "'>";
        foreach ($css as $key => $value) {
            echo "<link href='" . $value . "' rel='stylesheet' type='text/css' />";
        }
        foreach ($script as $key => $value) {
            echo "<script src='" . $value . "' type='text/JavaScript'></script>";
        }
        echo "</head>";
        echo "<body>";
    }

    /**
     * nariše tabelo, array $data ima vhodne podatke
     *
     * @param $data     array s podatki
     * @param $id_table       id tabele
     * @param string $class css class
     */
    public function drawTableFromArray($data, $id_table, $showTotal = 'showTotal', $class = '')
    {

        $sum = array();

        echo "<table";
        if ($id_table != '') echo " id='" . $id_table . "'";
        if ($class != '') echo " class='" . $class . "'";
        echo ">";
        //echo "<caption>test</caption>";

        if ($data) {
            echo "<thead>";
                echo "<tr>";
                    foreach ($data[0] as $headerkey => $row_value) {
                        echo "<th>" . $headerkey . "</th>";
                        $sum[$headerkey] = 0;
                    }
                echo "</tr>";
            echo "</thead>";

            echo "<tbody>";
            foreach ($data as $rowkey => $row_value) {
                echo "<tr>";
                $column_number = 0;
                foreach ($row_value as $colkey => $col_value) {
                    if($column_number == 0)    echo "<th>";
                    else                echo "<td>";
                    //echo "<td>" . $col_value . "</td>";
                        echo $col_value;
                    if($column_number == 0)    echo "</th>";
                    else                echo "</td>";
                    $sum[$colkey] += $col_value;
                    $column_number++;
                }
                echo "</tr>";
            }
            echo "</tbody>";


//            echo "<pre>" . print_r($data[0], true) . "</pre>";
//            echo key($data[0]);
//            echo "<pre>" . print_r($sum, true) . "</pre>";

            // da prikaže spodaj vrstico total z seštevki stolpcev
            if ($showTotal == 'showTotal') {
                $sum[key($data[0])] = 'Total';
                echo "<tr>";
                foreach ($sum as $sumkey => $sum_value) {
                    echo "<td>" . $sum_value . "</td>";
                }
                echo "</tr>";
            }


        } else {
            echo "<tr><td>No data for these parameters</td></tr>";
        }
        echo "</table>";
    }

    /**
     * nariše select box, array $data ima vhodne podatke
     *
     * @param $data
     * @param $selectedValue
     * @param $id
     * @param string $onchange
     */
    public function drawSelectBoxFromArray($data, $selectedValue, $id, $onchange = '', $class = '')
    {
        echo "<select id='" . $id . "' name='" . $id . "' onchange='" . $onchange . "' class='" . $class . "'>\n";
        foreach ($data as $key => $value) {
            echo '<option value="' . ($value) . '"';
            if ($value == $selectedValue) echo "selected";
            echo ">" . $value . "</option>\n";
        }
        echo "</select>\n";
    }


    /**
     * zapre html
     */
    public
    function __destruct()
    {
        echo "</body>";
        echo "</html>";
    }

} 
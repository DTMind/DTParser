<?php

/**
 * DTParser Class <http://www.dtmind.com>
 * get and parse file
 *
 * @version             version 1.00, 16/08/2016
 * @author		DTMind.com <develop@dtmind.com>
 * @author		Stefano Oggioni <stefano@oggioni.net>
 * @link 		https://github.com/DTMind/DTPDO
 * @link 		http://www.dtmind.com/
 * @license		This software is licensed under the MIT license, http://opensource.org/licenses/MIT
 *
 */
class DTParser {

    public static function download_ftp($file, $newfile, $ftp) {

        $ftp_server = $ftp['server'];
        $ftp_user = $ftp['user'];
        $ftp_pass = $ftp['pass'];

        #-- Inizio download

        $conn = ftp_connect($ftp_server) or die("Could not connect<br>");
        if (@ftp_login($conn, $ftp_user, $ftp_pass)) {
            echo "Connected as $ftp_user@$ftp_server<br>";
        } else {
            echo "Couldn't connect as $ftp_user<br>";
        }

        // prova a scaricare $server_file e a salvarlo su $local_file
        if (ftp_get($conn, $newfile, $file, FTP_BINARY)) {
            echo "Scrittura su $file terminata con successo<br>";
        } else {
            echo "Problemi nello scaricamento<br>";
        }


        unlink($file_check);
    }

    public static function download_file($file, $newfile) {
        #echo "$file > $newfile<br>";
        copy($file, $newfile);
    }

    public static function download_script($url, $newfile) {

        $fp2 = fopen($newfile, "wb");

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $data = curl_exec($curl);
        fwrite($fp2, $data);
        fclose($fp2);

        curl_close($curl);
    }

    public static function unzip($file = "") {
        #echo $file."<br>";

        $active_file = $file;

        $info = pathinfo($active_file);
        if ((strtolower($info['extension'])) == "zip") {
            echo "unzip<br>";
            $zip = new ZipArchive;

            if ($zip->open($file) === TRUE) {
                $zip->extractTo($info['dirname']);

                // Prendo solo il primo
                #for($i = 0; $i < $zip->numFiles; $i++) {
                $fileunzip = $zip->getNameIndex(0);
                $info2 = pathinfo($fileunzip);
                echo $filename . "<br>";
                $old_name = "{$info['dirname']}/{$fileunzip}";
                $active_file = "{$info['dirname']}/{$info['filename']}.{$info2['extension']}";
                #die($old_name."-".$new_name);
                rename($old_name, $active_file);
                #}                          

                $zip->close();
                echo 'ok';
            } else {
                echo 'failed';
            }

            unlink($file);
        } else if ((strtolower($info['extension'])) == "gz") {
            echo "gzip<br>";
            $active_file_new = "{$info['dirname']}/{$info['filename']}";
            system("gunzip {$active_file} $active_file_new");
            $active_file = $active_file_new;
        }

        return $active_file;
    }

    public static function parser_csv($file, $csv, $dbh, $fields_name) {
        
        $handle = @fopen($file, "r");

        $data_header = array();
        $data_row = array();

        if (!$handle) {
            echo "Error opening file: {$file}";
        } else {

            $row = -1;
            while (($data = fgetcsv($handle, $csv['lenght'], $csv['delimiter'], $csv['enclosure'])) !== FALSE) {
                $num = count($data);

                if ($row == -1) {
                    for ($c = 0; $c < $num; $c++) {
                        $data_header[$c] = $data[$c];
                    }
                } else {
                    for ($c = 0; $c < $num; $c++) {
                        $data_row[$row][$data_header[$c]] = $data[$c];
                    }
                    $prepared_line = self::prepareLineProduct($data_row[$row], $fields_name);
                    $dbh->insertRow("adm_sync_raw_data_products",$prepared_line);
                    #myprint_r($prepared_line, 1);
                }

                $row++;
            }
            fclose($handle);
        }

        #return $data_row;
    }

    public static function parser_xml($file) {

        $xml = file_get_contents($file);
        return json_decode(json_encode((array) simplexml_load_string($xml)), true);
    }

    private static function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return self::getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }

    public static function parser_excel($file) {

        $data_header = array();
        $data_row = array();

        $Reader = PHPExcel_IOFactory::createReaderForFile($file);
        $Reader->setReadDataOnly(true);
        $objXLS = $Reader->load($file);

        $max_col = 100;
        $max_row = 10000;

        for ($i = 0; $i < $max_col; $i++) {
            $cellname = self::getNameFromNumber($i) . "1";
            $value = $objXLS->getSheet(0)->getCell($cellname)->getValue();

            if ($value == "")
                break;

            #echo $cellname.":".$value."<br>";
            $data_header[$i] = $value;
        }
        $num_col = $i;


        for ($k = 0; $k < $max_row; $k++) {
            for ($i = 0; $i < $num_col; $i++) {
                $cellname = self::getNameFromNumber($i) . $k;
                $value = $objXLS->getSheet(0)->getCell($cellname)->getValue();

                if (($i == 0) && ($value == ""))
                    break;

                #echo $cellname.":".$value."<br>";
                $data_row[$k][$data_header[$i]] = $value;
            }
        }


        $objXLS->disconnectWorksheets();
        unset($objXLS);

        return $data_row;
    }

    public static function archive($file) {

        $info = pathinfo($file);
        #myprint_r($info);
        $parse_name = explode("_", $file);

        $dir_type = $parse_name[0];
        if (!file_exists($dir_type)) {
            echo "Creazione directory {$dir_type}<br>";
            mkdir($dir_type);
        }

        $dir_year = "{$dir_type}/{$parse_name[1][0]}{$parse_name[1][1]}";
        if (!file_exists($dir_year)) {
            echo "Creazione directory {$dir_year}<br>";
            mkdir($dir_year);
        }

        $dir_month = "{$dir_year}/{$parse_name[1][2]}{$parse_name[1][3]}";
        if (!file_exists($dir_month)) {
            echo "Creazione directory {$dir_month}<br>";
            mkdir($dir_month);
        }

        rename($file, "{$dir_month}/{$info['basename']}");
    }

    static function prepareLineProduct($data, $fields_name) {

        // Controllo esistenza product_id
        if (IsSet($data[$fields_name["product_id"]])) {
            
            #myprint_r($fields_name,1);
            
            while (list($key, $value) = each($fields_name)) {
                if ($key=="product_extrafields") {
                    $tmp=explode("\r\n",$value);
                    $tmp2=array();
                    while (list(, $value1) = each($tmp)) {
                        if (IsSet($data[$value1])) {
                            $tmp2[$value1]=$data[$value1];
                        }
                    }
                    $line[$key]=DTJax::arrayToJson($tmp2);
                } else if ($key=="product_extrafields_lng") {
                    $tmp=explode("\r\n",$value);
                    $tmp2=array();
                    while (list(, $value1) = each($tmp)) {
                        if (IsSet($data[$value1])) {
                            $tmp2[$value1]=$data[$value1];
                        }
                    }
                    $line[$key]=DTJax::arrayToJson($tmp2);
                } 
                elseif (IsSet($data[$value])) {      
                    $line[$key]=$data[$value];
                }
                
            }

            // SETTO I VALORI DI DEFAULT
            
            // product_type
            if (!IsSet($line["product_type"])) {
                $line["product_type"]="PHYSICAL";
            }
            
            // condition
            if (!IsSet($line["condition"])) {
                $line["condition"] = "NEW";
            }

            // tax
            #if (!IsSet($line["tax"])) {
            #    $line["tax"] = 22;
            #}            
            
            #myprint_r($line,1);
            
            return $line;
            
        } else {
            #DTPage::setMessage("Errore: product_id necessario", "alert");
            return -1;
        }
    }

}

?>
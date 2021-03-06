<?php

    /*
    *
    * (c) ILYES ABDELRAZAK BELADEL <ia.beladel@gmail.com>
    *
    * For the full copyright and license information, please view the LICENSE
    * file that was distributed with this source code.
    */




    /**
     * Download a file while showing the progress bar
     * 
     * @param   string      URL file
     * @param   string      (optional) save the downloaded file as $save_name
     * @return  integer     1 on success, 0 on failure
     */
    function download_file_progress($remote_file, $save_name = '') 
    {
        echo "Retrieving http header... " . PHP_EOL;
        $header = get_headers($remote_file);
        //echo json_encode($header, JSON_PRETTY_PRINT);
        $file_length = array_key_last(preg_grep('/\bLength\b/i', $header));
        $file_length = intval(explode(' ', $header[ $file_length ])[1]);
        $file_name = array_key_last(preg_grep('/\bContent-Disposition\b/i', $header));
        $file_name = (empty($save_name)) ? explode('filename=', $header[ $file_name ])[1] : $save_name;
        $http = substr($header[0], 9, 3);
        if($http == 200 || $http == 302)
        {
            echo PHP_EOL;
            echo " Target size: " . floor($file_length / (1000 * 1024)) . " Mo || " . floor($file_length / 1000) . " Kb";
            $t = explode("/", $remote_file);
            $remote = fopen($remote_file, 'r');
            $local = fopen($file_name, 'w');
            $read_bytes = 0;  
            $pp = 0;
            while(!feof($remote)) {
                $buffer = fread($remote, $file_length);
                fwrite($local, $buffer);
                $read_bytes += strlen($buffer);
                $progress = (100 * $read_bytes) / $file_length;
                
                // Progress bar width
                $shell = 20; 
                $rt = $shell * $progress / 100;
                //$rt = $shell * $read_bytes / $file_length;
                echo " \033[35;2m\e[0m Downloading " . $file_name . ": [" . round($progress, 2) . "%] " . floor((($read_bytes/1024))) . "Kb ";
                if ($pp === $shell) $pp = 0;
                if ($rt === $shell) $rt = 0;
                echo str_repeat("█", $rt) . str_repeat("=", ($pp++)) . ">@\r";
                usleep(1000);
            }
    
            echo " \033[35;2m\e[0m" . $file_name . " done [100%] " . floor((($file_length / 1000) / 1000)) . " Mo || " . floor(($file_length / 1000)) . " Kb   \r" . PHP_EOL;
            fclose($remote);
            fclose($local);

            return 1;
        }
        
        return 0;
    }

 ?>
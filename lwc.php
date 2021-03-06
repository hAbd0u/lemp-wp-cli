<?php

    /*
    *
    * (c) ILYES ABDELRAZAK BELADEL <ia.beladel@gmail.com>
    *
    * For the full copyright and license information, please view the LICENSE
    * file that was distributed with this source code.
    */

    require_once 'vendor/autoload.php';

    use mikehaertl\shellcommand\Command;



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



    /**
     * Install docker and docker-compose on Ubuntu system
     * 
     * @return  integer     1 on success, 0 on failure
     */
    function install_docker_dcompose()
    {
        echo 'Updating the package lists' . PHP_EOL;
        $command = new Command();
        $command->setCommand( 'sudo apt-get update' );
        if ($command->execute()) {
            $result = $command->getOutput();
            echo $result . PHP_EOL;
            echo '=====================================================' . PHP_EOL;
            if( strstr( $result, 'Reading package lists...' ) === false )
            {
                echo 'Fail to update the package lists' . PHP_EOL; 
                echo 'Error: ' . $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;
                echo 'Exiting the script from line: ' . __LINE__ . PHP_EOL;
                return 0;
            }
            
            echo 'Installing necessary packages for docker and docker-composer' . PHP_EOL;
            $command->setCommand( 'sudo apt-get install -y apt-transport-https ca-certificates curl gnupg' );
            if ( $command->execute() ) {
                $result = $command->getOutput();
                echo $result . PHP_EOL;
                echo '=====================================================' . PHP_EOL;
                if( strstr( $result, 'Processing triggers for man-db' ) !== false 
                    || preg_match( '/[0-9]+ upgraded, [0-9]+ newly installed, [0-9]+ to remove/', $result ) )
                {
            		$command->setCommand( 'sudo rm -f /usr/share/keyrings/docker-archive-keyring.gpg && curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg' );
            		$command->execute();
            		$command->setCommand( 'echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null' );
            		$command->execute();
                    $result = $command->getOutput();
                    echo 'Installing docker repository. ' . $result . PHP_EOL;
                    echo '=====================================================' . PHP_EOL;
                    echo 'Updating the package lists' . PHP_EOL;
                    echo '=====================================================' . PHP_EOL;
                    $command->setCommand( 'sudo apt-get update' );
                    if ( $command->execute() ) {
                        $result = $command->getOutput();
                        echo $result . PHP_EOL;
                        echo '=====================================================' . PHP_EOL;
                        if( strstr( $result, 'Reading package lists...' ) === false )
                        {
                            echo 'Fail to update the package lists' . PHP_EOL; 
                            echo 'Error: ' . $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;

                            echo 'Cleaning up any installed packages' . PHP_EOL;
                            $command->setCommand( 'sudo rm -f /usr/share/keyrings/docker-archive-keyring.gpg' );
                            $command->execute();

                            echo 'Exiting the script from line: ' . __LINE__ . PHP_EOL;
                            return 0;
                        }
                    }    
                        
                    echo 'Installing docker and docker-composer' . PHP_EOL;
                    $command->setCommand( 'sudo apt-get install -y docker-ce docker-ce-cli containerd.io' );
                    if ( $command->execute() ) {
                        $result = $command->getOutput();
                        if( strstr( $result, 'Processing triggers for man-db' ) !== false 
                            || preg_match( '/[0-9]+ upgraded, [0-9]+ newly installed, [0-9]+ to remove/', $result ) )
                        {
                            echo $result . PHP_EOL;
                            echo '=====================================================' . PHP_EOL;

                            $command->setCommand( 'echo "https://github.com/docker/compose/releases/download/1.28.5/docker-compose-$(uname -s)-$(uname -m)"' );
                            $command->execute();
                            $download_link = $command->getOutput();
                            $result = download_file_progress($download_link, 'docker-compose');
                            if($result == 1) {
                                $command->setCommand( 'sudo chmod +x docker-compose && sudo mv docker-compose /usr/local/bin/' );
                                if ( $command->execute() ) {
                                    echo 'docker and docker-composer installed successfully!' . PHP_EOL;
                                    return 1;
                                } else {
                                    echo 'Fail to set docker-composer execute permission' . PHP_EOL; 
                                    echo $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;
                                }
                            } else {
                                echo 'Fail to download docker-composer' . PHP_EOL; 
                                echo $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;
                                uninstall_docker_dcompose();
                                echo 'Exiting the script from line: ' . __LINE__ . PHP_EOL;
                                return 0;
                            }
                        }
                    } else {
                        echo 'Fail to install docker and docker-composer' . PHP_EOL; 
                        echo 'Error: ' . $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;
                        echo 'Cleaning up any installed packages' . PHP_EOL;
                        uninstall_docker_dcompose();
                        echo 'TIP: Reboot the system and try again.' . PHP_EOL;
                        echo 'Exiting the script from line: ' . __LINE__ . PHP_EOL;
                        return 0;
                    }
                }
            } else {
                echo 'Fail to install necessary packages for  docker and docker-composer' . PHP_EOL; 
                echo 'Error: ' . $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;
                echo 'Exiting the script from line: ' . __LINE__ . PHP_EOL;
                return 0;
            }
        } else {
            echo 'Fail in updating the package lists' . PHP_EOL; 
            echo 'Error: ' . $command->getError() . PHP_EOL . 'Exit code: ' . $command->getExitCode() . PHP_EOL;
            echo 'Exiting the script from line: ' . __LINE__ . PHP_EOL;
            return 0;
        }
    }



    /**
     * Uninstall docker and docker-compose on Ubuntu system
     * 
     */
    function uninstall_docker_dcompose() 
    {
        echo 'Uninstalling docker and docker compose' . PHP_EOL;
        echo '=====================================================' . PHP_EOL;

        $command = new Command();
        $command->setCommand('sudo rm -f /usr/share/keyrings/docker-archive-keyring.gpg');
        $command->execute();

        $command->setCommand('sudo apt-get purge -y docker-ce docker-ce-cli containerd.io');
        $command->execute();
        $result = $command->getOutput();
        echo $result . PHP_EOL;

        $command->setCommand('sudo rm -rf /var/lib/docker && sudo rm -rf /var/lib/containerd');
        $command->execute();

        $command->setCommand('sudo rm -f docker-compose && sudo rm -f /usr/local/bin/docker-compose');
        $command->execute();
    }



    /**
     * Check whether docker and docker-compose are installed or not
     * 
     * @return  bool        true on success, false on failure
     */
    function is_docker_dcompose_installed() 
    {
        $command = new Command();
        $command->setCommand('docker -v');
        $command->execute();
        $docker = $command->getOutput();

        $command->setCommand('docker-compose -v');
        $command->execute();
        $docker_compose = $command->getOutput();

        if(preg_match('/Docker version/', $docker) != 1 || preg_match('/docker-compose version/', $docker_compose) != 1)
            return false;

        return true;
    }



    /**
     * Check whether is valid or not
     * 
     * @return  bool        true on success, false on failure
     */
    function is_valid_url($url)
    {
        $pattern = '/^(http(s)?:\/\/)?(www.)?([a-zA-Z0-9])+([\-\.]{1}[a-zA-Z0-9]+)*\.[a-zA-Z]{2,5}(:[0-9]{1,5})?(\/[^\s]*)?$/';
        if(preg_match($pattern, $url) === 1)
            return true;

        return false;
    }

 ?>
<?php

    /*
    *
    * (c) ILYES ABDELRAZAK BELADEL <ia.beladel@gmail.com>
    *
    * For the full copyright and license information, please view the LICENSE
    * file that was distributed with this source code.
    */

    require_once __DIR__ . '/vendor/autoload.php';

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
     * @return  string      A system account user in order to run docker with no root privileges.
     * @return  integer     1 on success, 0 on failure
     */
    function install_docker_dcompose($user_account)
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
                                    $command->setCommand( 'sudo usermod -aG ' . $user_account );
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
     * Check whether a user account exist or not
     * 
     * @return  string      A system account user in order to run docker with no root privileges.
     * @return  bool        true if it does exist, false otherwise, prints a message if there is an error
     */
    function is_user_exist($user_account)
    {
        $command = new Command();
        $command->setCommand('id ' . $user_account);
        $result = $command->execute();
        if ($result && strstr($command->getOutput(), $user_account) !== false)
            return true;

        if(strstr($command->getError(), 'no such user') !== false
            || strstr($command->getOutput(), 'no such user') !== false)
            return false;
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



    /**
     * Get all current active listenning ports
     * 
     * @return  bool|array    false on failure, array of active ports on success
     */
    function get_listening_ports()
    {
        $command = new Command();
        $command->setCommand('netstat -anp4');
        if(!$command->execute())
            return false;
        
        $lines = explode("\n", $command->getOutput());
        $listening_ports = [];
        foreach($lines as $line) 
        {
            $line = preg_replace('/\s\s+/', ' ', $line);
            $line = explode(' ', $line);
            if($line[5] == 'LISTEN')
            {
                $listening_ports[] = explode(':', $line[3])[1];
            }
        }

        return $listening_ports;
    }



    /**
     * Get an available port
     * 
     * @return  integer        0 failure, > 10000 on success
     */
    function reserve_port(array $current_ports)
    {
        $port = 0;
        do
        {
            $port = rand(10000, 65353);
        }
        while(in_array($port, $current_ports));

        return ($port > 10000) ? $port : false;
    }



    /**
     * Copy an entire directory or file to a specified destination
     * 
     * @param   string      source file or dir
     * @param   string      destination file or dir
     */
    function xcopy($src, $dest)
    {
        if (!file_exists($dest))
            mkdir($dest);

        foreach (scandir($src) as $file) 
        {
            $src_file = rtrim($src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            $dest_file = rtrim($dest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            if (!is_readable($src_file))
                continue;

            if ($file != '.' && $file != '..') 
            {
                if (is_dir($src_file)) 
                {
                    if (!file_exists($dest_file))
                        mkdir($dest_file);
                        
                    xcopy($src_file, $dest_file);
                } 
                else
                    copy($src_file, $dest_file);
            }
        }
    }



    /**
     * Recursively delete a file or directory.  Use with care!
     *
     * @param string $path
     */
    function recursive_remove($path) 
    {
        if (is_dir($path)) 
        {
            foreach (scandir($path) as $entry) 
            {
                if (!in_array($entry, ['.', '..']))
                    recursive_remove($path . DIRECTORY_SEPARATOR . $entry);
            }

            rmdir($path);
        } 
        else
            unlink($path);
    }



    /**
     * Recursively set the owner of a path to a user
     * 
     * @param string    $path      Full path of directory
     * @param string    $group     Group owner
     * @param string    $user      User owner
     * @param string    $mode      access mode
     */
    function rchown($path, $group, $user, $mode)
    {
        $dir = rtrim($path, '/');
        if($items = glob($dir . '/*')) 
        {
            foreach ($items as $item) 
            {
                if (is_dir($item)) 
                {
                    (!empty($mode)) ? chmod($item, $mode) : '';
                    (!empty($user)) ? chown($item, $user) : '';
                    (!empty($group)) ? chgrp($item, $group) : '';
                    rchown($item, $group, $user, $mode);
                } else 
                {
                    (!empty($mode)) ? chmod($item, $mode) : '';
                    (!empty($user)) ? chown($item, $user) : '';
                    (!empty($group)) ? chgrp($item, $group) : '';
                }
            }
        }

        (!empty($mode)) ? chmod($path, $mode) : '';
        (!empty($user)) ? chown($path, $user) : '';
        (!empty($group)) ? chgrp($path, $group) : '';
    }



    /**
     * Add a host to /etc/hosts
     * 
     * @param bool|int    0 if the $ip:$host already exist, true on addition success, false if the file doesn't exist or not writable
     */
    function add_host($url, $ip)
    {
        $hosts_path = '/etc/hosts';
        if(is_readable($hosts_path) && is_writable($hosts_path))
        {
            $hosts = file_get_contents($hosts_path);
            if(preg_match('/' . $url . '/', $hosts) && preg_match('/' . $ip . '/', $hosts))
                return 0;

            $hosts .= "\n" . $ip . "\t\t" . $url . "\n";
            file_put_contents($hosts_path, $hosts);
            return true;
        }

        return false;
    }



    /**
     * Generate a random string
     * 
     * @param int   $length     How long the returned salt
     * @return      string      The returned salt
     */
    function random_salt($length) 
    {
        if($length < 8)
            $length = 8;
        
        $salt = '';
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+=-}{[}]\|;:<>?/ ';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $salt .= $keyspace[random_int(0, $max)];
        }
        
        return $salt;
    }



    /**
     * DockerComposeClient
     */
    class DockerComposeClient 
    {
        const COMPOSE_UP        = 0;
        const COMPOSE_BUILD     = 1;
        const COMPOSE_START     = 2;
        const COMPOSE_RESTART   = 3;
        const COMPOSE_STOP      = 4;
        const COMPOSE_REMOVE    = 5;
        const COMPOSE_KILL      = 6;
        const COMPOSE_LIST      = 7;
        const COMPOSE_PS        = 8;
        const COMPOSE_IP        = 9;
        const COMPOSE_EXEC      = 10;

        private $last_command = 0;
        private $execute_command = false;
        private $composer_file = '';
        private $site = '';
        private $command;

        /**
         * Constructor
         * 
         * @param   string      Site name of container services
         * @param   string      (optional) Full path of Dockercompose file
         * @throws  ComposerFileNotFoundException if the passed file does not exist
         */
        public function __construct($site, $composer_file = '')
        {
            if(!empty($composer_file) && file_exists($composer_file))
                $this->composer_file = $composer_file;
            else if(!empty($composer_file) && !file_exists($composer_file))
                throw new ComposeFileNotFoundException();

            $this->site = $site;
            $this->command = new Command(['command' => 'docker-compose', 'escapeArgs' => false]);
        }


        /**
         * Set the Dockercompose file
         * 
         * @param   string      Dockercompose file
         * @throws  ComposerFileNotFoundException if the passed file does not exist
         */
        public function setFile($composer_file)
        {
            if(file_exists($composer_file))
                $this->composer_file = $composer_file;
            else
                throw new ComposeFileNotFoundException();
        }


        /**
         * Get the Dockercompose file
         * 
         */
        public function getFile()
        {
            return $this->composer_file;
        }


        /**
         * Build if the images not exist and starts the services that defined in Dockercompose
         * 
         * @return  array
         */
        public function up()
        {
            $this->command->addArg('-f', $this->getFile());
            $this->command->addArg('up -d --force-recreate');

            $this->execute_command = $this->command->getExecCommand();
            $this->last_command = DockerComposeClient::COMPOSE_UP;
            return $this->execute($this->command);
        }


        /**
         * Starts the services that defined in Dockercompose
         * 
         * @return  array
         */
        public function start()
        {
            $this->command->addArg('-f', $this->getFile());
            $this->command->addArg('start');

            $this->execute_command = $this->command->getExecCommand();
            $this->last_command = DockerComposeClient::COMPOSE_START;
            return $this->execute($this->command);
        }


        /**
         * Stops the services that defined in Dockercompose
         * 
         * @return  array
         */
        public function stop()
        {
            $this->command->addArg('-f', $this->getFile());
            $this->command->addArg('stop');

            $this->execute_command = $this->command->getExecCommand();
            $this->last_command = DockerComposeClient::COMPOSE_STOP;
            return $this->execute($this->command);
        }


        /**
         * Stops the services that defined in Dockercompose and delete the images
         * 
         * @return  array
         */
        public function remove()
        {
            $this->command->addArg('-f', $this->getFile());
            $this->command->addArg('down -v');

            $this->execute_command = $this->command->getExecCommand();
            $this->last_command = DockerComposeClient::COMPOSE_REMOVE;
            return $this->execute($this->command);
        }


        /**
         * Execute a docker-compose command
         * 
         * @return  array
         */
        protected function execute($command)
        {
            $command->execute();
            return [
                'output' => $command->getExecuted() ? $command->getStdErr() : $command->getOutput(),
                'code' => $command->getExitCode()
            ];
        }


        /**
         * Get the last command executed
         * 
         * @return  array
         */
        public function getExecuteCommand()
        {
            return $this->execute_command;
        }


        /**
         * Show full information about the last command executed
         * 
         * @return  array
         */
        public function debug()
        {
            return [
                'Error' => $this->command->getError(),
                'StdErr' => $this->command->getStdErr(),
                'ExitCode' => $this->command->getExitCode(),
                'Executed' => $this->command->getExecuted(),
            ];
        }


        /**
         * Parse the output of docker-compose command
         * 
         * @return  array
         */
        public function parse($result)
        {
            switch($this->last_command)
            {
                case DockerComposeClient::COMPOSE_UP:
                    if ($result['code'] === 0)
                        return $this->parse_up_logs($result['output'], $this->site);
                    else
                        return $result['output'];
                break;
                case DockerComposeClient::COMPOSE_START:
                    if ($result['code'] === 0)
                        return $this->parse_start_logs($result['output'], $this->site);
                    else
                        return $result['output'];
                break;
                case DockerComposeClient::COMPOSE_STOP:
                    if ($result['code'] === 0)
                        return $this->parse_stop_logs($result['output'], $this->site);
                    else
                        return $result['output'];
                break;
                case DockerComposeClient::COMPOSE_REMOVE:
                    if ($result['code'] === 0)
                        return $this->parse_remove_logs($result['output'], $this->site);
                    else
                        return $result['output'];
                break;
                default:
                    return [];
                break;
            }
        }


        /**
         * Parse the output of docker-compose up command
         * 
         * @return  array
         */
        protected function parse_up_logs($logs, $site)
        {
            $services = [];
            $lines = explode("\n", $logs);
            foreach($lines as $line) {
                if(strstr($line, 'Creating ' . $site) != false || strstr($line, 'Recreating ' . $site) != false 
                    || strstr($line, 'Starting ' . $site) != false) 
                {
                    $line = explode(' ', $line);
                    $last = array_pop($line);
                    $services[$line[1]] = trim($last);
                }
                else if(preg_match('/' . $site . '[\-a-zA-Z]* is up-to-date/', $line))
                {
                    $line = explode(' ', $line);
                    $last = array_pop($line);
                    $services[$line[0]] = trim($last);
                }
            }
        
            return $services;
        }


        /**
         * Parse the output of docker-compose start command
         * 
         * @return  array
         */
        protected function parse_start_logs($logs, $site)
        {
            $services = [];
            $lines = explode("\n", $logs);
            foreach($lines as $line) {
                if(strstr($line, 'Starting ') != false) {
                    $line = explode(' ', $line);
                    $last = array_pop($line);
                    $services[$line[1]] = trim($last);
                }
            }

            return $services;
        }


        /**
         * Parse the output of docker-compose stop command
         * 
         * @return  array
         */
        protected function parse_stop_logs($logs, $site)
        {
            $services = [];
            $lines = explode("\n", $logs);
            foreach($lines as $line) {
                if(strstr($line, 'Stopping ') != false) {
                    $line = explode(' ', $line);
                    $last = array_pop($line);
                    $services[$line[1]] = trim($last);
                }
            }

            return $services;
        }


        /**
         * Parse the output of docker-compose remove command
         * 
         * @return  array
         */
        protected function parse_remove_logs($logs, $site)
        {
            $services = [];
            $lines = explode("\n", $logs);
            foreach($lines as $line) {
                if(strstr($line, 'Removing ' . $site) != false) {
                    $line = explode(' ', $line);
                    $services[$line[1]] = array_pop($line);
                }
            }
        
            return $services;
        }
    }


    class ComposeFileNotFoundException extends Exception
    {
        public function __construct(Exception $previous = null)
        {
            parent::__construct(sprintf('Docker compose file not found'), 404, $previous);
        }
    }
 ?>
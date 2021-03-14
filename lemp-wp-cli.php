<?php
    require_once __DIR__ . '/vendor/autoload.php';
    require_once 'lwc.php';

    use Symfony\Component\Console\Application;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;


    define('CONTAINER_SITES_DIR', '/rt-sites');
    define('WORDPRESS_DOWNLOAD_URL', 'https://wordpress.org/latest.zip');


    class BootstrapCommand extends  Symfony\Component\Console\Command\Command
    {
        protected static $defaultName = 'bootstrap';
        protected $user_account;

        protected function configure()
        {
            $this->setName('bootstrap')
                    ->setDescription('A CLI to bootstrap a full LEMP with latest WordPress installation.')
                    ->setHelp("Launch a full LEMP containers with a latest Wordpress installation.\nExample:\n\tphp " . basename(__FILE__) ." bootstrap --site mysite.com --user SYS_ACCOUNT")
                    ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Set WordPress site name')
                    ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Set no root user system account');
        }

        protected function interact(InputInterface $input, OutputInterface $output)
        { 
            if($input->getOption('site') === NULL || $input->getOption('user') === NULL)
            {
                $output->writeln('Please pass all options --site and --user.');
                exit();
            }

            if(!is_valid_url($input->getOption('site')))
            {
                $output->writeln('Invalid url format passed!');
                exit();
            }

            $this->user_account = $input->getOption('user');
            $result = is_user_exist($this->user_account);
            if($result === false)
            {
                $output->writeln(sprintf('id: %s: no such user', $this->user_account));
                exit();
            }
            else if($result !== true)
            {
                $output->writeln(sprintf('Error: %s ', $result));
                exit();
            }
    
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {      
            if (!extension_loaded('openssl') || !extension_loaded('curl') || !extension_loaded('zip')) 
            {
                $output->writeln('One of the following extensions `openssl curl zip` is not installed/ loaded, the app needs them to function properly.');
                $output->writeln('Existing the application!');
                exit();
            }

            if(!file_exists(CONTAINER_SITES_DIR))
            {    if(!mkdir(CONTAINER_SITES_DIR, 0775))
                {
                    $output->writeln('Failed to create the directory: ' . CONTAINER_SITES_DIR );
                    $output->writeln('Existing the application!');
                    exit();
                }
            }

            $site_name = rtrim($input->getOption('site'), '/');
            // Check if the passed site already exist
            $site_dir = rtrim(CONTAINER_SITES_DIR, '/') . '/' . $site_name . '/';
            if(file_exists($site_dir))
            {
                $output->writeln('Such container site already exist, maybe you meant something else.');
                $output->writeln('Existing the application!');
                exit();
            }

            // Create the container site and install the necessary packages if needed
            if(!mkdir($site_dir, 0775))
            {
                $output->writeln('Failed to create the directory: ' . $site_dir);
                $output->writeln('Existing the application!');
                exit();
            }

            // Copy the docker templates files of LEMP
            xcopy('docker', $site_dir . 'docker');

            // Modify the template files
            $template = file_get_contents($site_dir . 'docker/nginx/site.conf');
            $template = str_replace('${CONTAINER_SITE}', $site_name, $template);
            file_put_contents($site_dir . 'docker/nginx/site.conf', $template);

            $template = file_get_contents($site_dir . 'docker/docker-compose.yml');
            $template = str_replace('/${CONTAINER_SITES_DIR}', CONTAINER_SITES_DIR, $template);
            $template = str_replace('${CONTAINER_SITE}', $site_name, $template);

            $listening_ports = get_listening_ports();
            //$http_port = (is_array($listening_ports)) ? reserve_port($listening_ports) : reserve_port([0]);
            //$https_port = (is_array($listening_ports)) ? reserve_port($listening_ports) : reserve_port([0]);
            $http_port = 80;
            $https_port = 443;
            $mariadb_port = (is_array($listening_ports)) ? reserve_port($listening_ports) : reserve_port([0]);

            $template = str_replace('${HTTP_PORT}', $http_port, $template);
            $template = str_replace('${HTTPS_PORT}', $https_port, $template);
            $template = str_replace('${MARIADB_PORT}', $mariadb_port, $template);

            $wp_user = $wp_db = str_replace( ['-', '_', '.'], '', $site_name);
            $wp_pass = random_salt(64, ["'", "\\", "$"]);
            $root_pass = random_salt(64, ["'", "\\", "$"]);

            $template = str_replace('${WP_USER}', $wp_user, $template);
            $template = str_replace('${WP_PASSWORD}', $wp_pass, $template);
            $template = str_replace('${WP_DATABASE}', $wp_db, $template);
            $template = str_replace('${WP_ROOT_PASSWORD}', $root_pass, $template);
            file_put_contents($site_dir . '/docker/docker-compose.yml', $template);

            // Checking if docker is installed, and if not then install it
            if(!is_docker_dcompose_installed())
            {
                $result = install_docker_dcompose($this->user_account);
                if(!$result) 
                {
                    $output->writeln('Exiting the app, failed to install docker and docker-compose.');
                    recursive_remove($site_dir);
                    return Command::FAILURE;
                    exit();
                }
            }
            else
            {
                // make sure user account is already added to the group
            }

            // Download the latest wordpress and extract it to $site_dir
            $output->writeln('Downloading WordPress...');
            //$result = download_file_progress('https://wordpress.org/wordpress-2.7.1.zip', 'wp-latest.zip');
            $result = download_file_progress(WORDPRESS_DOWNLOAD_URL, 'wp-latest.zip');
            if($result == 0)
            {
                $output->writeln('Exiting the app, failed to download wordpress.');
                recursive_remove($site_dir);
                exit();
            }

            $zip = new ZipArchive;
            if ($zip->open('wp-latest.zip') === TRUE) 
            {
                $zip->extractTo($site_dir . 'public');
                $zip->close();

                xcopy($site_dir . 'public/wordpress', $site_dir . 'public');
                recursive_remove($site_dir . 'public/wordpress');
            } 
            else 
            {
                $output->writeln('Exiting the app, failed to extract wp-latest.zip.');
                recursive_remove($site_dir);
                exit();
            }

            // Configure wp-config.php
            $config_template = file_get_contents($site_dir . 'public/wp-config-sample.php');
            $result = preg_replace("/DB_NAME',\s+'[a-zA-Z_\- ]*'/i", "DB_NAME', '" . $wp_db . "'", $config_template);
            $result = preg_replace("/DB_HOST',\s+'[a-zA-Z_\- ]*'/i", "DB_HOST', '" . $site_name . "-mariadb'", $result);
            $result = preg_replace("/DB_USER',\s+'[a-zA-Z_\- ]*'/i", "DB_USER', '" . $wp_user . "'", $result);
            $result = preg_replace("/DB_PASSWORD',\s+'[a-zA-Z_\- ]*'/i", "DB_PASSWORD', '" . $wp_pass . "'", $result);
            $result = preg_replace("/AUTH_KEY',\s+'[a-zA-Z_\- ]*'/i", "AUTH_KEY', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/SECURE_AUTH_KEY',\s+'[a-zA-Z_\- ]*'/i", "SECURE_AUTH_KEY', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/LOGGED_IN_KEY',\s+'[a-zA-Z_\- ]*'/i", "LOGGED_IN_KEY', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/NONCE_KEY',\s+'[a-zA-Z_\- ]*'/i", "NONCE_KEY', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/AUTH_SALT',\s+'[a-zA-Z_\- ]*'/i", "AUTH_SALT', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/SECURE_AUTH_SALT',\s+'[a-zA-Z_\- ]*'/i", "SECURE_AUTH_SALT', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/LOGGED_IN_SALT',\s+'[a-zA-Z_\- ]*'/i", "LOGGED_IN_SALT', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $result = preg_replace("/NONCE_SALT',\s+'[a-zA-Z_\- ]*'/i", "NONCE_SALT', '" . random_salt(64, ["'", "\\"]) . "'", $result);
            $config_template = file_put_contents($site_dir . 'public/wp-config.php', $result);

            // Start the container
            $docker_manager = new DockerComposeClient($site_name, $site_dir . '/docker/docker-compose.yml');
            $output->writeln('');
            $output->writeln(sprintf('Starting up the services, this may take few seconds or minutes if the images didn\'t downloaded before.'));
            $result = $docker_manager->up();
            if(!empty($result['output']))
            {
                $logs = $docker_manager->parse($result);
                if(is_array($logs))
                {
                    foreach($logs as $service => $status)
                    {
                        $service = explode('-', $service);
                        $service = array_pop($service);
                        if($service == 'mariadb')
                        {
                            $output->writeln(sprintf('Starting the service %s on port %d, access url: %s:%d', $service, $mariadb_port, $site_name, $mariadb_port));
                        }
                        else if($service == 'nginx')
                        {
                            $output->writeln(sprintf('Starting the service %s on port %d, access url: http://%s:%d', $service, $http_port, $site_name, $http_port));
                            $output->writeln(sprintf('Starting the service %s on port %d, access url: https://%s:%d', $service, $https_port, $site_name, $https_port));
                            $result = add_host($site_name, '127.0.0.1');
                        }
                    }
    
                    // Set the ownership for $this->user_account
                    //rchown($site_dir, 'docker', $this->user_account, 0775);
                    rchown($site_dir . 'mariadb', 'docker', '', 0775);
                    rchown($site_dir . 'docker', 'docker', $this->user_account, 0775);
                    rchown($site_dir . 'logs', 'docker', $this->user_account, 0775);
                    rchown($site_dir . 'public', 'docker', $this->user_account, 0775);
                    
                    $output->writeln('');
                    $output->writeln(sprintf('Loading the services...'));
                    // Give some time to mariadb to load fully
                    sleep(10);
                    $output->writeln(sprintf('Container for %s started successfully', $site_name));

                    // Installing WordPress
                    $output->writeln('');
                    $output->writeln('Installing WordPress...');

                    $wpu = random_salt(12);
                    $form_fields = [
                                    'user_name' => $wp_user,
                                    'admin_password' => $wpu,
                                    'admin_password2' => $wpu,
                                    'admin_email' => 'admin@' . $site_name,
                                    'weblog_title' => $site_name];

                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $site_name . '/wp-admin/install.php?step=2');
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $form_fields);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    $result = curl_exec($curl);
                    curl_close($curl);
                    
                    if(preg_match('/WordPress has been installed. Thank you, and enjoy!/', $result))
                    {
                        $output->writeln('WordPress has been installed successfully');

                        // Saving all credentials to a file
                        $credentials  = 'DB_ROOT_PASSWORD: ' . $wp_user . "\n";
                        $credentials .= 'WP_USER: ' . $wp_user . "\n";
                        $credentials .= 'WP_PASSWORD: ' . $wp_pass . "\n";
                        $credentials .= 'WP_DADATABASE: ' . $wp_db . "\n";
                        $credentials .= 'WP_USERNAME: ' . $form_fields['user_name'] . "\n";
                        $credentials .= 'WP_PASSWORD: ' . $form_fields['admin_password'] . "\n";
                        $credentials .= 'WP_EMAIL: ' . $form_fields['admin_email'] . "\n";
                        file_put_contents($site_dir . 'credentials', $credentials);

                        $output->writeln('');
                        $output->writeln(sprintf('All credentials are written in %s', $site_dir . 'credentials'));
                        return Command::SUCCESS;
                    }
                }
                else
                {
                    $output->writeln('Some thing wrong happend, bellow is the latest stack trace');
                    print_r($docker_manager->parse($result));
                    print_r($docker_manager->debug());
                    recursive_remove($site_dir);
                    return Command::FAILURE;
                }
            }
            else
            {
                $output->writeln(sprintf('Exiting the app, failed to bootstrap Dockercompose for %s.', $site_name));
                print_r($result);
                print_r($docker_manager->debug());
                return Command::FAILURE;
            }
        }
    }


    class StartCommand extends  Symfony\Component\Console\Command\Command
    {
        protected static $defaultName = 'bootstrap';
        protected $user_account;

        protected function configure()
        {
            $this->setName('start')
                    ->setDescription('Start a LEMP that already lunched before.')
                    ->setHelp("Start a LEMP after it has been stopped.\nExample:\n\tphp " . basename(__FILE__) ." start --site mysite.com")
                    ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Set WordPress site name');
        }

        protected function interact(InputInterface $input, OutputInterface $output)
        { 
            if($input->getOption('site') === NULL)
            {
                $output->writeln('Please pass all options --site and --user.');
                exit();
            }

            if(!is_valid_url($input->getOption('site')))
            {
                $output->writeln('Invalid url format passed!');
                exit();
            }
        }
    
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $site_name = rtrim($input->getOption('site'), '/');
            // Check if the passed site already exist
            $site_compose = rtrim(CONTAINER_SITES_DIR, '/') . '/' . $site_name . '/docker/docker-compose.yml';
            if(!file_exists($site_compose))
            {
                $output->writeln(sprintf('A container site of %s doesn\'t exist, may be it is already deleted.', $site_name));
                $output->writeln('Existing the application!');
                exit();
            }

            // Start the container
            $docker_manager = new DockerComposeClient($site_name, $site_compose);
            $output->writeln('');
            $output->writeln(sprintf('Starting up the services, this may take few seconds.'));
            $result = $docker_manager->start();
            if(!empty($result['output']))
            {
                $logs = $docker_manager->parse($result);
                if(is_array($logs))
                {
                    foreach($logs as $service => $status)
                    {
                        $service = explode('-', $service);
                        $service = array_pop($service);
                        $output->writeln(sprintf("Starting the service: %s\t\t%s", $service, $status));
                    }
                    
                    return Command::SUCCESS;
                }
                else
                {
                    $output->writeln('Some thing wrong happened, bellow is the latest stack trace');
                    print_r($docker_manager->parse($result));
                    print_r($docker_manager->debug());
                    return Command::FAILURE;
                }
            }
            else
            {
                $output->writeln(sprintf('Exiting the app, failed to stop container site of %s.', $site_name));
                print_r($result);
                print_r($docker_manager->debug());
                return Command::FAILURE;
            }
        }
    }

 
    class LWCApp extends Application
    {
        protected function getDefaultInputDefinition()
        {
            return new Symfony\Component\Console\Input\InputDefinition([
                new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
                new Symfony\Component\Console\Input\InputOption('--help', '-h', Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Display help for the given command. When no command is given display help for the <info>`list`</info> command'),
                new Symfony\Component\Console\Input\InputOption('--version', '-v', Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Display this application version'),
            ]);
        }
    }

    
    $app = new LWCApp('LEMP WP installer', '1.0.0');
    $app->add(new BootstrapCommand());
    $app->add(new StartCommand());
    $app->run();
?>
# Docker LEMP WP installer
------------

A command line tool to install docker and lunch a full LEMP with latest WordPress version installed.


## Features

- Start / Stop / Destroy the LEMP.
- Lunch multiple LEMP on same host.
- Dynamic ports choosing for HTTP, HTTPS, MariaDB.
- Modular, basically you can modify it to install any other script.
- Verify duplicate LEMP names.
- Permissive license, your guess is right, it is BSD-2 clause.

## Operating system support

Currently tested in the following distributions:
- Ubuntu 20.04.2 LTS.

## Requirements

Set the packages that your project may need.
 
- PHP 7.0+
- [zip extension](https://www.php.net/manual/en/zip.installation.php)
- [curl extension](https://www.php.net/manual/en/curl.installation.php)
- [openssl extension](https://www.php.net/manual/en/openssl.installation.php)
- [composer](https://getcomposer.org/download/)
- `netstat` utility to support dynamic port selection for LEMP services.

## Installation		
```bash
$ git clone https://github.com/hAbd0u/lemp-wp-cli.git
$ cd lemp-wp-cli
$ composer install
```
Until [mikehaertl/php-shellcommand](https://github.com/mikehaertl/php-shellcommand) accepts my PR, please when you finish the installation, go to this path `lemp-wp-cli/vendor/mikehaertl/php-shellcommand/src` and replace `Command.php` file with one [here](https://github.com/hAbd0u/php-shellcommand/blob/master/src/Command.php).

## Usage
Lunch a LEMP with a a site named first-site.org
```bash
$ sudo php lemp-wp-cli.php bootstrap --site first-site.org --user ${USER}
```
${USER} is your current logged user.

Stop a LEMP site named first-site.org
```bash
$ sudo php lemp-wp-cli.php stop --site first-site.org
```

Start a LEMP site named first-site.org
```bash
$ sudo php lemp-wp-cli.php start --site first-site.org
```

Destroy a LEMP site named first-site.org
```bash
$ sudo php lemp-wp-cli.php destroy --site first-site.org
```

### Note
By default the script lunches the services nginx and mariadb in port 80 and 3306, to support dynamic port selection comment the following lines in the file `lemp-wp-cli.php`;
```php
$http_port = 80;
$https_port = 443;
$mariadb_port = 3306;
```

And uncomment the following:
```php
$http_port = (is_array($listening_ports)) ? reserve_port($listening_ports) : reserve_port([0]);
$https_port = (is_array($listening_ports)) ? reserve_port($listening_ports) : reserve_port([0]);
$mariadb_port = (is_array($listening_ports)) ? reserve_port($listening_ports) : reserve_port([0]);
```

## TODO 
This is a list of features hopefully to implement in future.
- [ ] List current running LEMPs.
- [ ] Add the support to select ports dynamically.
- [ ] Upgrade WordPress.
- [ ] Install plugins.
- [ ] Change WordPress site name.
- [ ] Dump database.


## Versioning

For transparency into our release cycle and in striving to maintain backward compatibility, **LEMP WP CLI** is maintained under the Semantic Versioning guidelines.


## FAQ

**What is this repo?**
- It is full LEMP with an installed WP lunched directly from the console.

**Does all the services independent?**
- Yes, each service [nginx - phpfm - mariadb] run in its own container.

**What is the version of the installed WordPress?**
- Of course it is the latest one, downloaded directly from [here](https://wordpress.org/latest.zip).

**Can I change the WordPress version to be installed when lunching a LEMP?**
- Yes, you can do so by changing the defined const here:
```php
define('WORDPRESS_DOWNLOAD_URL', 'https://wordpress.org/latest.zip');
```
**Where can I find the files of the lunched LEMP?**
- By default all LEMP files are saved under `/rt-sites/${SITE_NAME}`, but you can change the location of that by redefine it here:
```php
define('CONTAINER_SITES_DIR', '/rt-sites');
```


## Issues
Please report all issues [here](https://github.com/hAbd0u/lemp-wp-cli/issues).


## Code of Conduct

This project has adopted the [Open Source Code of Conduct](https://opensource.guide/code-of-conduct/). For more information see the [Code of Conduct FAQ](https://opensource.guide/code-of-conduct/faq/) or contact [opencode@opensource.guide](mailto:opencode@opensource.guide) with any additional questions or comments.


## Feedback

* Ask a question witch starts with [QUESTION](https://github.com/hAbd0u/lemp-wp-cli/issues) in the title.
* Request a new feature.


## Authors and contributors
- [BELADEL ILYES ABDELRAZAK](https://github.com/hAbd0u)


## Credits
- A special thanks to [Michael Härtl](https://github.com/mikehaertl) for his [php-shellcommand](https://github.com/mikehaertl/php-shellcommand) library, I would build the same one if he already didn't.
- Other repo I get inspired by:
  *  [DOCKER-COMPOSE PHP](https://github.com/omauger/docker-compose-php).
  *  [Awesome Docker Php Sdk](https://github.com/theodorosploumis/awesome-docker-php#docker-compose-php-sdk)



## License

[![License](https://img.shields.io/badge/License-BSD%202--Clause-orange.svg)](https://opensource.org/licenses/BSD-2-Clause)

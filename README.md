# PHP Web Server

Simple web server implemented in PHP for my Bachelor's degree thesis project. PHP Web Server supports `HEAD`, `GET` and `POST` methods, compression, and chunked transfer encoding. PHP Web Server can execute CGI and PHP scripts.

## Installation

PHP Web Server has been tested only on Ubuntu Linux with PHP5.

PHP Web Server requires that `php-cli` and `php-cgi` are installed:

	sudo apt-get install php5 php5-cgi

In `php-cgi` `php.ini` file set the following line:

	cgi.force_redirect = 0

Then simply clone the repository:

	git clone https://github.com/ValerioLuconi/PHP-Web-Server.git

## Configuration

The general configuration file is `conf/general.conf`.

By default the PHP Web Server listens on port 1234. To change the port edit the line

	Port: 1234

with:

	Port: <your_port_nuber>

You can set up your host configuration with:

	Host: <host_name> <home_dir>

You can set up aliases for an host with:

	Alias: /<alias_name>/ /<alias_path>/

Make sure to insert the `/`s and not leave white spaces or empty rows between the `Host` and `Alias` rows.

An example host configuration is:

	Host: localhost /home/www/
	Alias: /~valerio/ /home/valerio/
	Alias: /cgi-bin/ /script/cgi-bin/

CGI scripts have to be located in a `/cgi-bin/` directory **inside** the home directory of a host.

## Usage

Simply type

	php server.php

<?php
/**
 * @file globals.php
 * This file contains global variables.
 * @brief Global variables.
 * @author Valerio Luconi
 * @version 0.1
 */

/// Web Server's port
/**
 * String containing web server's listening port, set in file ./conf/general.conf
 */
$port;

/// Web Server's Domains.
/**
 * An associative bidimensional array containing every domain's home directory, and aliases, in the format:
 * $host["name"]["home"] = "home_path"
 * $host["name"]["alias_name"] = "alias_relative_path"
 * set in file general.conf
 */
$host;

/// Supported methods.
/**
 * An array containing all supported methods by the server.
 */
$supported_methods = array(
                        0       => "GET",
                        1       => "HEAD",
                        2       => "POST");

/// Index pages.
/**
 * Array containing all possible index pages.
 */
$index_pages = array(
                        0       => "index.html",
                        1       => "index.cgi",
                        2       => "index.php",
                        3       => "index.xhtml",
                        4       => "index.htm");

/// Mime types
/**
 * Associative array containing supported mime types, taken from configuration file ./conf/mime.conf in the format $mime_types["extension"] = "type".
 */
$mime_types;

/// Languages.
/**
 * Associative array containing supported languages, taken from configuration file ./conf/language.conf in the format $languages["extension"] = "language".
 */
$languages;

/// Charsets.
/**
 * Associative array containing supported charsets, taken from configuration file ./conf/charset.conf in the format $charset["extension"] = "charset".
 */
$charsets;

/// Encodings.
/**
 * Associative array containing supported encodings, in the format $encodings["extension"] = "encoding".
 */
$encodings = array(
                        "gz"    => "gzip",
                        "Z"     => "compress",
                        "gzd"   => "deflate");

/// Transfer Codings
/**
 * Associative array containing supported transfer codings, in the format $transfer_codings["coding"] = "coding".
 */
$transfer_codings = array(
                        "gzip" => "gzip",
                        "compress" => "compress",
                        "deflate" => "deflate");

?>

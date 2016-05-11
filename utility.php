<?php
/**
 * @file utility.php
 * This file contains some utility functions.
 * @brief Utility functions.
 * @author Valerio Luconi
 * @version 0.1
 */


/// Server Configuration.
/**
 * Sets all variables to be configured.
 * @return      True on success, or false on error.
 */
function config()
{
        // set mime_types
        $fd = fopen("./conf/mime.conf", "r");
        if($fd === false)
                return false;
        while(!feof($fd)) {
                $str = fgets($fd);
                if(strpos($str, "#") === false) {
                        if(!empty($str)) {
                                $array = str_word_count($str, 1, "/-+.0123456789");
                                $size = count($array);
                                if($size == 2)
                                        $GLOBALS["mime_types"][$array[0]] = $array[1];
                        }
                }
        }
        fclose($fd);

        // set languages
        $fd = fopen("./conf/language.conf", "r");
        if($fd === false)
                return false;
        while(!feof($fd)) {
                $str = fgets($fd);
                if(strpos($str, "#") === false) {
                        if(!empty($str)) {
                                $array = str_word_count($str, 1, "/-+.0123456789");
                                $size = count($array);
                                if($size > 1) {
                                        for($i = 1; $i < $size; $i++)
                                                $GLOBALS["languages"][$array[$i]] = $array[0];
                                }
                        }
                }
        }
        fclose($fd);

        // set charsets
        $fd = fopen("./conf/charset.conf", "r");
        if($fd === false)
                return false;
        while(!feof($fd)) {
                $str = fgets($fd);
                if(strpos($str, "#") === false) {
                        if(!empty($str)) {
                                $array = str_word_count($str, 1, "/-+.0123456789");
                                $size = count($array);
                                if($size > 1) {
                                        for($i = 1; $i < $size; $i++)
                                                $GLOBALS["charsets"][$array[$i]] = $array[0];
                                }
                        }
                }
        }
        fclose($fd);

        // set general configuration: hosts, aliases and port
        $fd = fopen("./conf/general.conf", "r");
        if($fd === false)
                return false;
        while(!feof($fd)) {
                $str = fgets($fd);
                if(strpos($str, "#") === false) {
                        if(!empty($str)) {
                                $array = str_word_count($str, 1, "àèéìòù~_<>/-+.:0123456789");
                                $size = count($array);
                                if($size === 2 && $array[0] === "Port:")
                                        $GLOBALS["port"] = $array[1];
                                if($size === 3 && $array[0] === "Host:") {
                                        if(isset($GLOBALS["port"]))
                                                $host = $array[1].":".$GLOBALS["port"];
                                        else
                                                return false;
                                        $GLOBALS["host"][$host]["home"] = $array[2];
                                }
                                if($size === 3 && $array[0] === "Alias:")
                                        $GLOBALS["host"][$host][$array[1]] = $array[2];
                        }
                }
        }
        return true;
}

/// Parses URI.
/**
 * Checks if given uri is valid or not and returns an associative array containing its parts.
 *
 * @param[in]   $home_dir The home directory of the webserver.
 * @param[in]   $uri A string containing an http uri.
 * @return      Returns an associative array containing the parts of the given uri, or null if it is not a valid uri.
 */
function parse_uri($uri)
{
        $uri = parse_url($uri);

        // is it a valid url?
        if($uri === false)
                return null;

        // if absolute-uri, is it http? is host set?
        if(isset($uri["scheme"]) && ($uri["scheme"] != "http" || !isset($uri["host"])))
                return null;
        if(isset($uri["host"]) && !isset($uri["port"]))
                $uri["port"] = "80";

        // is there a valid path?
        if(!isset($uri["path"]))
                return null;

        // removes "one dir up"s from path
        $uri["path"] = str_replace("..", "", $uri["path"]);
        $uri["path"] = str_replace("//", "/", $uri["path"]);
        $uri["path"] = urldecode($uri["path"]);
        return $uri;
}


/// Gets HTTP-version from given string.
/**
 * Checks if given string is a valid HTTP version string and returns the version value.
 * @param[in]   $http_version A string containing an HTTP-Version.
 * @return      Version number or null if given string is a non valid HTTP-Version.
 */
function get_version($http_version)
{
        $http_version = explode("/", $http_version);
        $len = count($http_version);
        if($len != 2)
                return null;
        if($http_version[0] != "HTTP")
                return null;
        if($http_version[1] != "0.9" && $http_version[1] != "1.0" && $http_version[1] != "1.1")
                return null;
        return $http_version[1];
}


/// Reads a HTTP header.
/**
 * Reads a HTTP header from given socket.
 * @param[in]   $socket A valid TCP socket.
 * @param[in,out] $buffer Buffer in which will be stored the HTTP request to be parsed.
 * @return      A string containing the header of a HTTP request, or null on error.
 */
function read_header($socket, &$buffer)
{
        while(1) {
                $buffer = ltrim($buffer, "\r\n");
                $pos = strpos($buffer, "\r\n\r\n");
                if($pos !== false) {
                        $header = substr($buffer, 0, $pos + 4);
                        $buffer = substr($buffer, $pos + 4);
                        return $header;
                }
                $read = socket_read($socket, 1024);
                if($read === false)
                        return null;
                $buffer .= $read;
        }
}


/// Reads body of a HTTP request.
/**
 * Reads body of a HTTP Request from given socket
 * @param[in]   $socket A valid TCP socket.
 * @param[in]   $length If 0 the transfer is chunked, othewise the function will return a body of $length bytes.
 * @param[in,out] $buffer Buffer in which will be stored the HTTP request to be parsed.
 * @return      A string containing the body of a HTTP request, null on reading error, -1 on syntactical error.
 */
function read_body($socket, $length, &$buffer)
{
        if($length !== 0) {
                $to_read = $length;
                while(1) {
                        $len = strlen($buffer);
                        $to_read = $length - $len;
                        if($to_read > 0) {
                                $read = socket_read($socket, $to_read);
                                if($read === false)
                                        return null;
                                $buffer .= $read;
                        }
                        else {
                                $body = substr($buffer, 0, $length);
                                $buffer = substr($buffer, $length);
                                return $body;
                        }
                }
        }
        else {
                $body = "";
                while(1) {
                        $chunk_length = read_chunk_length($socket, $buffer);
                        if($chunk_length === null)
                                return null;
                        if(!ctype_xdigit($chunk_length))
                                return -1;
                        $chunk_length = hexdec($chunk_length);
                        if($chunk_length === 0)
                                return $body;
                        $chunk = read_chunk($socket, $chunk_length, $buffer);
                        if($chunk === null)
                                return null;
                        if($chunk === -1)
                                return -1;
                        $body .= $chunk;
                }
        }
}

/// Reads a chunk length.
/**
 * Returns the length of the next chunk to read.
 * @param[in]   $socket A valid TCP socket.
 * @param[in,out] $buffer Buffer in which will be stored the HTTP request to be parsed.
 * @return      A string containing chunk length in hex characters, or null on error.
 */
function read_chunk_length($socket, &$buffer)
{
        while(1) {
                $pos = strpos($buffer, "\r\n");
                if($pos !== false) {
                       $chunk_length = substr($buffer, 0, $pos);
                       $buffer = substr($buffer, $pos + 2);
                       return $chunk_length;
                }
                $read = socket_read($socket, 1024);
                if($read === false)
                       return null;
                $buffer .= $read;
        }
}

/// Reads chunk body.
/**
 * Reads a chunk's body of given length from given socket.
 * @param[in]   $socket A valid TCP socket.
 * @param[in]   $chunk_length Length of chunk to be read.
 * @param[in,out] $buffer Buffer in which will be stored the HTTP request to be parsed.
 * @return      A string containing chunk body, null on read error or -1 on syntactical error.
 */
function read_chunk($socket, $chunk_length, &$buffer)
{
        while(1) {
                $len = strlen($buffer);
                if($chunk_length + 2 <= $len) {
                        $pos = strpos($buffer, "\r\n", $chunk_length);
                        if($pos === false || $pos !== $chunk_length)
                                return -1;
                        $chunk = substr($buffer, 0, $chunk_length);
                        $buffer = substr($buffer, $chunk_length + 2);
                        return $chunk;
                }
                $read = socket_read($socket, 1024);
                if($read === false)
                        return null;
                $buffer .= $read;
        }
}

/// Is supported method?
/**
 * Tells if a givend method is supported by the web server or not.
 * @param[in]   $method A string containing a HTTP method.
 * @return      true if the method is supported, false otherwise.
 */
function is_supported_method($method)
{
        $len = count($GLOBALS["supported_methods"]);
        for($i = 0; $i < $len; $i++) {
                if($method == $GLOBALS["supported_methods"][$i])
                        return true;
        }
        return false;
}

/// Sends an error message.
/**
 * Sends an error response messagge.
 * @param[in]   $socket A valid TCP socket.
 * @param[in]   $code Error code.
 * @param[in]   $method If HEAD the error message will not have any body.
 * @return      true on success, false on error.
 */
function send_error($socket, $code, $method = "GET", $version = "1.1", $url = 0)
{
        $code = strval($code);
        $response = file_get_contents("./errors/http/$code.http");
        if($response === false) {
                $response = file_get_contents("./errors/http/500.http");
                $code = "500";
        }
        if($code == 300) {
                foreach($GLOBALS["host"] as $key => $value) {
                        $temp = "http://".$key."/";
                        $uri .= "<a href=\"$temp\">$temp</a><br/>\n";
                }
                $url = $uri;
                $response_body = file_get_contents("./response/300.html");
                eval("\$response_body = \"$response_body\";");
        }
        else
                $response_body = file_get_contents("./errors/html/$code.html");
        if($code == 404)
                eval("\$response_body = \"$response_body\";");
        $len = strlen($response_body);
        $date = gmdate("D, d M Y H:i:s T");
        eval("\$response = \"$response\";");
        if($method !== "HEAD")
                $response .= $response_body;
        $ret = write_to_socket($socket, $response);
        return $ret;
}

/// Finds the requested resource.
/**
 * Finds the requested resource/resources.
 * @param[in]   $path The path to requested resource.
 * @return      An array containing paths to found resources, or false on error. If no resource is found an empty array will be returned.
 */
function find_resource($path)
{
        if(file_exists($path)) {
                if(is_file($path)) {
                        $files = array(0 => $path);
                        return $files;
                }
                if(is_dir($path)) {
                        $path .= "/";
                        $path = str_replace("//", "/", $path);
                        $size = count($GLOBALS["index_pages"]);
                        for($i = 0; $i < $size; $i++) {
                                $index = $path.$GLOBALS["index_pages"][$i];
                                if(is_file($index)) {
                                        $files = array(0 => $index);
                                        return $files;
                                }
                                $files = glob($index.".*");
                                if(!empty($files))
                                        return $files;
                        }
                        return false;
                }
        }
        else
                $search = "$path.*";
        $files = glob($search);
        if(empty($files))
                return false;
        return $files;
}

/// Set file features
/**
 * Sets file characteristic by inspecting its extensions.
 * @param[in]   $path Path to file to inspect.
 * @return      An associative array containing file features: mime_type, language, charset and encoding, or an empty array if file has no extensions.
 */
function set_file_features($path)
{
        $options = "";
        $dir = pathinfo($path, PATHINFO_DIRNAME)."/";
        $file = str_replace($dir, "", $path);
        $exts = explode(".", $file);
        $len = count($exts);
        if($len === 1)
                return $options;
        for($i = 1; $i < $len; $i++) {
                if(isset($GLOBALS["mime_types"][$exts[$i]])) {
                        if(!isset($options["mime_type"])) {
                                $options["mime_type"] = $GLOBALS["mime_types"][$exts[$i]];
                                continue;
                        }
                }
                if(isset($GLOBALS["languages"][$exts[$i]])) {
                        if(!isset($options["language"])) {
                                $options["language"] = $GLOBALS["languages"][$exts[$i]];
                                continue;
                        }
                }
                if(isset($GLOBALS["charsets"][$exts[$i]])) {
                        if(!isset($options["charset"])) {
                                $options["language"] = $GLOBALS["charsets"][$exts[$i]];
                                continue;
                        }
                }
                if(isset($GLOBALS["encodings"][$exts[$i]])) {
                        if(!isset($options["encoding"])) {
                                $options["encoding"] = $GLOBALS["encodings"][$exts[$i]];
                                continue;
                        }
                }
        }
        return $options;
}

/// Parses content of Accept* headers.
/**
 * Parses content of Accept, Accept-Language, Accept-Encoding, Accept-Charset and TE headers.
 * @param[in]   $content A string containing the header content field, without any spaces.
 * @return      An associative array in the format $accept["media"] = "quality value", sorted in reverse numerical order, or false on error.
 */
function parse_accept($content)
{
        if(empty($content))
                return "";
        $content = explode(",", $content);
        $size = count($content);
        for($i = 0; $i < $size; $i++) {
                $pos = strpos($content[$i], ";q=");
                if($pos === false)
                        $accept[$content[$i]] = "1";
                elseif($pos === 0)
                        return false;
                else {
                        $quality = substr($content[$i], $pos);
                        $content[$i] = substr($content[$i], 0, $pos);
                        $quality = str_replace(";q=", "", $quality);
                        if(!is_numeric($quality))
                                $quality = 0.01;
                        if(floatval($quality) > 1)
                                $quality = 1;
                        $accept[$content[$i]] = floatval($quality);
               }
        }
        arsort($accept, SORT_NUMERIC);
        return $accept;
}

/// Read script header.
/**
 * Reads script header from a pipe (if resource set) or from a given string and returns it.
 * @param[in,out]       &$buffer A string where to put read output or already containing output from a cgi program.
 * @param[in]   $resource A valid pipe connected to stdout of a cgi program.
 * @return      CGI headers or false on failure.
 */
function read_script_header(&$buffer, $resource = false)
{
        if($resource !== false) {
                while(!feof($resource)) {
                        $read = fread($resource, 1024);
                        $buffer .= $read;
                        // script header can end with two CRLFs or only two LFs
                        $crlfpos = strpos($buffer, "\r\n\r\n");
                        $lfpos = strpos($buffer, "\n\n");
                        if($crlfpos === false && $lfpos === false)
                                continue;
                        else
                                break;
                }
        }
        else {
                $crlfpos = strpos($buffer, "\r\n\r\n");
                $lfpos = strpos($buffer, "\n\n");
                if($crlfpos === false && $lfpos === false)
                       return false;
        }
        if($crlfpos === false) {
                $header = substr($buffer, 0, $lfpos + 2);
                $buffer = substr($buffer, $lfpos + 2);
                $header = str_replace("\n", "\r\n", $header);
                return $header;
        }
        elseif($lfpos === false) {
                $header = substr($buffer, 0, $crlfpos + 4);
                $buffer = substr($buffer, $crlfpos + 4);
                return $header;
        }
        else
                return false;
}

/// Parse script header
/**
 * Returns an array containing script headers from a given header
 * @param[in]   A valid script header.
 * @return      An associative array in the format $headers["name"] = "content", or false on failure.
 */
function parse_script_header($header)
{
        $header = trim($header);
        if(empty($header))
                return false;
        $header = explode("\r\n", $header);
        $size = count($header);
        for($i = 0; $i < $size; $i++) {
                if(strpos($header[$i], ":") === false)
                        return false;
                $array = explode(":", $header[$i], 2);
                $headers[$array[0]] = trim($array[1]);
        }
        return $headers;
}

/// Write to socket
/**
 * Writes the content of a given buffer to given socket.
 * @param[in]   A valid TCP socket.
 * @param[in]   String to write.
 * @return      True on succes or false on failure.
 */
function write_to_socket($socket, $buffer)
{
        do {
                $size = strlen($buffer);
                $ret = socket_write($socket, $buffer);
                if($ret === false)
                        return false;
                if($ret !== $size)
                        $buffer = substr($buffer, $ret);
        } while($ret !== $size);
        return true;
}

?>

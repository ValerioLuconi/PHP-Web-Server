<?php
/**
 * @file Response.php
 * Class for creating a HTTP response and sending it to client.
 * @brief Response Class
 * @author Valerio Luconi
 * @version 0.1
 */

/// Class Response.
/**
 * @class Response
 * Reponse Class contains all parts of a HTTP response, and functions to handle them.
 */
class Response
{
        /// Absolute path.
        /**
         * Absolute path to requested resource.
         */
        private $absolute_path;

        /// Relative path.
        /**
         * Relative path to requested resource, from domain home directory.
         */
        private $relative_path;

        /// Virtual path. 
        /**
         * If a script is to be executed, virtual path to it.
         */
        private $virtual_path;

        /// Domain host.
        private $host;

        /// Resource
        /**
         * The effective resource to be sent.
         */
        private $resource;

        /// Accept header.
        /**
         * Request Accept header (if any).
         */
        private $accept;

        /// Accept-Charset header.
        /**
         * Request Accept-Charset header (if any).
         */
        private $accept_charset;

        /// Accept-Language header.
        /**
         * Request Accept-Language header (if any).
         */
        private $accept_language;

        /// Accept-Encoding header.
        /**
         * Request Accept-Encoding header (if any).
         */
        private $accept_encoding;

        /// TE header.
        /**
         * Request TE header (if any).
         */
        private $te;

        /// Connection status.
        /**
         * Connection status, depends on Connection header and HTTP version.
         */
        private $connection;

        /// Response headers.
        /**
         * Response headers to be set.
         */
        private $headers;

        /// Message body.
        /**
         * Requested resource's content (if any).
         */
        private $body;

        /// Is Script.
        /**
         * True if selected resource is a php script or a cgi program.
         */
        private $is_script = false;

        /// Script type.
        /**
         * Set to "cgi" or "php", if $is_script is true.
         */
        private $script_type;

        /// Argc
        /**
         * If query string is passed through command line, number of arguments
         */
        private $argc;

        /// Argv
        /**
         * If query string is passed through command line, array of arguments
         */
        private $argv;

        /// Env.
        /**
         * Environment variables for cgi scripts, set only if requested resource is a script.
         */
        private $env;

        /// Request body.
        /**
         * Request body (if any);
         */
        private $request_body;

        /// Error.
        /**
         * Boolean set if an error occurs.
         */
        private $error = false;

        /// Error code.
        /**
         * Response code.
         */
        private $code;


        /// Response constructor.
        /**
         * Initializes a Response object.
         * @param[in]   $req A Request object.
         * @return      No value is returned.
         */
        function __construct($req = 0)
        {
                // if bad request
                if($req === 0) {
                        $this->connection = "close";
                        return;
                }

                // set host
                $this->host = $req->get_host();

                // set relative path
                $this->relative_path = $req->get_path();

                // set absolute path
                $this->absolute_path = $GLOBALS["host"][$this->host]["home"].$this->relative_path;
                $this->absolute_path = str_replace("//", "/", $this->absolute_path);

                // replace aliases
                foreach($GLOBALS["host"][$this->host] as $key => $value) {
                        if($key === "home")
                                continue;
                        $pos = strpos($this->absolute_path, $key);
                        if($pos !== false) {
                                $this->absolute_path = str_replace($key, $value, $this->absolute_path);
                                if($key === "/cgi-bin/")
                                        $this->is_script = true;
                        }
                }

                // set accept field
                if($req->is_header_set("accept"))
                        $this->accept = $req->get_header("accept");

                // set accept charset field
                if($req->is_header_set("accept-charset"))
                        $this->accept_charset = $req->get_header("accept-charset");

                // set accept language field
                if($req->is_header_set("accept-language"))
                        $this->accept_language = $req->get_header("accept-language");

                // set accept encoding field
                if($req->is_header_set("accept-encoding"))
                        $this->accept_encoding = $req->get_header("accept-encoding");

                // set te field
                if($req->is_header_set("te"))
                        $this->te = $req->get_header("te");

                // set connection field
                if($req->get_http_version() !== "1.1") {
                        if(!$req->is_header_set("connection"))
                                $this->connection = "close";
                        else
                                $this->connection = $req->get_header("connection");
                }
                else {
                        if(!$req->is_header_set("connection"))
                                $this->connection = "keep-alive";
                        else
                                $this->connection = $req->get_header("connection");
                }
        }


        /// Get connection.
        /**
         * Gets connection value.
         * @return      "close" or "keep-alive".
         */
        function get_connection()
        {
                return $this->connection;
        }

        /// Get resource
        /**
         * Gets requested resource, and sets the resource private var with:
         * - An empty array if no resource is found (and sets code to 404) or resources found are not acceptable (and sets code to 406).
         * - An array of 1 element if only one resource is found (and sets code to 200).
         * - An array of more elements if more than one resource is found (and sets code to 300).
         * @return      No value is returned.
         */
        function get_resource()
        {
                $this->resource = find_resource($this->absolute_path);

                // no resource found
                if($this->resource === false) {
                        $this->error = true;
                        $this->code = 404;
                        $this->connection = "close";
                        return;
                }

                $ret = $this->content_negotiation($options);

                // bad accept* headers
                if($ret === false) {
                        $this->error = true;
                        $this->code = 400;
                        $this->connection = "close";
                        return;
                }

                // not acceptable resource
                elseif($ret === 0) {
                        $this->error = true;
                        $this->code = 406;
                        $this->connection = "close";
                        return;
                }

                // one resource found
                elseif($ret === 1)
                        $this->error = false;

                // more than one resource found
                else {
                        $i = 0;
                        foreach($this->resource as $key => $value) {
                                if(!isset($highest)) {
                                        $highest = $value;
                                        $this->resource[$i] = $key;
                                        unset($this->resource[$key]);
                                        $i++;
                                }
                                elseif($value === $highest) {
                                        $this->resource[$i] = $key;
                                        unset($this->resource[$key]);
                                        $i++;
                                }
                                else
                                        unset($this->resource[$key]);
                        }

                        // multiple choices
                        if($i > 1) {
                                $this->error = false;
                                $this->code = 300;
                                return;
                        }
                        // one choice
                        $options = $options[$this->resource[0]];
                        $this->error = false;
                }

                if($options["mime_type"] === "cgi-script") {
                        $this->is_script = true;
                        $this->script_type = "cgi";
                }
                if($options["mime_type"] === "application/x-httpd-php") {
                        $this->is_script = true;
                        $this->script_type = "php";
                }

                // if script, set virtual path to it for self referencing urls
                if($this->is_script) {
                        $this->virtual_path = str_replace($GLOBALS["host"][$this->host]["home"], "", $this->resource[0]);
                        $this->virtual_path = "/".$this->virtual_path;
                        foreach($GLOBALS["host"][$this->host] as $key => $value) {
                                if($key == "home")
                                        continue;
                                $pos = strpos($this->virtual_path, $value);
                                if($pos !== false)
                                        $this->virtual_path = str_replace($value, $key, $this->virtual_path);
                        }
                }

                $this->set_headers($options);
                $this->code = 200;
        }

        /// Content negotiation.
        /**
         * Does the content negotiation.
         * @param[out]  $options An associative bidimensional array in the format $options["resource"]["option"] = value
         * @return      The number of resources found of false on error.
         */
        function content_negotiation(&$options)
        {
                $size = count($this->resource);
                if($size === 1) {
                        // NO negotiation, only 1 file found
                        $options = set_file_features($this->resource[0]);
                        if(empty($options))
                                if($this->is_script === true)
                                        $options["mime_type"] = "cgi-script";
                                else
                                        $options["mime-type"] = "text/plain";
                        return 1;
                }

                // more than 1 file found
                if(!is_dir($this->absolute_uri))
                        // sets file features already present in requested path. they will NOT be negotiated
                        $options = set_file_features($this->absolute_uri);

                // which Accept* headers should i parse? the ones not present in options
                $ret = $this->accept_parsing($options);
                if($ret === false)
                        return false;

                $resource = array();
                $resource_options;
                // negotiate content
                for($i = 0; $i < $size; $i++) {
                        $resource_options[$this->resource[$i]] = set_file_features($this->resource[$i]);
                        $value = $this->get_resource_value($resource_options[$this->resource[$i]]);
                        if($value !== 0)
                                $resource[$this->resource[$i]] = $value;
                        else
                                unset($resource_options[$this->resource[$i]]);
                }

                // return resources
                $size = count($resource);
                if($size === 1) {
                        $len = count($this->resource);
                        for($i = 0; $i < $len; $i++) {
                                if(isset($resource[$this->resource[$i]])) {
                                        $this->resource = array(0 => $this->resource[$i]);
                                        break;
                                }
                        }
                        $options = $resource_options[$this->resource[0]];
                        return 1;
                }

                // $this->resource, associative array in the format $this->resource["path"] = "value";
                $this->resource = $resource;
                arsort($this->resource, SORT_NUMERIC);
                $options = $resource_options;
                return $size;
        }

        /// Accept* parsing
        /**
         * Does the parsing of accept* header fields that are not already specified in the $options array.
         * @param[in]   $options An associative array containing the options already specified and not to parse in the accept header.
         * @return      True on succes, false on error.
         */
        function accept_parsing($options)
        {
                if(!isset($options["mime_type"])) {
                        $this->accept = parse_accept($this->accept);
                        if($this->accept === false)
                                return false;
                }
                if(!isset($options["language"])) {
                        $this->accept_language = parse_accept($this->accept_language);
                        if($this->accept_language === false)
                                return false;
                }
                if(!isset($options["charset"])) {
                        $this->accept_charset = parse_accept($this->accept_charset);
                        if($this->accept_charset === false)
                                return false;
                }
                if(!isset($options["encoding"])) {
                        $this->accept_encoding = parse_accept($this->accept_encoding);
                        if($this->accept_encoding === false)
                                return false;
                        if(!isset($this->accept_encoding["identity"]))
                                $this->accept_encoding["identity"] = 1;
                }
                return true;
        }

        /// Get Resource Value.
        /**
         * Implementation of the content negotiation algorithm.
         * @param[in,out]       &$options An associative array containing file charateristics, that will be used to decide if it is an acceptable resource.
         * @return      A value between 0 and 1, that indicates the level of acceptability of the resource.
         */
        function get_resource_value(&$options)
        {
                $value = 1;

                if(!isset($options["mime_type"]))
                        if($this->is_script === true)
                                $options["mime_type"] =  "cgi-script";
                        else
                                $options["mime_type"] =  "text/plain";
                $type = $options["mime_type"];

                // file is a php script or cgi script
                if($type === "cgi-script" || $type === "application/x-httpd-php")
                        $value *= 1;

                // accept anything
                elseif(empty($this->accept))
                        $value *= 1;

                // accept found
                elseif(isset($this->accept[$type]))
                        $value *= $this->accept[$type];

                // is there type/* or */* ?
                else {
                        $pos = strpos($type, "/");
                        $subtype = substr($type, $pos + 1);
                        $type = str_replace($subtype, "*", $type);
                        if(isset($this->accept[$type]))
                                $value *= $this->accept[$type];
                        elseif(isset($this->accept["*/*"]))
                                $value *= $this->accept["*/*"];
                        // not acceptable
                        else {
                                $value *= 0;
                                return $value;
                        }
                }

                // unset language
                if(!isset($options["language"])) {
                        if(empty($this->accept_language))
                                $value *= 1;
                        elseif(isset($this->accept_language["*"]))
                                $value *= $this->accept_language["*"];
                }

                // language set, accept any language
                elseif(empty($this->accept_language))
                        $value *= 1;

                // accept-language found
                elseif(isset($this->accept_language[$options["language"]]))
                        $value *= $this->accept_language[$options["language"]];

                // is there accept-language: *?
                elseif(isset($this->accept_language["*"]))
                        $value *= $this->accept_language["*"];

                // not acceptable
                else {
                        $value *= 0;
                        return $value;
                }

                // unset charset
                if(!isset($options["charset"])) {
                        if(empty($this->accept_charset))
                                $value *= 1;
                        elseif(isset($this->accept_charset["*"]))
                                $value *= $this->accept_charset["*"];
                }

                // charset set, accept any
                elseif(empty($this->accept_charset))
                        $value *= 1;

                // accept-charset found
                elseif(isset($this->accept_charset[$options["charset"]]))
                        $value *= $this->accept_charset[$options["charset"]];

                // accept charset *
                elseif(isset($this->accept_charset["*"]))
                        $value *= $this->accept_charset["*"];

                // not acceptable
                else {
                        $value *= 0;
                        return $value;
                }

                // no encoding
                if(!isset($options["encoding"]))
                        $options["encoding"] = "identity";
                $encoding = $options["encoding"];

                // accept any encoding
                if(empty($this->accept_encoding))
                         $value *= 1;

                // accept-encoding found
                elseif(isset($this->accept_encoding[$encoding]))
                         $value *= $this->accept_encoding[$encoding];

                // accept-encoding *
                elseif(isset($this->accept_encoding["*"]))
                        $value *= $this->accept_encoding["*"];

                // not acceptable
                else {
                        $value *= 0;
                        return $value;
                }

                return $value;
        }

        /// Set headers.
        /** 
         * Sets response headers with their values.
         * @param[in]   $options Associative array containing headers to set and values.
         * @return      No value is returned.
         */
        function set_headers($options)
        {
                if(isset($options["mime_type"])) {
                        if($options["mime_type"] !== "cgi-script" && $options["mime_type"] !== "application/x-httpd-php") {
                                $this->headers["Content-Type"] = $options["mime_type"];
                                if(isset($options["charset"]))
                                        $this->headers["Content-Type"] .= "; charset=".$options["charset"];
                        }
                }
                if(isset($options["language"]))
                        $this->headers["Content-Language"] = $options["language"];
                if(isset($options["encoding"]))
                        $this->headers["Content-Encoding"] = $options["encoding"];
        }

        /// Set environment variables.
        /**
         * Sets environment variables to be passed to a cgi or php script.
         * @param[in]   $req A valid Request object.
         * @return      No value is returned
         */
        function set_env($req)
        {
                $host = $this->host;
                $pos = strpos($host, ":");
                $host = substr($host, 0, $pos);
                $version = $req->get_http_version();
                $method = $req->get_method();
                $headers = $req->get_headers();
                $remote_host = $req->get_remote_host();
                $remote_port = $req->get_remote_port();
                $query_string = $req->get_query_string();
                $this->env["SERVER_SOFTWARE"] = "PHP Web Server";
                $this->env["SERVER_NAME"] = $host;
                $this->env["GATEWAY_INTERFACE"] = "CGI/1.1";
                $this->env["SERVER_PROTOCOL"] = "HTTP/".$version;
                $this->env["SERVER_PORT"] = $GLOBALS["port"];
                $this->env["REQUEST_METHOD"] = $method;

                // setting query string, path info and path translated, or arguments for cgi script.
                if($query_string !== false) {
                        $pos = strpos($query_string, "/");
                        if($pos !== false) {
                                $path_info = substr($query_string, $pos);
                                $query_string = substr($query_string, 0, $pos);
                                $path_translated = $GLOBALS["host"][$this->host]["home"].$path_info;
                                $path_translated = str_replace("//", "/", $path_translated);
                                foreach($GLOBALS["host"][$this->host] as $key => $value) {
                                        if($key === "home")
                                                continue;
                                        $pos = strpos($path_translated, $key);
                                        if($pos !== false) {
                                                $path_translated = str_replace($key, $value, $path_translated);
                                        }
                                }
                                $this->env["PATH_INFO"] = $path_info;
                                $this->env["PATH_TRANSLATED"] = $path_translated;
                        }
                        $pos = strpos($query_string, "=");
                        if($pos === false) {
                                $this->argv = explode("+", $query_string);
                                $this->argc = count($this->argv);
                        }
                        else
                                $this->env["QUERY_STRING"] = $query_string;
                }
                $this->env["SCRIPT_NAME"] = $this->virtual_path;
                $this->env["REMOTE_HOST"] = "";
                $this->env["REMOTE_ADDR"] = $remote_host;
                if($method === "POST") {
                        $this->request_body = $req->get_body();
                        $len = strlen($this->request_body);
                        $this->env["CONTENT_TYPE"] = $headers["content-type"];
                        $this->env["CONTENT_LENGTH"] = $len;
                }
                foreach($headers as $key => $value) {
                        $key = strtoupper($key);
                        $key = str_replace("-", "_", $key);
                        $this->env["HTTP_".$key] = $value;
                }
                if($this->script_type === "php") {
                        $this->env["PHP_SELF"] = $this->virtual_path;
                        $this->env["DOCUMENT_ROOT"] = $GLOBALS["host"][$this->host]["home"];
                        $this->env["REMOTE_PORT"] = $remote_port;
                        $this->env["SCRIPT_FILENAME"] = $this->resource[0];
                        $this->env["REQUEST_URI"] = $this->relative_path;
                }
        }

        /// Send response.
        /**
         * Sends the HTTP response if there is no error.
         * @param[in]   $socket A valid TCP socket.
         * @return      False on error.
         */
        function send($socket, $method, $version)
        {
                // set headers
                if($this->code == 200) {
                        $response = file_get_contents("./response/200.http");
                        if(!$this->is_script()) {
                                $this->body = file_get_contents($this->resource[0]);
                                $stat = stat($this->resource[0]);
                                $last = $stat["mtime"];
                                $last = gmdate("D, d M Y H:i:s T", $last);
                                $this->headers["Last-Modified"] = $last;
                        }
                        if(!empty($this->headers)) {
                                foreach($this->headers as $key => $value) {
                                        $response .= $key.": ".$value."\r\n";
                                }
                        }
                        $response .= "\r\n";
                }
                if($this->code == 300) {
                        $response = file_get_contents("./response/300.http");       
                        $size = count($this->resource);
                        for($i = 0; $i < $size; $i++) {
                                $home_dir = $GLOBALS["host"][$this->host]["home"];
                                $relative_path = str_replace($home_dir, "", $this->resource[$i]);
                                $relative_path = "/".$relative_path;
                                $temp = "http://".$this->host.$relative_path;
                                $url .= "<a href=\"$temp\">$relative_path</a><br/>\n";
                        }
                        $body = file_get_contents("./response/300.html");
                        eval("\$body = \"$body\";");
                        $this->body = $body;
                }
                if($this->code == 302) {
                        $response = file_get_contents("./response/302.http");
                        if(!empty($this->headers)) {
                                foreach($this->headers as $key => $value)
                                        $response .= $key.": ".$value."\r\n";
                        }
                        $response .= "\r\n";
                        $url = "http://".$this->host.$this->headers["Location"];
                        $url = "<a href=\"$url\">$url</a>";
                        $body = file_get_contents("./response/302.html");
                        eval("\$body = \"$body\";");
                        $this->body = $body;
                }
                $len = strlen($this->body);
                $date = gmdate("D, d M Y H:i:s T");
                eval("\$response = \"$response\";");

                // if method is head, no body
                if($method !== "HEAD")
                        $response .= $this->body;

                // send response
                $ret = write_to_socket($socket, $response);
                if($ret === false)
                        $this->connection = "close";
        }

        /// Send script.
        /**
         * Builds and sends response if requested resource is a cgi or php script.
         * @param[in]   $socket A valid TCP socket.
         * @param[in]   $method Request method.
         * @param[in]   $version Request version.
         * @return      No value is returned.
         */
        function send_script($socket, $method, $version)
        {
                if($this->script_type === "php") {
                        $name = "php-cgi";
                        // command line arguments
                        if(isset($this->argv)) {
                                unset($this->env);
                                $name .= " ".$this->resource[0];
                                for($i = 0; $i < $this->argc; $i++)
                                        $name .= " ".$this->argv[$i];
                        }
                }
                else {
                        $name = $this->resource[0];
                        // command line arguments
                        if(isset($this->argv)) {
                                for($i = 0; $i < $this->argc; $i++)
                                        $name .= " ".$this->argv[$i];
                        }
                }
                // i/o descriptors for script. 0 -> stdin; 1 -> stdout; 2 -> stderr.
                $descriptors = array(   0 => array("pipe", "r"),
                                        1 => array("pipe", "w"),
                                        2 => array("pipe", "w"));

                // current working directory for script.
                $cwd = pathinfo($this->resource[0], PATHINFO_DIRNAME)."/";
                $filename = str_replace($cwd, "", $this->resource[0]);

                // execute script
                $res = proc_open($name, $descriptors, $pipes, $cwd, $this->env);

                // if POST, send data on stdin
                if($method === "POST")
                        fwrite($pipes[0], $this->request_body);
                fclose($pipes[0]);

                // is Non-parsed-header script? redirect output to client.
                $pos = strpos($filename, "nph-");
                if($pos === 0 && $this->script_type === "cgi") {
                        $buffer = stream_get_contents($pipes[1]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        $return = proc_close($res);
                        $ret = write_to_socket($socket, $buffer);
                        if($ret === false)
                                $this->connection = "close";
                        return;
                }

                // no chunked, get all script output
                if($version !== "1.1") {
                        $buffer = stream_get_contents($pipes[1]);
                        $header = read_script_header($buffer);
                }

                // transfer-encoding enabled
                else {
                        $buffer = "";
                        $header = read_script_header($buffer, $pipes[1]);
                }

                // header given from script is not valid.
                if($header === false) {
                        $this->error($socket, 500, $method, $version);
                        $this->connection = "close";
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }

                $headers = parse_script_header($header);
                // not valid headers
                if($headers === false) {
                        $this->error($socket, 500, $method, $version);
                        $this->connection = "close";
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }

                // parse status header
                if(isset($headers["Status"])) {
                        $status = $headers["Status"];
                        $status = explode(" ", $status, 2);
                        $code = $status[0];
                        if(!ctype_digit($code)) {
                                $this->error($socket, 500, $method, $version);
                                $this->connection = "close";
                                fclose($pipes[1]);
                                fclose($pipes[2]);
                                proc_close($res);
                                return;
                        }
                        $this->error($socket, $code, $method, $version);
                        $this->connection = "close";
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }
                foreach($headers as $key => $value)
                        $this->headers[$key] = $value;

                // if set location, send 302 redirect.
                if(isset($headers["Location"])) {
                        $this->code = 302;
                        $this->send($socket, $method, $version);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }
                // send response for version <= 1.0
                if($version !== "1.1") {
                        $this->body = $buffer;
                        $this->send($socket, $method, $version);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }

                // start sending response for version 1.1 (chunked and other transfer encodings)

                // check if some coding is desired
                if(isset($this->te)) {
                        $this->te = parse_accept($this->te);
                        // check transfer codings
                        if(!empty($this->te)) {
                                foreach($this->te as $key => $value) {
                                        if(isset($GLOBALS["transfer_codings"][$key]) && $value != 0) {
                                                $coding = $key;
                                                break;
                                        }
                                }
                        }
                }

                // set response headers
                $response = file_get_contents("./response/200chunked.http");

                if(isset($coding))
                        $this->headers["Transfer-Encoding"] = "$coding, chunked";
                else
                        $this->headers["Transfer-Encoding"] = "chunked";

                if(!empty($this->headers)) {
                        foreach($this->headers as $key => $value)
                                $response .= $key.": ".$value."\r\n";
                }
                $response .= "\r\n";
                $date = gmdate("D, d M Y H:i:s T");
                eval("\$response = \"$response\";");

                // send response header
                $ret = write_to_socket($socket, $response);
                if($ret === false) {
                        $this->connection = "close";
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }
                
                if($method === "HEAD") {
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }

                // send encoded + chunked body
                if(isset($coding)) {
                        $buffer .= stream_get_contents($pipes[1]);
                        switch($coding) {
                                case "gzip":
                                        $buffer = gzencode($buffer);
                                        break;
                                case "deflate":
                                        $buffer = gzencode($buffer, FORCE_DEFLATE);
                                        break;
                                case "compress":
                                        $buffer = gzcompress($buffer);
                                        break;
                        }
                        $len = strlen($buffer);
                        $len = dechex($len);
                        $ret = write_to_socket($socket, "$len\r\n$buffer\r\n0\r\n\r\n");
                        if($ret === false)
                                $this->connection = "close";
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($res);
                        return;
                }
                
                // send only chunked body
                while(!feof($pipes[1])) {
                        $read = fread($pipes[1], 1024);
                        $buffer .= $read;
                        if($buffer === "")
                                continue;
                        $len = strlen($buffer);
                        $chunk = dechex($len)."\r\n".$buffer."\r\n";
                        $ret = write_to_socket($socket, $chunk);
                        if($ret === false) {
                                $this->connection = "close";
                                fclose($pipes[1]);
                                fclose($pipes[2]);
                                proc_close($res);
                                return;
                        }
                        $buffer = "";
                }
                $ret = write_to_socket($socket, "0\r\n\r\n");
                if($ret === false)
                        $this->connection = "close";
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($res);
        }

        /// Is error?
        /**
         * Checks if an error has occured in the resource retrieval.
         * @return      True if no error has occured, or false on error.
         */
        function is_error()
        {
                return $this->error;
        }

        /// Is script?
        /**
         * Checks if requested resource is a cgi or php script
         */
        function is_script()
        {
                return $this->is_script;
        }

        /// Get code.
        /**
         * Returns the code for the HTTP response.
         * @return      Code identificating current response.
         */
        function get_code()
        {
                return $this->code;
        }

        /// Error
        /**
         * Sends an error response.
         * @param[in]   $socket A valid TCP socket.
         * @param[in]   $code Error code.
         * @param[in]   $method If HEAD sends only message headers.
         * @return      True on succes, false on failure.
         */
        function error($socket, $code, $method, $version)
        {
                $url = 0;
                if($this->code == 404)
                        $url = $this->relative_path;
                $ret = send_error($socket, $code, $method, $version, $url);
                if($ret === false)
                        return false;
                return true;
        }
}

?>

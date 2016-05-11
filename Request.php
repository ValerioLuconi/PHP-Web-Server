<?php
/**
 * @file Request.php
 * Class for processing a HTTP request.
 * @brief Request Class
 * @author Valerio Luconi
 * @version 0.1
 */

/// Class Request.
/**
 * @class Request
 * Request Class contains all parts of a HTTP request, and functions to handle them.
 */
class Request
{
        /// HTTP method.
        /**
         * HTTP Request method.
         */
        private $method;

        /// Request URI.
        /**
         * An associative array containing all parts of a HTTP Request URI.
         */
        private $uri;

        /// HTTP version.
        /**
         * HTTP Request version.
         */
        private $version;

        /// Request headers.
        /**
         * Associative array: $headers["header-name"] = "value".
         */
        private $headers;

        /// Request body (if any).
        /**
         * HTTP Message body, if method is POST.
         */
        private $body;

        /// Remote Host
        /**
         * Client's IP Address.
         */
        private $remote_host;

        /// Remote Port
        /**
         * Client's Port.
         */
        private $remote_port;

        /// Boolean bad_request.
        /**
         * Set true if the request is a non valid HTTP request.
         */
        private $bad_request = false;

        /// Error code.
        /**
         * If $bad_request is true, contains the HTTP error code.
         */
        private $error_code = "";


        /// Request constructor.
        /**
         * Initializes a Request object.
         * @param[in]   $socket A valid TCP socket.
         * @param[in,out]       &$buffer Buffer in which storing HTTP requests.
         * @return      No value is returned.
         */
        function __construct($socket, &$buffer)
        {
                // who is client?
                $ret = socket_getpeername($socket, $this->remote_host, $this->remote_port);
                if($ret === false) {
                        $this->bad_request = true;
                        $this->error_code = 500;
                        return;
                }

                // read request header
                $header = read_header($socket, $buffer);
                if($header === null) {
                        $this->bad_request = true;
                        $this->error_code = 500;
                        return;
                }

                // set $method, $uri and $version
                $this->set_request_line($header);
                if($this->bad_request)
                        return;

                // parse $uri in an associative array containing its parts
                $uri = parse_uri($this->uri);
                if($uri === null) {
                        $this->bad_request = true;
                        $this->error_code = 400;
                        return;
                }
                $this->uri = $uri;

                // parse headers
                $this->set_request_headers($header);
                if($this->bad_request)
                        return;

                // check errors in header
                if($this->is_bad_header())
                        return;

                // read body if method is POST
                if($this->method == "POST") {
                        if($this->is_header_set("content-length"))
                                $length = intval($this->get_header("content-length"));
                        else
                                $length = 0;
                        $body = read_body($socket, $length, $buffer);
                        if($body === null) {
                                $this->bad_request = true;
                                $this->error_code = 500;
                                return;
                        }
                        if($body === -1) {
                                $this->bad_request = true;
                                $this->error_code = 400;
                                return;
                        }
                        $this->body = $body;
                }
        }

        /// Set Request Line
        /**
         * Sets method, uri and version of a HTTP request, or sets bad_request if the request line is not valid.
         * @param[in]   $request_line A string containing a HTTP request line.
         */
        function set_request_line(&$header)
        {
                $pos = strpos($header, "\r\n");
                $request_line = substr($header, 0, $pos);
                $header = substr($header, $pos + 2);
                $request_line = explode(" ", $request_line);
                if (count($request_line) != 3) {
                        // not an http request
                        $this->bad_request = true;
                        $this->error_code = 400;
                        return;
                }
                $this->method = $request_line[0];
                $this->uri = $request_line[1];
                $this->version = get_version($request_line[2]);
                if($this->version == null) {
                        // not an http request
                        $this->bad_request = true;
                        $this->error_code = 400;
                        return;
                }
        }                


        /// Set Request Headers.
        /**
         * Fills Class $headers from a given string containing a HTTP request.
         * @param[in]   $request_header String containing a HTTP request.
         */
        function set_request_headers($request_headers)
        {
                $request_headers = trim($request_headers);

                // no headers
                if(empty($request_headers)) {
                        return;
                }
                $req_array = explode("\r\n", $request_headers);

                $size = count($req_array);                

                // parse headers
                for ($i = 0; $i < $size; $i++) {
                        $req_array[$i] = str_replace(" ", "", $req_array[$i]);
                        $req_array[$i] = str_replace("\t", "", $req_array[$i]);
                        $req_array[$i] = strtolower($req_array[$i]);
                        // checks if there is ":" in the header string
                        if(!strpos($req_array[$i], ":")) {
                                // not a valid HTTP header
                                $this->bad_request = true;
                                $this->error_code = 400;
                                return;
                        }
                        $temp = explode(":", $req_array[$i], 2);
                        $this->headers[$temp[0]] = $temp[1];
                }
        }

        /// Is bad header?
        /**
         * Checks if relevant syntactical errors occur in request header. If yes sets bad_request to true and an appropriate error_code.
         * @return      True if header is syntactically wrong, or false if not.
         */
        function is_bad_header()
        {
                if($this->bad_request)
                        return true;

                // is method supported?
                if(!is_supported_method($this->method)) {
                        $this->bad_request = true;
                        $this->error_code = 501;
                        return true;
                }

                // if HTTP/1.1, is host set?
                if($this->version == "1.1" && !isset($this->uri["host"]) && !$this->is_header_set("host")) {
                        $this->bad_request = true;
                        $this->error_code = 400;
                        return true;
                }

                // if HTTP/1.0, where's host?
                if($this->version !== "1.1" && !isset($this->uri["host"]) && !$this->is_header_set("host")) {
                        $this->bad_request = true;
                        // not an error code, but we can consider it a "not properly correct" request.
                        $this->error_code = 300;
                        return true;
                }

                // if host set, is it correct?
                if(isset($this->uri["host"])) {
                        $host = $this->uri["host"].":".$this->uri["port"];
                        if(!isset($GLOBALS["host"][$host]["home"])) {
                                $this->bad_request = true;
                                $this->error_code = 400;
                                return true;
                        }
                }
                elseif($this->is_header_set("host")) {
                        $host = $this->get_header("host");
                        if(!isset($GLOBALS["host"][$host]["home"])) {
                                $this->bad_request = true;
                                $this->error_code = 400;
                                return true;
                        }
                }

                // if POST
                if($this->method == "POST") {
                        // is Content-Length set?
                        if(!$this->is_header_set("content-length")) {
                                // no

                                // is Transfer-Encoding set? 
                                if(!$this->is_header_set("transfer-encoding")) {
                                        // no
                                        $this->bad_request = true;
                                        $this->error_code = 411;
                                        return true;
                                }

                                // Transfer-Encoding is chunked?
                                if ($this->get_header("transfer-encoding") != "chunked") {
                                        // no
                                        $this->bad_request = true;
                                        $this->error_code = 400;
                                        return true;
                                }
                                // Transfer-Encoding chunked
                                return false;
                        }
                        // is set Content-Length and Transfer-Encoding?
                        if($this->is_header_set("transfer-encoding")) {
                                // yes
                                $this->bad_request = true;
                                $this->error_code = 400;
                                return true;
                        }

                        // only Content-Length
                        $len = $this->get_header("content-length");
                        if(!ctype_digit($len)) {
                                // Content-Length contains non numerical characters
                                $this->bad_request = true;
                                $this->error_code = 400;
                                return true;
                        }
                        return false;
                }

                // GET or HEAD: is Content-Length or Transfer-Encoding set? if yes, error
                elseif($this->is_header_set("content-length") || $this->is_header_set("transfer-encoding")) {
                        $this->bad_request = true;
                        $this->error_code = 400;
                        return true;
                }

                // no errors in header
                return false;
        }



        /// Returns method.
        /**
         * Gets request method.
         * @return      Request method.
         */
        function get_method()
        {
                return $this->method;
        }

        /// Returns request-URI.
        /**
         * Gets requested path.
         * @return      Requested path.
         */
        function get_path()
        {
                return $this->uri["path"];
        }

        /// Returns HTTP version.
        /**
         * Gets request HTTP version.
         * @return      Request HTTP version.
         */
        function get_http_version()
        {
                return $this->version;
        }

        /// Is header set?
        /**
         * Checks if a header is set or not.
         * @param[in]   $header Header name to be checked.
         * @return      True if header is present or false if not.
         */
        function is_header_set($header)
        {
                return isset($this->headers[$header]);
        }

        /// Get header.
        /**
         * Gets the value of a specific header.
         * @param[in]   $header A string containing a HTTP header.
         * @return      The value of the selected header, or "" if not set.
         */
        function get_header($header)
        {
                if(isset($this->headers[$header]))
                        return $this->headers[$header];
                return false;
        }

        /// Get Headers
        /**
         * Gets all request headers.
         * @return an associative array in format $array["header"]="value".
         */
        function get_headers()
        {
                return $this->headers;
        }

        /// Returns request body.
        /**
         * Gets request body.
         * @return      Request body.
         */
        function get_body()
        {
                return $this->body;
        }

        /// Returns request host.
        /**
         * Gets Request host.
         * @return      Request host.
         */
        function get_host()
        {
                if(isset($this->uri["host"]))
                        $host = $this->uri["host"].":".$this->uri["port"];
                else
                        $host = $this->get_header("host");
                return $host;
        }

        /// Get query string.
        /**
         * Returns query string (if any).
         * @return      Query string or false if no query string is set.
         */
        function get_query_string()
        {
                if(isset($this->uri["query"]))
                        return $this->uri["query"];
                else
                        return false;
        }

        /// Get remote host.
        /**
         * Gets remote host ip address.
         * @return      Remote host ip address.
         */
        function get_remote_host()
        {
                return $this->remote_host;
        }

        /// Get remote port.
        /**
         * Gets remote host port number.
         * @return      Remote host port number.
         */
        function get_remote_port()
        {
                return $this->remote_port;
        }

        /// Is Bad.
        /**
         * Checks if request is sintactically wrong.
         * @return      True if bad request, false if not.
         */
        function is_bad()
        {
                return $this->bad_request;
        }

        /// Error code.
        /**
         * Gets error code.
         * @return      Error code if request is bad.
         */
        function get_error_code()
        {
                return $this->error_code;
        }
}

?>


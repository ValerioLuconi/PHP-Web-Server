#!/usr/bin/php

<?php
/**
 * @file server.php
 * This file contains the main program functions.
 * @brief Main function.
 * @author Valerio Luconi
 * @version 0.1
 */

include("Request.php");
include("Response.php");
include("utility.php");
include("globals.php");


/// Child process.
/**
 * Handles connection with client.
 * @param[in]   $socket The parent socket.
 * @param[in]   $accept The client socket.
 * @return      No value is returned.
 */
function child($socket, $accept)
{
        socket_close($socket);
        $buffer = "";
        $timeout = 20;


        do {
                unset($request);
                unset($response);

                // waits max $timeout seconds for a request from client
                $read = array($accept);
                $write = null;
                $exp = null;
                $ret = socket_select($read, $write, $exp, $timeout);
                if($ret === 0 || $ret === false) {
                        // if no request arrives return 408 Request Time-out
                        send_error($accept, "408", "GET");
                        socket_close($accept);
                        exit(0);
                }

                $request = new Request($accept, $buffer);

                if($request->is_bad()) {
                        $response = new Response();
                        send_error($accept, $request->get_error_code(), $request->get_method(), $request->get_http_version());
                }

                else {
                        $response = new Response($request);
                        $response->get_resource();
                        if(!$response->is_error()) {
                                if($response->is_script()) {
                                        $response->set_env($request);
                                        $response->send_script($accept, $request->get_method(), $request->get_http_version());
                                }
                                else
                                        $response->send($accept, $request->get_method(), $request->get_http_version());
                        }
                        else
                                $response->error($accept, $response->get_code(), $request->get_method(), $request->get_http_version());
                }
                if($request->is_header_set("connection") && $request->is_header_set("keep-alive"))
                        $timeout = intval($request->get_header("keep-alive"));
        } while($response->get_connection() !== "close");

        socket_close($accept);
        exit(0);
}

/// Parent process.
/**
 * Kills all zombie child processes.
 * @param[in]   $accept The client socket to close.
 * @return      No value is returned.
 */
function parent($accept)
{
        socket_close($accept);
        while (pcntl_wait($status, WNOHANG) > 0);
}

/// Main Function
/**
 * Initializes socket, waits for incoming connections and forks into parent and child.
 * @param[in]   $port Listening port.
 * @return      No value is returned.
 */
function main()
{
        // read configuration files.
        $ret = config();
        if($ret === false) {
                echo "Error: unable to read configuration files\n";
                exit(1);
        }

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if($socket === false) {
                echo "Error: unable to create socket\n";
                exit(1);
        }

        $ret = socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if($ret === false) {
                echo "Error: unable to set REUSEADDR option on socket\n";
                exit(1);
        }

        // bind to INADDR_ANY on given port.
        $ret = socket_bind($socket, "0.0.0.0", $GLOBALS["port"]);
        if($ret === false) {
                echo "Error: unable to bind socket\n";
                exit(1);
        }

        $ret = socket_listen($socket, 10);
        if($ret === false) {
                echo "Error: unable to listen on socket\n";
                exit(1);
        }

        for(;;) {
                $accept = socket_accept($socket);
                if($accept === false) {
                        echo "Error: unable to accept incoming connection\n";
                        continue;
                }
                $pid = pcntl_fork();
                if($pid == -1) {
                        send_error($accept, 500);
                        socket_close($accept);
                        continue;
                } 
                elseif($pid == 0)
                        // child process
                        child($socket, $accept);
                else
                        // parent process
                        parent($accept);
        }
}

main();

?>

#!/usr/bin/php
<?php

error_reporting(E_STRICT | E_ALL);

// Set some default values
$authorize_url = "https://developer.api.autodesk.com/authentication/v1/authorize";
$gettoken_url = "https://developer.api.autodesk.com/authentication/v1/gettoken";
$refreshtoken_url = "https://developer.api.autodesk.com/authentication/v1/refreshtoken";
$scope="data:read";
$response_type="code";
$grant_type="authorization_code";


//Describe command line usage
$usage = "php_forge_auth_cli.php is used to 3-legged auth and re-auth against Forge generating tokens for access to A360/Fusion Team/BIM360 Team files.
There are two usage paradigms:
    1) Initial authentication opening the browser and allowing the user to authorize their account and get a token with an expiration time.
    2) Refresh authentication allowing the token to be refreshed before the expiration time.
**Note: If authentication is not refreshed before the token expires, then initial authentication is required 

Usage: php auth.php [OPTIONS]
    -m --mode=[initial|refresh]
    -t --tokenfile=<file path to store the current token and expiry>
    -k --keyfile=<file path to the location of a file with your Forge client id and secret>
    
Examples:
    Initial: php php_forge_auth_cli.php --mode=initial --tokenfile=\"temp.txt\" --keyfile=\"keyfile.txt\"
    Refresh: php php_forge_auth_cli.php --mode=refresh --tokenfile=\"temp.txt\" --keyfile=\"keyfile.txt\"

Key File:
    The Key File format should be a plain-text file with the name of the variable followed by its value in the file separated by an equals sign (e.g. NAME=value):
    FORGE_CLIENT_ID
    FORGE_CLIENT_SECRET
    FORGE_CALLBACK_URL
";

//Exit if no options passed in
if ($argc <= 1) {
    usage_error("No inputs specified");
}

//Parse out option values
while (next_opt($opt, $val, $args)) {
    switch($opt) {
        case 'm':
        case 'mode':
            $mode = $val;
            if( !in_array($mode, array('initial', 'refresh'), true) ) {
                usage_error("mode not 'initial' or 'refresh'");
            }
            break;
            
        case 't':
        case 'tokenfile':
            $tokenfile = $val;
            if ( !file_exists($tokenfile)) {
                usage_error(" $tokenfile doesn't exist");
            }
            break;
        
        case 'k':
        case 'keyfile':
            $keyfile = $val;
            if ( !file_exists($keyfile)) {
                usage_error(" $keyfile doesn't exist");
            }
            break;
            
        default:
            usage_error("Unrecognized option $opt found in command line");
    }
}

//Check we have values for all the command line options we need
if ( !(isset($mode) && strlen(trim($mode)) > 0)  ) {
    usage_error("mode not defined.");
}
if ( !(isset($tokenfile) && strlen(trim($tokenfile)) > 0)  ) {
    usage_error("tokenfile not defined.");
}
if ( !(isset($keyfile) && strlen(trim($keyfile)) > 0)  ) {
    usage_error("keyfile not defined.");
}

//Check we have values for Client ID & Secret and get them into variables named appropriately
$keys = file($keyfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$keys) {
    usage_error("keyfile did not contain any content");
}
foreach($keys as $line) {
    $vals = explode("=", $line);
    ${$vals[0]} = $vals[1];
}
if ( !(isset($FORGE_CLIENT_ID) && strlen(trim($FORGE_CLIENT_ID)) > 0)  ) {
    usage_error("FORGE_CLIENT_ID not found in $keyfile.");
}
if ( !(isset($FORGE_CLIENT_SECRET) && strlen(trim($FORGE_CLIENT_SECRET)) > 0)  ) {
    usage_error("FORGE_CLIENT_SECRET not found in $keyfile.");
}
if ( !(isset($FORGE_CALLBACK_URL) && strlen(trim($FORGE_CALLBACK_URL)) > 0)  ) {
    usage_error("FORGE_CALLBACK_URL not found in $keyfile.");
}


if ($mode == "refresh") {
    $tokens = file_get_contents($tokenfile);
    if (!$tokens) {
        usage_error("No tokens found in $tokenfile. Can't refresh a non-existent token.");
    }
    $tokens_array = json_decode($tokens, true);
    if ( (filemtime($tokenfile)+$tokens_array['expires_in']) <= time() ) {
        usage_error("Token is too old to refresh. You must re-authenticate with initial mode.");
    }
    $data = "refresh_token=".$tokens_array['refresh_token']."&client_id=".$FORGE_CLIENT_ID."&client_secret=".$FORGE_CLIENT_SECRET."&grant_type=refresh_token";
    $response = http_post($refreshtoken_url, $data);
    file_put_contents($tokenfile, $response);
} else if ($mode == "initial") {
    fclose(fopen($tokenfile,'w'));
    $auth_url = $authorize_url . "?response_type=".$response_type."&client_id=".$FORGE_CLIENT_ID."&redirect_uri=".$FORGE_CALLBACK_URL."&scope=".$scope;
    $server = stream_socket_server("tcp://127.0.0.1:3000", $errno, $errorMessage);

    if ($server === false) {
        throw new UnexpectedValueException("Could not bind to socket: $errorMessage");
    }
    open_browser($auth_url);
    for (;;) {
        $client = @stream_socket_accept($server);
        $buff = "";
        do {
            $buff .= fread($client, 1024*8);
        } while (!preg_match('/\r?\n\r?\n/', $buff));
        fwrite($client, "HTTP/1.0 200 OK\r\n" .
                    "Connection: close\r\n" .
                    "Expires: -1\r\n".
                    "Content-Type: text/html\r\n" .
                    "\r\n" .
                    "<h1>OK.</h1>\n".
                    "You can close this tab now.\n
                    <script>open('', '_self', ''); close()</script>");
        fclose($client);
        break;
    }
    $resp = parse_http_response($buff);
    $code = $resp["code"];
    $data = "client_id=".$FORGE_CLIENT_ID."&client_secret=".$FORGE_CLIENT_SECRET."&grant_type=".$grant_type."&code=".$code."&redirect_uri=".$FORGE_CALLBACK_URL;
    $response = http_post($gettoken_url, $data);
    file_put_contents($tokenfile, $response);
    exit(0);
} else {
    usage_error("Unknown run mode");
}


/////////////////////////////////////////////////// Helper Functions /////////////////////////////////////////////////////////////////////

function http_post($url, $data) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curl);
    $curl_errno = curl_errno($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);
    return $output;
}

function open_browser($url) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $string = '"C:\Program Files\internet explorer\iexplore.exe" ' . escapeshellarg($url);
        pclose(popen('explorer '.escapeshellarg($url), 'r'));
    } else {
        shell_exec("x-www-browser --new-window " . escapeshellarg($url));
    }   
}

function parse_http_response($data) {
    preg_match('/^GET\s+\/\?(\S+)\s+HTTP\/\S+\r?\n/', $data, $matches);
    parse_str($matches[1], $params);
    return $params;
}

function next_opt(&$opt, &$val, &$args) {
    global $argv;
    
    start:
    $opt = null;
    $val = null;
    
    if (count($argv) == 0) {
        return false;
    }
    
    $arg = array_shift($argv);
    if ($arg == "--") {
        while (count($argv) > 0)
            $args[] = array_shift($argv);
            
        return false;
    }
    else if (strpos($arg, '--') === 0) {
        
        $eqpos = strpos($arg, '=');
        if ($eqpos !== false) {
            
            $opt = substr($arg, 2, $eqpos-2);
            $val = substr($arg, $eqpos+1);
        }
        else {
            $opt = substr($arg, 2);
            
            if (count($argv) > 0 && strpos($argv[0], "-") !== 0) {
                $val = array_shift($argv);
            }
        }
    }
    else if (strpos($arg, '-') === 0 && strlen($arg) > 1) {
        $opt = $arg[1];
        
        $eqpos = strpos($arg, '=');
        if ($eqpos !== false) {
            $val = substr($arg, $eqpos+1);
        } else {
            $val = false;
        }
    }
    else {
        $args[] =  $arg;
        goto start;
    }
    
    return true;
}

function usage_error($msg) {
    global $usage;
    fwrite(STDERR, "\nERROR: $msg\n\n");
    fwrite(STDERR, "$usage\n");
    exit(1);
}
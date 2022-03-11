<?php

namespace GrinJackal\LGSL;

use GrinJackal\LGSL\Protocols;

class LGSL extends Protocols{

    public static function Query($type, $ip, $c_port, $q_port, $s_port, $request) 
    {
        //---------------------------------------------------------+
        if (preg_match("/[^0-9a-zA-Z\.\-\[\]\:]/i", $ip))
        {
            exit("LGSL PROBLEM: INVALID IP OR HOSTNAME");
        }
        
        $lgsl_protocol_list = self::ProtocolList();
        
        if (!isset($lgsl_protocol_list[$type]))
        {
            exit("LGSL PROBLEM: ".($type ? "INVALID TYPE '{$type}'" : "MISSING TYPE")." FOR {$ip}, {$c_port}, {$q_port}, {$s_port}");
        }
        
        $lgsl_function = "Query{$lgsl_protocol_list[$type]}";
        
        //if (!function_exists($lgsl_function))
        //{
        //    exit("LGSL PROBLEM: FUNCTION DOES NOT EXIST FOR: {$type}");
        //}
        
        if (!intval($q_port))
        {
            exit("LGSL PROBLEM: INVALID QUERY PORT");
        }
        
        //---------------------------------------------------------+
        //  ARRAYS ARE SETUP IN ADVANCE
        $server = [
            "b" => ["type" => $type, "ip" => $ip, "c_port" => $c_port, "q_port" => $q_port, "s_port" => $s_port, "status" => 1],
            "s" => ["game" => "", "name" => "", "map" => "", "players" => 0, "playersmax" => 0, "password" => ""],
            "e" => [],
            "p" => [],
            "t" => []
        ];
        
        //---------------------------------------------------------+
        //  GET DATA
        if ($lgsl_function == "lgsl_query_01") // TEST RETURNS DIRECT
        {
            $lgsl_need = ""; $lgsl_fp = "";
            $response = call_user_func_array($lgsl_function, [&$server, &$lgsl_need, &$lgsl_fp]);
            return $server;
        }
        
        $response = self::QueryDirect($server, $request, $lgsl_function, self::GameTypeScheme($type));
        
        //---------------------------------------------------------+
        //  FORMAT RESPONSE
        if (!$response) // SERVER OFFLINE
        {
            $server['b']['status'] = 0;
        }else{
            // FILL IN EMPTY VALUES
            if (empty($server['s']['game'])) { $server['s']['game'] = $type; }
            if (empty($server['s']['map']))  { $server['s']['map']  = "-"; }
        
            // REMOVE FOLDERS FROM MAP NAMES
            if (($pos = strrpos($server['s']['map'], "/"))  !== FALSE) { $server['s']['map'] = substr($server['s']['map'], $pos + 1); }
            if (($pos = strrpos($server['s']['map'], "\\")) !== FALSE) { $server['s']['map'] = substr($server['s']['map'], $pos + 1); }
        
            // PLAYER COUNT AND PASSWORD STATUS SHOULD BE NUMERIC
            $server['s']['players']    = intval($server['s']['players']);
            $server['s']['playersmax'] = intval($server['s']['playersmax']);
        
            if (isset($server['s']['password'][0])) { $server['s']['password'] = (strtolower($server['s']['password'][0]) == "t") ? 1 : 0; }
            else                                    { $server['s']['password'] = intval($server['s']['password']); }
        
            // REMOVE EMPTY AND UN-REQUESTED ARRAYS
            if (strpos($request, "p") === FALSE && empty($server['p']) && $server['s']['players'] != 0) { unset($server['p']); }
            if (strpos($request, "p") === FALSE && empty($server['t']))                                 { unset($server['t']); }
            if (strpos($request, "e") === FALSE && empty($server['e']))                                 { unset($server['e']); }
            if (strpos($request, "s") === FALSE && empty($server['s']['name']))                         { unset($server['s']); }
        }
        
        $server['s']['cache_time'] = time();
        
        //---------------------------------------------------------+
        return $server;
    }

    public static function QueryDirect(&$server, $request, $lgsl_function, $scheme)
    {
        //---------------------------------------------------------+
        $lgsl_config['timeout'] = 5;
        
        if ($scheme == 'http') { 
            if(!function_exists('curl_init') || !function_exists('curl_setopt') || !function_exists('curl_exec')) 
                return FALSE;
        
            $lgsl_fp =  curl_init('');
            curl_setopt($lgsl_fp, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($lgsl_fp, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($lgsl_fp, CURLOPT_CONNECTTIMEOUT, $lgsl_config['timeout']);
            curl_setopt($lgsl_fp, CURLOPT_TIMEOUT, 3);
            curl_setopt($lgsl_fp, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        }else{
            $lgsl_fp = @fsockopen("{$scheme}://{$server['b']['ip']}", $server['b']['q_port'], $errno, $errstr, 1);
        
            if (!$lgsl_fp) { 
                $server['e']['_error'] = $errstr; 
                return FALSE; 
            }
        
            stream_set_timeout($lgsl_fp, $lgsl_config['timeout'], $lgsl_config['timeout'] ? 0 : 500000);
            stream_set_blocking($lgsl_fp, TRUE);
        }
        
        //---------------------------------------------------------+
        //  CHECK WHAT IS NEEDED
        $lgsl_need      = [];
        $lgsl_need['s'] = strpos($request, "s") !== FALSE ? TRUE : FALSE;
        $lgsl_need['e'] = strpos($request, "e") !== FALSE ? TRUE : FALSE;
        $lgsl_need['p'] = strpos($request, "p") !== FALSE ? TRUE : FALSE;
        
        // ChANGE [e] TO [s][e] AS BASIC QUERIES OFTEN RETURN EXTRA INFO
        if ($lgsl_need['e'] && !$lgsl_need['s']) { 
            $lgsl_need['s'] = TRUE; 
        }
        
        //---------------------------------------------------------+
        //  QUERY FUNCTION IS REPEATED TO REDUCE DUPLICATE CODE
        do{
            $lgsl_need_check = $lgsl_need;
        
            // CALL FUNCTION REQUIRES '&$variable' TO PASS 'BY REFERENCE'
            $response = call_user_func_array([self::class, $lgsl_function], [&$server, &$lgsl_need, &$lgsl_fp]);
        
            // CHECK IF SERVER IS OFFLINE
            if (!$response) { break; }
        
            // CHECK IF NEED HAS NOT CHANGED - THIS SERVES TWO PURPOSES - TO PREVENT INFINITE LOOPS - AND TO
            // AVOID WRITING $lgsl_need = FALSE FALSE FALSE FOR GAMES THAT RETURN ALL DATA IN ONE RESPONSE
            if ($lgsl_need_check == $lgsl_need) { break; }
        
            // OPTIMIZATION THAT SKIPS REQUEST FOR PLAYER DETAILS WHEN THE SERVER IS KNOWN TO BE EMPTY
            if ($lgsl_need['p'] && $server['s']['players'] == "0") { $lgsl_need['p'] = FALSE; }
        }
        while ($lgsl_need['s'] == TRUE || $lgsl_need['e'] == TRUE || $lgsl_need['p'] == TRUE);
        
        //---------------------------------------------------------+
        if ($scheme == 'http') {
            curl_close($lgsl_fp);
        }else{
            @fclose($lgsl_fp);
        }
        
        return $response;
    }
}

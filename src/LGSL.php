<?php

namespace GrinJackal\LGSL;

use GrinJackal\LGSL\Protocols;

class LGSL extends Protocols{

    /**
     * Retrive server status
     * 
     * @param string    $type
     * @param string    $ip
     * @param int       $c_port
     * @param int       $q_port
     * @param int       $s_port
     * @param string    $request
     * 
     * @note $s_port is 0 or is eqaul $q_port
     * @note $request can be: 
     *          s = server, 
     *          c = Convars,
     *          p = Players
     * 
     * @return Array
     */
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
        
        if (!intval($q_port))
        {
            exit("LGSL PROBLEM: INVALID QUERY PORT");
        }
        
        $server = [
            "basic" => ["type" => $type, "ip" => $ip, "c_port" => $c_port, "q_port" => $q_port, "s_port" => $s_port, "status" => 1],
            "server" => ["game" => "", "name" => "", "map" => "", "players" => 0, "playersmax" => 0, "password" => ""],
            "convars" => [],
            "players" => [],
            "teams" => []
        ];
        
        $response = self::QueryDirect($server, $request, $lgsl_function, self::GameTypeScheme($type));
        
        //---------------------------------------------------------+
        //  FORMAT RESPONSE
        if (!$response) // SERVER OFFLINE
        {
            $server['basic']['status'] = 0;
        }else{
            // FILL IN EMPTY VALUES
            if (empty($server['server']['game'])) { $server['server']['game'] = $type; }
            if (empty($server['server']['map']))  { $server['server']['map']  = "-"; }
        
            // REMOVE FOLDERS FROM MAP NAMES
            if (($pos = strrpos($server['server']['map'], "/"))  !== FALSE) { $server['server']['map'] = substr($server['server']['map'], $pos + 1); }
            if (($pos = strrpos($server['server']['map'], "\\")) !== FALSE) { $server['server']['map'] = substr($server['server']['map'], $pos + 1); }
        
            // PLAYER COUNT AND PASSWORD STATUS SHOULD BE NUMERIC
            $server['server']['players']    = intval($server['server']['players']);
            $server['server']['playersmax'] = intval($server['server']['playersmax']);
        
            if (isset($server['server']['password'][0])) { $server['server']['password'] = (strtolower($server['server']['password'][0]) == "t") ? 1 : 0; }
            else                                    { $server['server']['password'] = intval($server['server']['password']); }
        
            // REMOVE EMPTY AND UN-REQUESTED ARRAYS
            if (strpos($request, "p") === FALSE && empty($server['players']) && $server['server']['players'] != 0) { unset($server['players']); }
            if (strpos($request, "p") === FALSE && empty($server['teams']))                                 { unset($server['teams']); }
            if (strpos($request, "e") === FALSE && empty($server['convars']))                                 { unset($server['convars']); }
            if (strpos($request, "s") === FALSE && empty($server['server']['name']))                         { unset($server['server']); }
        }
        
        $server['server']['cache_time'] = time();
        
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
            $lgsl_fp = @fsockopen("{$scheme}://{$server['basic']['ip']}", $server['basic']['q_port'], $errno, $errstr, 1);
        
            if (!$lgsl_fp) { 
                $server['convars']['_error'] = $errstr; 
                return FALSE; 
            }
        
            stream_set_timeout($lgsl_fp, $lgsl_config['timeout'], $lgsl_config['timeout'] ? 0 : 500000);
            stream_set_blocking($lgsl_fp, TRUE);
        }
        
        //---------------------------------------------------------+
        //  CHECK WHAT IS NEEDED
        $lgsl_need      = [];
        $lgsl_need['s'] = strpos($request, "s") !== FALSE ? TRUE : FALSE;
        $lgsl_need['c'] = strpos($request, "c") !== FALSE ? TRUE : FALSE;
        $lgsl_need['p'] = strpos($request, "p") !== FALSE ? TRUE : FALSE;
        
        // ChANGE [e] TO [s][e] AS BASIC QUERIES OFTEN RETURN EXTRA INFO
        if ($lgsl_need['c'] && !$lgsl_need['s']) { 
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
            if ($lgsl_need['p'] && $server['server']['players'] == "0") { $lgsl_need['p'] = FALSE; }
        }
        while ($lgsl_need['s'] == TRUE || $lgsl_need['c'] == TRUE || $lgsl_need['p'] == TRUE);
        
        //---------------------------------------------------------+
        if ($scheme == 'http') {
            curl_close($lgsl_fp);
        }else{
            @fclose($lgsl_fp);
        }
        
        return $response;
    }
}

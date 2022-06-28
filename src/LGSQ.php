<?php

namespace GrinJackal\LGSQ;

use GrinJackal\LGSQ\Protocols;

// Live Game Server Query -> LGSQ
class LGSQ
{
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
        
        $lgsl_protocol_list = Functions::ProtocolList();
        
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
        
        $response = Protocols::QueryDirect($server, $request, $lgsl_function, Functions::GameTypeScheme($type));
        
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
}


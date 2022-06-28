<?php
namespace GrinJackal\LGSQ\Protocols;

class Query04 extends Query03{
    public static function Query04(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "REPORT");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $lgsl_ravenshield_key = [
            "A1" => "playersmax",
            "A2" => "tkpenalty",
            "B1" => "players",
            "B2" => "allowradar",
            "D2" => "version",
            "E1" => "mapname",
            "E2" => "lid",
            "F1" => "maptype",
            "F2" => "gid",
            "G1" => "password",
            "G2" => "hostport",
            "H1" => "dedicated",
            "H2" => "terroristcount",
            "I1" => "hostname",
            "I2" => "aibackup",
            "J1" => "mapcycletypes",
            "J2" => "rotatemaponsuccess",
            "K1" => "mapcycle",
            "K2" => "forcefirstpersonweapons",
            "L1" => "players_name",
            "L2" => "gamename",
            "L3" => "punkbuster",
            "M1" => "players_time",
            "N1" => "players_ping",
            "O1" => "players_score",
            "P1" => "queryport",
            "Q1" => "rounds",
            "R1" => "roundtime",
            "S1" => "bombtimer",
            "T1" => "bomb",
            "W1" => "allowteammatenames",
            "X1" => "iserver",
            "Y1" => "friendlyfire",
            "Z1" => "autobalance"
        ];
  
        //---------------------------------------------------------+
        $item = explode("\xB6", $buffer);
  
        foreach ($item as $data_value)
        {
            $tmp = explode(" ", $data_value, 2);
            $data_key = isset($lgsl_ravenshield_key[$tmp[0]]) ? $lgsl_ravenshield_key[$tmp[0]] : $tmp[0]; // CONVERT TO DESCRIPTIVE KEYS
            $server['convars'][$data_key] = trim($tmp[1]); // ALL VALUES NEED TRIMMING
        }
  
        $server['convars']['mapcycle']      = str_replace("/"," ", $server['convars']['mapcycle']);      // CONVERT SLASH TO SPACE
        $server['convars']['mapcycletypes'] = str_replace("/"," ", $server['convars']['mapcycletypes']); // SO LONG LISTS WRAP
  
        //---------------------------------------------------------+
        $server['server']['game']       = $server['convars']['gamename'];
        $server['server']['name']       = $server['convars']['hostname'];
        $server['server']['map']        = $server['convars']['mapname'];
        $server['server']['players']    = $server['convars']['players'];
        $server['server']['playersmax'] = $server['convars']['playersmax'];
        $server['server']['password']   = $server['convars']['password'];
  
        //---------------------------------------------------------+
        $player_name  = isset($server['convars']['players_name'])  ? explode("/", substr($server['convars']['players_name'],  1)) : array(); unset($server['convars']['players_name']);
        $player_time  = isset($server['convars']['players_time'])  ? explode("/", substr($server['convars']['players_time'],  1)) : array(); unset($server['convars']['players_time']);
        $player_ping  = isset($server['convars']['players_ping'])  ? explode("/", substr($server['convars']['players_ping'],  1)) : array(); unset($server['convars']['players_ping']);
        $player_score = isset($server['convars']['players_score']) ? explode("/", substr($server['convars']['players_score'], 1)) : array(); unset($server['convars']['players_score']);
  
        foreach ($player_name as $key => $name)
        {
            $server['players'][$key]['name']  = $player_name[$key];
            $server['players'][$key]['time']  = $player_time[$key];
            $server['players'][$key]['ping']  = $player_ping[$key];
            $server['players'][$key]['score'] = $player_score[$key];
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
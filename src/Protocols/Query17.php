<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query17 extends Query16{
    public static function Query17(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://masterserver.savage.s2games.com
        fwrite($lgsl_fp, "\x9e\x4c\x23\x00\x00\xce\x21\x21\x21\x21");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 12); // REMOVE HEADER
  
        while ($key = strtolower(Functions::CutString($buffer, 0, "\xFE")))
        {
            if ($key == "players") { break; }
        
            $value = Functions::CutString($buffer, 0, "\xFF");
            $value = str_replace("\x00", "", $value);
            $value = Functions::ParserColor($value, $server['basic']['type']);
        
            $server['convars'][$key] = $value;
        }
  
        $server['server']['name']       = $server['convars']['name'];  unset($server['convars']['name']);
        $server['server']['map']        = $server['convars']['world']; unset($server['convars']['world']);
        $server['server']['players']    = $server['convars']['cnum'];  unset($server['convars']['cnum']);
        $server['server']['playersmax'] = $server['convars']['cmax'];  unset($server['convars']['cnum']);
        $server['server']['password']   = $server['convars']['pass'];  unset($server['convars']['cnum']);
  
        //---------------------------------------------------------+
        $server['teams'][0]['name'] = $server['convars']['race1'];
        $server['teams'][1]['name'] = $server['convars']['race2'];
        $server['teams'][2]['name'] = "spectator";
  
        $team_key   = -1;
        $player_key = 0;
  
        while ($value = Functions::CutString($buffer, 0, "\x0a"))
        {
            if ($value[0] == "\x00") { break; }
            if ($value[0] != "\x20") { $team_key++; continue; }
        
            $server['players'][$player_key]['name'] = Functions::ParserColor(substr($value, 1), $server['basic']['type']);
            $server['players'][$player_key]['team'] = $server['teams'][$team_key]['name'];
        
            $player_key++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
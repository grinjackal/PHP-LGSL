<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query08 extends Query07{
    public static function Query08(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "s"); // ASE ( ALL SEEING EYE ) PROTOCOL
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 4); // REMOVE HEADER
  
        $server['convars']['gamename']      = Functions::CutPascal($buffer, 1, -1);
        $server['convars']['hostport']      = Functions::CutPascal($buffer, 1, -1);
        $server['server']['name']           = Functions::ParserColor(Functions::CutPascal($buffer, 1, -1), $server['basic']['type']);
        $server['convars']['gamemode']      = Functions::CutPascal($buffer, 1, -1);
        $server['server']['map']            = Functions::CutPascal($buffer, 1, -1);
        $server['convars']['version']       = Functions::CutPascal($buffer, 1, -1);
        $server['server']['password']       = Functions::CutPascal($buffer, 1, -1);
        $server['server']['players']        = Functions::CutPascal($buffer, 1, -1);
        $server['server']['playersmax']     = Functions::CutPascal($buffer, 1, -1);
  
        while ($buffer && $buffer[0] != "\x01")
        {
            $item_key   = strtolower(Functions::CutPascal($buffer, 1, -1));
            $item_value = Functions::CutPascal($buffer, 1, -1);
        
            $server['convars'][$item_key] = $item_value;
        }
  
        $buffer = substr($buffer, 1); // REMOVE END MARKER
  
        //---------------------------------------------------------+
        $player_key = 0;
  
        while ($buffer)
        {
            $bit_flags = Functions::CutByte($buffer, 1); // FIELDS HARD CODED BELOW BECAUSE GAMES DO NOT USE THEM PROPERLY
        
            if     ($bit_flags == "\x3D")                       { $field_list = [ "name",                  "score", "",     "time" ]; } // FARCRY PLAYERS CONNECTING
            elseif ($server['basic']['type'] == "farcry")       { $field_list = [ "name", "team", "",      "score", "ping", "time" ]; } // FARCRY PLAYERS JOINED
            elseif ($server['basic']['type'] == "mta")          { $field_list = [ "name", "",      "",     "score", "ping", ""     ]; }
            elseif ($server['basic']['type'] == "painkiller")   { $field_list = [ "name", "",     "skin",  "score", "ping", ""     ]; }
            elseif ($server['basic']['type'] == "soldat")       { $field_list = [ "name", "team", "",      "score", "ping", "time" ]; }
        
            foreach ($field_list as $item_key)
            {
                $item_value = Functions::CutPascal($buffer, 1, -1);

                if (!$item_key) { continue; }

                if ($item_key == "name") { Functions::ParserColor($item_value, $server['basic']['type']); }

                $server['players'][$player_key][$item_key] = $item_value;
            }
            $player_key ++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
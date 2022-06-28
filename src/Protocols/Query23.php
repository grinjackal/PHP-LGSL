<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query23 extends Query22{
    public static function Query23(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://siteinthe.us
        //  http://www.tribesmasterserver.com
        fwrite($lgsl_fp, "b++");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        $buffer = substr($buffer, 4); // REMOVE HEADER
  
        //---------------------------------------------------------+
        $server['server']['game']           = Functions::CutPascal($buffer);
        $server['convars']['version']       = Functions::CutPascal($buffer);
        $server['server']['name']           = Functions::CutPascal($buffer);
        $server['convars']['dedicated']     = ord(Functions::CutByte($buffer, 1));
        $server['server']['password']       = ord(Functions::CutByte($buffer, 1));
        $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
        $server['convars']['cpu']           = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['convars']['mod']           = Functions::CutPascal($buffer);
        $server['convars']['type']          = Functions::CutPascal($buffer);
        $server['server']['map']            = Functions::CutPascal($buffer);
        $server['convars']['motd']          = Functions::CutPascal($buffer);
        $server['convars']['teams']         = ord(Functions::CutByte($buffer, 1));
  
        //---------------------------------------------------------+
        $team_field = "?".Functions::CutPascal($buffer);
        $team_field = explode("\t", $team_field);
  
        foreach ($team_field as $key => $value)
        {
            $value = substr($value, 1);
            $value = strtolower($value);
            $team_field[$key] = $value;
        }
  
        //---------------------------------------------------------+
        $player_field = "?".Functions::CutPascal($buffer);
        $player_field = explode("\t", $player_field);
  
        foreach ($player_field as $key => $value)
        {
            $value = substr($value, 1);
            $value = strtolower($value);
        
            if ($value == "player name") { $value = "name"; }
        
            $player_field[$key] = $value;
        }
  
        $player_field[] = "unknown_1";
        $player_field[] = "unknown_2";
  
        //---------------------------------------------------------+
        for ($i=0; $i<$server['convars']['teams']; $i++)
        {
            $team_name = Functions::CutPascal($buffer);
            $team_info = Functions::CutPascal($buffer);
        
            if (!$team_info) { continue; }
        
            $team_info = str_replace("%t", $team_name, $team_info);
            $team_info = explode("\t", $team_info);
        
            foreach ($team_info as $key => $value)
            {
                $field = $team_field[$key];
                $value = trim($value);

                if ($field == "team name") { $field = "name"; }

                $server['teams'][$i][$field] = $value;
            }
        }
  
        //---------------------------------------------------------+
        for ($i = 0; $i < $server['server']['players']; $i++)
        {
            $player_bits   = [];
            $player_bits[] = ord(Functions::CutByte($buffer, 1)) * 4; // %p = PING
            $player_bits[] = ord(Functions::CutByte($buffer, 1));     // %l = PACKET LOSS
            $player_bits[] = ord(Functions::CutByte($buffer, 1));     // %t = TEAM
            $player_bits[] = Functions::CutPascal($buffer);           // %n = PLAYER NAME
            $player_info   = Functions::CutPascal($buffer);
  
            if (!$player_info) { continue; }
  
            $player_info = str_replace(array("%p","%l","%t","%n"), $player_bits, $player_info);
            $player_info = explode("\t", $player_info);
  
            foreach ($player_info as $key => $value)
            {
                $field = $player_field[$key];
                $value = trim($value);
            
                if ($field == "team") { $value = $server['teams'][$value]['name']; }
            
                $server['players'][$i][$field] = $value;
            }
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query19 extends Query18{
    public static function Query19(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\xC0\xDE\xF1\x11\x42\x06\x00\xF5\x03\x21\x21\x21\x21");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 25); // REMOVE HEADER
  
        $server['server']['name']           = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        $server['server']['map']            = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        $server['convars']['nextmap']       = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        $server['convars']['gametype']      = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
  
        $buffer = substr($buffer, 1);
  
        $server['server']['password']   = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax'] = ord(Functions::CutByte($buffer, 4));
        $server['server']['players']    = ord(Functions::CutByte($buffer, 4));
  
        //---------------------------------------------------------+
        for ($player_key = 0; $player_key < $server['server']['players']; $player_key++)
        {
             $server['players'][$player_key]['name'] = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 17);
  
        $server['convars']['version']    = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        $server['convars']['mods']       = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        $server['convars']['dedicated']  = ord(Functions::CutByte($buffer, 1));
        $server['convars']['time']       = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['status']     = ord(Functions::CutByte($buffer, 4));
        $server['convars']['gamemode']   = ord(Functions::CutByte($buffer, 4));
        $server['convars']['motd']       = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
        $server['convars']['respawns']   = ord(Functions::CutByte($buffer, 4));
        $server['convars']['time_limit'] = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['voting']     = ord(Functions::CutByte($buffer, 4));
  
        $buffer = substr($buffer, 2);
  
        //---------------------------------------------------------+
        for ($player_key=0; $player_key<$server['server']['players']; $player_key++)
        {
            $server['players'][$player_key]['team'] = ord(Functions::CutByte($buffer, 4));
        
            $unknown = ord(Functions::CutByte($buffer, 1));
        }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 7);
  
        $server['convars']['platoon_1_color']   = ord(Functions::CutByte($buffer, 8));
        $server['convars']['platoon_2_color']   = ord(Functions::CutByte($buffer, 8));
        $server['convars']['platoon_3_color']   = ord(Functions::CutByte($buffer, 8));
        $server['convars']['platoon_4_color']   = ord(Functions::CutByte($buffer, 8));
        $server['convars']['timer_on']          = ord(Functions::CutByte($buffer, 1));
        $server['convars']['timer_time']        = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['time_debriefing']   = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['time_respawn_min']  = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['time_respawn_max']  = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['time_respawn_safe'] = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['difficulty']        = ord(Functions::CutByte($buffer, 4));
        $server['convars']['respawn_total']     = ord(Functions::CutByte($buffer, 4));
        $server['convars']['random_insertions'] = ord(Functions::CutByte($buffer, 1));
        $server['convars']['spectators']        = ord(Functions::CutByte($buffer, 1));
        $server['convars']['arcademode']        = ord(Functions::CutByte($buffer, 1));
        $server['convars']['ai_backup']         = ord(Functions::CutByte($buffer, 1));
        $server['convars']['random_teams']      = ord(Functions::CutByte($buffer, 1));
        $server['convars']['time_starting']     = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
        $server['convars']['identify_friends']  = ord(Functions::CutByte($buffer, 1));
        $server['convars']['identify_threats']  = ord(Functions::CutByte($buffer, 1));
  
        $buffer = substr($buffer, 5);
  
        $server['convars']['restrictions']      = Functions::GetString(Functions::CutPascal($buffer, 4, 3, -3));
  
        //---------------------------------------------------------+
        switch ($server['convars']['status'])
        {
            case 3: $server['convars']['status'] = "Joining"; break;
            case 4: $server['convars']['status'] = "Joining"; break;
            case 5: $server['convars']['status'] = "Joining"; break;
        }
  
        switch ($server['convars']['gamemode'])
        {
            case 2: $server['convars']['gamemode'] = "Co-Op"; break;
            case 3: $server['convars']['gamemode'] = "Solo";  break;
            case 4: $server['convars']['gamemode'] = "Team";  break;
        }
  
        switch ($server['convars']['respawns'])
        {
            case 0: $server['convars']['respawns'] = "None";       break;
            case 1: $server['convars']['respawns'] = "Individual"; break;
            case 2: $server['convars']['respawns'] = "Team";       break;
            case 3: $server['convars']['respawns'] = "Infinite";   break;
        }
  
        switch ($server['convars']['difficulty'])
        {
            case 0: $server['convars']['difficulty'] = "Recruit"; break;
            case 1: $server['convars']['difficulty'] = "Veteran"; break;
            case 2: $server['convars']['difficulty'] = "Elite";   break;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
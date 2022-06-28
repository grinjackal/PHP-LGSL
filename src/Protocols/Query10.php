<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query10 extends Query09{
    public static function Query10(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        if ($server['basic']['type'] == "quakewars")    { fwrite($lgsl_fp, "\xFF\xFFgetInfoEX\xFF"); }
        else                                            { fwrite($lgsl_fp, "\xFF\xFFgetInfo\xFF");   }
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        if     ($server['basic']['type'] == "wolf2009")     { $buffer = substr($buffer, 31); }  // REMOVE HEADERS
        elseif ($server['basic']['type'] == "quakewars")    { $buffer = substr($buffer, 33); }
        else                                                { $buffer = substr($buffer, 23); }
  
        $buffer = Functions::ParserColor($buffer, "2");
  
        //---------------------------------------------------------+
        while ($buffer && $buffer[0] != "\x00")
        {
            $item_key   = strtolower(Functions::CutString($buffer));
            $item_value = Functions::CutString($buffer);
        
            $server['convars'][$item_key] = $item_value;
        }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 2);
        $player_key = 0;
  
        //---------------------------------------------------------+
        if ($server['basic']['type'] == "wolf2009") // WOLFENSTEIN: (PID)(PING)(NAME)(TAGPOSITION)(TAG)(BOT)
        {
            while ($buffer && $buffer[0] != "\x10") // STOPS AT PID 16
            {
                $server['players'][$player_key]['pid']      = ord(Functions::CutByte($buffer, 1));
                $server['players'][$player_key]['ping']     = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['rate']     = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['unknown']  = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $player_name                                = Functions::CutString($buffer);
                $player_tag_position                        = ord(Functions::CutByte($buffer, 1));
                $player_tag                                 = Functions::CutString($buffer);
                $server['players'][$player_key]['bot']      = ord(Functions::CutByte($buffer, 1));

                if     ($player_tag == "")           { $server['players'][$player_key]['name'] = $player_name; }
                elseif ($player_tag_position == "0") { $server['players'][$player_key]['name'] = $player_tag." ".$player_name; }
                else                                 { $server['players'][$player_key]['name'] = $player_name." ".$player_tag; }

                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        // QUAKEWARS: (PID)(PING)(NAME)(TAGPOSITION)(TAG)(BOT)
        elseif ($server['basic']['type'] == "quakewars")
        {
            while ($buffer && $buffer[0] != "\x20") // STOPS AT PID 32
            {
                $server['players'][$player_key]['pid']      = ord(Functions::CutByte($buffer, 1));
                $server['players'][$player_key]['ping']     = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $player_name                                = Functions::CutString($buffer);
                $player_tag_position                        = ord(Functions::CutByte($buffer, 1));
                $player_tag                                 = Functions::CutString($buffer);
                $server['players'][$player_key]['bot']      = ord(Functions::CutByte($buffer, 1));
                
                if ($player_tag_position == "")         { $server['players'][$player_key]['name'] = $player_name; }
                elseif ($player_tag_position == "1")    { $server['players'][$player_key]['name'] = $player_name." ".$player_tag; }
                else                                    { $server['players'][$player_key]['name'] = $player_tag." ".$player_name; }
            
                $player_key++;
            }
        
            $buffer                             = substr($buffer, 1);
            $server['convars']['si_osmask']     = Functions::UnPack(Functions::CutByte($buffer, 4), "I");
            $server['convars']['si_ranked']     = ord(Functions::CutByte($buffer, 1));
            $server['convars']['si_timeleft']   = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "I") / 1000);
            $server['convars']['si_gamestate']  = ord(Functions::CutByte($buffer, 1));
            $buffer                             = substr($buffer, 2);
        
            $player_key = 0;
        
            while ($buffer && $buffer[0] != "\x20") // QUAKEWARS EXTENDED: (PID)(XP)(TEAM)(KILLS)(DEATHS)
            {
                $server['players'][$player_key]['pid']    = ord(Functions::CutByte($buffer, 1));
                $server['players'][$player_key]['xp']     = intval(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
                $server['players'][$player_key]['team']   = Functions::CutString($buffer);
                $server['players'][$player_key]['score']  = Functions::UnPack(Functions::CutByte($buffer, 4), "i");
                $server['players'][$player_key]['deaths'] = Functions::UnPack(Functions::CutByte($buffer, 4), "i");
                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        elseif ($server['basic']['type'] == "quake4") // QUAKE4: (PID)(PING)(RATE)(NULLNULL)(NAME)(TAG)
        {
            while ($buffer && $buffer[0] != "\x20") // STOPS AT PID 32
            {
                $server['players'][$player_key]['pid']  = ord(Functions::CutByte($buffer, 1));
                $server['players'][$player_key]['ping'] = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['rate'] = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $buffer                                 = substr($buffer, 2);
                $player_name                            = Functions::CutString($buffer);
                $player_tag                             = Functions::CutString($buffer);
                $server['players'][$player_key]['name'] = $player_tag ? $player_tag." ".$player_name : $player_name;
                
                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        else // DOOM3 AND PREY: (PID)(PING)(RATE)(NULLNULL)(NAME)
        {
            while ($buffer && $buffer[0] != "\x20") // STOPS AT PID 32
            {
                $server['players'][$player_key]['pid']  = ord(Functions::CutByte($buffer, 1));
                $server['players'][$player_key]['ping'] = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['rate'] = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
                $buffer                                 = substr($buffer, 2);
                $server['players'][$player_key]['name'] = Functions::CutString($buffer);
            
                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        $server['server']['game']       = $server['convars']['gamename'];
        $server['server']['name']       = $server['convars']['si_name'];
        $server['server']['map']        = $server['convars']['si_map'];
        $server['server']['players']    = $server['players'] ? count($server['players']) : 0;
        $server['server']['playersmax'] = $server['convars']['si_maxplayers'];
  
        if ($server['basic']['type'] == "wolf2009" || $server['basic']['type'] == "quakewars")
        {
            $server['server']['map']      = str_replace(".entities", "", $server['server']['map']);
            $server['server']['password'] = $server['convars']['si_needpass'];
        }else{
            $server['server']['password'] = $server['convars']['si_usepass'];
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
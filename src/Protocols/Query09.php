<?php
namespace GrinJackal\LGSQ\Protocols;

class Query09 extends Query08{
    public static function Query09(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        // SERIOUS SAM 2 RETURNS ALL PLAYER NAMES AS "Unknown Player" SO SKIP OR CONVERT ANY PLAYER REQUESTS
        if ($server['basic']['type'] == "serioussam2") { $lgsl_need['p'] = FALSE; if (!$lgsl_need['s'] && !$lgsl_need['c']) { $lgsl_need['s'] = TRUE; } }
  
        //---------------------------------------------------------+
        if ($lgsl_need['s'] || $lgsl_need['c'])
        {
            $lgsl_need['s'] = FALSE; 
            $lgsl_need['c'] = FALSE;
  
            fwrite($lgsl_fp, "\xFE\xFD\x00\x21\x21\x21\x21\xFF\x00\x00\x00");
  
            $buffer = fread($lgsl_fp, 4096);
            $buffer = substr($buffer, 5, -2); // REMOVE HEADER AND FOOTER
  
            if (!$buffer) { return FALSE; }
  
            $item = explode("\x00", $buffer);
  
            foreach ($item as $item_key => $data_key)
            {
                if ($item_key % 2) { continue; } // SKIP EVEN KEYS
            
                $data_key = strtolower($data_key);
                $server['convars'][$data_key] = $item[$item_key+1];
            }
  
            if (isset($server['convars']['hostname']))   { $server['server']['name']       = $server['convars']['hostname']; }
            if (isset($server['convars']['mapname']))    { $server['server']['map']        = $server['convars']['mapname']; }
            if (isset($server['convars']['numplayers'])) { $server['server']['players']    = $server['convars']['numplayers']; }
            if (isset($server['convars']['maxplayers'])) { $server['server']['playersmax'] = $server['convars']['maxplayers']; }
            if (isset($server['convars']['password']))   { $server['server']['password']   = $server['convars']['password']; }
  
            if (!empty($server['convars']['gamename']))   { $server['server']['game'] = $server['convars']['gamename']; }   // AARMY
            if (!empty($server['convars']['gsgamename'])) { $server['server']['game'] = $server['convars']['gsgamename']; } // FEAR
            if (!empty($server['convars']['game_id']))    { $server['server']['game'] = $server['convars']['game_id']; }    // BFVIETNAM
  
            if ($server['basic']['type'] == "arma" || $server['basic']['type'] == "arma2")
            {
                $server['server']['map'] = $server['convars']['mission'];
            }
            elseif ($server['basic']['type'] == "vietcong2")
            {
                $server['convars']['extinfo_autobalance'] = ord($server['convars']['extinfo'][18]) == 2 ? "off" : "on";
                // [ 13 = Vietnam and RPG Mode 19 1b 99 9b ] [ 22 23 = Mounted MG Limit ]
                // [ 27 = Idle Limit ] [ 18 = Auto Balance ] [ 55 = Chat and Blind Spectator 5a 5c da dc ]
            }
        }
  
        //---------------------------------------------------------+
        elseif ($lgsl_need['p'])
        {
            $lgsl_need['p'] = FALSE;
        
            fwrite($lgsl_fp, "\xFE\xFD\x00\x21\x21\x21\x21\x00\xFF\x00\x00");
        
            $buffer = fread($lgsl_fp, 4096);
            $buffer = substr($buffer, 7, -1); // REMOVE HEADER / PLAYER TOTAL / FOOTER
        
            if (!$buffer) { return FALSE; }
            if (strpos($buffer, "\x00\x00") === FALSE) { return TRUE; } // NO PLAYERS
        
            $buffer     = explode("\x00\x00",$buffer, 2);            // SPLIT FIELDS FROM ITEMS
            $buffer[0]  = str_replace("_",      "",     $buffer[0]); // REMOVE UNDERSCORES FROM FIELDS
            $buffer[0]  = str_replace("player", "name", $buffer[0]); // LGSL STANDARD
            $field_list = explode("\x00",$buffer[0]);                // SPLIT UP FIELDS
            $item       = explode("\x00",$buffer[1]);                // SPLIT UP ITEMS
        
            $item_position = 0;
            $item_total    = count($item);
            $player_key    = 0;
        
            do
            {
                foreach ($field_list as $field)
                {
                    $server['players'][$player_key][$field] = $item[$item_position];
                    $item_position ++;
                }
            
                $player_key ++;
            }
            while ($item_position < $item_total);
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query05 extends Query04{
    public static function Query05(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://developer.valvesoftware.com/wiki/Server_Queries
        if ($server['basic']['type'] == "halflifewon")
        {
            if     ($lgsl_need['s']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFdetails\x00"); }
            elseif ($lgsl_need['c']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFrules\x00");   }
            elseif ($lgsl_need['p']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFplayers\x00"); }
        }else{
            $challenge_code = isset($lgsl_need['challenge']) ? $lgsl_need['challenge'] : "\x00\x00\x00\x00";
  
            if     ($lgsl_need['s']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00" . (isset($lgsl_need['challenge']) ? $challenge_code : "")); }
            elseif ($lgsl_need['c']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x56{$challenge_code}");                                                                 }
            elseif ($lgsl_need['p']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x55{$challenge_code}");                                                                 }
        }
  
        //---------------------------------------------------------+
        //  THE STANDARD HEADER POSITION REVEALS THE TYPE BUT IT MAY NOT ARRIVE FIRST
        //  ONCE WE KNOW THE TYPE WE CAN FIND THE TOTAL NUMBER OF PACKETS EXPECTED
        $packet_temp  = [];
        $packet_type  = 0;
        $packet_count = 0;
        $packet_total = 4;
  
        do
        {
            if (!($packet = fread($lgsl_fp, 4096))) {
                if ($lgsl_need['s']) { return FALSE; }
                elseif ($lgsl_need['c']) { $lgsl_need['c'] = FALSE; return TRUE; }
                else { return TRUE; }
            }
  
            //---------------------------------------------------------------------------------------------------------------------------------+
            // NEWER HL1 SERVERS REPLY TO A2S_INFO WITH 3 PACKETS ( HL1 FORMAT INFO, SOURCE FORMAT INFO, PLAYERS )
            // THIS DISCARDS UN-EXPECTED PACKET FORMATS ON THE GO ( AS READING IN ADVANCE CAUSES TIMEOUT DELAYS FOR OTHER SERVER VERSIONS )
            // ITS NOT PERFECT AS [s] CAN FLIP BETWEEN HL1 AND SOURCE FORMATS DEPENDING ON ARRIVAL ORDER ( MAYBE FIX WITH RETURN ON HL1 APPID )
            if     ($lgsl_need['s']) { if ($packet[4] == "D") { continue; } }
            elseif ($lgsl_need['c']) { if ($packet[4] == "m" || $packet[4] == "I" || $packet[4] == "D") { continue; } }
            elseif ($lgsl_need['p']) { if ($packet[4] == "m" || $packet[4] == "I") { continue; } }
            
            //---------------------------------------------------------------------------------------------------------------------------------+
            if     (substr($packet, 0,  5) == "\xFF\xFF\xFF\xFF\x41") { $lgsl_need['challenge'] = substr($packet, 5, 4); $server['server']['players'] = !$server['server']['game'] ? -1 : $server['server']['players']; return TRUE; } // REPEAT WITH GIVEN CHALLENGE CODE
            elseif (substr($packet, 0,  4) == "\xFF\xFF\xFF\xFF")     { $packet_total = 1;                     $packet_type = 1;       } // SINGLE PACKET - HL1 OR HL2
            elseif (substr($packet, 9,  4) == "\xFF\xFF\xFF\xFF")     { $packet_total = ord($packet[8]) & 0xF; $packet_type = 2;       } // MULTI PACKET  - HL1 ( TOTAL IS LOWER NIBBLE OF BYTE )
            elseif (substr($packet, 12, 4) == "\xFF\xFF\xFF\xFF")     { $packet_total = ord($packet[8]);       $packet_type = 3;       } // MULTI PACKET  - HL2
            elseif (substr($packet, 18, 2) == "BZ")                   { $packet_total = ord($packet[8]);       $packet_type = 4;       } // BZIP PACKET   - HL2
  
            $packet_count ++;
            $packet_temp[] = $packet;
        }
        while ($packet && $packet_count < $packet_total);
  
        if ($packet_type == 0) { return $server['server'] ? TRUE : FALSE; } // UNKNOWN RESPONSE ( SOME SERVERS ONLY SEND [s] )
  
        //---------------------------------------------------------+
        //  WITH THE TYPE WE CAN NOW SORT AND JOIN THE PACKETS IN THE CORRECT ORDER
        //  REMOVING ANY EXTRA HEADERS IN THE PROCESS
        $buffer = [];
  
        foreach ($packet_temp as $packet)
        {
            if     ($packet_type == 1) { $packet_order = 0; }
            elseif ($packet_type == 2) { $packet_order = ord($packet[8]) >> 4; $packet = substr($packet, 9);  } // ( INDEX IS UPPER NIBBLE OF BYTE )
            elseif ($packet_type == 3) { $packet_order = ord($packet[9]);      $packet = substr($packet, 12); }
            elseif ($packet_type == 4) { $packet_order = ord($packet[9]);      $packet = substr($packet, 18); }
            
            $buffer[$packet_order] = $packet;
        }
  
        ksort($buffer);
  
        $buffer = implode("", $buffer);
  
        //---------------------------------------------------------+
        //  WITH THE PACKETS JOINED WE CAN NOW DECOMPRESS BZIP PACKETS
        //  THEN REMOVE THE STANDARD HEADER AND CHECK ITS CORRECT
        if ($packet_type == 4)
        {
            if (!function_exists("bzdecompress")) // REQUIRES http://php.net/bzip2
            {
                $server['convars']['bzip2'] = "unavailable"; 
                $lgsl_need['c'] = FALSE;
                return TRUE;
            }
        
            $buffer = bzdecompress($buffer);
        }
  
        $header = Functions::CutByte($buffer, 4);
  
        if ($header != "\xFF\xFF\xFF\xFF") { return FALSE; } // SOMETHING WENT WRONG
  
        //---------------------------------------------------------+
        $response_type = Functions::CutByte($buffer, 1);
  
        if ($response_type == "I") // SOURCE INFO ( HALF-LIFE 2 )
        {
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['server']['name']           = Functions::CutString($buffer);
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['game']           = Functions::CutString($buffer);
            $server['convars']['description']   = Functions::CutString($buffer);
            $server['convars']['appid']         = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
            $server['convars']['bots']          = ord(Functions::CutByte($buffer, 1));
            $server['convars']['dedicated']     = Functions::CutByte($buffer, 1);
            $server['convars']['os']            = Functions::CutByte($buffer, 1);
            $server['server']['password']       = ord(Functions::CutByte($buffer, 1));
            $server['convars']['anticheat']     = ord(Functions::CutByte($buffer, 1));
            $server['convars']['version']       = Functions::CutString($buffer);
        
            if (ord(Functions::CutByte($buffer, 1)) == 177) {
                Functions::CutByte($buffer, 10);
            }else{
                Functions::CutByte($buffer, 6);
            }
            $server['convars']['tags']          = Functions::CutString($buffer);
        
            if($server['server']['game'] == 'rust'){
                preg_match('/cp\d{1,3}/', $server['convars']['tags'], $e);
                $server['server']['players'] = substr($e[0], 2);

                preg_match('/mp\d{1,3}/', $server['convars']['tags'], $e);
                $server['server']['playersmax'] = substr($e[0], 2);
            }
        }
  
        elseif ($response_type == "m") // HALF-LIFE 1 INFO
        {
            $server_ip                          = Functions::CutString($buffer);
            $server['server']['name']           = Functions::CutString($buffer);
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['game']           = Functions::CutString($buffer);
            $server['convars']['description']   = Functions::CutString($buffer);
            $server['server']['players']        = ord(Functions::CutByte($buffer, 1));
            $server['server']['playersmax']     = ord(Functions::CutByte($buffer, 1));
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['convars']['dedicated']     = Functions::CutByte($buffer, 1);
            $server['convars']['os']            = Functions::CutByte($buffer, 1);
            $server['server']['password']       = ord(Functions::CutByte($buffer, 1));
  
            // MOD FIELDS ( OFF FOR SOME HALFLIFEWON-VALVE SERVERS )
            if (ord(Functions::CutByte($buffer, 1)))
            {
                $server['convars']['mod_url_info']     = Functions::CutString($buffer);
                $server['convars']['mod_url_download'] = Functions::CutString($buffer);
                $buffer = substr($buffer, 1);
                $server['convars']['mod_version']      = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
                $server['convars']['mod_size']         = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
                $server['convars']['mod_server_side']  = ord(Functions::CutByte($buffer, 1));
                $server['convars']['mod_custom_dll']   = ord(Functions::CutByte($buffer, 1));
            }
  
            $server['convars']['anticheat'] = ord(Functions::CutByte($buffer, 1));
            $server['convars']['bots']      = ord(Functions::CutByte($buffer, 1));
        }
  
        // SOURCE AND HALF-LIFE 1 PLAYERS
        elseif ($response_type == "D")
        {
            $returned = ord(Functions::CutByte($buffer, 1));
  
            $player_key = 0;
  
            while ($buffer)
            {
                Functions::CutByte($buffer, 1);
                $server['players'][$player_key]['name']  = Functions::CutString($buffer);
                $server['players'][$player_key]['score'] = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
                $server['players'][$player_key]['time']  = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 4), "f"));
                
                $player_key ++;
            }
        }
  
        // SOURCE AND HALF-LIFE 1 RULES
        elseif ($response_type == "E")
        {
            $returned = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        
            while ($buffer)
            {
                $item_key   = strtolower(Functions::CutString($buffer));
                $item_value = Functions::CutString($buffer);
            
                $server['convars'][$item_key] = $item_value;
            }
        }
  
        //---------------------------------------------------------+
        // IF ONLY [s] WAS REQUESTED THEN REMOVE INCOMPLETE [e]
        if ($lgsl_need['s'] && !$lgsl_need['c']) { $server['convars'] = []; }
  
        if     ($lgsl_need['s']) { $lgsl_need['s'] = FALSE; }
        elseif ($lgsl_need['c']) { $lgsl_need['c'] = FALSE; }
        elseif ($lgsl_need['p']) { $lgsl_need['p'] = FALSE; }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
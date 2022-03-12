<?php
namespace GrinJackal\LGSL;

use GrinJackal\LGSL\Functions;

class Protocols extends Functions{

    /**
     * Query 01
     */
    //public static function Query01(&$server, &$lgsl_need, &$lgsl_fp)
    //{
    //    //---------------------------------------------------------+
    //    //  PROTOCOL FOR DEVELOPING WITHOUT USING LIVE SERVERS TO HELP ENSURE RETURNED
    //    //  DATA IS SANITIZED AND THAT LONG SERVER AND PLAYER NAMES ARE HANDLED PROPERLY
    //    $server['server'] = [
    //        "game"       => "test_game",
    //        "name"       => "test_ServerNameThatsOften'Really'LongAndCanHaveSymbols<hr />ThatWill\"Screw\"UpHtmlUnlessEntitied",
    //        "map"        => "test_map",
    //        "players"    => rand(0,  16),
    //        "playersmax" => rand(16, 32),
    //        "password"   => rand(0,  1)
    //    ];
    //
    //    //---------------------------------------------------------+
    //    $server['convars'] = [
    //        "testextra1" => "normal",
    //        "testextra2" => 123,
    //        "testextra3" => time(),
    //        "testextra4" => "",
    //        "testextra5" => "<b>Setting<hr />WithHtml</b>",
    //        "testextra6" => "ReallyLongSettingLikeSomeMapCyclesThatHaveNoSpacesAndCauseThePageToGoReallyWideIfNotBrokenUp"
    //    ];
    //
    //    //---------------------------------------------------------+
    //    $server['players']['0']['name']  = "Normal";
    //    $server['players']['0']['score'] = "12";
    //    $server['players']['0']['ping']  = "34";
    //
    //    $server['players']['1']['name']  = "\xc3\xa9\x63\x68\x6f\x20\xd0\xb8-d0\xb3\xd1\x80\xd0\xbe\xd0\xba"; // UTF PLAYER NAME
    //    $server['players']['1']['score'] = "56";
    //    $server['players']['1']['ping']  = "78";
    //
    //    $server['players']['2']['name']  = "One&<Two>&Three&\"Four\"&'Five'";
    //    $server['players']['2']['score'] = "90";
    //    $server['players']['2']['ping']  = "12";
    //
    //    $server['players']['3']['name']  = "ReallyLongPlayerNameBecauseTheyAreUberCoolAndAreInFiveClans";
    //    $server['players']['3']['score'] = "90";
    //    $server['players']['3']['ping']  = "12";
    //
    //    //---------------------------------------------------------+
    //    if (rand(0, 10) == 5) { $server['players'] = array(); } // RANDOM NO PLAYERS
    //    if (rand(0, 10) == 5) { return FALSE; }           // RANDOM GOING OFFLINE
    //
    //    //---------------------------------------------------------+
    //    return TRUE;
    //}

    public static function Query02(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        if     ($server['basic']['type'] == "quake2")              { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFstatus");        }
        elseif ($server['basic']['type'] == "warsowold")           { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFgetinfo");       }
        elseif (strpos($server['basic']['type'], "moh") !== FALSE) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x02getstatus"); } // mohaa_ mohaab_ mohaas_ mohpa_
        else                                                   { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFgetstatus");     }

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        //---------------------------------------------------------+
        $part = explode("\n", $buffer);  // SPLIT INTO PARTS: HEADER/SETTINGS/PLAYERS/FOOTER
        array_pop($part);                // REMOVE FOOTER WHICH IS EITHER NULL OR "\challenge\"
        $item = explode("\\", $part[1]); // SPLIT PART INTO ITEMS

        foreach ($item as $item_key => $data_key)
        {
            if (!($item_key % 2)) { continue; } // SKIP EVEN KEYS

            $data_key               = strtolower(self::ParserColor($data_key, "1"));
            $server['convars'][$data_key] = self::ParserColor($item[$item_key+1], "1");
        }

        //---------------------------------------------------------+
        if (!empty($server['convars']['hostname']))    { $server['server']['name'] = $server['convars']['hostname']; }
        if (!empty($server['convars']['sv_hostname'])) { $server['server']['name'] = $server['convars']['sv_hostname']; }

        if (isset($server['convars']['gamename'])) { $server['server']['game'] = $server['convars']['gamename']; }
        if (isset($server['convars']['mapname']))  { $server['server']['map']  = $server['convars']['mapname']; }

        $server['server']['players'] = empty($part['2']) ? 0 : count($part) - 2;

        if (isset($server['convars']['maxclients']))    { $server['server']['playersmax'] = $server['convars']['maxclients']; }    // QUAKE 2
        if (isset($server['convars']['sv_maxclients'])) { $server['server']['playersmax'] = $server['convars']['sv_maxclients']; }

        if (isset($server['convars']['pswrd']))      { $server['server']['password'] = $server['convars']['pswrd']; }              // CALL OF DUTY
        if (isset($server['convars']['needpass']))   { $server['server']['password'] = $server['convars']['needpass']; }           // QUAKE 2
        if (isset($server['convars']['g_needpass'])) { $server['server']['password'] = (int)$server['convars']['g_needpass']; }

        array_shift($part); // REMOVE HEADER
        array_shift($part); // REMOVE SETTING

        //---------------------------------------------------------+
        if ($server['basic']['type'] == "nexuiz") // (SCORE) (PING) (TEAM IF TEAM GAME) "(NAME)"
        {
            $pattern = "/(.*) (.*) (.*)\"(.*)\"/U"; $fields = array(1=>"score", 2=>"ping", 3=>"team", 4=>"name");
        }
        elseif ($server['basic']['type'] == "warsow") // (SCORE) (PING) "(NAME)" (TEAM)
        {
            $pattern = "/(.*) (.*) \"(.*)\" (.*)/"; $fields = array(1=>"score", 2=>"ping", 3=>"name", 4=>"team");
        }
        elseif ($server['basic']['type'] == "sof2") // (SCORE) (PING) (DEATHS) "(NAME)"
        {
            $pattern = "/(.*) (.*) (.*) \"(.*)\"/"; $fields = array(1=>"score", 2=>"ping", 3=>"deaths", 4=>"name");
        }
        elseif (strpos($server['basic']['type'], "mohpa") !== FALSE) // (?) (SCORE) (?) (TIME) (?) "(RANK?)" "(NAME)"
        {
            $pattern = "/(.*) (.*) (.*) (.*) (.*) \"(.*)\" \"(.*)\"/"; $fields = array(2=>"score", 3=>"deaths", 4=>"time", 6=>"rank", 7=>"name");
        }
        elseif (strpos($server['basic']['type'], "moh") !== FALSE) // (PING) "(NAME)"
        {
            $pattern = "/(.*) \"(.*)\"/"; $fields = array(1=>"ping", 2=>"name");
        }
        else // (SCORE) (PING) "(NAME)"
        {
            $pattern = "/(.*) (.*) \"(.*)\"/"; $fields = array(1=>"score", 2=>"ping", 3=>"name");
        }

        //---------------------------------------------------------+
        foreach ($part as $player_key => $data)
        {
            if (!$data) { continue; }

            preg_match($pattern, $data, $match);

            foreach ($fields as $match_key => $field_name)
            {
                if (isset($match[$match_key])) { $server['players'][$player_key][$field_name] = trim($match[$match_key]); }
            }

            $server['players'][$player_key]['name'] = self::ParserColor($server['players'][$player_key]['name'], "1");

            if (isset($server['players'][$player_key]['time']))
            {
                $server['players'][$player_key]['time'] = self::Time($server['players'][$player_key]['time']);
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query03(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        // BF1942 BUG: RETURNS 'GHOST' NAMES - TO SKIP THESE WE NEED AN [s] REQUEST FOR AN ACCURATE PLAYER COUNT
        if ($server['basic']['type'] == "bf1942" && $lgsl_need['p'] && !$lgsl_need['s'] && !isset($lgsl_need['sp'])) { $lgsl_need['s'] = TRUE; $lgsl_need['sp'] = TRUE; }
  
        if     ($server['basic']['type'] == "cncrenegade") { fwrite($lgsl_fp, "\\status\\"); }
        elseif ($lgsl_need['s'] || $lgsl_need['c'])    { fwrite($lgsl_fp, "\\basic\\\\info\\\\rules\\"); $lgsl_need['s'] = FALSE; $lgsl_need['c'] = FALSE; }
        elseif ($lgsl_need['p'])                       { fwrite($lgsl_fp, "\\players\\");                $lgsl_need['p'] = FALSE; }
  
        //---------------------------------------------------------+
        $buffer = "";
        $queryid = "";
        $packet_count = 0;
        $packet_total = 20;
  
        do
        {
            $packet = fread($lgsl_fp, 4096);
  
            // QUERY PORT CHECK AS THE CONNECTION PORT WILL ALSO RESPOND
            if (strpos($packet, "\\") === FALSE) { return FALSE; }
  
            // REMOVE SLASH PREFIX
            if ($packet[0] == "\\") { $packet = substr($packet, 1); }
  
            while ($packet)
            {
                $key   = strtolower(self::CutString($packet, 0, "\\"));
                $value =       trim(self::CutString($packet, 0, "\\"));
  
                // CHECK IF KEY IS PLAYER DATA
                if (preg_match("/(.*)_([0-9]+)$/", $key, $match))
                {
                    // SEPERATE TEAM NAMES
                    if ($match[1] == "teamname") { $server['teams'][$match[2]]['name'] = $value; continue; }
                
                    // CONVERT TO LGSL STANDARD
                    if     ($match[1] == "player")     { $match[1] = "name";  }
                    elseif ($match[1] == "playername") { $match[1] = "name";  }
                    elseif ($match[1] == "frags")      { $match[1] = "score"; }
                    elseif ($match[1] == "ngsecret")   { $match[1] = "stats"; }
                
                    $server['players'][$match[2]][$match[1]] = $value; continue;
                }
  
                // SEPERATE QUERYID
                if ($key == "queryid") { $queryid = $value; continue; }
  
                // SERVER SETTING
                $server['convars'][$key] = $value;
            }
  
            // FINAL PACKET NUMBER IS THE TOTAL
            if (isset($server['convars']['final']))
            {
                preg_match("/([0-9]+)\.([0-9]+)/", $queryid, $match);
                $packet_total = intval($match[2]);
                unset($server['convars']['final']);
            }
  
            $packet_count ++;
        }
        while ($packet_count < $packet_total);
  
        //---------------------------------------------------------+
        if (isset($server['convars']['mapname']))
        {
            $server['server']['map'] = $server['convars']['mapname'];
  
            if (!empty($server['convars']['hostname']))    { $server['server']['name'] = $server['convars']['hostname']; }
            if (!empty($server['convars']['sv_hostname'])) { $server['server']['name'] = $server['convars']['sv_hostname']; }
  
            if (isset($server['convars']['password']))   { $server['server']['password']   = $server['convars']['password']; }
            if (isset($server['convars']['numplayers'])) { $server['server']['players']    = $server['convars']['numplayers']; }
            if (isset($server['convars']['maxplayers'])) { $server['server']['playersmax'] = $server['convars']['maxplayers']; }
  
            if (!empty($server['convars']['gamename']))                                   { $server['server']['game'] = $server['convars']['gamename']; }
            if (!empty($server['convars']['gameid']) && empty($server['convars']['gamename']))  { $server['server']['game'] = $server['convars']['gameid']; }
            if (!empty($server['convars']['gameid']) && $server['basic']['type'] == "bf1942") { $server['server']['game'] = $server['convars']['gameid']; }
        }
  
        //---------------------------------------------------------+
        if ($server['players'])
        {
            // BF1942 BUG - REMOVE 'GHOST' PLAYERS
            if ($server['basic']['type'] == "bf1942" && $server['server']['players'])
            {
                $server['players'] = array_slice($server['players'], 0, $server['server']['players']);
            }
  
            // OPERATION FLASHPOINT BUG: 'GHOST' PLAYERS IN UN-USED 'TEAM' FIELD
            if ($server['basic']['type'] == "flashpoint")
            {
                foreach ($server['players'] as $key => $value)
                {
                    unset($server['players'][$key]['team']);
                }
            }
  
            // AVP2 BUG: PLAYER NUMBER PREFIXED TO NAMES
            if ($server['basic']['type'] == "avp2")
            {
                foreach ($server['players'] as $key => $value)
                {
                    $server['players'][$key]['name'] = preg_replace("/[0-9]+~/", "", $server['players'][$key]['name']);
                }
            }
  
            // IF TEAM NAMES AVAILABLE USED INSTEAD OF TEAM NUMBERS
            if (isset($server['teams'][0]['name']))
            {
                foreach ($server['players'] as $key => $value)
                {
                    $team_key = $server['players'][$key]['team'] - 1;
                    $server['players'][$key]['team'] = $server['teams'][$team_key]['name'];
                }
            }
  
            // RE-INDEX PLAYER KEYS TO REMOVE ANY GAPS
            $server['players'] = array_values($server['players']);
        }
        return TRUE;
    }

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
                $server['convars']['bzip2'] = "unavailable"; $lgsl_need['c'] = FALSE;
                return TRUE;
            }
        
            $buffer = bzdecompress($buffer);
        }
  
        $header = self::CutByte($buffer, 4);
  
        if ($header != "\xFF\xFF\xFF\xFF") { return FALSE; } // SOMETHING WENT WRONG
  
        //---------------------------------------------------------+
        $response_type = self::CutByte($buffer, 1);
  
        if ($response_type == "I") // SOURCE INFO ( HALF-LIFE 2 )
        {
            $server['convars']['netcode']     = ord(self::CutByte($buffer, 1));
            $server['server']['name']        = self::CutString($buffer);
            $server['server']['map']         = self::CutString($buffer);
            $server['server']['game']        = self::CutString($buffer);
            $server['convars']['description'] = self::CutString($buffer);
            $server['convars']['appid']       = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['server']['players']     = ord(self::CutByte($buffer, 1));
            $server['server']['playersmax']  = ord(self::CutByte($buffer, 1));
            $server['convars']['bots']        = ord(self::CutByte($buffer, 1));
            $server['convars']['dedicated']   = self::CutByte($buffer, 1);
            $server['convars']['os']          = self::CutByte($buffer, 1);
            $server['server']['password']    = ord(self::CutByte($buffer, 1));
            $server['convars']['anticheat']   = ord(self::CutByte($buffer, 1));
            $server['convars']['version']     = self::CutString($buffer);
        
            if (ord(self::CutByte($buffer, 1)) == 177) {
              self::CutByte($buffer, 10);
            }else{
                self::CutByte($buffer, 6);
            }
            $server['convars']['tags']        = self::CutString($buffer);
        
            if($server['server']['game'] == 'rust'){
                preg_match('/cp\d{1,3}/', $server['convars']['tags'], $e);
                $server['server']['players'] = substr($e[0], 2);
                preg_match('/mp\d{1,3}/', $server['convars']['tags'], $e);
                $server['server']['playersmax'] = substr($e[0], 2);
            }
        }
  
        elseif ($response_type == "m") // HALF-LIFE 1 INFO
        {
            $server_ip                  = self::CutString($buffer);
            $server['server']['name']        = self::CutString($buffer);
            $server['server']['map']         = self::CutString($buffer);
            $server['server']['game']        = self::CutString($buffer);
            $server['convars']['description'] = self::CutString($buffer);
            $server['server']['players']     = ord(self::CutByte($buffer, 1));
            $server['server']['playersmax']  = ord(self::CutByte($buffer, 1));
            $server['convars']['netcode']     = ord(self::CutByte($buffer, 1));
            $server['convars']['dedicated']   = self::CutByte($buffer, 1);
            $server['convars']['os']          = self::CutByte($buffer, 1);
            $server['server']['password']    = ord(self::CutByte($buffer, 1));
  
            if (ord(self::CutByte($buffer, 1))) // MOD FIELDS ( OFF FOR SOME HALFLIFEWON-VALVE SERVERS )
            {
                $server['convars']['mod_url_info']     = self::CutString($buffer);
                $server['convars']['mod_url_download'] = self::CutString($buffer);
                $buffer = substr($buffer, 1);
                $server['convars']['mod_version']      = self::UnPack(self::CutByte($buffer, 4), "l");
                $server['convars']['mod_size']         = self::UnPack(self::CutByte($buffer, 4), "l");
                $server['convars']['mod_server_side']  = ord(self::CutByte($buffer, 1));
                $server['convars']['mod_custom_dll']   = ord(self::CutByte($buffer, 1));
            }
  
            $server['convars']['anticheat'] = ord(self::CutByte($buffer, 1));
            $server['convars']['bots']      = ord(self::CutByte($buffer, 1));
        }
  
        elseif ($response_type == "D") // SOURCE AND HALF-LIFE 1 PLAYERS
        {
            $returned = ord(self::CutByte($buffer, 1));
  
            $player_key = 0;
  
            while ($buffer)
            {
                self::CutByte($buffer, 1);
                $server['players'][$player_key]['name']  = self::CutString($buffer);
                $server['players'][$player_key]['score'] = self::UnPack(self::CutByte($buffer, 4), "l");
                $server['players'][$player_key]['time']  = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
                
                $player_key ++;
            }
        }
  
        elseif ($response_type == "E") // SOURCE AND HALF-LIFE 1 RULES
        {
            $returned = self::UnPack(self::CutByte($buffer, 2), "S");
        
            while ($buffer)
            {
                $item_key   = strtolower(self::CutString($buffer));
                $item_value = self::CutString($buffer);
            
                $server['convars'][$item_key] = $item_value;
            }
        }
  
        //---------------------------------------------------------+
        // IF ONLY [s] WAS REQUESTED THEN REMOVE INCOMPLETE [e]
        if ($lgsl_need['s'] && !$lgsl_need['c']) { $server['convars'] = array(); }
  
        if     ($lgsl_need['s']) { $lgsl_need['s'] = FALSE; }
        elseif ($lgsl_need['c']) { $lgsl_need['c'] = FALSE; }
        elseif ($lgsl_need['p']) { $lgsl_need['p'] = FALSE; }
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query06(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  GET A CHALLENGE CODE IF NEEDED
        $challenge_code = "";

        if ($server['basic']['type'] != "bf2" && $server['basic']['type'] != "graw")
        {
            fwrite($lgsl_fp, "\xFE\xFD\x09\x21\x21\x21\x21\xFF\xFF\xFF\x01");

            $challenge_packet = fread($lgsl_fp, 4096);

            if (!$challenge_packet) { return FALSE; }

            $challenge_code = substr($challenge_packet, 5, -1); // REMOVE HEADER AND TRAILING NULL

            // IF CODE IS RETURNED ( SOME STALKER SERVERS RETURN BLANK WHERE THE CODE IS NOT NEEDED )
            // CONVERT DECIMAL |TO| HEX AS 8 CHARACTER STRING |TO| 4 PAIRS OF HEX |TO| 4 PAIRS OF DECIMAL |TO| 4 PAIRS OF ASCII
            $challenge_code = $challenge_code ? chr($challenge_code >> 24).chr($challenge_code >> 16).chr($challenge_code >> 8).chr($challenge_code >> 0) : "";
        }

        fwrite($lgsl_fp, "\xFE\xFD\x00\x21\x21\x21\x21{$challenge_code}\xFF\xFF\xFF\x01");

        //---------------------------------------------------------+
        //  GET RAW PACKET DATA
        $buffer = [];
        $packet_count = 0;
        $packet_total = 4;

        do
        {
            $packet_count ++;
            $packet = fread($lgsl_fp, 8192);

            if (!$packet) { return FALSE; }

            $packet       = substr($packet, 14); // REMOVE SPLITNUM HEADER
            $packet_order = ord(self::CutByte($packet, 1));

            if ($packet_order >= 128) // LAST PACKET - SO ITS ORDER NUMBER IS ALSO THE TOTAL
            {
                $packet_order -= 128;
                $packet_total = $packet_order + 1;
            }

            $buffer[$packet_order] = $packet;
            if ($server['basic']['type'] == "minecraft" || $server['basic']['type'] == "jc2mp") { $packet_total = 1; }

        }
        while ($packet_count < $packet_total);

        //---------------------------------------------------------+
        //  PROCESS AND SORT PACKETS
        foreach ($buffer as $key => $packet)
        {
            $packet = substr($packet, 0, -1); // REMOVE END NULL FOR JOINING

            if (substr($packet, -1) != "\x00") // LAST VALUE HAS BEEN SPLIT
            {
                $part = explode("\x00", $packet); // REMOVE SPLIT VALUE AS COMPLETE VALUE IS IN NEXT PACKET
                array_pop($part);
                $packet = implode("\x00", $part)."\x00";
            }

            if ($packet[0] != "\x00") // PLAYER OR TEAM DATA THAT MAY BE A CONTINUATION
            {
                $pos = strpos($packet, "\x00") + 1; // WHEN DATA IS SPLIT THE NEXT PACKET STARTS WITH A REPEAT OF THE FIELD NAME

                if (isset($packet[$pos]) && $packet[$pos] != "\x00") // REPEATED FIELD NAMES END WITH \x00\x?? INSTEAD OF \x00\x00
                {
                    $packet = substr($packet, $pos + 1); // REMOVE REPEATED FIELD NAME
                }else{
                    $packet = "\x00".$packet; // RE-ADD NULL AS PACKET STARTS WITH A NEW FIELD
                }
            }

            $buffer[$key] = $packet;
        }

        ksort($buffer);

        $buffer = implode("", $buffer);

        //---------------------------------------------------------+
        //  SERVER SETTINGS
        $buffer = substr($buffer, 1); // REMOVE HEADER \x00

        while ($key = strtolower(self::CutString($buffer)))
        {
            $server['convars'][$key] = self::CutString($buffer);
        }

        $lgsl_conversion = [ "hostname" => "name", "gamename" => "game", "mapname" => "map", "map" => "map", "numplayers" => "players", "maxplayers" => "playersmax", "password" => "password" ];
        foreach ($lgsl_conversion as $e => $s) { 
            if (isset($server['convars'][$e])) { 
                $server['server'][$s] = $server['convars'][$e]; 
                unset($server['convars'][$e]); 
            }
        }

        if ($server['basic']['type'] == "bf2" || $server['basic']['type'] == "bf2142") {
          $server['server']['map'] = ucwords(str_replace("_", " ", $server['server']['map']));
        } // MAP NAME CONSISTENCY
        elseif ($server['basic']['type'] == "jc2mp") {
          $server['server']['map'] = 'Panau';
        }
        elseif ($server['basic']['type'] == "minecraft") {
            if (isset($server['convars']['gametype'])) {
                $server['server']['game'] = strtolower($server['convars']['game_id']);
            }

            $server['server']['name'] = self::ParserColor($server['server']['name'], "minecraft");
            foreach ($server['convars'] as $key => $val) {
                if (($key != 'version') && ($key != 'plugins')) {
                    unset($server['convars'][$key]);
                }
            }

            $plugins = explode(": ", $server['convars']['plugins'], 2);
            if ($plugins[0]) {
                $server['convars']['plugins'] = $plugins[0];
            } else {
                $server['convars']['plugins'] = 'none (Vanilla)';
            }
            if (count($plugins) == 2) {
                while ($key = self::CutString($plugins[1], 0, " ")) {
                    $server['convars'][$key] = self::CutString($plugins[1], 0, "; ");
                }
            }
            $buffer = $buffer."\x00"; // Needed to correctly terminate the players list
        }

        if ($server['server']['players'] == "0") { return TRUE; } // IF SERVER IS EMPTY SKIP THE PLAYER CODE

        //---------------------------------------------------------+
        //  PLAYER DETAILS
        $buffer = substr($buffer, 1); // REMOVE HEADER \x01

        while ($buffer)
        {
            if ($buffer[0] == "\x02") { break; }
            if ($buffer[0] == "\x00") { $buffer = substr($buffer, 1); break; }

            $field = self::CutString($buffer, 0, "\x00\x00");
            $field = strtolower(substr($field, 0, -1));

            if     ($field == "player") { $field = "name"; }
            elseif ($field == "aibot")  { $field = "bot";  }

            if ($buffer[0] == "\x00") { $buffer = substr($buffer, 1); continue; }

            $value_list = self::CutString($buffer, 0, "\x00\x00");
            $value_list = explode("\x00", $value_list);

            foreach ($value_list as $key => $value)
            {
                $server['players'][$key][$field] = $value;
            }
        }

        //---------------------------------------------------------+
        //  TEAM DATA
        $buffer = substr($buffer, 1); // REMOVE HEADER \x02

        while ($buffer)
        {
            if ($buffer[0] == "\x00") { break; }

            $field = self::CutString($buffer, 0, "\x00\x00");
            $field = strtolower($field);

            if     ($field == "team_t")  { $field = "name";  }
            elseif ($field == "score_t") { $field = "score"; }

            $value_list = self::CutString($buffer, 0, "\x00\x00");
            $value_list = explode("\x00", $value_list);

            foreach ($value_list as $key => $value)
            {
                $server['teams'][$key][$field] = $value;
            }
        }

        //---------------------------------------------------------+
        //  TEAM NAME CONVERSION
        if ($server['players'] && isset($server['teams'][0]['name']) && $server['teams'][0]['name'] != "Team")
        {
            foreach ($server['players'] as $key => $value)
            {
                if (empty($server['players'][$key]['team'])) { continue; }
            
                $team_key = $server['players'][$key]['team'] - 1;
            
                if (!isset($server['teams'][$team_key]['name'])) { continue; }
            
                $server['players'][$key]['team'] = $server['teams'][$team_key]['name'];
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query07(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFstatus\x00");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        //---------------------------------------------------------+
        $buffer = substr($buffer, 6, -2); // REMOVE HEADER AND FOOTER
        $part   = explode("\n", $buffer); // SPLIT INTO SETTINGS/PLAYER/PLAYER/PLAYER

        //---------------------------------------------------------+
        $item = explode("\\", $part[0]);

        foreach ($item as $item_key => $data_key)
        {
            if ($item_key % 2) { continue; } // SKIP ODD KEYS

            $data_key               = strtolower($data_key);
            $server['convars'][$data_key] = $item[$item_key+1];
        }

        //---------------------------------------------------------+
        array_shift($part); // REMOVE SETTINGS

        foreach ($part as $key => $data)
        {
            preg_match("/(.*) (.*) (.*) (.*) \"(.*)\" \"(.*)\" (.*) (.*)/s", $data, $match); // GREEDY MATCH FOR SKINS

            $server['players'][$key]['pid']         = $match[1];
            $server['players'][$key]['score']       = $match[2];
            $server['players'][$key]['time']        = $match[3];
            $server['players'][$key]['ping']        = $match[4];
            $server['players'][$key]['name']        = self::ParserColor($match[5], $server['basic']['type']);
            $server['players'][$key]['skin']        = $match[6];
            $server['players'][$key]['skin_top']    = $match[7];
            $server['players'][$key]['skin_bottom'] = $match[8];
        }

        //---------------------------------------------------------+
        $server['server']['game']       = $server['convars']['*gamedir'];
        $server['server']['name']       = $server['convars']['hostname'];
        $server['server']['map']        = $server['convars']['map'];
        $server['server']['players']    = $server['players'] ? count($server['players']) : 0;
        $server['server']['playersmax'] = $server['convars']['maxclients'];
        $server['server']['password']   = isset($server['convars']['needpass']) && $server['convars']['needpass'] > 0 && $server['convars']['needpass'] < 4 ? 1 : 0;

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query08(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "s"); // ASE ( ALL SEEING EYE ) PROTOCOL
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 4); // REMOVE HEADER
  
        $server['convars']['gamename']   = self::CutPascal($buffer, 1, -1);
        $server['convars']['hostport']   = self::CutPascal($buffer, 1, -1);
        $server['server']['name']       = self::ParserColor(self::CutPascal($buffer, 1, -1), $server['basic']['type']);
        $server['convars']['gamemode']   = self::CutPascal($buffer, 1, -1);
        $server['server']['map']        = self::CutPascal($buffer, 1, -1);
        $server['convars']['version']    = self::CutPascal($buffer, 1, -1);
        $server['server']['password']   = self::CutPascal($buffer, 1, -1);
        $server['server']['players']    = self::CutPascal($buffer, 1, -1);
        $server['server']['playersmax'] = self::CutPascal($buffer, 1, -1);
  
        while ($buffer && $buffer[0] != "\x01")
        {
            $item_key   = strtolower(self::CutPascal($buffer, 1, -1));
            $item_value = self::CutPascal($buffer, 1, -1);
        
            $server['convars'][$item_key] = $item_value;
        }
  
        $buffer = substr($buffer, 1); // REMOVE END MARKER
  
        //---------------------------------------------------------+
        $player_key = 0;
  
        while ($buffer)
        {
            $bit_flags = self::CutByte($buffer, 1); // FIELDS HARD CODED BELOW BECAUSE GAMES DO NOT USE THEM PROPERLY
        
            if     ($bit_flags == "\x3D")                 { $field_list = array("name",                  "score", "",     "time"); } // FARCRY PLAYERS CONNECTING
            elseif ($server['basic']['type'] == "farcry")     { $field_list = array("name", "team", "",      "score", "ping", "time"); } // FARCRY PLAYERS JOINED
            elseif ($server['basic']['type'] == "mta")        { $field_list = array("name", "",      "",     "score", "ping", ""    ); }
            elseif ($server['basic']['type'] == "painkiller") { $field_list = array("name", "",     "skin",  "score", "ping", ""    ); }
            elseif ($server['basic']['type'] == "soldat")     { $field_list = array("name", "team", "",      "score", "ping", "time"); }
        
            foreach ($field_list as $item_key)
            {
                $item_value = self::CutPascal($buffer, 1, -1);

                if (!$item_key) { continue; }

                if ($item_key == "name") { self::ParserColor($item_value, $server['basic']['type']); }

                $server['players'][$player_key][$item_key] = $item_value;
            }
            $player_key ++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }

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

    public static function Query10(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        if ($server['basic']['type'] == "quakewars") { fwrite($lgsl_fp, "\xFF\xFFgetInfoEX\xFF"); }
        else                                     { fwrite($lgsl_fp, "\xFF\xFFgetInfo\xFF");   }
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        if     ($server['basic']['type'] == "wolf2009")  { $buffer = substr($buffer, 31); }  // REMOVE HEADERS
        elseif ($server['basic']['type'] == "quakewars") { $buffer = substr($buffer, 33); }
        else                                         { $buffer = substr($buffer, 23); }
  
        $buffer = self::ParserColor($buffer, "2");
  
        //---------------------------------------------------------+
        while ($buffer && $buffer[0] != "\x00")
        {
            $item_key   = strtolower(self::CutString($buffer));
            $item_value = self::CutString($buffer);
        
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
                $server['players'][$player_key]['pid']     = ord(self::CutByte($buffer, 1));
                $server['players'][$player_key]['ping']    = self::UnPack(self::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['rate']    = self::UnPack(self::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['unknown'] = self::UnPack(self::CutByte($buffer, 2), "S");
                $player_name                         = self::CutString($buffer);
                $player_tag_position                 = ord(self::CutByte($buffer, 1));
                $player_tag                          = self::CutString($buffer);
                $server['players'][$player_key]['bot']     = ord(self::CutByte($buffer, 1));

                if     ($player_tag == "")           { $server['players'][$player_key]['name'] = $player_name; }
                elseif ($player_tag_position == "0") { $server['players'][$player_key]['name'] = $player_tag." ".$player_name; }
                else                                 { $server['players'][$player_key]['name'] = $player_name." ".$player_tag; }

                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        elseif ($server['basic']['type'] == "quakewars") // QUAKEWARS: (PID)(PING)(NAME)(TAGPOSITION)(TAG)(BOT)
        {
            while ($buffer && $buffer[0] != "\x20") // STOPS AT PID 32
            {
                $server['players'][$player_key]['pid']  = ord(self::CutByte($buffer, 1));
                $server['players'][$player_key]['ping'] = self::UnPack(self::CutByte($buffer, 2), "S");
                $player_name                      = self::CutString($buffer);
                $player_tag_position              = ord(self::CutByte($buffer, 1));
                $player_tag                       = self::CutString($buffer);
                $server['players'][$player_key]['bot']  = ord(self::CutByte($buffer, 1));
                
                    if ($player_tag_position == "")  { $server['players'][$player_key]['name'] = $player_name; }
                elseif ($player_tag_position == "1") { $server['players'][$player_key]['name'] = $player_name." ".$player_tag; }
                else                                 { $server['players'][$player_key]['name'] = $player_tag." ".$player_name; }
            
                $player_key ++;
            }
        
            $buffer                      = substr($buffer, 1);
            $server['convars']['si_osmask']    = self::UnPack(self::CutByte($buffer, 4), "I");
            $server['convars']['si_ranked']    = ord(self::CutByte($buffer, 1));
            $server['convars']['si_timeleft']  = self::Time(self::UnPack(self::CutByte($buffer, 4), "I") / 1000);
            $server['convars']['si_gamestate'] = ord(self::CutByte($buffer, 1));
            $buffer                      = substr($buffer, 2);
        
            $player_key = 0;
        
            while ($buffer && $buffer[0] != "\x20") // QUAKEWARS EXTENDED: (PID)(XP)(TEAM)(KILLS)(DEATHS)
            {
                $server['players'][$player_key]['pid']    = ord(self::CutByte($buffer, 1));
                $server['players'][$player_key]['xp']     = intval(self::UnPack(self::CutByte($buffer, 4), "f"));
                $server['players'][$player_key]['team']   = self::CutString($buffer);
                $server['players'][$player_key]['score']  = self::UnPack(self::CutByte($buffer, 4), "i");
                $server['players'][$player_key]['deaths'] = self::UnPack(self::CutByte($buffer, 4), "i");
                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        elseif ($server['basic']['type'] == "quake4") // QUAKE4: (PID)(PING)(RATE)(NULLNULL)(NAME)(TAG)
        {
            while ($buffer && $buffer[0] != "\x20") // STOPS AT PID 32
            {
                $server['players'][$player_key]['pid']  = ord(self::CutByte($buffer, 1));
                $server['players'][$player_key]['ping'] = self::UnPack(self::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['rate'] = self::UnPack(self::CutByte($buffer, 2), "S");
                $buffer                           = substr($buffer, 2);
                $player_name                      = self::CutString($buffer);
                $player_tag                       = self::CutString($buffer);
                $server['players'][$player_key]['name'] = $player_tag ? $player_tag." ".$player_name : $player_name;
                
                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        else // DOOM3 AND PREY: (PID)(PING)(RATE)(NULLNULL)(NAME)
        {
            while ($buffer && $buffer[0] != "\x20") // STOPS AT PID 32
            {
                $server['players'][$player_key]['pid']  = ord(self::CutByte($buffer, 1));
                $server['players'][$player_key]['ping'] = self::UnPack(self::CutByte($buffer, 2), "S");
                $server['players'][$player_key]['rate'] = self::UnPack(self::CutByte($buffer, 2), "S");
                $buffer                           = substr($buffer, 2);
                $server['players'][$player_key]['name'] = self::CutString($buffer);
            
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

    public static function Query11(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://wiki.unrealadmin.org/UT3_query_protocol
        //  UT3 RESPONSE IS REALLY MESSY SO THIS CLEANS IT UP
        $status = self::Query06($server, $lgsl_need, $lgsl_fp);
  
        if (!$status) { return FALSE; }
  
        //---------------------------------------------------------+
        $server['server']['map'] = $server['convars']['p1073741825'];
        unset($server['convars']['p1073741825']);
  
        //---------------------------------------------------------+
        $lgsl_ut3_key = [
            "s0"          => "bots_skill",
            "s6"          => "pure",
            "s7"          => "password",
            "s8"          => "bots_vs",
            "s10"         => "forcerespawn",
            "p268435703"  => "bots",
            "p268435704"  => "goalscore",
            "p268435705"  => "timelimit",
            "p268435717"  => "mutators_default",
            "p1073741826" => "gamemode",
            "p1073741827" => "description",
            "p1073741828" => "mutators_custom"
        ];
  
        foreach ($lgsl_ut3_key as $old => $new)
        {
            if (!isset($server['convars'][$old])) { continue; }
            $server['convars'][$new] = $server['convars'][$old];
            unset($server['convars'][$old]);
        }
  
        //---------------------------------------------------------+
        $part = explode(".", $server['convars']['gamemode']);
        if ($part[0] && (stristr($part[0], "UT") === FALSE))
        {
            $server['server']['game'] = $part[0];
        }
  
        //---------------------------------------------------------+
        $tmp = $server['convars']['mutators_default'];
        $server['convars']['mutators_default'] = "";
  
        if ($tmp & 1)     { $server['convars']['mutators_default'] .= " BigHead";           }
        if ($tmp & 2)     { $server['convars']['mutators_default'] .= " FriendlyFire";      }
        if ($tmp & 4)     { $server['convars']['mutators_default'] .= " Handicap";          }
        if ($tmp & 8)     { $server['convars']['mutators_default'] .= " Instagib";          }
        if ($tmp & 16)    { $server['convars']['mutators_default'] .= " LowGrav";           }
        if ($tmp & 64)    { $server['convars']['mutators_default'] .= " NoPowerups";        }
        if ($tmp & 128)   { $server['convars']['mutators_default'] .= " NoTranslocator";    }
        if ($tmp & 256)   { $server['convars']['mutators_default'] .= " Slomo";             }
        if ($tmp & 1024)  { $server['convars']['mutators_default'] .= " SpeedFreak";        }
        if ($tmp & 2048)  { $server['convars']['mutators_default'] .= " SuperBerserk";      }
        if ($tmp & 8192)  { $server['convars']['mutators_default'] .= " WeaponReplacement"; }
        if ($tmp & 16384) { $server['convars']['mutators_default'] .= " WeaponsRespawn";    }
  
        $server['convars']['mutators_default'] = str_replace(" ",    " / ", trim($server['convars']['mutators_default']));
        $server['convars']['mutators_custom']  = str_replace("\x1c", " / ",      $server['convars']['mutators_custom']);
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query12(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        if     ($server['basic']['type'] == "samp") { $challenge_packet = "SAMP\x21\x21\x21\x21\x00\x00"; }
        elseif ($server['basic']['type'] == "vcmp") { $challenge_packet = "VCMP\x21\x21\x21\x21\x00\x00"; $lgsl_need['c'] = FALSE; }
  
        if     ($lgsl_need['s']) { $challenge_packet .= "i"; }
        elseif ($lgsl_need['c']) { $challenge_packet .= "r"; }
        elseif ($lgsl_need['p'] && $server['basic']['type'] == "samp") { $challenge_packet .= "d"; }
        elseif ($lgsl_need['p'] && $server['basic']['type'] == "vcmp") { $challenge_packet .= "c"; }
  
        fwrite($lgsl_fp, $challenge_packet);  
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer && substr($challenge_packet, 10, 1) == "i") { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 10); // REMOVE HEADER
        $response_type = self::CutByte($buffer, 1);
  
        //---------------------------------------------------------+
        if ($response_type == "i")
        {
            $lgsl_need['s'] = FALSE;
        
            if ($server['basic']['type'] == "vcmp") { $buffer = substr($buffer, 12); }
        
            $server['server']['password']   = ord(self::CutByte($buffer, 1));
            $server['server']['players']    = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['server']['playersmax'] = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['server']['name']       = self::CutPascal($buffer, 4);
            $server['convars']['gamemode']   = self::CutPascal($buffer, 4);
            $server['server']['map']        = self::CutPascal($buffer, 4);
        }
  
        //---------------------------------------------------------+
        elseif ($response_type == "r")
        {
            $lgsl_need['c'] = FALSE;
        
            $item_total = self::UnPack(self::CutByte($buffer, 2), "S");
        
            for ($i = 0; $i < $item_total; $i++)
            {
                if (!$buffer) { return FALSE; }

                $data_key   = strtolower(self::CutPascal($buffer));
                $data_value = self::CutPascal($buffer);

                $server['convars'][$data_key] = $data_value;
            }
        }
  
        //---------------------------------------------------------+
        elseif ($response_type == "d")
        {
            $lgsl_need['p'] = FALSE;
        
            $player_total = self::UnPack(self::CutByte($buffer, 2), "S");
        
            for ($i = 0; $i < $player_total; $i++)
            {
                if (!$buffer) { return FALSE; }

                $server['players'][$i]['pid']   = ord(self::CutByte($buffer, 1));
                $server['players'][$i]['name']  = self::CutPascal($buffer);
                $server['players'][$i]['score'] = self::UnPack(self::CutByte($buffer, 4), "S");
                $server['players'][$i]['ping']  = self::UnPack(self::CutByte($buffer, 4), "S");
            }
        }
      
        //---------------------------------------------------------+
        elseif ($response_type == "c")
        {
            $lgsl_need['p'] = FALSE;
        
            $player_total = self::UnPack(self::CutByte($buffer, 2), "S");
        
            for ($i = 0; $i < $player_total; $i++)
            {
                if (!$buffer) { return FALSE; }

                $server['players'][$i]['name']  = self::CutPascal($buffer);
            }
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query13(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        $buffer_s = ""; fwrite($lgsl_fp, "\x21\x21\x21\x21\x00"); // REQUEST [s]
        $buffer_e = ""; fwrite($lgsl_fp, "\x21\x21\x21\x21\x01"); // REQUEST [e]
        $buffer_p = ""; fwrite($lgsl_fp, "\x21\x21\x21\x21\x02"); // REQUEST [p]
  
        //---------------------------------------------------------+
        while ($packet = fread($lgsl_fp, 4096))
        {
            if     ($packet[4] == "\x00") { $buffer_s .= substr($packet, 5); }
            elseif ($packet[4] == "\x01") { $buffer_e .= substr($packet, 5); }
            elseif ($packet[4] == "\x02") { $buffer_p .= substr($packet, 5); }
        }
  
        if (!$buffer_s) { return FALSE; }
  
        //---------------------------------------------------------+
        //  SOME VALUES START WITH A PASCAL LENGTH AND END WITH A NULL BUT THERE IS AN ISSUE WHERE
        //  CERTAIN CHARACTERS CAUSE A WRONG PASCAL LENGTH AND NULLS TO APPEAR WITHIN NAMES
        $buffer_s = str_replace("\xa0", "\x20", $buffer_s); // REPLACE SPECIAL SPACE WITH NORMAL SPACE
        $buffer_s = substr($buffer_s, 5);
        $server['convars']['hostport']   = self::UnPack(self::CutByte($buffer_s, 4), "S");
        $buffer_s = substr($buffer_s, 4);
        $server['server']['name']       = self::CutString($buffer_s, 1);
        $server['server']['map']        = self::CutString($buffer_s, 1);
        $server['convars']['gamemode']   = self::CutString($buffer_s, 1);
        $server['server']['players']    = self::UnPack(self::CutByte($buffer_s, 4), "S");
        $server['server']['playersmax'] = self::UnPack(self::CutByte($buffer_s, 4), "S");
  
        //---------------------------------------------------------+
        while ($buffer_e && $buffer_e[0] != "\x00")
        {
            $item_key   = strtolower(self::CutString($buffer_e, 1));
            $item_value = self::CutString($buffer_e, 1);
            
            $item_key   = str_replace("\x1B\xFF\xFF\x01", "", $item_key);   // REMOVE MOD
            $item_value = str_replace("\x1B\xFF\xFF\x01", "", $item_value); // GARBAGE
  
            $server['convars'][$item_key] = $item_value;
        }
  
        //---------------------------------------------------------+
        //  THIS PROTOCOL RETURNS MORE INFO THAN THE ALTERNATIVE BUT IT DOES NOT
        //  RETURN THE GAME NAME ! SO WE HAVE MANUALLY DETECT IT USING THE GAME TYPE
  
        $tmp = strtolower(substr($server['convars']['gamemode'], 0, 2));
  
        if ($tmp == "ro") { $server['server']['game'] = "Red Orchestra"; }
        elseif ($tmp == "kf") { $server['server']['game'] = "Killing Floor"; }
  
        $server['server']['password'] = empty($server['convars']['password']) && empty($server['convars']['gamepassword']) ? "0" : "1";
  
        //---------------------------------------------------------+
        $player_key = 0;
  
        while ($buffer_p && $buffer_p[0] != "\x00")
        {
            $server['players'][$player_key]['pid']   = self::UnPack(self::CutByte($buffer_p, 4), "S");
  
            $end_marker = ord($buffer_p[0]) > 64 ? "\x00\x00" : "\x00"; // DIRTY WORK-AROUND FOR NAMES WITH PROBLEM CHARACTERS
  
            $server['players'][$player_key]['name']  = self::CutString($buffer_p, 1, $end_marker);
            $server['players'][$player_key]['ping']  = self::UnPack(self::CutByte($buffer_p, 4), "S");
            $server['players'][$player_key]['score'] = self::UnPack(self::CutByte($buffer_p, 4), "s");
            $tmp                               = self::CutByte($buffer_p, 4);
  
            if ($tmp[3] == "\x20") { $server['players'][$player_key]['team'] = 1; }
            elseif ($tmp[3] == "\x40") { $server['players'][$player_key]['team'] = 2; }
  
            $player_key ++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query14(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://flstat.cryosphere.co.uk/global-list.php
        fwrite($lgsl_fp, "\x00\x02\xf1\x26\x01\x26\xf0\x90\xa6\xf0\x26\x57\x4e\xac\xa0\xec\xf8\x68\xe4\x8d\x21");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 4); // HEADER   ( 00 03 F1 26 )
        $buffer = substr($buffer, 4); // NOT USED ( 87 + NAME LENGTH )
        $buffer = substr($buffer, 4); // NOT USED ( NAME END TO BUFFER END LENGTH )
        $buffer = substr($buffer, 4); // UNKNOWN  ( 80 )
  
        $server['server']['map']        = "freelancer";
        $server['server']['password']   = self::UnPack(self::CutByte($buffer, 4), "l") - 1 ? 1 : 0;
        $server['server']['playersmax'] = self::UnPack(self::CutByte($buffer, 4), "l") - 1;
        $server['server']['players']    = self::UnPack(self::CutByte($buffer, 4), "l") - 1;
        $buffer                    = substr($buffer, 4);  // UNKNOWN ( 88 )
        $name_length               = self::UnPack(self::CutByte($buffer, 4), "l");
        $buffer                    = substr($buffer, 56); // UNKNOWN
        $server['server']['name']       = self::CutByte($buffer, $name_length);
  
        self::CutString($buffer, 0, ":");
        self::CutString($buffer, 0, ":");
        self::CutString($buffer, 0, ":");
        self::CutString($buffer, 0, ":");
        self::CutString($buffer, 0, ":");
  
        // WHATS LEFT IS THE MOTD
        $server['convars']['motd'] = substr($buffer, 0, -1);
  
        // REMOVE UTF-8 ENCODING NULLS
        $server['server']['name'] = str_replace("\x00", "", $server['server']['name']);
        $server['convars']['motd'] = str_replace("\x00", "", $server['convars']['motd']);
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query15(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "GTR2_Direct_IP_Search\x00");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = str_replace("\xFE", "\xFF", $buffer);
        $buffer = explode("\xFF", $buffer);
  
        $server['server']['name']       = $buffer[3];
        $server['server']['game']       = $buffer[7];
        $server['convars']['version']    = $buffer[11];
        $server['convars']['hostport']   = $buffer[15];
        $server['server']['map']        = $buffer[19];
        $server['server']['players']    = $buffer[25];
        $server['server']['playersmax'] = $buffer[27];
        $server['convars']['gamemode']   = $buffer[31];
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
  
    public static function Query16(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://www.planetpointy.co.uk/software/rfactorsspy.shtml
        //  http://users.pandora.be/viperius/mUtil/
        //  USES FIXED DATA POSITIONS WITH RANDOM CHARACTERS FILLING THE GAPS
        fwrite($lgsl_fp, "rF_S");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        // $server['convars']['gamename']         = self::GetString($buffer);
        $buffer = substr($buffer, 8);
        // $server['convars']['fullupdate']       = self::UnPack($buffer[0], "C");
        $server['convars']['region']           = self::UnPack($buffer[1] .$buffer[2],  "S");
        // $server['convars']['ip']               = ($buffer[3] .$buffer[4].$buffer[5].$buffer[6]); // UNSIGNED LONG
        // $server['convars']['size']             = self::UnPack($buffer[7] .$buffer[8],  "S");
        $server['convars']['version']          = self::UnPack($buffer[9] .$buffer[10], "S");
        // $server['convars']['version_racecast'] = self::UnPack($buffer[11].$buffer[12], "S");
        $server['convars']['hostport']         = self::UnPack($buffer[13].$buffer[14], "S");
        // $server['convars']['queryport']        = self::UnPack($buffer[15].$buffer[16], "S");
        $buffer = substr($buffer, 17);
        $server['server']['game']             = self::GetString($buffer);
        $buffer = substr($buffer, 20);
        $server['server']['name']             = self::GetString($buffer);
        $buffer = substr($buffer, 28);
        $server['server']['map']              = self::GetString($buffer);
        $buffer = substr($buffer, 32);
        $server['convars']['motd']             = self::GetString($buffer);
        $buffer = substr($buffer, 96);
        $server['convars']['packed_aids']      = self::UnPack($buffer[0].$buffer[1], "S");
        // $server['convars']['ping']             = self::UnPack($buffer[2].$buffer[3], "S");
        $server['convars']['packed_flags']     = self::UnPack($buffer[4],  "C");
        $server['convars']['rate']             = self::UnPack($buffer[5],  "C");
        $server['server']['players']          = self::UnPack($buffer[6],  "C");
        $server['server']['playersmax']       = self::UnPack($buffer[7],  "C");
        $server['convars']['bots']             = self::UnPack($buffer[8],  "C");
        $server['convars']['packed_special']   = self::UnPack($buffer[9],  "C");
        $server['convars']['damage']           = self::UnPack($buffer[10], "C");
        $server['convars']['packed_rules']     = self::UnPack($buffer[11].$buffer[12], "S");
        $server['convars']['credits1']         = self::UnPack($buffer[13], "C");
        $server['convars']['credits2']         = self::UnPack($buffer[14].$buffer[15], "S");
        $server['convars']['time']             = self::Time(self::UnPack($buffer[16].$buffer[17], "S"));
        $server['convars']['laps']             = self::UnPack($buffer[18].$buffer[19], "s") / 16;
        $buffer                          = substr($buffer, 23);
        $server['convars']['vehicles']         = self::GetString($buffer);
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        $server['server']['password']    = ($server['convars']['packed_special'] & 2)  ? 1 : 0;
        $server['convars']['racecast']    = ($server['convars']['packed_special'] & 4)  ? 1 : 0;
        $server['convars']['fixedsetups'] = ($server['convars']['packed_special'] & 16) ? 1 : 0;
  
        $server['convars']['aids']  = "";
        if ($server['convars']['packed_aids'] & 1)    { $server['convars']['aids'] .= " TractionControl"; }
        if ($server['convars']['packed_aids'] & 2)    { $server['convars']['aids'] .= " AntiLockBraking"; }
        if ($server['convars']['packed_aids'] & 4)    { $server['convars']['aids'] .= " StabilityControl"; }
        if ($server['convars']['packed_aids'] & 8)    { $server['convars']['aids'] .= " AutoShifting"; }
        if ($server['convars']['packed_aids'] & 16)   { $server['convars']['aids'] .= " AutoClutch"; }
        if ($server['convars']['packed_aids'] & 32)   { $server['convars']['aids'] .= " Invulnerability"; }
        if ($server['convars']['packed_aids'] & 64)   { $server['convars']['aids'] .= " OppositeLock"; }
        if ($server['convars']['packed_aids'] & 128)  { $server['convars']['aids'] .= " SteeringHelp"; }
        if ($server['convars']['packed_aids'] & 256)  { $server['convars']['aids'] .= " BrakingHelp"; }
        if ($server['convars']['packed_aids'] & 512)  { $server['convars']['aids'] .= " SpinRecovery"; }
        if ($server['convars']['packed_aids'] & 1024) { $server['convars']['aids'] .= " AutoPitstop"; }
  
        $server['convars']['aids']     = str_replace(" ", " / ", trim($server['convars']['aids']));
        $server['convars']['vehicles'] = str_replace("|", " / ", trim($server['convars']['vehicles']));
  
        unset($server['convars']['packed_aids']);
        unset($server['convars']['packed_flags']);
        unset($server['convars']['packed_special']);
        unset($server['convars']['packed_rules']);
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query17(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://masterserver.savage.s2games.com
        fwrite($lgsl_fp, "\x9e\x4c\x23\x00\x00\xce\x21\x21\x21\x21");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 12); // REMOVE HEADER
  
        while ($key = strtolower(self::CutString($buffer, 0, "\xFE")))
        {
            if ($key == "players") { break; }
        
            $value = self::CutString($buffer, 0, "\xFF");
            $value = str_replace("\x00", "", $value);
            $value = self::ParserColor($value, $server['basic']['type']);
        
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
  
        while ($value = self::CutString($buffer, 0, "\x0a"))
        {
            if ($value[0] == "\x00") { break; }
            if ($value[0] != "\x20") { $team_key++; continue; }
        
            $server['players'][$player_key]['name'] = self::ParserColor(substr($value, 1), $server['basic']['type']);
            $server['players'][$player_key]['team'] = $server['teams'][$team_key]['name'];
        
            $player_key++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query18(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://masterserver.savage2.s2games.com
        fwrite($lgsl_fp, "\x01");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 12); // REMOVE HEADER
  
        $server['server']['name']            = self::CutString($buffer);
        $server['server']['players']         = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax']      = ord(self::CutByte($buffer, 1));
        $server['convars']['time']            = self::CutString($buffer);
        $server['server']['map']             = self::CutString($buffer);
        $server['convars']['nextmap']         = self::CutString($buffer);
        $server['convars']['location']        = self::CutString($buffer);
        $server['convars']['minimum_players'] = ord(self::CutString($buffer));
        $server['convars']['gamemode']        = self::CutString($buffer);
        $server['convars']['version']         = self::CutString($buffer);
        $server['convars']['minimum_level']   = ord(self::CutByte($buffer, 1));
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query19(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\xC0\xDE\xF1\x11\x42\x06\x00\xF5\x03\x21\x21\x21\x21");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 25); // REMOVE HEADER
  
        $server['server']['name']       = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        $server['server']['map']        = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        $server['convars']['nextmap']    = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        $server['convars']['gametype']   = self::GetString(self::CutPascal($buffer, 4, 3, -3));
  
        $buffer = substr($buffer, 1);
  
        $server['server']['password']   = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax'] = ord(self::CutByte($buffer, 4));
        $server['server']['players']    = ord(self::CutByte($buffer, 4));
  
        //---------------------------------------------------------+
        for ($player_key = 0; $player_key < $server['server']['players']; $player_key++)
        {
             $server['players'][$player_key]['name'] = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 17);
  
        $server['convars']['version']    = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        $server['convars']['mods']       = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        $server['convars']['dedicated']  = ord(self::CutByte($buffer, 1));
        $server['convars']['time']       = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['status']     = ord(self::CutByte($buffer, 4));
        $server['convars']['gamemode']   = ord(self::CutByte($buffer, 4));
        $server['convars']['motd']       = self::GetString(self::CutPascal($buffer, 4, 3, -3));
        $server['convars']['respawns']   = ord(self::CutByte($buffer, 4));
        $server['convars']['time_limit'] = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['voting']     = ord(self::CutByte($buffer, 4));
  
        $buffer = substr($buffer, 2);
  
        //---------------------------------------------------------+
        for ($player_key=0; $player_key<$server['server']['players']; $player_key++)
        {
            $server['players'][$player_key]['team'] = ord(self::CutByte($buffer, 4));
        
            $unknown = ord(self::CutByte($buffer, 1));
        }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 7);
  
        $server['convars']['platoon_1_color']   = ord(self::CutByte($buffer, 8));
        $server['convars']['platoon_2_color']   = ord(self::CutByte($buffer, 8));
        $server['convars']['platoon_3_color']   = ord(self::CutByte($buffer, 8));
        $server['convars']['platoon_4_color']   = ord(self::CutByte($buffer, 8));
        $server['convars']['timer_on']          = ord(self::CutByte($buffer, 1));
        $server['convars']['timer_time']        = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['time_debriefing']   = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['time_respawn_min']  = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['time_respawn_max']  = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['time_respawn_safe'] = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['difficulty']        = ord(self::CutByte($buffer, 4));
        $server['convars']['respawn_total']     = ord(self::CutByte($buffer, 4));
        $server['convars']['random_insertions'] = ord(self::CutByte($buffer, 1));
        $server['convars']['spectators']        = ord(self::CutByte($buffer, 1));
        $server['convars']['arcademode']        = ord(self::CutByte($buffer, 1));
        $server['convars']['ai_backup']         = ord(self::CutByte($buffer, 1));
        $server['convars']['random_teams']      = ord(self::CutByte($buffer, 1));
        $server['convars']['time_starting']     = self::Time(self::UnPack(self::CutByte($buffer, 4), "f"));
        $server['convars']['identify_friends']  = ord(self::CutByte($buffer, 1));
        $server['convars']['identify_threats']  = ord(self::CutByte($buffer, 1));
  
        $buffer = substr($buffer, 5);
  
        $server['convars']['restrictions']      = self::GetString(self::CutPascal($buffer, 4, 3, -3));
  
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

    public static function Query20(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        if ($lgsl_need['s'])
        {
            fwrite($lgsl_fp, "\xFF\xFF\xFF\xFFFLSQ");
        }else{
            fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x57");
  
            $challenge_packet = fread($lgsl_fp, 4096);
  
            if (!$challenge_packet) { return FALSE; }
  
            $challenge_code = substr($challenge_packet, 5, 4);
  
            if     ($lgsl_need['c']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x56{$challenge_code}"); }
            elseif ($lgsl_need['p']) { fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x55{$challenge_code}"); }
        }
  
        $buffer = fread($lgsl_fp, 4096);
        $buffer = substr($buffer, 4); // REMOVE HEADER
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $response_type = self::CutByte($buffer, 1);
  
        if ($response_type == "I")
        {
            $server['convars']['netcode']     = ord(self::CutByte($buffer, 1));
            $server['server']['name']        = self::CutString($buffer);
            $server['server']['map']         = self::CutString($buffer);
            $server['server']['game']        = self::CutString($buffer);
            $server['convars']['gamemode']    = self::CutString($buffer);
            $server['convars']['description'] = self::CutString($buffer);
            $server['convars']['version']     = self::CutString($buffer);
            $server['convars']['hostport']    = self::UnPack(self::CutByte($buffer, 2), "n");
            $server['server']['players']     = self::UnPack(self::CutByte($buffer, 1), "C");
            $server['server']['playersmax']  = self::UnPack(self::CutByte($buffer, 1), "C");
            $server['convars']['dedicated']   = self::CutByte($buffer, 1);
            $server['convars']['os']          = self::CutByte($buffer, 1);
            $server['server']['password']    = self::UnPack(self::CutByte($buffer, 1), "C");
            $server['convars']['anticheat']   = self::UnPack(self::CutByte($buffer, 1), "C");
            $server['convars']['cpu_load']    = round(3.03 * self::UnPack(self::CutByte($buffer, 1), "C"))."%";
            $server['convars']['round']       = self::UnPack(self::CutByte($buffer, 1), "C");
            $server['convars']['roundsmax']   = self::UnPack(self::CutByte($buffer, 1), "C");
            $server['convars']['timeleft']    = self::Time(self::UnPack(self::CutByte($buffer, 2), "S") / 250);
        }
  
        elseif ($response_type == "E")
        {
            $returned = self::UnPack(self::CutByte($buffer, 2), "S");
  
            while ($buffer)
            {
                $item_key   = strtolower(self::CutString($buffer));
                $item_value = self::CutString($buffer);
                $server['convars'][$item_key] = $item_value;
            }
        }
  
        elseif ($response_type == "D")
        {
            $returned = ord(self::CutByte($buffer, 1));
            $player_key = 0;
  
            while ($buffer)
            {
                $server['players'][$player_key]['pid']   = ord(self::CutByte($buffer, 1));
                $server['players'][$player_key]['name']  = self::CutString($buffer);
                $server['players'][$player_key]['score'] = self::UnPack(self::CutByte($buffer, 4), "N");
                $server['players'][$player_key]['time']  = self::Time(self::UnPack(strrev(self::CutByte($buffer, 4)), "f"));
                $server['players'][$player_key]['ping']  = self::UnPack(self::CutByte($buffer, 2), "n");
                $server['players'][$player_key]['uid']   = self::UnPack(self::CutByte($buffer, 4), "N");
                $server['players'][$player_key]['team']  = ord(self::CutByte($buffer, 1));
  
                $player_key ++;
            }
        }
  
        //---------------------------------------------------------+
        if     ($lgsl_need['s']) { $lgsl_need['s'] = FALSE; }
        elseif ($lgsl_need['c']) { $lgsl_need['c'] = FALSE; }
        elseif ($lgsl_need['p']) { $lgsl_need['p'] = FALSE; }
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query21(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp,"\xff\xff\xff\xff\xff\xff\xff\xff\xff\xffgief");
  
        $buffer = fread($lgsl_fp, 4096);
        $buffer = substr($buffer, 20); // REMOVE HEADER
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $server['server']['name']       = self::CutString($buffer);
        $server['server']['map']        = self::CutString($buffer);
        $server['convars']['gamemode']   = self::CutString($buffer);
        $server['server']['password']   = self::CutString($buffer);
        $server['convars']['progress']   = self::CutString($buffer)."%";
        $server['server']['players']    = self::CutString($buffer);
        $server['server']['playersmax'] = self::CutString($buffer);
  
        switch ($server['convars']['gamemode'])
        {
            case 0: $server['convars']['gamemode'] = "Deathmatch"; break;
            case 1: $server['convars']['gamemode'] = "Team Deathmatch"; break;
            case 2: $server['convars']['gamemode'] = "Capture The Flag"; break;
        }
  
        //---------------------------------------------------------+
        $player_key = 0;
  
        while ($buffer)
        {
            $server['players'][$player_key]['name']  = self::CutString($buffer);
            $server['players'][$player_key]['score'] = self::CutString($buffer);
            $player_key ++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query22(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp,"\x03\x00\x00");
  
        $buffer = fread($lgsl_fp, 4096);
        $buffer = substr($buffer, 3); // REMOVE HEADER
  
        if (!$buffer) { return FALSE; }
  
        $response_type = ord(self::CutByte($buffer, 1)); // TYPE SHOULD BE 4
  
        //---------------------------------------------------------+
        $grf_count = ord(self::CutByte($buffer, 1));
  
        for ($a = 0; $a < $grf_count; $a++)
        {
            $server['convars']['grf_'.$a.'_id'] = strtoupper(dechex(self::UnPack(self::CutByte($buffer, 4), "N")));
            for ($b = 0; $b < 16; $b++)
            {
                $server['convars']['grf_'.$a.'_md5'] .= strtoupper(dechex(ord(self::CutByte($buffer, 1))));
            }
        }
  
        //---------------------------------------------------------+
        $server['convars']['date_current']   = self::UnPack(self::CutByte($buffer, 4), "L");
        $server['convars']['date_start']     = self::UnPack(self::CutByte($buffer, 4), "L");
        $server['convars']['companies_max']  = ord(self::CutByte($buffer, 1));
        $server['convars']['companies']      = ord(self::CutByte($buffer, 1));
        $server['convars']['spectators_max'] = ord(self::CutByte($buffer, 1));
        $server['server']['name']           = self::CutString($buffer);
        $server['convars']['version']        = self::CutString($buffer);
        $server['convars']['language']       = ord(self::CutByte($buffer, 1));
        $server['server']['password']       = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax']     = ord(self::CutByte($buffer, 1));
        $server['server']['players']        = ord(self::CutByte($buffer, 1));
        $server['convars']['spectators']     = ord(self::CutByte($buffer, 1));
        $server['server']['map']            = self::CutString($buffer);
        $server['convars']['map_width']      = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['convars']['map_height']     = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['convars']['map_set']        = ord(self::CutByte($buffer, 1));
        $server['convars']['dedicated']      = ord(self::CutByte($buffer, 1));
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }

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
        $server['server']['game']       = self::CutPascal($buffer);
        $server['convars']['version']    = self::CutPascal($buffer);
        $server['server']['name']       = self::CutPascal($buffer);
        $server['convars']['dedicated']  = ord(self::CutByte($buffer, 1));
        $server['server']['password']   = ord(self::CutByte($buffer, 1));
        $server['server']['players']    = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
        $server['convars']['cpu']        = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['convars']['mod']        = self::CutPascal($buffer);
        $server['convars']['type']       = self::CutPascal($buffer);
        $server['server']['map']        = self::CutPascal($buffer);
        $server['convars']['motd']       = self::CutPascal($buffer);
        $server['convars']['teams']      = ord(self::CutByte($buffer, 1));
  
        //---------------------------------------------------------+
        $team_field = "?".self::CutPascal($buffer);
        $team_field = explode("\t", $team_field);
  
        foreach ($team_field as $key => $value)
        {
            $value = substr($value, 1);
            $value = strtolower($value);
            $team_field[$key] = $value;
        }
  
        //---------------------------------------------------------+
        $player_field = "?".self::CutPascal($buffer);
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
            $team_name = self::CutPascal($buffer);
            $team_info = self::CutPascal($buffer);
        
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
            $player_bits[] = ord(self::CutByte($buffer, 1)) * 4; // %p = PING
            $player_bits[] = ord(self::CutByte($buffer, 1));     // %l = PACKET LOSS
            $player_bits[] = ord(self::CutByte($buffer, 1));     // %t = TEAM
            $player_bits[] = self::CutPascal($buffer);           // %n = PLAYER NAME
            $player_info   = self::CutPascal($buffer);
  
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

    public static function Query24(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://cubelister.sourceforge.net
        fwrite($lgsl_fp, "\x21\x21");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 2); // REMOVE HEADER

        //---------------------------------------------------------+
        if ($buffer[0] == "\x1b") // CUBE 1
        {
            // RESPONSE IS XOR ENCODED FOR SOME STRANGE REASON
            for ($i = 0; $i < strlen($buffer); $i++) { 
                $buffer[$i] = chr(ord($buffer[$i]) ^ 0x61); 
            }

            $server['server']['game']       = "Cube";
            $server['convars']['netcode']    = ord(self::CutByte($buffer, 1));
            $server['convars']['gamemode']   = ord(self::CutByte($buffer, 1));
            $server['server']['players']    = ord(self::CutByte($buffer, 1));
            $server['convars']['timeleft']   = self::Time(ord(self::CutByte($buffer, 1)) * 60);
            $server['server']['map']        = self::CutString($buffer);
            $server['server']['name']       = self::CutString($buffer);
            $server['server']['playersmax'] = "0"; // NOT PROVIDED

            // DOES NOT RETURN PLAYER INFORMATION
            return TRUE;
        }

        elseif ($buffer[0] == "\x80") // ASSAULT CUBE
        {
            $server['server']['game']       = "AssaultCube";
            $server['convars']['netcode']    = ord(self::CutByte($buffer, 1));
            $server['convars']['version']    = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['convars']['gamemode']   = ord(self::CutByte($buffer, 1));
            $server['server']['players']    = ord(self::CutByte($buffer, 1));
            $server['convars']['timeleft']   = self::Time(ord(self::CutByte($buffer, 1)) * 60);
            $server['server']['map']        = self::CutString($buffer);
            $server['server']['name']       = self::CutString($buffer);
            $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
        }

        elseif ($buffer[1] == "\x05") // CUBE 2 - SAUERBRATEN
        {
            $server['server']['game']       = "Sauerbraten";
            $server['server']['players']    = ord(self::CutByte($buffer, 1));
            $info_returned             = ord(self::CutByte($buffer, 1)); // CODED FOR 5
            $server['convars']['netcode']    = ord(self::CutByte($buffer, 1));
            $server['convars']['version']    = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['convars']['gamemode']   = ord(self::CutByte($buffer, 1));
            $server['convars']['timeleft']   = self::Time(ord(self::CutByte($buffer, 1)) * 60);
            $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
            $server['server']['password']   = ord(self::CutByte($buffer, 1)); // BIT FIELD
            $server['server']['password']   = $server['server']['password'] & 4 ? "1" : "0";
            $server['server']['map']        = self::CutString($buffer);
            $server['server']['name']       = self::CutString($buffer);
        }

        elseif ($buffer[1] == "\x06") // BLOODFRONTIER
        {
            $server['server']['game']       = "Blood Frontier";
            $server['server']['players']    = ord(self::CutByte($buffer, 1));
            $info_returned             = ord(self::CutByte($buffer, 1)); // CODED FOR 6
            $server['convars']['netcode']    = ord(self::CutByte($buffer, 1));
            $server['convars']['version']    = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['convars']['gamemode']   = ord(self::CutByte($buffer, 1));
            $server['convars']['mutators']   = ord(self::CutByte($buffer, 1));
            $server['convars']['timeleft']   = self::Time(ord(self::CutByte($buffer, 1)) * 60);
            $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
            $server['server']['password']   = ord(self::CutByte($buffer, 1)); // BIT FIELD
            $server['server']['password']   = $server['server']['password'] & 4 ? "1" : "0";
            $server['server']['map']        = self::CutString($buffer);
            $server['server']['name']       = self::CutString($buffer);
        }

        else // UNKNOWN
        {
            return FALSE;
        }

        //---------------------------------------------------------+
        //  CRAZY PROTOCOL - REQUESTS MUST BE MADE FOR EACH PLAYER
        //  BOTS ARE RETURNED BUT NOT INCLUDED IN THE PLAYER TOTAL
        //  AND THERE CAN BE ID GAPS BETWEEN THE PLAYERS RETURNED

        if ($lgsl_need['p'] && $server['server']['players'])
        {
            $player_key = 0;

            for ($player_id=0; $player_id<32; $player_id++)
            {
                fwrite($lgsl_fp, "\x00\x01".chr($player_id));

                // READ PACKET
                $buffer = fread($lgsl_fp, 4096);
                if (!$buffer) { break; }

                // CHECK IF PLAYER ID IS ACTIVE
                if ($buffer[5] != "\x00")
                {
                    if ($player_key < $server['server']['players']) { continue; }
                    break;
                }

                // IF PREVIEW PACKET GET THE FULL PACKET THAT FOLLOWS
                if (strlen($buffer) < 15)
                {
                    $buffer = fread($lgsl_fp, 4096);
                    if (!$buffer) { break; }
                }

                // REMOVE HEADER
                $buffer = substr($buffer, 7);

                // WE CAN NOW GET THE PLAYER DETAILS
                if ($server['server']['game'] == "Blood Frontier")
                {
                    $server['players'][$player_key]['pid']       = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['ping']      = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['ping']      = $server['players'][$player_key]['ping'] == 128 ? self::UnPack(self::CutByte($buffer, 2), "S") : $server['players'][$player_key]['ping'];
                    $server['players'][$player_key]['name']      = self::CutString($buffer);
                    $server['players'][$player_key]['team']      = self::CutString($buffer);
                    $server['players'][$player_key]['score']     = self::UnPack(self::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['damage']    = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['deaths']    = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['teamkills'] = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['accuracy']  = self::UnPack(self::CutByte($buffer, 1), "C")."%";
                    $server['players'][$player_key]['health']    = self::UnPack(self::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['spree']     = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['weapon']    = self::UnPack(self::CutByte($buffer, 1), "C");
                }else{
                    $server['players'][$player_key]['pid']       = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['name']      = self::CutString($buffer);
                    $server['players'][$player_key]['team']      = self::CutString($buffer);
                    $server['players'][$player_key]['score']     = self::UnPack(self::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['deaths']    = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['teamkills'] = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['accuracy']  = self::UnPack(self::CutByte($buffer, 1), "C")."%";
                    $server['players'][$player_key]['health']    = self::UnPack(self::CutByte($buffer, 1), "c");
                    $server['players'][$player_key]['armour']    = self::UnPack(self::CutByte($buffer, 1), "C");
                    $server['players'][$player_key]['weapon']    = self::UnPack(self::CutByte($buffer, 1), "C");
                }
                $player_key++;
            }
        }

        //----------------------------------------------------------
        return TRUE;
    }

    public static function Query25(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://www.tribesnext.com
        fwrite($lgsl_fp,"\x12\x02\x21\x21\x21\x21");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 6); // REMOVE HEADER

        //---------------------------------------------------------+
        $server['server']['game']       = self::CutPascal($buffer);
        $server['convars']['gamemode']   = self::CutPascal($buffer);
        $server['server']['map']        = self::CutPascal($buffer);
        $server['convars']['bit_flags']  = ord(self::CutByte($buffer, 1));
        $server['server']['players']    = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
        $server['convars']['bots']       = ord(self::CutByte($buffer, 1));
        $server['convars']['cpu']        = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['convars']['motd']       = self::CutPascal($buffer);
        $server['convars']['unknown']    = self::UnPack(self::CutByte($buffer, 2), "S");

        $server['convars']['dedicated']  = ($server['convars']['bit_flags'] & 1)  ? "1" : "0";
        $server['server']['password']   = ($server['convars']['bit_flags'] & 2)  ? "1" : "0";
        $server['convars']['os']         = ($server['convars']['bit_flags'] & 4)  ? "L" : "W";
        $server['convars']['tournament'] = ($server['convars']['bit_flags'] & 8)  ? "1" : "0";
        $server['convars']['no_alias']   = ($server['convars']['bit_flags'] & 16) ? "1" : "0";

        unset($server['convars']['bit_flags']);

        //---------------------------------------------------------+
        $team_total = self::CutString($buffer, 0, "\x0A");

        for ($i=0; $i<$team_total; $i++)
        {
            $server['teams'][$i]['name']  = self::CutString($buffer, 0, "\x09");
            $server['teams'][$i]['score'] = self::CutString($buffer, 0, "\x0A");
        }

        $player_total = self::CutString($buffer, 0, "\x0A");

        for ($i=0; $i<$player_total; $i++)
        {
            self::CutByte($buffer, 1); // ? 16
            self::CutByte($buffer, 1); // ? 8 or 14 = BOT / 12 = ALIAS / 11 = NORMAL
            if (ord($buffer[0]) < 32) { 
                self::CutByte($buffer, 1); 
            } // ? 8 PREFIXES SOME NAMES

            $server['players'][$i]['name']  = self::CutString($buffer, 0, "\x11");
            self::CutString($buffer, 0, "\x09"); // ALWAYS BLANK
            $server['players'][$i]['team']  = self::CutString($buffer, 0, "\x09");
            $server['players'][$i]['score'] = self::CutString($buffer, 0, "\x0A");
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query26(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://hazardaaclan.com/wiki/doku.php?id=aa3_server_query
        //  http://aluigi.altervista.org/papers.htm#aa3authdec
        if (!function_exists('gzuncompress')) { return FALSE; } // REQUIRES http://www.php.net/zlib

        $packet = "\x0A\x00playerName\x06\x06\x00query\x00";
        self::GSEncrypt($server['basic']['type'], $packet, TRUE);
        fwrite($lgsl_fp, "\x4A\x35\xFF\xFF\x02\x00\x02\x00\x01\x00{$packet}");

        $buffer = array();
        $packet_count = 0;
        $packet_total = 4;

        do
        {
            $packet_count ++;
            $packet = fread($lgsl_fp, 4096);

            if (!isset($packet[5])) { return FALSE; }

            if ($packet[5] == "\x03") // MULTI PACKET
            {
                $packet_order = ord($packet[10]);
                $packet_total = ord($packet[12]);
                $packet = substr($packet, 14);
                $buffer[$packet_order] = $packet;
            }
            elseif ($packet[5] == "\x02") // SINGLE PACKET
            {
                $buffer[0] = substr($packet, 10);
                break;
            }else{
                return FALSE;
            }
        }
        while ($packet_count < $packet_total);

        //---------------------------------------------------------+
        ksort($buffer);

        $buffer = implode("", $buffer);

        self::GSEncrypt($server['basic']['type'], $buffer, FALSE);

        $buffer = @gzuncompress($buffer);

        if (!$buffer) { return FALSE; }

        //----------------------------------------------------------
        $raw = [];

        do
        {
            $raw_name = self::CutPascal($buffer, 2);
            $raw_type = self::CutByte($buffer, 1);

            switch ($raw_type)
            {
                // SINGLE INTEGER
                case "\x02":
                    $raw[$raw_name] = self::UnPack(self::CutByte($buffer, 4), "i");
                    break;

                // ARRAY OF STRINGS
                case "\x07":
                    $raw_total = self::UnPack(self::CutByte($buffer, 2), "S");

                    for ($i = 0; $i < $raw_total;$i++)
                    {
                        $raw_value = self::CutPascal($buffer, 2);
                        if (substr($raw_value, -1) == "\x00") { $raw_value = substr($raw_value, 0, -1); } // SOME STRINGS HAVE NULLS
                        $raw[$raw_name][] = $raw_value;
                    }
                    break;
          
                // 01=BOOLEAN|03=SHORT INTEGER|04=DOUBLE
                // 05=CHAR|06=STRING|09=ARRAY OF INTEGERS
                default:
                    break 2;
            }
        }
        while ($buffer);

        if (!isset($raw['attributeNames'])  || !is_array($raw['attributeNames']))  { return FALSE; }
        if (!isset($raw['attributeValues']) || !is_array($raw['attributeValues'])) { return FALSE; }

        //---------------------------------------------------------+
        foreach ($raw['attributeNames'] as $key => $field)
        {
            $field = strtolower($field);

            preg_match("/^player(.*)(\d+)$/U", $field, $match);

            if (isset($match[1]))
            {
                // IGNORE POINTLESS PLAYER FIELDS
                if ($match[1] == "mapname")         { continue; }
                if ($match[1] == "version")         { continue; }
                if ($match[1] == "servermapname")   { continue; }
                if ($match[1] == "serveripaddress") { continue; }

                // LGSL STANDARD ( SWAP NAME AS ITS ACTUALLY THE ACCOUNT NAME )
                if ($match[1] == "name")        { $match[1] = "username"; }
                if ($match[1] == "soldiername") { $match[1] = "name"; }

                $server['players'][$match[2]][$match[1]] = $raw['attributeValues'][$key];
            }else{
                if (substr($field, 0, 6) == "server") { $field = substr($field, 6); }
                $server['convars'][$field] = $raw['attributeValues'][$key];
            }
        }

        $lgsl_conversion = [ "gamename" => "name", "mapname" => "map", "playercount" => "players", "maxplayers" => "playersmax", "flagpassword" => "password" ];
        foreach ($lgsl_conversion as $e => $s) { 
            $server['server'][$s] = $server['convars'][$e];
             unset($server['ea'][$e]); 
        } // LGSL STANDARD
        $server['server']['playersmax'] += intval($server['convars']['maxspectators']); // ADD SPECTATOR SLOTS TO MAX PLAYERS

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query27(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://skulltag.com/wiki/Launcher_protocol
        //  http://en.wikipedia.org/wiki/Huffman_coding
        //  http://www.greycube.com/help/lgsl_other/skulltag_huffman.txt

        $huffman_table = [
            "010","110111","101110010","00100","10011011","00101","100110101","100001100","100101100","001110100","011001001","11001000","101100001","100100111","001111111","101110000","101110001","001111011",
            "11011011","101111100","100001110","110011111","101100000","001111100","0011000","001111000","10001100","100101011","100010000","101111011","100100110","100110010","0111","1111000","00010001",
            "00011010","00011000","00010101","00010000","00110111","00110110","00011100","01100101","1101001","00110100","10110011","10110100","1111011","10111100","10111010","11001001","11010101","11111110",
            "11111100","10001110","11110011","001101011","10000000","000101101","11010000","001110111","100000010","11100111","001100101","11100110","00111001","10001010","00010011","001110110","10001111",
            "000111110","11000111","11010111","11100011","000101000","001100111","11010100","000111010","10010111","100000111","000100100","001110001","11111010","100100011","11110100","000110111","001111010",
            "100010011","100110001","11101","110001011","101110110","101111110","100100010","100101001","01101","100100100","101100101","110100011","100111100","110110001","100010010","101101101","011001110",
            "011001101","11111101","100010001","100110000","110001000","110110000","0001001010","110001010","101101010","000110110","10110001","110001101","110101101","110001100","000111111","110010101",
            "111000100","11011001","110010110","110011110","000101100","001110101","101111101","1001110","0000","1000010","0001110111","0001100101","1010","11001110","0110011000","0110011001","1000011011",
            "1001100110","0011110011","0011001100","11111001","0110010001","0001010011","1000011010","0001001011","1001101001","101110111","1000001101","1000011111","1100000101","0110000000","1011011101",
            "11110101","0001111011","1101000101","1101000100","1001000010","0110000001","1011001000","100101010","1100110","111100101","1100101111","0001100111","1110000","0011111100","11111011","1100101110",
            "101110011","1001100111","1001111111","1011011100","111110001","101111010","1011010110","1001010000","1001000011","1001111110","0011111011","1000011110","1000101100","01100001","00010111",
            "1000000110","110000101","0001111010","0011001101","0110011110","110010100","111000101","0011001001","0011110010","110000001","101101111","0011111101","110110100","11100100","1011001001",
            "0011001000","0001110110","111111111","110101100","111111110","1000001011","1001011010","110000000","000111100","111110000","011000001","1001111010","111001011","011000111","1001000001",
            "1001111100","1000110111","1001101000","0110001100","1001111011","0011010101","1000101101","0011111010","0001100100","01100010","110000100","101101100","0110011111","1001011011","1000101110",
            "111100100","1000110110","0110001101","1001000000","110110101","1000001000","1000001001","1100000100","110001001","1000000111","1001111101","111001010","0011010100","1000101111","101111111",
            "0001010010","0011100000","0001100110","1000001010","0011100001","11000011","1011010111","1000001100","100011010","0110010000","100100101","1001010001","110000011"
        ];

        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\x02\xB8\x49\x1A\x9C\x8B\xB5\x3F\x1E\x8F\x07");

        $packet = fread($lgsl_fp, 4096);

        if (!$packet) { return FALSE; }

        $packet = substr($packet, 1); // REMOVE HEADER

        //---------------------------------------------------------+
        $packet_binary = "";

        for ($i = 0; $i < strlen($packet); $i++)
        {
            $packet_binary .= strrev(sprintf("%08b", ord($packet[$i])));
        }

        $buffer = "";

        while ($packet_binary)
        {
            foreach ($huffman_table as $ascii => $huffman_binary)
            {
                $huffman_length = strlen($huffman_binary);
                if (substr($packet_binary, 0, $huffman_length) === $huffman_binary)
                {
                    $packet_binary = substr($packet_binary, $huffman_length);
                    $buffer .= chr($ascii);
                    continue 2;
                }
            }
            break;
        }

        //---------------------------------------------------------+
        $response_status        = self::UnPack(self::CutByte($buffer, 4), "l"); if ($response_status != "5660023") { return FALSE; }
        $response_time          = self::UnPack(self::CutByte($buffer, 4), "l");
        $server['convars']['version'] = self::CutString($buffer);
        $response_flag          = self::UnPack(self::CutByte($buffer, 4), "l");

        //---------------------------------------------------------+
        if ($response_flag & 0x00000001) { $server['server']['name']       = self::CutString($buffer); }
        if ($response_flag & 0x00000002) { $server['convars']['wadurl']     = self::CutString($buffer); }
        if ($response_flag & 0x00000004) { $server['convars']['email']      = self::CutString($buffer); }
        if ($response_flag & 0x00000008) { $server['server']['map']        = self::CutString($buffer); }
        if ($response_flag & 0x00000010) { $server['server']['playersmax'] = ord(self::CutByte($buffer, 1)); }
        if ($response_flag & 0x00000020) { $server['convars']['playersmax'] = ord(self::CutByte($buffer, 1)); }
        
        if ($response_flag & 0x00000040){
            $pwad_total = ord(self::CutByte($buffer, 1));
            $server['convars']['pwads'] = "";
            for ($i = 0; $i < $pwad_total; $i++)
            {
                $server['convars']['pwads'] .= self::CutString($buffer)." ";
            }
        }

        if ($response_flag & 0x00000080){
            $server['convars']['gametype'] = ord(self::CutByte($buffer, 1));
            $server['convars']['instagib'] = ord(self::CutByte($buffer, 1));
            $server['convars']['buckshot'] = ord(self::CutByte($buffer, 1));
        }
        
        if ($response_flag & 0x00000100) { $server['server']['game']         = self::CutString($buffer); }
        if ($response_flag & 0x00000200) { $server['convars']['iwad']         = self::CutString($buffer); }
        if ($response_flag & 0x00000400) { $server['server']['password']     = ord(self::CutByte($buffer, 1)); }
        if ($response_flag & 0x00000800) { $server['convars']['playpassword'] = ord(self::CutByte($buffer, 1)); }
        if ($response_flag & 0x00001000) { $server['convars']['skill']        = ord(self::CutByte($buffer, 1)) + 1; }
        if ($response_flag & 0x00002000) { $server['convars']['botskill']     = ord(self::CutByte($buffer, 1)) + 1; }
        
        if ($response_flag & 0x00004000){
            $server['convars']['dmflags']     = self::UnPack(self::CutByte($buffer, 4), "l");
            $server['convars']['dmflags2']    = self::UnPack(self::CutByte($buffer, 4), "l");
            $server['convars']['compatflags'] = self::UnPack(self::CutByte($buffer, 4), "l");
        }
        
        if ($response_flag & 0x00010000){
            $server['convars']['fraglimit'] = self::UnPack(self::CutByte($buffer, 2), "s");
            $timelimit                = self::UnPack(self::CutByte($buffer, 2), "S");
            if ($timelimit){
                $server['convars']['timeleft'] = self::Time(self::UnPack(self::CutByte($buffer, 2), "S") * 60);
            }
            $server['convars']['timelimit']  = self::Time($timelimit * 60);
            $server['convars']['duellimit']  = self::UnPack(self::CutByte($buffer, 2), "s");
            $server['convars']['pointlimit'] = self::UnPack(self::CutByte($buffer, 2), "s");
            $server['convars']['winlimit']   = self::UnPack(self::CutByte($buffer, 2), "s");
        }

        if ($response_flag & 0x00020000) { $server['convars']['teamdamage'] = self::UnPack(self::CutByte($buffer, 4), "f"); }
        
        if ($response_flag & 0x00040000){
            $server['teams'][0]['score'] = self::UnPack(self::CutByte($buffer, 2), "s");
            $server['teams'][1]['score'] = self::UnPack(self::CutByte($buffer, 2), "s");
        }
        if ($response_flag & 0x00080000) { $server['server']['players'] = ord(self::CutByte($buffer, 1)); }
        
        if ($response_flag & 0x00100000){
            for ($i = 0; $i < $server['server']['players']; $i++){
                $server['players'][$i]['name']      = self::ParserColor(self::CutString($buffer), $server['basic']['type']);
                $server['players'][$i]['score']     = self::UnPack(self::CutByte($buffer, 2), "s");
                $server['players'][$i]['ping']      = self::UnPack(self::CutByte($buffer, 2), "S");
                $server['players'][$i]['spectator'] = ord(self::CutByte($buffer, 1));
                $server['players'][$i]['bot']       = ord(self::CutByte($buffer, 1));

                if (($response_flag & 0x00200000) && ($response_flag & 0x00400000)){
                    $server['players'][$i]['team'] = ord(self::CutByte($buffer, 1));
                }
                $server['players'][$i]['time'] = self::Time(ord(self::CutByte($buffer, 1)) * 60);
            }
        }

        if ($response_flag & 0x00200000){
            $team_total = ord(self::CutByte($buffer, 1));

            if ($response_flag & 0x00400000){
                for ($i = 0; $i < $team_total; $i++) { 
                    $server['teams'][$i]['name'] = self::CutString($buffer); 
                }
            }

            if ($response_flag & 0x00800000){
                for ($i = 0; $i < $team_total; $i++) { 
                    $server['teams'][$i]['color'] = self::UnPack(self::CutByte($buffer, 4), "l"); 
                }
            }

            if ($response_flag & 0x01000000){
                for ($i = 0; $i < $team_total; $i++) { 
                    $server['teams'][$i]['score'] = self::UnPack(self::CutByte($buffer, 2), "s"); 
                }
            }

            for ($i=0; $i<$server['server']['players']; $i++){
                if ($server['teams'][$i]['name']) { 
                    $server['players'][$i]['team'] = $server['teams'][$i]['name']; 
                }
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query28(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://doomutils.ucoz.com
        fwrite($lgsl_fp, "\xA3\xDB\x0B\x00"."\xFC\xFD\xFE\xFF"."\x01\x00\x00\x00"."\x21\x21\x21\x21");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        //---------------------------------------------------------+
        $response_status  = self::UnPack(self::CutByte($buffer, 4), "l"); if ($response_status != "5560022") { return FALSE; }
        $response_version = self::UnPack(self::CutByte($buffer, 4), "l");
        $response_time    = self::UnPack(self::CutByte($buffer, 4), "l");

        $server['convars']['invited']    = ord(self::CutByte($buffer, 1));
        $server['convars']['version']    = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['server']['name']       = self::CutString($buffer);
        $server['server']['players']    = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
        $server['server']['map']        = self::CutString($buffer);

        $pwad_total = ord(self::CutByte($buffer, 1));

        for ($i=0; $i<$pwad_total; $i++)
        {
            $server['convars']['pwads'] .= self::CutString($buffer)." ";
            $pwad_optional         = ord(self::CutByte($buffer, 1));
            $pwad_alternative      = self::CutString($buffer);
        }

        $server['convars']['gametype']   = ord(self::CutByte($buffer, 1));
        $server['server']['game']       = self::CutString($buffer);
        $server['convars']['iwad']       = self::CutString($buffer);
        $iwad_altenative           = self::CutString($buffer);
        $server['convars']['skill']      = ord(self::CutByte($buffer, 1)) + 1;
        $server['convars']['wadurl']     = self::CutString($buffer);
        $server['convars']['email']      = self::CutString($buffer);
        $server['convars']['dmflags']    = self::UnPack(self::CutByte($buffer, 4), "l");
        $server['convars']['dmflags2']   = self::UnPack(self::CutByte($buffer, 4), "l");
        $server['server']['password']   = ord(self::CutByte($buffer, 1));
        $server['convars']['inviteonly'] = ord(self::CutByte($buffer, 1));
        $server['convars']['players']    = ord(self::CutByte($buffer, 1));
        $server['convars']['playersmax'] = ord(self::CutByte($buffer, 1));
        $server['convars']['timelimit']  = self::Time(self::UnPack(self::CutByte($buffer, 2), "S") * 60);
        $server['convars']['timeleft']   = self::Time(self::UnPack(self::CutByte($buffer, 2), "S") * 60);
        $server['convars']['fraglimit']  = self::UnPack(self::CutByte($buffer, 2), "s");
        $server['convars']['gravity']    = self::UnPack(self::CutByte($buffer, 4), "f");
        $server['convars']['aircontrol'] = self::UnPack(self::CutByte($buffer, 4), "f");
        $server['convars']['playersmin'] = ord(self::CutByte($buffer, 1));
        $server['convars']['removebots'] = ord(self::CutByte($buffer, 1));
        $server['convars']['voting']     = ord(self::CutByte($buffer, 1));
        $server['convars']['serverinfo'] = self::CutString($buffer);
        $server['convars']['startup']    = self::UnPack(self::CutByte($buffer, 4), "l");

        for ($i = 0; $i < $server['server']['players']; $i++)
        {
            $server['players'][$i]['name']      = self::CutString($buffer);
            $server['players'][$i]['score']     = self::UnPack(self::CutByte($buffer, 2), "s");
            $server['players'][$i]['death']     = self::UnPack(self::CutByte($buffer, 2), "s");
            $server['players'][$i]['ping']      = self::UnPack(self::CutByte($buffer, 2), "S");
            $server['players'][$i]['time']      = self::Time(self::UnPack(self::CutByte($buffer, 2), "S") * 60);
            $server['players'][$i]['bot']       = ord(self::CutByte($buffer, 1));
            $server['players'][$i]['spectator'] = ord(self::CutByte($buffer, 1));
            $server['players'][$i]['team']      = ord(self::CutByte($buffer, 1));
            $server['players'][$i]['country']   = self::CutByte($buffer, 2);
        }

        $team_total                = ord(self::CutByte($buffer, 1));
        $server['convars']['pointlimit'] = self::UnPack(self::CutByte($buffer, 2), "s");
        $server['convars']['teamdamage'] = self::UnPack(self::CutByte($buffer, 4), "f");

        for ($i = 0; $i < $team_total; $i++) // RETURNS 4 TEAMS BUT IGNORE THOSE NOT IN USE
        {
            $server['teams']['team'][$i]['name']  = self::CutString($buffer);
            $server['teams']['team'][$i]['color'] = self::UnPack(self::CutByte($buffer, 4), "l");
            $server['teams']['team'][$i]['score'] = self::UnPack(self::CutByte($buffer, 2), "s");
        }

        for ($i = 0; $i < $server['server']['players']; $i++)
        {
            if ($server['teams'][$i]['name']) { 
                $server['players'][$i]['team'] = $server['teams'][$i]['name']; 
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query29(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://www.cs2d.com/servers.php
        if ($lgsl_need['s'] || $lgsl_need['c']){
            $lgsl_need['s'] = FALSE;
            $lgsl_need['c'] = FALSE;

            fwrite($lgsl_fp, "\x01\x00\x03\x10\x21\xFB\x01\x75\x00");

            $buffer = fread($lgsl_fp, 4096);

            if (!$buffer) { return FALSE; }

            $buffer = substr($buffer, 4); // REMOVE HEADER

            $server['convars']['bit_flags']  = ord(self::CutByte($buffer, 1)) - 48;
            $server['server']['name']       = self::CutPascal($buffer);
            $server['server']['map']        = self::CutPascal($buffer);
            $server['server']['players']    = ord(self::CutByte($buffer, 1));
            $server['server']['playersmax'] = ord(self::CutByte($buffer, 1));
            $server['convars']['gamemode']   = ord(self::CutByte($buffer, 1));
            $server['convars']['bots']       = ord(self::CutByte($buffer, 1));

            $server['server']['password']        = ($server['convars']['bit_flags'] & 1) ? "1" : "0";
            $server['convars']['registered_only'] = ($server['convars']['bit_flags'] & 2) ? "1" : "0";
            $server['convars']['fog_of_war']      = ($server['convars']['bit_flags'] & 4) ? "1" : "0";
            $server['convars']['friendlyfire']    = ($server['convars']['bit_flags'] & 8) ? "1" : "0";
        }

        if ($lgsl_need['p'])
        {
            $lgsl_need['p'] = FALSE;

            fwrite($lgsl_fp, "\x01\x00\xFB\x05");

            $buffer = fread($lgsl_fp, 4096);

            if (!$buffer) { return FALSE; }

            $buffer = substr($buffer, 4); // REMOVE HEADER

            $player_total = ord(self::CutByte($buffer, 1));

            for ($i = 0; $i < $player_total; $i++)
            {
                $server['players'][$i]['pid']    = ord(self::CutByte($buffer, 1));
                $server['players'][$i]['name']   = self::CutPascal($buffer);
                $server['players'][$i]['team']   = ord(self::CutByte($buffer, 1));
                $server['players'][$i]['score']  = self::UnPack(self::CutByte($buffer, 4), "l");
                $server['players'][$i]['deaths'] = self::UnPack(self::CutByte($buffer, 4), "l");
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query30(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://blogs.battlefield.ea.com/battlefield_bad_company/archive/2010/02/05/remote-administration-interface-for-bfbc2-pc.aspx
        //  THIS USES TCP COMMUNICATION
        if ($lgsl_need['s'] || $lgsl_need['c']){
            fwrite($lgsl_fp, "\x00\x00\x00\x00\x1B\x00\x00\x00\x01\x00\x00\x00\x0A\x00\x00\x00serverInfo\x00");
        }
        elseif ($lgsl_need['p']){
          fwrite($lgsl_fp, "\x00\x00\x00\x00\x24\x00\x00\x00\x02\x00\x00\x00\x0B\x00\x00\x00listPlayers\x00\x03\x00\x00\x00all\x00");
        }

        //---------------------------------------------------------+
        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $length = self::UnPack(substr($buffer, 4, 4), "L");

        while (strlen($buffer) < $length){
            $packet = fread($lgsl_fp, 4096);
            if ($packet) { $buffer .= $packet; } else { break; }
        }

        //---------------------------------------------------------+
        $buffer = substr($buffer, 12); // REMOVE HEADER

        $response_type = self::CutPascal($buffer, 4, 0, 1);

        if ($response_type != "OK") { return FALSE; }

        //---------------------------------------------------------+

        if ($lgsl_need['s'] || $lgsl_need['c'])
        {
            $lgsl_need['s'] = FALSE;
            $lgsl_need['c'] = FALSE;

            $server['server']['name']            = self::CutPascal($buffer, 4, 0, 1);
            $server['server']['players']         = self::CutPascal($buffer, 4, 0, 1);
            $server['server']['playersmax']      = self::CutPascal($buffer, 4, 0, 1);
            $server['convars']['gamemode']        = self::CutPascal($buffer, 4, 0, 1);
            $server['server']['map']             = self::CutPascal($buffer, 4, 0, 1);
            $server['convars']['score_attackers'] = self::CutPascal($buffer, 4, 0, 1);
            $server['convars']['score_defenders'] = self::CutPascal($buffer, 4, 0, 1);

            // CONVERT MAP NUMBER TO DESCRIPTIVE NAME
            $server['convars']['level'] = $server['server']['map'];
            $map_check = strtolower($server['server']['map']);

            if     (strpos($map_check, "mp_001") !== FALSE) { $server['server']['map'] = "Panama Canal";   }
            elseif (strpos($map_check, "mp_002") !== FALSE) { $server['server']['map'] = "Valparaiso";     }
            elseif (strpos($map_check, "mp_003") !== FALSE) { $server['server']['map'] = "Laguna Alta";    }
            elseif (strpos($map_check, "mp_004") !== FALSE) { $server['server']['map'] = "Isla Inocentes"; }
            elseif (strpos($map_check, "mp_005") !== FALSE) { $server['server']['map'] = "Atacama Desert"; }
            elseif (strpos($map_check, "mp_006") !== FALSE) { $server['server']['map'] = "Arica Harbor";   }
            elseif (strpos($map_check, "mp_007") !== FALSE) { $server['server']['map'] = "White Pass";     }
            elseif (strpos($map_check, "mp_008") !== FALSE) { $server['server']['map'] = "Nelson Bay";     }
            elseif (strpos($map_check, "mp_009") !== FALSE) { $server['server']['map'] = "Laguna Presa";   }
            elseif (strpos($map_check, "mp_012") !== FALSE) { $server['server']['map'] = "Port Valdez";    }
        }

        //---------------------------------------------------------+
        elseif ($lgsl_need['p'])
        {
            $lgsl_need['p'] = FALSE;

            $field_total = self::CutPascal($buffer, 4, 0, 1);
            $field_list  = array();

            for ($i = 0; $i < $field_total; $i++)
            {
              $field_list[] = strtolower(self::CutPascal($buffer, 4, 0, 1));
            }

            $player_squad = array("","Alpha","Bravo","Charlie","Delta","Echo","Foxtrot","Golf","Hotel");
            $player_team  = array("","Attackers","Defenders");
            $player_total = self::CutPascal($buffer, 4, 0, 1);

            for ($i = 0; $i < $player_total; $i++){
                foreach ($field_list as $field){
                    $value = self::CutPascal($buffer, 4, 0, 1);
                    switch ($field)
                    {
                        case "clantag": $server['players'][$i]['name']  = $value;                                                                             break;
                        case "name":    $server['players'][$i]['name']  = empty($server['players'][$i]['name']) ? $value : "[{$server['players'][$i]['name']}] {$value}"; break;
                        case "teamid":  $server['players'][$i]['team']  = isset($player_team[$value]) ? $player_team[$value] : $value;                        break;
                        case "squadid": $server['players'][$i]['squad'] = isset($player_squad[$value]) ? $player_squad[$value] : $value;                      break;
                        default:        $server['players'][$i][$field]  = $value;                                                                             break;
                    }
                }
            }
        }

        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query31(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  AVP 2010 ONLY ROUGHLY FOLLOWS THE SOURCE QUERY FORMAT
        //  SERVER RULES ARE ON THE END OF THE INFO RESPONSE
        fwrite($lgsl_fp, "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 5); // REMOVE HEADER

        $server['convars']['netcode']     = ord(self::CutByte($buffer, 1));
        $server['server']['name']        = self::CutString($buffer);
        $server['server']['map']         = self::CutString($buffer);
        $server['server']['game']        = self::CutString($buffer);
        $server['convars']['description'] = self::CutString($buffer);
        $server['convars']['appid']       = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['server']['players']     = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax']  = ord(self::CutByte($buffer, 1));
        $server['convars']['bots']        = ord(self::CutByte($buffer, 1));
        $server['convars']['dedicated']   = self::CutByte($buffer, 1);
        $server['convars']['os']          = self::CutByte($buffer, 1);
        $server['server']['password']    = ord(self::CutByte($buffer, 1));
        $server['convars']['anticheat']   = ord(self::CutByte($buffer, 1));
        $server['convars']['version']     = self::CutString($buffer);

        $buffer = substr($buffer, 1);
        $server['convars']['hostport']     = self::UnPack(self::CutByte($buffer, 2), "S");
        $server['convars']['friendlyfire'] = $buffer[124];

        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query32(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\x05\x00\x00\x01\x0A");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 5); // REMOVE HEADER

        $server['server']['name']       = self::CutPascal($buffer);
        $server['server']['map']        = self::CutPascal($buffer);
        $server['server']['players']    = ord(self::CutByte($buffer, 1));
        $server['server']['playersmax'] = 0; // HELD ON MASTER

        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }

    public static function Query33(&$server, &$lgsl_need, &$lgsl_fp)
    {
        if (strpos(fread($lgsl_fp, 4096), 'TS') === FALSE) {
            if (strpos(fread($lgsl_fp, 4096), 'TeaSpeak') === FALSE) {
                return FALSE;
            }
        }

        $ver = $server['basic']['type'] == 'ts' ? 0 : 1;
        $param[0] = [ 'sel ', 'si',"\r\n", 'pl' ];
        $param[1] = [ 'use port=', 'serverinfo', ' ','clientlist -country', 'channellist -topic' ];

        if ($ver) { 
            fread($lgsl_fp, 4096); 
        }

        fwrite($lgsl_fp, $param[$ver][0].$server['basic']['c_port']."\n"); // select virtualserver
        if (strtoupper(substr(fread($lgsl_fp, 4096), -4, -2)) != 'OK') { 
            return FALSE; 
        }

        fwrite($lgsl_fp, $param[$ver][1]."\n"); // request serverinfo
        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer || substr($buffer, 0, 5) == 'error') { 
            return FALSE; 
        }

        while (strtoupper(substr($buffer, -4, -2)) != 'OK') {
            $part = fread($lgsl_fp, 4096);
            if ($part && substr($part, 0, 5) != 'error') { $buffer .= $part; } else { break; }
        }

        while ($val = self::CutString($buffer, 7+7*$ver, $param[$ver][2])) {
            $key = self::CutString($val, 0, '='); $items[$key] = $val;
        }

        if (!isset($items['name'])) { 
            return FALSE; 
        }

        $server['server']['name']       = $ver ? self::UnEscape($items['name']) : $items['name'];
        $server['server']['map']        = $server['basic']['type'];
        $server['server']['players']    = intval($items[$ver ? 'clientsonline' : 'currentusers']) - $ver;
        $server['server']['playersmax'] = intval($items[$ver ? 'maxclients' : 'maxusers']);
        $server['server']['password']   = intval($items[$ver ? 'flag_password' : 'password']);
        $server['convars']['platform']   = $items['platform'];
        $server['convars']['motd']       = $ver ? self::UnEscape($items['welcomemessage']) : $items['welcomemessage'];
        $server['convars']['uptime']     = self::Time($items['uptime']);
        $server['convars']['channels']   = $items[$ver ? 'channelsonline' : 'currentchannels'];
    
        if ($ver) { $server['convars']['version'] = self::UnEscape($items['version']); }
        if (!$lgsl_need['p'] || $server['server']['players'] < 1) { return TRUE; }

        fwrite($lgsl_fp, $param[$ver][3]."\n"); // request playerlist
        $buffer = fread($lgsl_fp, 4096);

        while (substr($buffer, -4) != "OK\r\n" && substr($buffer, -2) != "\n\r") {
            $part = fread($lgsl_fp, 4096);
            if ($part && substr($part, 0, 5) != 'error') { 
                $buffer .= $part; 
            } else { 
                break; 
            }
        }

        $i = 0;
        if ($ver) {
            while ($items = self::CutString($buffer, 0, '|')) {
                self::CutString($items, 0, 'e='); 
                $name = self::CutString($items, 0, ' ');

                if (substr($name, 0, 15) == 'Unknown\sfrom\s') { continue; }

                $server['players'][$i]['name'] = self::UnEscape($name); self::CutString($items, 0, 'ry');
                $server['players'][$i]['country'] = substr($items, 0, 1) == '=' ? substr($items, 1, 2) : ''; $i++;
            }
        }else {
            $buffer = substr($buffer, 89, -4);
            while ($items = self::CutString($buffer, 0, "\r\n")) {
                $items = explode("\t", $items);
                $server['players'][$i]['name'] = substr($items[14], 1, -1);
                $server['players'][$i]['ping'] = $items[7];
                $server['players'][$i]['time'] = self::Time($items[8]); $i++;
            }
        }

        if($ver){
            fwrite($lgsl_fp, $param[$ver][4]."\n"); // request channellist
            $buffer = fread($lgsl_fp, 4096);
            while (substr($buffer, -4) != "OK\r\n" && substr($buffer, -2) != "\n\r") {

                $part = fread($lgsl_fp, 4096);
                if ($part && substr($part, 0, 5) != 'error') {
                    $buffer .= $part; 
                } else { 
                    break; 
                }
            }
            while ($items = self::CutString($buffer, 0, '|')) {
                $id = self::CutString($items, 4, ' ');
                self::CutString($items, 0, 'e=');
                $name = self::CutString($items, 0, ' ');
                if(strpos($name, '*spacer') != FALSE) { continue; }
                $server['convars']['channel'.$id] = self::UnEscape($name);
            }
        }
        return TRUE;
    }

    public static function Query34(&$server, &$lgsl_need, &$lgsl_fp) // Rage:MP
    {
        if(!$lgsl_fp) return FALSE;

        $lgsl_need['c'] = FALSE;
        $lgsl_need['p'] = FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, 'https://cdn.rage.mp/master/');
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(isset($buffer[$server['basic']['ip'].':'.$server['basic']['c_port']])){
            $value = $buffer[$server['basic']['ip'].':'.$server['basic']['c_port']];
            $server['server']['name']       = $value['name'];
            $server['server']['map']        = "ragemp";
            $server['server']['players']    = $value['players'];
            $server['server']['playersmax'] = $value['maxplayers'];
            $server['convars']['url']        = $value['url'];
            $server['convars']['peak']       = $value['peak'];
            $server['convars']['gamemode']   = $value['gamemode'];
            $server['convars']['lang']       = $value['lang'];
            return TRUE;
        }
        return FALSE;
    }

    public static function Query35(&$server, &$lgsl_need, &$lgsl_fp) // FiveM / RedM
    {
        if(!$lgsl_fp) return FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/dynamic.json");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(!$buffer) return FALSE;

        $server['server']['name'] = self::ParserColor($buffer['hostname'], 'fivem');
        $server['server']['players'] = $buffer['clients'];
        $server['server']['playersmax'] = $buffer['sv_maxclients'];
        $server['server']['map'] = $buffer['mapname'];

        if ($server['server']['map'] == 'redm-map-one'){
            $server['server']['game'] = 'redm';
        }

        $server['convars']['gametype'] = $buffer['gametype'];
        $server['convars']['version'] = $buffer['iv'];

        if($lgsl_need['p']) {
            $lgsl_need['p'] = FALSE;

            curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/players.json");
            $buffer = curl_exec($lgsl_fp);
            $buffer = json_decode($buffer, true);

            foreach($buffer as $key => $value){
                $server['players'][$key]['name'] = $value['name'];
                $server['players'][$key]['ping'] = $value['ping'];
            }
        }
        return TRUE;
    }

    public static function Query36(&$server, &$lgsl_need, &$lgsl_fp) // Discord
    {
        if(!$lgsl_fp) return FALSE;

        $lgsl_need['s'] = FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, "https://discord.com/api/v9/invites/{$server['basic']['ip']}?with_counts=true");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(isset($buffer['message'])){
            $server['convars']['_error_fetching_info'] = $buffer['message'];
            return FALSE;
        }

        $server['server']['map'] = 'discord';
        $server['server']['name'] = $buffer['guild']['name'];
        $server['server']['players'] = $buffer['approximate_presence_count'];
        $server['server']['playersmax'] = $buffer['approximate_member_count'];
        $server['convars']['id'] = $buffer['guild']['id'];

        if($buffer['guild']['description'])
            $server['convars']['description'] = $buffer['guild']['description'];

        if($buffer['guild']['welcome_screen'] && $buffer['guild']['welcome_screen']['description'])
            $server['convars']['description'] = $buffer['guild']['welcome_screen']['description'];

        $server['convars']['features'] = implode(', ', $buffer['guild']['features']);
        $server['convars']['nsfw'] = (int) $buffer['guild']['nsfw'];
    
        if(isset($buffer['inviter']))
            $server['convars']['inviter'] = $buffer['inviter']['username'] . "#" . $buffer['inviter']['discriminator'];

        if($lgsl_need['p']) {
            $lgsl_need['p'] = FALSE;

            curl_setopt($lgsl_fp, CURLOPT_URL, "https://discordapp.com/api/guilds/{$server['convars']['id']}/widget.json");
            $buffer = curl_exec($lgsl_fp);
            $buffer = json_decode($buffer, true);

            if(isset($buffer['code']) and $buffer['code'] == 0){
                $server['convars']['_error_fetching_users'] = $buffer['message'];
            }

            if(isset($buffer['channels'])){
                foreach($buffer['channels'] as $key => $value){
                    $server['convars']['channel'.$key] = $value['name'];
                }
            }

            if(isset($buffer['members'])){
                foreach($buffer['members'] as $key => $value){
                    $server['players'][$key]['name'] = $value['username'];
                    $server['players'][$key]['status'] = $value['status'];
                    $server['players'][$key]['game'] = isset($value['game']) ? $value['game']['name'] : '--';
                }
            }
        }
        return TRUE;
    }

    public static function Query37(&$server, &$lgsl_need, &$lgsl_fp) // SCUM API
    {
        if (!$lgsl_fp) return FALSE;

        $lgsl_need['c'] = FALSE;
        $lgsl_need['p'] = FALSE;

        curl_setopt($lgsl_fp, CURLOPT_URL, "https://api.hellbz.de/scum/api.php?address={$server['basic']['ip']}&port={$server['basic']['c_port']}");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if(!$buffer['success']){ return FALSE; }

        $lgsl_need['s'] = FALSE;

        $server['server']['name']        = $buffer['data'][0]['name'];
        $server['server']['map']         = "SCUM";
        $server['server']['players']     = $buffer['data'][0]['players'];
        $server['server']['playersmax']  = $buffer['data'][0]['players_max'];
        $server['convars']['time']        = $buffer['data'][0]['time'];
        $server['convars']['version']     = $buffer['data'][0]['version'];

        return TRUE;
    }
  
    public static function Query38(&$server, &$lgsl_need, &$lgsl_fp) // Terraria
    {
        if (!$lgsl_fp) return FALSE;
    
        curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/v2/server/status?players=true");
        $buffer = curl_exec($lgsl_fp);
        $buffer = json_decode($buffer, true);

        if($buffer['status'] != '200'){
            $server['convars']['_error']    = $buffer['error'];
            return FALSE;
        }
    
        $server['server']['name']        = $buffer['name'];
        $server['server']['map']         = $buffer['world'];
        $server['server']['players']     = $buffer['playercount'];
        $server['server']['playersmax']  = $buffer['maxplayers'];
        $server['server']['password']    = $buffer['serverpassword'];
        $server['convars']['uptime']      = $buffer['uptime'];
        $server['convars']['version']     = $buffer['serverversion'];

        return TRUE;
    }

    public static function Query39(&$server, &$lgsl_need, &$lgsl_fp) // Mafia 2: MP
    {
        fwrite($lgsl_fp, "M2MPi");
        $buffer = fread($lgsl_fp, 1024);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 4); // REMOVE HEADER

        $server['server']['name']        = self::CutPascal($buffer, 1, -1);
        $server['server']['map']         = "Empire Bay";
        $server['server']['players']     = self::CutPascal($buffer, 1, -1);
        $server['server']['playersmax']  = self::CutPascal($buffer, 1, -1);
        $server['server']['password']    = 0;
        $server['convars']['gamemode']    = self::CutPascal($buffer, 1, -1);

        return TRUE;
    }

    public static function Query40(&$server, &$lgsl_need, &$lgsl_fp) // Farming Simulator
    {
        curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/index.html"); // CAN QUERY ONLY SERVER NAME AND ONLINE STATUS, MEH
        $buffer = curl_exec($lgsl_fp);

        if (!$buffer) { return FALSE; }
    
        preg_match('/<h2>Login to [\w\d\s\/\\&@"\'-]+<\/h2>/', $buffer, $name);

        $server['server']['name']        = substr($name[0], 12, strlen($name[0])-17);
        $server['server']['map']         = "Farm";

        return strpos($buffer, 'status-indicator online') !== FALSE;
    }

    public static function Query41(&$server, &$lgsl_need, &$lgsl_fp) // ONLY BEACON: World of Warcraft, Satisfactory
    {
        if (!$lgsl_fp) return FALSE;

        $lgsl_need['c'] = FALSE;
        $lgsl_need['p'] = FALSE;

        if ($server['basic']['type'] == 'wow') {
            $buffer = fread($lgsl_fp, 5);
            if ($buffer && $buffer == "\x00\x2A\xEC\x01\x01") {
                $server['server']['name']        = "World of Warcraft Server";
                $server['server']['map']         = "Twisting Nether";
                return TRUE;
            }
            return FALSE;
        }

        if ($server['basic']['type'] == 'sf') {
            fwrite($lgsl_fp, "\x00\x00\xd6\x9c\x28\x25\x00\x00\x00\x00");
            $buffer = fread($lgsl_fp, 128);
            if (!$buffer) {
                return FALSE;
            }
            self::CutByte($buffer, 11);
            $version = self::UnPack(self::CutByte($buffer, 1), "H*");
            $version = self::UnPack(self::CutByte($buffer, 1), "H*") . $version;
            $version = self::UnPack(self::CutByte($buffer, 1), "H*") . $version;
            $server['server']['name']        = "Satisfactory Dedicated Server";
            $server['server']['map']         = "World";
            $server['convars']['version']     = hexdec($version);
            return TRUE;
        }
        return FALSE;
    }
}
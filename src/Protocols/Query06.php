<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query06 extends Query05{
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
            $packet_order = ord(Functions::CutByte($packet, 1));

            if ($packet_order >= 128) // LAST PACKET - SO ITS ORDER NUMBER IS ALSO THE TOTAL
            {
                $packet_order -= 128;
                $packet_total = $packet_order + 1;
            }

            $buffer[$packet_order] = $packet;
            if ($server['basic']['type'] == "minecraft" || $server['basic']['type'] == "jc2mp") { 
                $packet_total = 1; 
            }

        }
        while ($packet_count < $packet_total);

        //---------------------------------------------------------+
        //  PROCESS AND SORT PACKETS
        foreach ($buffer as $key => $packet)
        {
            // REMOVE END NULL FOR JOINING
            $packet = substr($packet, 0, -1);

            // LAST VALUE HAS BEEN SPLIT
            if (substr($packet, -1) != "\x00")
            {
                // REMOVE SPLIT VALUE AS COMPLETE VALUE IS IN NEXT PACKET
                $part = explode("\x00", $packet);
                array_pop($part);
                $packet = implode("\x00", $part)."\x00";
            }

            // PLAYER OR TEAM DATA THAT MAY BE A CONTINUATION
            if ($packet[0] != "\x00")
            {
                // WHEN DATA IS SPLIT THE NEXT PACKET STARTS WITH A REPEAT OF THE FIELD NAME
                $pos = strpos($packet, "\x00") + 1;

                // REPEATED FIELD NAMES END WITH \x00\x?? INSTEAD OF \x00\x00
                if (isset($packet[$pos]) && $packet[$pos] != "\x00")
                {
                    // REMOVE REPEATED FIELD NAME
                    $packet = substr($packet, $pos + 1);
                }else{
                    // RE-ADD NULL AS PACKET STARTS WITH A NEW FIELD
                    $packet = "\x00".$packet;
                }
            }

            $buffer[$key] = $packet;
        }

        ksort($buffer);

        $buffer = implode("", $buffer);

        //---------------------------------------------------------+
        //  SERVER SETTINGS
        // REMOVE HEADER \x00
        $buffer = substr($buffer, 1);

        while ($key = strtolower(Functions::CutString($buffer)))
        {
            $server['convars'][$key] = Functions::CutString($buffer);
        }

        $lgsl_conversion = [ 
            "hostname"      => "name", 
            "gamename"      => "game", 
            "mapname"       => "map", 
            "map"           => "map", 
            "numplayers"    => "players", 
            "maxplayers"    => "playersmax", 
            "password"      => "password" 
        ];
        foreach ($lgsl_conversion as $e => $s) { 
            if (isset($server['convars'][$e])) { 
                $server['server'][$s] = $server['convars'][$e]; 
                unset($server['convars'][$e]); 
            }
        }

        if ($server['basic']['type'] == "bf2" || $server['basic']['type'] == "bf2142") {
            $server['server']['map'] = ucwords(str_replace("_", " ", $server['server']['map']));
        }
        // MAP NAME CONSISTENCY
        elseif ($server['basic']['type'] == "jc2mp") {
            $server['server']['map'] = 'Panau';
        }
        elseif ($server['basic']['type'] == "minecraft") {
            if (isset($server['convars']['gametype'])) {
                $server['server']['game'] = strtolower($server['convars']['game_id']);
            }

            $server['server']['name'] = Functions::ParserColor($server['server']['name'], "minecraft");
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
                while ($key = Functions::CutString($plugins[1], 0, " ")) {
                    $server['convars'][$key] = Functions::CutString($plugins[1], 0, "; ");
                }
            }
            // Needed to correctly terminate the players list
            $buffer = $buffer."\x00";
        }

        // IF SERVER IS EMPTY SKIP THE PLAYER CODE
        if ($server['server']['players'] == "0") { return TRUE; }

        //---------------------------------------------------------+
        //  PLAYER DETAILS
        // REMOVE HEADER \x01
        $buffer = substr($buffer, 1);

        while ($buffer)
        {
            if ($buffer[0] == "\x02") { break; }
            if ($buffer[0] == "\x00") { $buffer = substr($buffer, 1); break; }

            $field = Functions::CutString($buffer, 0, "\x00\x00");
            $field = strtolower(substr($field, 0, -1));

            if     ($field == "player") { $field = "name"; }
            elseif ($field == "aibot")  { $field = "bot";  }

            if ($buffer[0] == "\x00") { $buffer = substr($buffer, 1); continue; }

            $value_list = Functions::CutString($buffer, 0, "\x00\x00");
            $value_list = explode("\x00", $value_list);

            foreach ($value_list as $key => $value)
            {
                $server['players'][$key][$field] = $value;
            }
        }

        //---------------------------------------------------------+
        //  TEAM DATA
        // REMOVE HEADER \x02
        $buffer = substr($buffer, 1);

        while ($buffer)
        {
            if ($buffer[0] == "\x00") { break; }

            $field = Functions::CutString($buffer, 0, "\x00\x00");
            $field = strtolower($field);

            if     ($field == "team_t")  { $field = "name";  }
            elseif ($field == "score_t") { $field = "score"; }

            $value_list = Functions::CutString($buffer, 0, "\x00\x00");
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
}
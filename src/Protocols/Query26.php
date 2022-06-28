<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query26 extends Query25{
    public static function Query26(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://hazardaaclan.com/wiki/doku.php?id=aa3_server_query
        //  http://aluigi.altervista.org/papers.htm#aa3authdec
        if (!function_exists('gzuncompress')) { return FALSE; } // REQUIRES http://www.php.net/zlib

        $packet = "\x0A\x00playerName\x06\x06\x00query\x00";
        Functions::GSEncrypt($server['basic']['type'], $packet, TRUE);
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

        Functions::GSEncrypt($server['basic']['type'], $buffer, FALSE);

        $buffer = @gzuncompress($buffer);

        if (!$buffer) { return FALSE; }

        //----------------------------------------------------------
        $raw = [];

        do
        {
            $raw_name = Functions::CutPascal($buffer, 2);
            $raw_type = Functions::CutByte($buffer, 1);

            switch ($raw_type)
            {
                // SINGLE INTEGER
                case "\x02":
                    $raw[$raw_name] = Functions::UnPack(Functions::CutByte($buffer, 4), "i");
                    break;

                // ARRAY OF STRINGS
                case "\x07":
                    $raw_total = Functions::UnPack(Functions::CutByte($buffer, 2), "S");

                    for ($i = 0; $i < $raw_total;$i++)
                    {
                        $raw_value = Functions::CutPascal($buffer, 2);
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
}
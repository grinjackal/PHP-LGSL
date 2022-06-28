<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query13 extends Query12{
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

        $server['convars']['hostport']      = Functions::UnPack(Functions::CutByte($buffer_s, 4), "S");
        $buffer_s = substr($buffer_s, 4);

        $server['server']['name']           = Functions::CutString($buffer_s, 1);
        $server['server']['map']            = Functions::CutString($buffer_s, 1);
        $server['convars']['gamemode']      = Functions::CutString($buffer_s, 1);
        $server['server']['players']        = Functions::UnPack(Functions::CutByte($buffer_s, 4), "S");
        $server['server']['playersmax']     = Functions::UnPack(Functions::CutByte($buffer_s, 4), "S");
  
        //---------------------------------------------------------+
        while ($buffer_e && $buffer_e[0] != "\x00")
        {
            $item_key   = strtolower(Functions::CutString($buffer_e, 1));
            $item_value = Functions::CutString($buffer_e, 1);
            
            $item_key   = str_replace("\x1B\xFF\xFF\x01", "", $item_key);   // REMOVE MOD
            $item_value = str_replace("\x1B\xFF\xFF\x01", "", $item_value); // GARBAGE
  
            $server['convars'][$item_key] = $item_value;
        }
  
        //---------------------------------------------------------+
        //  THIS PROTOCOL RETURNS MORE INFO THAN THE ALTERNATIVE BUT IT DOES NOT
        //  RETURN THE GAME NAME ! SO WE HAVE MANUALLY DETECT IT USING THE GAME TYPE
  
        $tmp = strtolower(substr($server['convars']['gamemode'], 0, 2));
  
        if ($tmp == "ro")       { $server['server']['game'] = "Red Orchestra"; }
        elseif ($tmp == "kf")   { $server['server']['game'] = "Killing Floor"; }
  
        $server['server']['password'] = empty($server['convars']['password']) && empty($server['convars']['gamepassword']) ? "0" : "1";
  
        //---------------------------------------------------------+
        $player_key = 0;
  
        while ($buffer_p && $buffer_p[0] != "\x00")
        {
            $server['players'][$player_key]['pid']      = Functions::UnPack(Functions::CutByte($buffer_p, 4), "S");
  
            $end_marker = ord($buffer_p[0]) > 64 ? "\x00\x00" : "\x00"; // DIRTY WORK-AROUND FOR NAMES WITH PROBLEM CHARACTERS
  
            $server['players'][$player_key]['name']     = Functions::CutString($buffer_p, 1, $end_marker);
            $server['players'][$player_key]['ping']     = Functions::UnPack(Functions::CutByte($buffer_p, 4), "S");
            $server['players'][$player_key]['score']    = Functions::UnPack(Functions::CutByte($buffer_p, 4), "s");
            $tmp                                        = Functions::CutByte($buffer_p, 4);
  
            if ($tmp[3] == "\x20")      { $server['players'][$player_key]['team'] = 1; }
            elseif ($tmp[3] == "\x40")  { $server['players'][$player_key]['team'] = 2; }
  
            $player_key ++;
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query20 extends Query19{
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
        $response_type = Functions::CutByte($buffer, 1);
  
        if ($response_type == "I")
        {
            $server['convars']['netcode']       = ord(Functions::CutByte($buffer, 1));
            $server['server']['name']           = Functions::CutString($buffer);
            $server['server']['map']            = Functions::CutString($buffer);
            $server['server']['game']           = Functions::CutString($buffer);
            $server['convars']['gamemode']      = Functions::CutString($buffer);
            $server['convars']['description']   = Functions::CutString($buffer);
            $server['convars']['version']       = Functions::CutString($buffer);
            $server['convars']['hostport']      = Functions::UnPack(Functions::CutByte($buffer, 2), "n");
            $server['server']['players']        = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
            $server['server']['playersmax']     = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
            $server['convars']['dedicated']     = Functions::CutByte($buffer, 1);
            $server['convars']['os']            = Functions::CutByte($buffer, 1);
            $server['server']['password']       = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
            $server['convars']['anticheat']     = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
            $server['convars']['cpu_load']      = round(3.03 * Functions::UnPack(Functions::CutByte($buffer, 1), "C"))."%";
            $server['convars']['round']         = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
            $server['convars']['roundsmax']     = Functions::UnPack(Functions::CutByte($buffer, 1), "C");
            $server['convars']['timeleft']      = Functions::Time(Functions::UnPack(Functions::CutByte($buffer, 2), "S") / 250);
        }
  
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
  
        elseif ($response_type == "D")
        {
            $returned = ord(Functions::CutByte($buffer, 1));
            $player_key = 0;
  
            while ($buffer)
            {
                $server['players'][$player_key]['pid']   = ord(Functions::CutByte($buffer, 1));
                $server['players'][$player_key]['name']  = Functions::CutString($buffer);
                $server['players'][$player_key]['score'] = Functions::UnPack(Functions::CutByte($buffer, 4), "N");
                $server['players'][$player_key]['time']  = Functions::Time(Functions::UnPack(strrev(Functions::CutByte($buffer, 4)), "f"));
                $server['players'][$player_key]['ping']  = Functions::UnPack(Functions::CutByte($buffer, 2), "n");
                $server['players'][$player_key]['uid']   = Functions::UnPack(Functions::CutByte($buffer, 4), "N");
                $server['players'][$player_key]['team']  = ord(Functions::CutByte($buffer, 1));
  
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
}
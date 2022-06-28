<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query12 extends Query11{
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
        $response_type = Functions::CutByte($buffer, 1);
  
        //---------------------------------------------------------+
        if ($response_type == "i")
        {
            $lgsl_need['s'] = FALSE;
        
            if ($server['basic']['type'] == "vcmp") { $buffer = substr($buffer, 12); }
        
            $server['server']['password']   = ord(Functions::CutByte($buffer, 1));
            $server['server']['players']    = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['server']['playersmax'] = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
            $server['server']['name']       = Functions::CutPascal($buffer, 4);
            $server['convars']['gamemode']   = Functions::CutPascal($buffer, 4);
            $server['server']['map']        = Functions::CutPascal($buffer, 4);
        }
  
        //---------------------------------------------------------+
        elseif ($response_type == "r")
        {
            $lgsl_need['c'] = FALSE;
        
            $item_total = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        
            for ($i = 0; $i < $item_total; $i++)
            {
                if (!$buffer) { return FALSE; }

                $data_key   = strtolower(Functions::CutPascal($buffer));
                $data_value = Functions::CutPascal($buffer);

                $server['convars'][$data_key] = $data_value;
            }
        }
  
        //---------------------------------------------------------+
        elseif ($response_type == "d")
        {
            $lgsl_need['p'] = FALSE;
        
            $player_total = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        
            for ($i = 0; $i < $player_total; $i++)
            {
                if (!$buffer) { return FALSE; }

                $server['players'][$i]['pid']   = ord(Functions::CutByte($buffer, 1));
                $server['players'][$i]['name']  = Functions::CutPascal($buffer);
                $server['players'][$i]['score'] = Functions::UnPack(Functions::CutByte($buffer, 4), "S");
                $server['players'][$i]['ping']  = Functions::UnPack(Functions::CutByte($buffer, 4), "S");
            }
        }
      
        //---------------------------------------------------------+
        elseif ($response_type == "c")
        {
            $lgsl_need['p'] = FALSE;
        
            $player_total = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        
            for ($i = 0; $i < $player_total; $i++)
            {
                if (!$buffer) { return FALSE; }

                $server['players'][$i]['name']  = Functions::CutPascal($buffer);
            }
        }
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
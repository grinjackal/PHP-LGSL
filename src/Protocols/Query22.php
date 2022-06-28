<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query22 extends Query21{
    
    public static function Query22(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp,"\x03\x00\x00");
  
        $buffer = fread($lgsl_fp, 4096);
        $buffer = substr($buffer, 3); // REMOVE HEADER
  
        if (!$buffer) { return FALSE; }
  
        $response_type = ord(Functions::CutByte($buffer, 1)); // TYPE SHOULD BE 4
  
        //---------------------------------------------------------+
        $grf_count = ord(Functions::CutByte($buffer, 1));
  
        for ($a = 0; $a < $grf_count; $a++)
        {
            $server['convars']['grf_'.$a.'_id'] = strtoupper(dechex(Functions::UnPack(Functions::CutByte($buffer, 4), "N")));
            for ($b = 0; $b < 16; $b++)
            {
                $server['convars']['grf_'.$a.'_md5'] .= strtoupper(dechex(ord(Functions::CutByte($buffer, 1))));
            }
        }
  
        //---------------------------------------------------------+
        $server['convars']['date_current']      = Functions::UnPack(Functions::CutByte($buffer, 4), "L");
        $server['convars']['date_start']        = Functions::UnPack(Functions::CutByte($buffer, 4), "L");
        $server['convars']['companies_max']     = ord(Functions::CutByte($buffer, 1));
        $server['convars']['companies']         = ord(Functions::CutByte($buffer, 1));
        $server['convars']['spectators_max']    = ord(Functions::CutByte($buffer, 1));
        $server['server']['name']               = Functions::CutString($buffer);
        $server['convars']['version']           = Functions::CutString($buffer);
        $server['convars']['language']          = ord(Functions::CutByte($buffer, 1));
        $server['server']['password']           = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax']         = ord(Functions::CutByte($buffer, 1));
        $server['server']['players']            = ord(Functions::CutByte($buffer, 1));
        $server['convars']['spectators']        = ord(Functions::CutByte($buffer, 1));
        $server['server']['map']                = Functions::CutString($buffer);
        $server['convars']['map_width']         = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['convars']['map_height']        = Functions::UnPack(Functions::CutByte($buffer, 2), "S");
        $server['convars']['map_set']           = ord(Functions::CutByte($buffer, 1));
        $server['convars']['dedicated']         = ord(Functions::CutByte($buffer, 1));
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
}
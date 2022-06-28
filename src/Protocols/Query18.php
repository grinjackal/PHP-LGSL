<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query18 extends Query17{
    public static function Query18(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://masterserver.savage2.s2games.com
        fwrite($lgsl_fp, "\x01");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 12); // REMOVE HEADER
  
        $server['server']['name']               = Functions::CutString($buffer);
        $server['server']['players']            = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax']         = ord(Functions::CutByte($buffer, 1));
        $server['convars']['time']              = Functions::CutString($buffer);
        $server['server']['map']                = Functions::CutString($buffer);
        $server['convars']['nextmap']           = Functions::CutString($buffer);
        $server['convars']['location']          = Functions::CutString($buffer);
        $server['convars']['minimum_players']   = ord(Functions::CutString($buffer));
        $server['convars']['gamemode']          = Functions::CutString($buffer);
        $server['convars']['version']           = Functions::CutString($buffer);
        $server['convars']['minimum_level']     = ord(Functions::CutByte($buffer, 1));
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
}
<?php
namespace GrinJackal\LGSQ\Protocols;

class Query15 extends Query14{
    
    public static function Query15(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "GTR2_Direct_IP_Search\x00");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = str_replace("\xFE", "\xFF", $buffer);
        $buffer = explode("\xFF", $buffer);
  
        $server['server']['name']           = $buffer[3];
        $server['server']['game']           = $buffer[7];
        $server['convars']['version']       = $buffer[11];
        $server['convars']['hostport']      = $buffer[15];
        $server['server']['map']            = $buffer[19];
        $server['server']['players']        = $buffer[25];
        $server['server']['playersmax']     = $buffer[27];
        $server['convars']['gamemode']      = $buffer[31];
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
}
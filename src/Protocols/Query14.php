<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query14 extends Query13{
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
        $server['server']['password']   = Functions::UnPack(Functions::CutByte($buffer, 4), "l") - 1 ? 1 : 0;
        $server['server']['playersmax'] = Functions::UnPack(Functions::CutByte($buffer, 4), "l") - 1;
        $server['server']['players']    = Functions::UnPack(Functions::CutByte($buffer, 4), "l") - 1;
        $buffer                         = substr($buffer, 4);  // UNKNOWN ( 88 )
        $name_length                    = Functions::UnPack(Functions::CutByte($buffer, 4), "l");
        $buffer                         = substr($buffer, 56); // UNKNOWN
        $server['server']['name']       = Functions::CutByte($buffer, $name_length);
  
        Functions::CutString($buffer, 0, ":");
        Functions::CutString($buffer, 0, ":");
        Functions::CutString($buffer, 0, ":");
        Functions::CutString($buffer, 0, ":");
        Functions::CutString($buffer, 0, ":");
  
        // WHATS LEFT IS THE MOTD
        $server['convars']['motd'] = substr($buffer, 0, -1);
  
        // REMOVE UTF-8 ENCODING NULLS
        $server['server']['name'] = str_replace("\x00", "", $server['server']['name']);
        $server['convars']['motd'] = str_replace("\x00", "", $server['convars']['motd']);
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
}
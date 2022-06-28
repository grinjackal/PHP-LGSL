<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query32 extends Query31{
    public static function Query32(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        fwrite($lgsl_fp, "\x05\x00\x00\x01\x0A");

        $buffer = fread($lgsl_fp, 4096);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 5); // REMOVE HEADER

        $server['server']['name']       = Functions::CutPascal($buffer);
        $server['server']['map']        = Functions::CutPascal($buffer);
        $server['server']['players']    = ord(Functions::CutByte($buffer, 1));
        $server['server']['playersmax'] = 0; // HELD ON MASTER

        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        return TRUE;
    }
}
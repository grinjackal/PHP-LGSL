<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query39 extends Query38{
    public static function Query39(&$server, &$lgsl_need, &$lgsl_fp) // Mafia 2: MP
    {
        fwrite($lgsl_fp, "M2MPi");
        $buffer = fread($lgsl_fp, 1024);

        if (!$buffer) { return FALSE; }

        $buffer = substr($buffer, 4); // REMOVE HEADER

        $server['server']['name']           = Functions::CutPascal($buffer, 1, -1);
        $server['server']['map']            = "Empire Bay";
        $server['server']['players']        = Functions::CutPascal($buffer, 1, -1);
        $server['server']['playersmax']     = Functions::CutPascal($buffer, 1, -1);
        $server['server']['password']       = 0;
        $server['convars']['gamemode']      = Functions::CutPascal($buffer, 1, -1);

        return TRUE;
    }
}
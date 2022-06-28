<?php
namespace GrinJackal\LGSQ\Protocols;

use GrinJackal\LGSQ\Functions;

class Query16 extends Query15{
    public static function Query16(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE:
        //  http://www.planetpointy.co.uk/software/rfactorsspy.shtml
        //  http://users.pandora.be/viperius/mUtil/
        //  USES FIXED DATA POSITIONS WITH RANDOM CHARACTERS FILLING THE GAPS
        fwrite($lgsl_fp, "rF_S");
  
        $buffer = fread($lgsl_fp, 4096);
  
        if (!$buffer) { return FALSE; }
  
        //---------------------------------------------------------+
        $buffer = substr($buffer, 8);
        $server['convars']['region']           = Functions::UnPack($buffer[1] .$buffer[2],  "S");
        $server['convars']['version']          = Functions::UnPack($buffer[9] .$buffer[10], "S");
        $server['convars']['hostport']         = Functions::UnPack($buffer[13].$buffer[14], "S");
        $buffer = substr($buffer, 17);
        $server['server']['game']              = Functions::GetString($buffer);
        $buffer = substr($buffer, 20);
        $server['server']['name']              = Functions::GetString($buffer);
        $buffer = substr($buffer, 28);
        $server['server']['map']               = Functions::GetString($buffer);
        $buffer = substr($buffer, 32);
        $server['convars']['motd']             = Functions::GetString($buffer);
        $buffer = substr($buffer, 96);
        $server['convars']['packed_aids']      = Functions::UnPack($buffer[0].$buffer[1], "S");
        $server['convars']['packed_flags']     = Functions::UnPack($buffer[4],  "C");
        $server['convars']['rate']             = Functions::UnPack($buffer[5],  "C");
        $server['server']['players']           = Functions::UnPack($buffer[6],  "C");
        $server['server']['playersmax']        = Functions::UnPack($buffer[7],  "C");
        $server['convars']['bots']             = Functions::UnPack($buffer[8],  "C");
        $server['convars']['packed_special']   = Functions::UnPack($buffer[9],  "C");
        $server['convars']['damage']           = Functions::UnPack($buffer[10], "C");
        $server['convars']['packed_rules']     = Functions::UnPack($buffer[11].$buffer[12], "S");
        $server['convars']['credits1']         = Functions::UnPack($buffer[13], "C");
        $server['convars']['credits2']         = Functions::UnPack($buffer[14].$buffer[15], "S");
        $server['convars']['time']             = Functions::Time(Functions::UnPack($buffer[16].$buffer[17], "S"));
        $server['convars']['laps']             = Functions::UnPack($buffer[18].$buffer[19], "s") / 16;
        $buffer                                = substr($buffer, 23);
        $server['convars']['vehicles']         = Functions::GetString($buffer);
  
        // DOES NOT RETURN PLAYER INFORMATION
        //---------------------------------------------------------+
        $server['server']['password']    = ($server['convars']['packed_special'] & 2)  ? 1 : 0;
        $server['convars']['racecast']    = ($server['convars']['packed_special'] & 4)  ? 1 : 0;
        $server['convars']['fixedsetups'] = ($server['convars']['packed_special'] & 16) ? 1 : 0;
  
        $server['convars']['aids']  = "";
        if ($server['convars']['packed_aids'] & 1)    { $server['convars']['aids'] .= " TractionControl"; }
        if ($server['convars']['packed_aids'] & 2)    { $server['convars']['aids'] .= " AntiLockBraking"; }
        if ($server['convars']['packed_aids'] & 4)    { $server['convars']['aids'] .= " StabilityControl"; }
        if ($server['convars']['packed_aids'] & 8)    { $server['convars']['aids'] .= " AutoShifting"; }
        if ($server['convars']['packed_aids'] & 16)   { $server['convars']['aids'] .= " AutoClutch"; }
        if ($server['convars']['packed_aids'] & 32)   { $server['convars']['aids'] .= " Invulnerability"; }
        if ($server['convars']['packed_aids'] & 64)   { $server['convars']['aids'] .= " OppositeLock"; }
        if ($server['convars']['packed_aids'] & 128)  { $server['convars']['aids'] .= " SteeringHelp"; }
        if ($server['convars']['packed_aids'] & 256)  { $server['convars']['aids'] .= " BrakingHelp"; }
        if ($server['convars']['packed_aids'] & 512)  { $server['convars']['aids'] .= " SpinRecovery"; }
        if ($server['convars']['packed_aids'] & 1024) { $server['convars']['aids'] .= " AutoPitstop"; }
  
        $server['convars']['aids']     = str_replace(" ", " / ", trim($server['convars']['aids']));
        $server['convars']['vehicles'] = str_replace("|", " / ", trim($server['convars']['vehicles']));
  
        unset($server['convars']['packed_aids']);
        unset($server['convars']['packed_flags']);
        unset($server['convars']['packed_special']);
        unset($server['convars']['packed_rules']);
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
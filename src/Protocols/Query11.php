<?php
namespace GrinJackal\LGSQ\Protocols;

class Query11 extends Query10{
    public static function Query11(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  REFERENCE: http://wiki.unrealadmin.org/UT3_query_protocol
        //  UT3 RESPONSE IS REALLY MESSY SO THIS CLEANS IT UP
        $status = self::Query06($server, $lgsl_need, $lgsl_fp);
  
        if (!$status) { return FALSE; }
  
        //---------------------------------------------------------+
        $server['server']['map'] = $server['convars']['p1073741825'];
        unset($server['convars']['p1073741825']);
  
        //---------------------------------------------------------+
        $lgsl_ut3_key = [
            "s0"          => "bots_skill",
            "s6"          => "pure",
            "s7"          => "password",
            "s8"          => "bots_vs",
            "s10"         => "forcerespawn",
            "p268435703"  => "bots",
            "p268435704"  => "goalscore",
            "p268435705"  => "timelimit",
            "p268435717"  => "mutators_default",
            "p1073741826" => "gamemode",
            "p1073741827" => "description",
            "p1073741828" => "mutators_custom"
        ];
  
        foreach ($lgsl_ut3_key as $old => $new)
        {
            if (!isset($server['convars'][$old])) { continue; }
            $server['convars'][$new] = $server['convars'][$old];
            unset($server['convars'][$old]);
        }
  
        //---------------------------------------------------------+
        $part = explode(".", $server['convars']['gamemode']);
        if ($part[0] && (stristr($part[0], "UT") === FALSE))
        {
            $server['server']['game'] = $part[0];
        }
  
        //---------------------------------------------------------+
        $tmp = $server['convars']['mutators_default'];
        $server['convars']['mutators_default'] = "";
  
        if ($tmp & 1)     { $server['convars']['mutators_default'] .= " BigHead";           }
        if ($tmp & 2)     { $server['convars']['mutators_default'] .= " FriendlyFire";      }
        if ($tmp & 4)     { $server['convars']['mutators_default'] .= " Handicap";          }
        if ($tmp & 8)     { $server['convars']['mutators_default'] .= " Instagib";          }
        if ($tmp & 16)    { $server['convars']['mutators_default'] .= " LowGrav";           }
        if ($tmp & 64)    { $server['convars']['mutators_default'] .= " NoPowerups";        }
        if ($tmp & 128)   { $server['convars']['mutators_default'] .= " NoTranslocator";    }
        if ($tmp & 256)   { $server['convars']['mutators_default'] .= " Slomo";             }
        if ($tmp & 1024)  { $server['convars']['mutators_default'] .= " SpeedFreak";        }
        if ($tmp & 2048)  { $server['convars']['mutators_default'] .= " SuperBerserk";      }
        if ($tmp & 8192)  { $server['convars']['mutators_default'] .= " WeaponReplacement"; }
        if ($tmp & 16384) { $server['convars']['mutators_default'] .= " WeaponsRespawn";    }
  
        $server['convars']['mutators_default'] = str_replace(" ",    " / ", trim($server['convars']['mutators_default']));
        $server['convars']['mutators_custom']  = str_replace("\x1c", " / ",      $server['convars']['mutators_custom']);
  
        //---------------------------------------------------------+
        return TRUE;
    }
}
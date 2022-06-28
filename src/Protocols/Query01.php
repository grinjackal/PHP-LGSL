<?php
namespace GrinJackal\LGSQ\Protocols;

class Query01{
    public static function Query01(&$server, &$lgsl_need, &$lgsl_fp)
    {
        //---------------------------------------------------------+
        //  PROTOCOL FOR DEVELOPING WITHOUT USING LIVE SERVERS TO HELP ENSURE RETURNED
        //  DATA IS SANITIZED AND THAT LONG SERVER AND PLAYER NAMES ARE HANDLED PROPERLY
        $server['server'] = [
            "game"       => "test_game",
            "name"       => "test_ServerNameThatsOften'Really'LongAndCanHaveSymbols<hr />ThatWill\"Screw\"UpHtmlUnlessEntitied",
            "map"        => "test_map",
            "players"    => rand(0,  16),
            "playersmax" => rand(16, 32),
            "password"   => rand(0,  1)
        ];
    
        //---------------------------------------------------------+
        $server['convars'] = [
            "testextra1" => "normal",
            "testextra2" => 123,
            "testextra3" => time(),
            "testextra4" => "",
            "testextra5" => "<b>Setting<hr />WithHtml</b>",
            "testextra6" => "ReallyLongSettingLikeSomeMapCyclesThatHaveNoSpacesAndCauseThePageToGoReallyWideIfNotBrokenUp"
        ];
    
        //---------------------------------------------------------+
        $server['players']['0']['name']  = "Normal";
        $server['players']['0']['score'] = "12";
        $server['players']['0']['ping']  = "34";
    
        $server['players']['1']['name']  = "\xc3\xa9\x63\x68\x6f\x20\xd0\xb8-d0\xb3\xd1\x80\xd0\xbe\xd0\xba"; // UTF PLAYER NAME
        $server['players']['1']['score'] = "56";
        $server['players']['1']['ping']  = "78";
    
        $server['players']['2']['name']  = "One&<Two>&Three&\"Four\"&'Five'";
        $server['players']['2']['score'] = "90";
        $server['players']['2']['ping']  = "12";
    
        $server['players']['3']['name']  = "ReallyLongPlayerNameBecauseTheyAreUberCoolAndAreInFiveClans";
        $server['players']['3']['score'] = "90";
        $server['players']['3']['ping']  = "12";
    
        //---------------------------------------------------------+
        if (rand(0, 10) == 5) { $server['players'] = array(); } // RANDOM NO PLAYERS
        if (rand(0, 10) == 5) { return FALSE; }           // RANDOM GOING OFFLINE
    
        //---------------------------------------------------------+
        return TRUE;
    }
}
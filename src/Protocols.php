<?php
namespace GrinJackal\LGSQ;

use GrinJackal\LGSQ\Protocols\Query41;

class Protocols extends Query41{
    public static function QueryDirect(&$server, $request, $lgsl_function, $scheme)
    {
        //---------------------------------------------------------+
        $lgsl_config['timeout'] = 5;
        
        if ($scheme == 'http') { 
            if(!function_exists('curl_init') || !function_exists('curl_setopt') || !function_exists('curl_exec')) 
                return FALSE;
        
            $lgsl_fp =  curl_init('');
            curl_setopt($lgsl_fp, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($lgsl_fp, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($lgsl_fp, CURLOPT_CONNECTTIMEOUT, $lgsl_config['timeout']);
            curl_setopt($lgsl_fp, CURLOPT_TIMEOUT, 3);
            curl_setopt($lgsl_fp, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        }else{
            $lgsl_fp = @fsockopen("{$scheme}://{$server['basic']['ip']}", $server['basic']['q_port'], $errno, $errstr, 1);
        
            if (!$lgsl_fp) { 
                $server['convars']['_error'] = $errstr; 
                return FALSE; 
            }
        
            stream_set_timeout($lgsl_fp, $lgsl_config['timeout'], $lgsl_config['timeout'] ? 0 : 500000);
            stream_set_blocking($lgsl_fp, TRUE);
        }
        
        //---------------------------------------------------------+
        //  CHECK WHAT IS NEEDED
        $lgsl_need      = [];
        $lgsl_need['s'] = strpos($request, "s") !== FALSE ? TRUE : FALSE;
        $lgsl_need['c'] = strpos($request, "c") !== FALSE ? TRUE : FALSE;
        $lgsl_need['p'] = strpos($request, "p") !== FALSE ? TRUE : FALSE;
        
        // ChANGE [e] TO [s][e] AS BASIC QUERIES OFTEN RETURN EXTRA INFO
        if ($lgsl_need['c'] && !$lgsl_need['s']) { 
            $lgsl_need['s'] = TRUE; 
        }
        
        //---------------------------------------------------------+
        //  QUERY FUNCTION IS REPEATED TO REDUCE DUPLICATE CODE
        do{
            $lgsl_need_check = $lgsl_need;
        
            // CALL FUNCTION REQUIRES '&$variable' TO PASS 'BY REFERENCE'
            $response = call_user_func_array([self::class, $lgsl_function], [&$server, &$lgsl_need, &$lgsl_fp]);
        
            // CHECK IF SERVER IS OFFLINE
            if (!$response) { break; }
        
            // CHECK IF NEED HAS NOT CHANGED - THIS SERVES TWO PURPOSES - TO PREVENT INFINITE LOOPS - AND TO
            // AVOID WRITING $lgsl_need = FALSE FALSE FALSE FOR GAMES THAT RETURN ALL DATA IN ONE RESPONSE
            if ($lgsl_need_check == $lgsl_need) { break; }
        
            // OPTIMIZATION THAT SKIPS REQUEST FOR PLAYER DETAILS WHEN THE SERVER IS KNOWN TO BE EMPTY
            if ($lgsl_need['p'] && $server['server']['players'] == "0") { $lgsl_need['p'] = FALSE; }
        }
        while ($lgsl_need['s'] == TRUE || $lgsl_need['c'] == TRUE || $lgsl_need['p'] == TRUE);
        
        //---------------------------------------------------------+
        if ($scheme == 'http') {
            curl_close($lgsl_fp);
        }else{
            @fclose($lgsl_fp);
        }
        
        return $response;
    }
}
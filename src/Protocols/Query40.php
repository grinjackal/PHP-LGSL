<?php
namespace GrinJackal\LGSQ\Protocols;

class Query40 extends Query39{
    public static function Query40(&$server, &$lgsl_need, &$lgsl_fp) // Farming Simulator
    {
        curl_setopt($lgsl_fp, CURLOPT_URL, "http://{$server['basic']['ip']}:{$server['basic']['q_port']}/index.html"); // CAN QUERY ONLY SERVER NAME AND ONLINE STATUS, MEH
        $buffer = curl_exec($lgsl_fp);

        if (!$buffer) { return FALSE; }
    
        preg_match('/<h2>Login to [\w\d\s\/\\&@"\'-]+<\/h2>/', $buffer, $name);

        $server['server']['name']        = substr($name[0], 12, strlen($name[0])-17);
        $server['server']['map']         = "Farm";

        return strpos($buffer, 'status-indicator online') !== FALSE;
    }
}
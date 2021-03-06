<?php

function getClass($file, $args = array()){
    
    $fp = fopen($file, 'r');
    $class = $buffer = '';
    $i = 0;
    while (!$class) {
        if (feof($fp)) break;

        $buffer .= fread($fp, 512);
        $tokens = token_get_all($buffer);

        if (strpos($buffer, '{') === false) continue;

        for (;$i<count($tokens);$i++) {
            if ($tokens[$i][0] === T_CLASS) {
                for ($j=$i+1;$j<count($tokens);$j++) {
                    if ($tokens[$j] === '{') {
                        $class = $tokens[$i+2][1];
                    }
                }
            }
        }
    }
    
    if(empty($args)){
        return new $class();
    }
    else{
        return new $class($args);
    }   
}

function startsWith ($string, $startString)
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

function getOSName ()
{
    if (startsWith(php_uname(), "Windows")) {
        return "Windows";
    } else if (startsWith(php_uname(), "Linux")) {
        return "Linux";
    } else {
        return "Unsupported";
    }
}

?>
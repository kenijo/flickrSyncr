<?php if ( ! defined ( 'DIRNAME' ) ) exit ( 'No direct script access allowed' );
    /*
    |--------------------------------------------------------------------------
    |  phpCLIClass
    |  Written by Ken (kenijo@gmail.com)
    |
    |  This class allows you to manage your PHP script for command line use.
    |
    |  The getArgs( ) function allows for| nix style argument parsing
    |  php file.php --foo=bar -abc -AB 'hello world' --baz produces:
    |   Array
    |   (
    |       [foo] => bar
    |       [a] => true
    |       [b] => true
    |       [c] => true
    |       [A] => true
    |       [B] => hello world
    |       [baz] => true
    |   )
    |
    */

    class phpCLI
    {
        var $allowed_arguments = array ( );

        function phpCLI ( $command_arguments = NULL )
        {
            // Streamline the arguments
            if ( $command_arguments != NULL ) {
                foreach ( $command_arguments['cmd_arguments'] as $argument => $description )
                {
                    $argument = preg_replace ( '/[^a-z0-9-]+/', '', $argument );
                    $this->allowed_arguments[$argument] = $description;
                }
            }
        }

        function is_cli ( )
        {
            if ( PHP_SAPI == 'cli' )
            {
                return true;
            }
            return false;
        }

        function generate_help ( $cfg )
        {
            echo PHP_EOL;
            echo $cfg['cmd_name']  . PHP_EOL;
            echo PHP_EOL;

            if ( !empty( $command_description ) )
            {
                echo $cfg['cmd_description']  . PHP_EOL;
                echo PHP_EOL;
            }

            if ( !empty( $command_usage ) )
            {
                echo $cfg['cmd_usage']  . PHP_EOL;
                echo PHP_EOL;
            }

            if ( !empty( $command_example ) )
            {
                echo $cfg['cmd_example']  . PHP_EOL;
                echo PHP_EOL;
            }

            $max_arg_len = 0;
            foreach ( $cfg['cmd_arguments'] as $argument => $description )
            {
                $tmp_len = strlen ( $argument );
                if ( $tmp_len >= $max_arg_len )
                {
                    $max_arg_len = $tmp_len;
                }
            }

            foreach ( $cfg['cmd_arguments'] as $argument => $description )
            {
                $spacing_arg = $max_arg_len  - strlen ( $argument );
                echo '  --' . $argument . str_repeat ( ' ', $spacing_arg + 2 ) . '' . $description . PHP_EOL;
            }
            return false;
        }

        function is_arguments_valid ( $arguments )
        {
            foreach ( $arguments as $argument => $value )
            {
                if ( ! array_key_exists ( $argument, $this->allowed_arguments ) )
                {
                    return false;
                }
            }
            return true;
        }

        function getArgs ( $arguments )
        {
            $out = array ( );
            $last_argument = null;
            for ( $i = 0; $i < sizeof ( $arguments ); $i++)
            {
                if ( ( bool ) preg_match ( '/^--(.+)/', $arguments[$i], $match ) )
                {
                    $parts = explode ( '=', $match[1] );
                    $key = preg_replace ( '/[^a-z0-9-]+/', '', $parts[0] );
                    if ( isset ( $parts[1] ) )
                    {
                        $out[$key] = $parts[1];
                    }
                    else
                    {
                        $out[$key] = true;
                    }
                    $last_argument = $key;
                }
                else if ( ( bool ) preg_match ( '/^-([a-zA-Z0-9]+)/', $arguments[$i], $match ) )
                {
                    for ( $j = 0, $jl = strlen ( $match[1] ); $j < $jl; $j++ )
                    {
                        $key = $match[1]{$j};
                        $out[$key] = true;
                    }
                $last_argument = $key;
                }
                else if ( $last_argument !== null )
                {
                    $out[$last_argument] = $arguments[$i];
                }
            }
            return $out;
        }
    }
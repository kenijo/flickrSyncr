<?php

	/**
	 * phpCLIClass
	 * Written by Ken (kenijo@gmail.com)
	 *
	 * This class allows you to manage your PHP script for command line use.
	 *
	 * The getArgs( ) function allows for *nix style argument parsing
	 * php file.php --foo=bar -abc -AB 'hello world' --baz produces:
	 *	Array
	 *	(
	 *		[foo] => bar
	 *		[a] => true
	 *		[b] => true
	 *		[c] => true
	 *		[A] => true
	 *		[B] => hello world
	 *		[baz] => true
	 *	)
	 *
	 **/

	class phpCLI {
	
		var $command_arguments_list = array ( );
		
		function phpCLI ( $command_arguments = NULL ) {
			// Streamline the arguments
			if ( $command_arguments != NULL ) {
				foreach ( $command_arguments as $argument => $description ) { 
					$argument = preg_replace ( "/[^a-z0-9-]+/", "", $argument );
					$this->command_arguments_list[$argument] = $description;
				} 
			}
		}
		
		function is_cli ( ) {
			if ( PHP_SAPI == "cli" ) {
				return true;
			} 
			return false;			
		}
		
		function generate_help ( $command_name, $command_description, $command_usage, $command_example, $command_arguments ) {
			echo PHP_EOL;
			echo $command_name . PHP_EOL;
			echo PHP_EOL;
			
			if ( !empty( $command_description ) ) {
				echo $command_description . PHP_EOL;
				echo PHP_EOL;
			}
			
			if ( !empty( $command_usage ) ) {
				echo $command_usage . PHP_EOL;
				echo PHP_EOL;
			}
			
			if ( !empty( $command_example ) ) {
				echo $command_example . PHP_EOL;
				echo PHP_EOL;
			}
			
			$max_arg_len = 0;	
			foreach ( $command_arguments as $argument => $description ) { 
				$tmp_len = strlen ( $argument );
				if ( $tmp_len >= $max_arg_len ) {
					$max_arg_len = $tmp_len;
				}
			} 
			
			foreach ( $command_arguments as $argument => $description ) {			
				$spacing_arg = $max_arg_len  - strlen ( $argument );
				echo "  --" . $argument . str_repeat ( " ", $spacing_arg + 2 ) ."" . $description . PHP_EOL;
			}
			return false;			
		}
		
		function is_arguments_valid ( $arguments ) {
			foreach ( $arguments as $argument => $value ) {
				if ( ! array_key_exists ( $argument, $this->command_arguments_list ) )
				{
					return false;
				}				
			}
			return true;
		}
		
		function getArgs ( $args ) {
			$out = array ( );
			$last_arg = null;
			for ( $i = 1, $il = sizeof ( $args ); $i < $il; $i++) {
				if ( ( bool ) preg_match ( "/^--(.+)/", $args[$i], $match ) ) {
					$parts = explode ( "=", $match[1] );
					$key = preg_replace ( "/[^a-z0-9-]+/", "", $parts[0] );
					if ( isset ( $parts[1] ) ) {
						$out[$key] = $parts[1];    
					} else {
						$out[$key] = true;    
					}
					$last_arg = $key;
				} else if ( ( bool ) preg_match ( "/^-([a-zA-Z0-9]+)/", $args[$i], $match ) ) {
					for ( $j = 0, $jl = strlen ( $match[1] ); $j < $jl; $j++ ) {
						$key = $match[1]{$j};
						$out[$key] = true;
					}
				$last_arg = $key;
				} else if ( $last_arg !== null ) {
					$out[$last_arg] = $args[$i];
				}
			}
			return $out;
		}

	}

?>

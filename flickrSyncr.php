<?php
	/**
	 * Flickr Syncr
	 * ============
	 * Written by Ken (kenijo@gmail.com)
	 *
	 * This is a command line script !
	 *	 
	 * This PHP script allows you to sync a local folder with your Flickr account in command line
	 * Through batch upload / download of photos and video on / from Flickr
	 *
	 * Usage: php flickrSyncr.php [arguments]

	 * Example: php flickrSyncr.php --upload --path=/path/to/my/photo --generate-tags
	 * 
  	 * --help            Print Help ( this message ) and exit
  	 * --upload          Specify the folder to upload ( default is current directory )
  	 * --download        Specify the folder where to download the photos from flickr ( default is current directory )
  	 * --path            Specify the folder to use ( default is current directory )
  	 * --cleanup-local   Delete local files that are not on Flickr
  	 * --cleanup-flickr  Delete Flickr files that are not on the local disk
  	 * --ignore-images   Ignore image files
  	 * --ignore-videos   Ignore video files when download or uploading
  	 * --generate-tags   Generate tags based on the name of the photoset when uploading
	 * 
	 **/
	 
	//////////////////////////////////////////////////////////////////////////////////
	// Flickr Syncr Script Variables

	Require_once ( "flickrSyncrAPI.php" );

	// Script Name
	$script_name = "Flickr Syncr";
	// Script version
	$script_version = "1.0";

	// Get current script directory
	$current_directory = realpath ( dirname ( __FILE__ ) );

	// Log file name
	$log_file = $current_directory . DIRECTORY_SEPARATOR . "log";
	/**
	 *	Logging level
	 *	When you choose a level, all the messages from this level and below will be displayed
	 *
	 *	DEBUG		// Debug: debug messages
	 *	INFO		// Informational: informational messages
	 *	NOTICE		// Notice: normal but significant condition
	 *	WARNING		// Warning: warning conditions
	 *	ERROR		// Error: error conditions
	 *	CRITICAL	// Critical: critical conditions
	 *	ALERT		// Alert: action must be taken immediately
	 *	EMERGENCY	// Emergency: system is unusable
	**/
	$log_level = "INFO";

	// File extensions that are allowed to be uploaded to Flickr
	$allowed_images = array ( "jpg", "png", "jpeg", "gif", "bmp", );
	$allowed_videos = array ( "avi", "wmv", "mov", "mp4", "3gp", "ogg", "ogv", "mts", );

	// Folders to exclude from uploading to Flickr
	$exclude_folder_list = array ( "@eaDir", ".SyncArchive", ".SyncID", ".SyncIgnore" );
	//////////////////////////////////////////////////////////////////////////////////
	// FOR DEBUG ONLY
	$debug_mode = false;
	$debug_path = "/volume1/data/photo/";
	$debug_args = "--upload --path=".$debug_path." --generate-tags";
	//////////////////////////////////////////////////////////////////////////////////

	mb_internal_encoding('UTF-8');

	// Loading KLogger class
	require_once ( "class/KLogger.class.php" );

	if ( $log_level == "EMERGENCY" ) {
		$log = new KLogger ( $log_file, KLogger::EMERG );
	} else if ( $log_level == "ALERT" ) {
		$log = new KLogger ( $log_file, KLogger::ALERT );
	} else if ( $log_level == "CRITICAL" ) {
		$log = new KLogger ( $log_file, KLogger::CRIT );
	} else if ( $log_level == "ERROR" ) {
		$log = new KLogger ( $log_file, KLogger::ERR );
	} else if ( $log_level == "WARNING" ) {
		$log = new KLogger ( $log_file, KLogger::WARN );
	} else if ( $log_level == "NOTICE" ) {
		$log = new KLogger ( $log_file, KLogger::NOTICE );
	} else if ( $log_level == "INFO" ) {
		$log = new KLogger ( $log_file, KLogger::INFO );
	} else if ( $log_level == "DEBUG" ) {
		$log = new KLogger ( $log_file, KLogger::DEBUG );
	}

	$log->logInfo ( PHP_EOL );
	$log->logInfo ( "----------------------------------------------------------------------------------------------------" );
	$log->logInfo ( "                                      STARTING " . $script_name . " " . $script_version );
	$log->logInfo ( "----------------------------------------------------------------------------------------------------" );
	
	// List all the allowed arguments and their help message
	$path_parts 			 = pathinfo ( __FILE__ );
	$script_basename		 = $path_parts['basename'];
	$command_name			 = $script_name . " " . $script_version . PHP_EOL . "================";
	$command_description	 = "This PHP script allows you to sync a local folder with your Flickr account in command line" . PHP_EOL;
	$command_description	.= "Through batch upload / download of photos and video on / from Flickr";
	$command_usage			 = "Usage   : php " . $script_basename . " [arguments]";
	$command_example		 = "Example : php " . $script_basename . " --upload --path=/path/to/my/photo --generate-tags";
	$command_arguments		 = array (
		"help"				=>	"Print Help ( this message ) and exit",
		"auth"				=>	"Authenticate only the app at www.flickr.com",
		"upload"			=>	"Specify the folder to upload ( default is current directory )",
		"download"			=>	"Specify the folder where to download the photos from flickr ( default is current directory )",
		"path"				=>	"Specify the folder to use ( default is current directory )",
		"cleanup-local"		=>	"Delete local files that are not on Flickr",
		"cleanup-flickr"	=>	"Delete Flickr files that are not on the local disk",
		"ignore-images"		=>	"Ignore image files",
		"ignore-videos"		=>	"Ignore video files when download or uploading",
		"generate-tags"		=>	"Generate tags based on the name of the photoset when uploading"
	);
	
	// Loading CLI class
	require_once ( "class/phpCLI.class.php" ) ;
	$cli = new phpCLI ( $command_arguments );

	// Ckeck if we are in command line
	if ( ! $cli->is_cli ( ) ) {
		$msg = $script_name . " is is intended to be run in command line.";
		$log->logError ( $msg );
		exit ( $msg ) ;
	}
	// Set args if debug mode is true
	if ( $debug_mode == true ) {
		$log->logDebug ( "Debug mode is enbaled, using debug arguments" );
		$_SERVER['argv'] = explode ( " ", $debug_args );
	}

	// Parse all the arguments
	$log->logDebug ( "Parsing arguments" );
	$arguments = $cli->getArgs ( $_SERVER["argv"] );

	// If we don't have arguments, we display the help message
	if ( sizeof ( $arguments ) <= 0 ) {
		$log->logError ( "Missing arguments" );
		$cli->generate_help ( $command_name, $command_description, $command_usage, $command_example, $command_arguments );
		exit ( );
	}
	// If an argument is invalid, we display the help message
	else if ( ! $cli->is_arguments_valid ( $arguments ) ) {
		$log->logError ( "Invalid arguments" );
		$cli->generate_help ( $command_name, $command_description, $command_usage, $command_example, $command_arguments );
		exit ( );
	}

	// Loading phpFlickr class
	require_once ( "class/phpFlickr.class.php" ) ;

	// Create new phpFlickr object
	$log->logDebug ( "Loading Flickr" );
	$f = new phpFlickr ( $api_key, $api_secret ) ;
	
	$log->logInfo ( "Checking Flickr authentication" );
	$credentials = auth_desktop ( $f, $log, "delete" );
	if ( ! $credentials ) {
		$log->logError ( "Error with authentication process" );
	} else {
		$log->logInfo ( "Connected as " . $credentials['user']['fullname'] . " to the account '" . $credentials['user']['username'] . "' with permission: '" . $credentials['perms'] . "'" );
	}
	
	if ( array_key_exists ( "auth", $arguments ) ) {
		exit ( );
	}

	// Cleanup the path
	$log->logDebug ( "Cleaning up path to prevent problems" );
	$arguments['path'] = str_replace ( array ( '/', '\\' ), DIRECTORY_SEPARATOR, $arguments['path'] );
	$arguments['path'] = rtrim ( $arguments['path'], DIRECTORY_SEPARATOR );
	// Check if the path provided in parameter is valid
	if ( ! file_exists ( $arguments['path'] ) ) {
		$log->logError ( "The path is invalid" );
		$cli->generate_help ( $command_name, $command_description, $command_usage, $command_example, $command_arguments );
		exit ( );
	}

	// Merge allowed extension arrays if needed
	$log->logDebug ( "Merging the list of allowed extensions" );
	$allowed_extension = array ( );
	if ( array_key_exists ( "ignore-images", $arguments ) ) {
		$allowed_filetypes = $allowed_videos;
	} else if ( array_key_exists ( "ignore-videos", $arguments ) ) {
		$allowed_filetypes = $allowed_images;
	} else {
		$allowed_filetypes = array_merge ( $allowed_images, $allowed_videos );
	}
	$allowed_filetypes = array_map ( 'strtolower', $allowed_filetypes );

	if ( array_key_exists ( "download", $arguments ) ) {
		// TODO : DOWNLOAD
		$log->logDebug ( "Starting download from Flickr" );
	}

	// TODO: Check space available on Flickr

	if ( array_key_exists ( "upload", $arguments ) ) {
		$log->logDebug ( "Starting upload to Flickr" );

		// Get the list of folders and files
		$dir_iterator = new RecursiveDirectoryIterator( $arguments['path'], FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
		$iterator = new RecursiveIteratorIterator ( $dir_iterator, RecursiveIteratorIterator::SELF_FIRST );

		// Parse the list of folders and files
		foreach ( $iterator as $file ) {
			// Check if we have a file and not a directory
			if ( $file->isFile() ) {
				// Skip them if they contain one of the excluded folder
				if ( !str_contain ( $file, $exclude_folder_list ) ) {
					// Check if the file has an allowed extension
					$path_parts = pathinfo ( $file );
					if ( in_array ( strtolower ( $path_parts['extension'] ), $allowed_filetypes ) ) {
						$file_relative_path = substr ( $path_parts['dirname'], strlen ( $arguments['path'] ), strlen ( $path_parts['dirname'] ) );
						$file_title = $path_parts['filename'];
						$file_description = $file_relative_path . DIRECTORY_SEPARATOR . $file_title;

						$file_tag = md5 ( $file_description );
						if ( array_key_exists ( "generate-tags", $arguments ) ) {
							$file_tag = $file_tag . " " . $file_relative_path;
							$file_tag = preg_replace ( "/\[([\d -]*?)\]/" , " " , $file_tag );
							$file_tag = str_replace ( DIRECTORY_SEPARATOR, " ", $file_tag );
							$file_tag = preg_replace ( "/#\b\w{1,3}(|\b)#u/" , " " , $file_tag );
							$file_tag = preg_replace( "/\s+/", ' ',$file_tag );
							$file_tag = trim ( $file_tag );
							$file_tag_array = explode ( " ", $file_tag );
							$file_tag_array = array_unique ( $file_tag_array );
							$file_tag = implode ( " ", $file_tag_array );
						}

						$photoset_title = substr ( $file_relative_path, strrpos ( $file_relative_path, DIRECTORY_SEPARATOR ) + 1, strlen ( $file_relative_path ) );
						$photoset_description = $file_relative_path;
						$file_basename = $file_relative_path . DIRECTORY_SEPARATOR . $path_parts['basename'];

						// NOTE : USING THIS METHOD TO CHECK IF PHOTO ALREADY EXISTS WORKS BUT YOU NEED A FEW SECONDS AFTER UPLOAD TO SEARCH THE PHOTO
						// OTHERWISE IT DOESN'T HAVE THE TIME TO BE INDEXED
						$files = $f->photos_search ( array ( "user_id" => $credentials['user']['nsid'], "text" => md5 ( $file_description ) ) );

						// If the file doesn't already exist then we upload it
						if ( count ( $files['photo'] ) == 0 ) {
							// Upload file and and it to photoset
							$log->logInfo ( "UPLOAD FILE     : " . $file_basename );
							$file_upload = $f->sync_upload (
								$file,								# ( Mandatory ) The path to the file to upload
								$title = $file_title,				# ( Optional ) The title of the photo.
								$description = $file_description,	# ( Optional ) A description of the photo. May contain some limited HTML.
								$tags = $file_tag,					# ( Optional ) A space-seperated list of tags to apply to the photo.
								$is_public = "0",					# ( Optional ) Set to 0 for no, 1 for yes. Specifies who can view the photo.
								$is_friend = "0",					# ( Optional ) Set to 0 for no, 1 for yes. Specifies who can view the photo.
								$is_family = "1"					# ( Optional ) Set to 0 for no, 1 for yes. Specifies who can view the photo.
							);

							if ( $file_upload == false ) {
								$log->logError ( "ERROR UPLOADING : " . $file_basename );
							} else {
								$log->logDebug ( "Set the content type of " . $file_basename );
								$content_type = "1";				# ( Optional ) Set to 1 to keep the photo in global search results, 2 to hide from public searches.
								$f->photos_setContentType ($file_upload, $content_type);
								$log->logDebug ( "Set the safety level of " . $file_basename );
								$safety_level = "1";				# ( Optional ) Set to 1 for Photo, 2 for Screenshot, or 3 for Other.
								$hidden = "2";						# ( Optional ) Set to 1 for Safe, 2 for Moderate, or 3 for Restricted.
								$f->photos_setSafetyLevel ($file_upload, $safety_level, $hidden);
							}

							// Check if photoset already exists
							// Create it if it doesn't
							$photoset_list = $f->photosets_getList ( );

							// Create / Add file to photoset
							if ( empty ( $photoset_list['photoset'] ) ) {
								// Photoset doesn't exists so we create it and add the file to it
								$log->logInfo ( "CREATE PHOTOSET : " . $photoset_title );
								$log->logInfo ( "ADD TO PHOTOSET : " . $file_basename );
								$f->photosets_create ( $photoset_title, $photoset_description, $file_upload );
							} else {
								foreach ( $photoset_list['photoset'] as $photosets => $photoset ) {
									// echo $photoset['title'] ." == ". $photoset_title.PHP_EOL;
									// echo $photoset['description'] ." == ". $photoset_description.PHP_EOL;
									if ( ( $photoset['title'] == $photoset_title ) && ( $photoset['description'] == $photoset_description ) ) {
										// Photoset already exists so we add the file to it
										$log->logInfo ( "ADD TO PHOTOSET : " . $file_basename );
										$f->photosets_addPhoto ( $photoset['id'], $file_upload );
										// Exits the foreach loop
										continue 2;
									} else {
										// Photoset doesn't exists so we create it and add the file to it
										$log->logInfo ( "CREATE PHOTOSET : " . $photoset_title );
										$log->logInfo ( "ADD TO PHOTOSET : " . $file_basename );
										$f->photosets_create ( $photoset_title, $photoset_description, $file_upload );
										// Exits the foreach loop
										continue 2;
									}
								}
							}
						} else {
							$log->logInfo ( "The file '" . $file_basename . "' has already been uploaded" );
						}
					} else {
						$log->logDebug ( "The file '" . $file_basename . "' is not part of the allowed file type list" );
					}
				} else {
					$log->logDebug ( "The file path '" . $file . "' contains a folder that is part of the exclusion list" );
				}
			} else {
				$log->logDebug ( "The path '" . $file . "' is not a file" );
			}
		}
	}

	if ( array_key_exists ( "cleanup-local", $arguments ) ) {
		// TODO : CLEANUP-LOCAL
		$log->logDebug ( "Starting cleanup of local files" );
	}

	if ( array_key_exists ( "cleanup-flickr", $arguments ) ) {
		// TODO : CLEANUP-FLICKR
		$log->logDebug ( "Starting cleanup of Flickr files" );
	}

	function str_contain ( $path, $exclusions ) {
		// Add a trailing slash for the purpose of checking the folder exclusion
		$path = $path . DIRECTORY_SEPARATOR;
		foreach ( $exclusions as $exclusion ) {
			$pos = strpos ( $path, DIRECTORY_SEPARATOR . $exclusion . DIRECTORY_SEPARATOR );
			if( $pos === false ) {
				// string $exclusion NOT found in $path
			}
			else {
				// string $exclusion found in $path
				return true;
			}
		}
		// string $exclusion NOT found in $path
		return false;
	}
	
	// We do not use phpFlickr $f->auth ( $perms ) because it only works for web apps
	function auth_desktop ( $f, $log, $perms = "read" ) {
		$token_filename = "flickr.token";
		if ( file_exists ( $token_filename ) ) {
			// Read the content of the token file and put it in a variable
			$log->logDebug ( "Reading auth token from file" );
			$_SESSION["phpFlickr_auth_token"] = file_get_contents ( $token_filename ) ;
		}	
		
		if ( empty ( $_SESSION['phpFlickr_auth_token'] ) && empty ( $f->token ) ) {	
			// The app makes a background call to flickr.auth.getFrob
			$log->logDebug ( "Getting FROB from Flickr" );
			$frob = $f->auth_getFrob ( );
			if ( $frob == false ) {
				$log->logError ( "Error getting FROB from Flickr" );
				exit ( );
			}

			// The user clicks on the link and launches a browser window with the URL
			// The user then will authorize the app
			$log->logDebug ( "Authorizing app on Flickr" );
			echo PHP_EOL;
			echo "Please authorize this application with Flickr at " . PHP_EOL;
			$api_sig = md5 ( $f->secret . "api_key" . $f->api_key . "frob" . $frob . "perms" . $perms ) ;
			echo "http://www.flickr.com/services/auth/?api_key=" . $f->api_key . "&frob=" . $frob . "&perms=" . $perms . "&api_sig=". $api_sig . PHP_EOL;
			echo PHP_EOL;
			echo "Press the enter key once you have authorized the application" . PHP_EOL;
			$handle = fopen ( "php://stdin", "r" );
			$line = fgets ( $handle );
			echo "Thank you for authenticating the app" . PHP_EOL;
			
			// The app makes a background call to flickr.auth.getToken
			
			$log->logDebug ( "Retrieving credentials from Flickr" );
			$credentials = $f->auth_getToken ( $frob );
			if ( $credentials == false ) {
				$log->logError ( "Credentials couldn't be retrieved from Flickr" );
				exit ( );
			} else {
				// Write the token to a file for later use
				$log->logDebug ( "Writing token to file for later use" );
				if ( ! file_put_contents ( $token_filename, $credentials['token'] ) ) {
					$log->logError ( "There was an issue saving the auth token as a file" );
					exit ( ) ;
				}
			}
			return $credentials;
		} else {
			$tmp = $f->die_on_error;
			$f->die_on_error = false;
			$credentials = $f->auth_checkToken ( );
			if ( $f->error_code !== false ) {
				unset ( $_SESSION['phpFlickr_auth_token'] );
				$log->logDebug ( "Re-authorizing app on Flickr" );
				auth_desktop ( $f, $log, $perms );
			}
			$f->die_on_error = $tmp;
			return $credentials['perms'];
		}
	}
	
?>

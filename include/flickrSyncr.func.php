<?php if ( ! defined ( 'DIRNAME' ) ) exit ( 'No direct script access allowed' );
    /*
    |--------------------------------------------------------------------------
    |  Flickr Syncr
    |  ============
    |  Written by Ken (kenijo@gmail.com)
    |
    |  This file groups all the functions for Flickr Syncr, a command line script !
    |--------------------------------------------------------------------------
    */

	function getArguments ( $cfg, $log )
	{
		// Load CLI class and instanciate it
	    require_once ( 'phpCLI.class.php' );
	    $cli = new phpCLI ( $cfg );

	    // Ckeck if we are in command line
	    if ( ! $cli->is_cli ( ) )
	    {
	        $msg = BASENAME . ' is is intended to be run in command line.';
	        $log->logError ( $msg );
	        exit ( $msg );
	    }

	    // Set default arguments if debug mode is true
	    if ( sizeof ( $_SERVER['argv'] ) <= 1 && $cfg['dbg_mode'] == true )
	    {
	        $log->logDebug ( 'Debug mode is enbaled, using debug arguments' );
	        $_SERVER['argv'] = explode ( ' ', $cfg['dbg_arguments'] );
	    }

	    // Parse all arguments
	    $log->logNotice ( 'Parsing arguments' );
	    $arguments = $cli->getArgs ( $_SERVER['argv'] );

	    // If we don't have arguments, we display the help message
	    if ( sizeof ( $arguments ) <= 0 )
	    {
	        $log->logError ( 'Missing arguments' );
	        $cli->generate_help ( $cfg );
	        exit ( );
	    }
	    // If an argument is invalid, we display the help message
	    else if ( ! $cli->is_arguments_valid ( $arguments ) )
	    {
	        $log->logError ( 'Invalid arguments' );
	        $cli->generate_help ( $cfg );
	        exit ( );
	    }

	    return $arguments;
	}

	function getAllowedExtensions ( $arguments, $cfg )
	{
	    // Merge allowed extension arrays if needed
	    if ( array_key_exists ( 'ignore-images', $arguments ) )
	    {
	        $allowed_filetypes = $cfg['allowed_videos'];
	    }
	    else if ( array_key_exists ( 'ignore-videos', $arguments ) )
	    {
	        $allowed_filetypes = $cfg['allowed_images'];
	    }
	    else
	    {
	        $allowed_filetypes = array_merge ( $cfg['allowed_images'], $cfg['allowed_videos'] );
	    }

	    return array_map ( 'strtolower', $allowed_filetypes );
	}

	function validatePath ( $arguments, $log )
	{
	    // Check the validity of the path
	    if ( ! array_key_exists ( 'path', $arguments ) || ! is_dir ( $arguments['path'] ) )
	    {
	        $log->logError ( 'The path is invalid' );
	        exit ( );
	    }
	    // Cleanup the path
	    else
	    {
	        $log->logDebug ( 'Cleaning up path to prevent problems' );
	        $arguments['path'] = str_replace ( array ( '/', '\\' ), DIR_SEPARATOR, $arguments['path'] );
	        $arguments['path'] = rtrim ( $arguments['path'], DIR_SEPARATOR );
	    }

	    return $arguments;
	}

    // We do not use phpFlickr $f->auth ( $perms ) because it only works for web apps
    function connectToFlickr ( $f, $log, $perms = 'read' )
    {
        $token_filename = DIRNAME . DIR_SEPARATOR . 'flickrSyncr.token';

        if ( file_exists ( $token_filename ) )
        {
            // Read the content of the token file and put it in a variable
            $log->logNotice ( 'Reading auth token from file' );
            $_SESSION['phpFlickr_auth_token'] = file_get_contents ( $token_filename ) ;
        }

        if ( empty ( $_SESSION['phpFlickr_auth_token'] ) && empty ( $f->token ) )
        {
            // The app makes a background call to flickr.auth.getFrob
            $log->logDebug ( 'Getting FROB from Flickr' );
            $frob = $f->auth_getFrob ( );
            if ( $frob == false )
            {
                $log->logError ( 'Error getting FROB from Flickr' );
                exit ( );
            }

            // The user clicks on the link and launches a browser window with the URL
            // The user then will authorize the app
            $log->logNotice ( 'Authorizing app on Flickr' );
            echo PHP_EOL;
            echo 'Please authorize this application with Flickr at ' . PHP_EOL;
            echo PHP_EOL;
            $api_sig = md5 ( $f->secret . 'api_key' . $f->api_key . 'frob' . $frob . 'perms' . $perms ) ;
            echo 'http://www.flickr.com/services/auth/?api_key=' . $f->api_key . '&frob=' . $frob . '&perms=' . $perms . '&api_sig='. $api_sig . PHP_EOL;
            echo PHP_EOL;
            echo 'Press the enter key once you have authorized the application' . PHP_EOL;
            $handle = fopen ( 'php://stdin', 'r' );
            $line = fgets ( $handle );
            echo 'Thank you for authenticating the app' . PHP_EOL;

            // The app makes a background call to flickr.auth.getToken
            $log->logDebug ( 'Retrieving credentials from Flickr' );
            $credentials = $f->auth_getToken ( $frob );
            if ( $credentials == false )
            {
                $log->logError ( 'Credentials could not be retrieved from Flickr' );
                exit ( );
            }
            else
            {
                // Write the token to a file for later use
                $log->logDebug ( 'Writing token to file for later use' );
                if ( ! file_put_contents ( $token_filename, $credentials['token'] ) )
                {
                    $log->logError ( 'There was an issue saving the auth token as a file' );
                    exit ( ) ;
                }
            }
        }
        else
        {
            $tmp = $f->die_on_error;
            $f->die_on_error = false;
            $credentials = $f->auth_checkToken ( );
            if ( $f->error_code !== false )
            {
                unset ( $_SESSION['phpFlickr_auth_token'] );
                unlink ( $token_filename );
                $log->logDebug ( 'Re-authorizing app on Flickr' );
                connectToFlickr ( $f, $log, $perms );
            }
            $f->die_on_error = $tmp;
        }

        $log->logNotice ( 'Connected as ' . $credentials['user']['fullname'] . ' to the account \'' . $credentials['user']['username'] . '\' with permission \'' . $credentials['perms'] . '\'' );
        return $credentials;
    }

    function prepareFileInfo ( $arguments, $fileInfo )
    {
        $pathInfo = pathinfo ( $fileInfo );

        $file['dirname'] 		= substr ( $pathInfo['dirname'], strlen ( $arguments['path'] ), strlen ( $pathInfo['dirname'] ) );
		$file['filename']		= $pathInfo['filename'];
		$file['extension']		= $pathInfo['extension'];
        $file['title'] 			= $pathInfo['filename'];
		$file['description']	= $file['dirname'] . DIR_SEPARATOR . $file['title'];
        if ( array_key_exists ( 'generate-tags', $arguments ) )
        {
            $file['tags'] 		= $file['dirname'];
            $file['tags'] 		= preg_replace ( '/\[([\d -]*?)\]/' , ' ' , $file['tags'] );
            $file['tags'] 		= str_replace ( DIR_SEPARATOR, ' ', $file['tags'] );
            $file['tags'] 		= preg_replace ( '/#\b\w{1,3}(|\b)#u/' , ' ' , $file['tags'] );
            $file['tags'] 		= preg_replace( '/\s+/', ' ',$file['tags'] );
            $file['tags'] 		= trim ( $file['tags'] );
            $file_tags_array 	= explode ( ' ', $file['tags'] );
            $file_tags_array 	= array_unique ( $file_tags_array );
            $file['tags'] 		= implode ( ' ', $file_tags_array );
        }

		return $file;
    }

    function deleteCollections ( $f, $log )
    {
        $log->logNotice ( 'FLICKR - Deleting COLLECTIONS ...' );

        $more = NULL;
        do
        {
            $collections = $f->collections_getTree ( );
            if ( isset ( $collections['collections']['collection'] ) && $collections['collections']['collection'] != NULL )
            {
                $count = sizeof( $collections['collections']['collection'] );
                $log->logNotice ( '         ' . $count . ' ' . $more . ' COLLECTIONS to delete' );

                for ( $i = 0;  $i < $count;  $i++ )
                {
                    $result = $f->collections_delete ( $collections['collections']['collection'][$i]['id'], true );
                    if ( $result == true )
                    {
                        $log->logDebug ( '         COLLECTION is being deleted' );
                        $log->logDebug ( '         COLLECTION ' . $collections['collections']['collection'][$i]['title'] );
                        $log->logDebug ( '         COLLECTION flickr_id=' . $collections['collections']['collection'][$i]['id'] );
                    }
                    else
                    {
                        $log->logError ( '         COLLECTION could not be deleted' );
                        $log->logError ( '         ' . $f->getError ( ) );
                        $log->logError ( '         COLLECTION ' . $collections['collections']['collection'][$i]['title'] );
                        $log->logError ( '         COLLECTION flickr_id=' . $collections['collections']['collection'][$i]['id'] );
                    }
                }

                $more = ' more';
            }
            else
            {
                $count = 0;
                $log->logNotice ( '         No' . $more . ' COLLECTION to delete' );
            }
        }
        while ( $count > 0 ) ;
    }

    function deleteSets ( $f, $log )
    {
        $log->logNotice ( 'FLICKR - Deleting SETS ...' );

        $more = NULL;
        do
        {
            $sets = $f->photosets_getList ( );
            if ( isset ( $sets['photoset'] ) && $sets['photoset'] != NULL )
            {
                $count = sizeof( $sets['photoset'] );
                $log->logNotice ( '         ' . $count . ' ' . $more . ' SETS to delete' );

                for ( $i = 0;  $i < $count;  $i++ )
                {
                    $files = NULL;
                    $fileList = $f->photosets_getPhotos ( $sets['photoset'][$i]['id'] );
                    foreach ( $fileList['photoset']['photo'] as $file_key => $file_value )
                    {
                        $result = $f->photos_delete ( $file_value['id'] );
                        if ( $result == true )
                        {
                            $log->logDebug ( '         FILE is being deleted' );
                            $log->logDebug ( '         FILE ' . $file_value['title'] );
                            $log->logDebug ( '         FILE flickr_id=' . $file_value['id'] );
                        }
                        else
                        {
                            $log->logError ( '         FILE could not be deleted' );
                            $log->logError ( '         ' . $f->getError ( ) );
                            $log->logError ( '         FILE ' . $file_value['title'] );
                            $log->logError ( '         FILE flickr_id=' . $file_value['id'] );
                        }
                    }

                    $result = $f->photosets_getInfo ( $sets['photoset'][$i]['id'] );
                    if ( $result != false )
                    {
                        $result = $f->photosets_delete ( $sets['photoset'][$i]['id'] );
                        if ( $result == true )
                        {
                            $log->logDebug ( '         SET is being deleted' );
                            $log->logDebug ( '         SET ' . $sets['photoset'][$i]['title'] );
                            $log->logDebug ( '         SET flickr_id=' . $sets['photoset'][$i]['id'] );
                        }
                        else
                        {
                            $log->logError ( '         SET could not be deleted' );
                            $log->logError ( '         ' . $f->getError ( ) );
                            $log->logError ( '         SET ' . $sets['photoset'][$i]['title'] );
                            $log->logError ( '         SET flickr_id=' . $sets['photoset'][$i]['id'] );
                        }
                    }
                }

                $more = ' more';
            }
            else
            {
                $count = 0;
                $log->logNotice ( '         No' . $more . ' SET to delete' );
            }
        }
        while ( $count > 0 ) ;
    }

    function deleteFiles ( $f, $log, $credentials )
    {
        $log->logNotice ( 'FLICKR - Deleting remaining FILES ...' );

        $more = NULL;
        do
        {
            $files = $f->people_getPhotos ( $credentials['user']['nsid'] );

            if ( isset ( $files['photos']['photo'] ) && $files['photos']['photo'] != NULL )
            {
                $count = sizeof( $files['photos']['photo'] );
                $log->logNotice ( '         ' . $count . ' ' . $more . ' FILES to delete' );

                for ( $i = 0;  $i < $count; $i++ )
                {
                    $file = $files['photos']['photo'][$i]['id'];

                    $result = $f->photos_delete ( $file );

                    if ( $result == true )
                    {
                        $log->logDebug ( '         FILES are being deleted' );
                        $log->logDebug ( '         FILES flickr_id=' . $file );
                    }
                    else
                    {
                        $log->logError ( '         FILES could not be deleted' );
                        $log->logError ( '         ' . $f->getError ( ) );
                        $log->logError ( '         FILES flickr_id=' . $file );
                    }
                }

                $more = ' more';
            }
            else
            {
                $count = 0;
                $log->logNotice ( '         No' . $more . ' FILE to delete' );
            }
        }
        while ( $count > 0 ) ;

        $log->logNotice ( 'FLICKR - COLLECTIONS, SETS and FILES deleted' );
    }

    function createCollectionIcons ( $f )
    {
        $result = $f->collections_getTree ( );

        if ( $result != false)
        {
            $result = getRecursiveArray ( $result, 'collection', 'id' );
            if ( $result != NULL )
            {
                foreach ( $result as $collection_name => $collection_id )
                {
                    $res = $f->collections_getInfo ( $collection_id );

                    if ( $res['collection']['iconphotos'] != NULL )
                    {
                        // Create COLLECTION icons
                        $r = $f->collections_getInfosuggestIconPhotos ( $collection_id );
                        
                        if ( $r != false )
                        {
                            $iconList = NULL;

                            $icons = $r['photos']['photo'];
                            shuffle ( $icons );

                            for ( $i = 0; $i < 12; $i++ )
                            {
                                $rand = rand ( 0, sizeof ( $icons ) - 1 );
                                $iconList .= $icons[$rand]['id'] . ',';
                            }
                            $f->collections_createIcon ( $collection_id, $iconList );
                        }
                    }
                }
            }
        }     
    }

    function getRecursiveArray ( $array, $arrayName, $arrayKey, &$newArr = NULL )
    {
        foreach ( $array as $arrKey => $arrValue )
        {
            if ( is_array ( $arrValue ) )
            {          
                if ( $arrKey === $arrayName )
                {               
                    for( $i = 0; $i < sizeof ( $arrValue ); $i++ )
                    {
                        $newArr[] = $arrValue[$i][$arrayKey];
                    }
                }
                getRecursiveArray ( $arrValue, $arrayName, $arrayKey, $newArr );
            }
        }
        return $newArr;
    }

    class extensionRecursiveFilterIterator extends RecursiveFilterIterator
    {
        public static $ALLOW_EXTENSIONS_FILTERS;

        public static $EXCLUDE_FOLDERS_FILTERS;

        public function accept()
        {
            if ( ! is_file ( $this->current() ) )
            {
                if ( ! $this->str_contain ( ) )
                {
                    return true;
                }
            }
            else
            {
                if( in_array($this->current()->getExtension(), self::$ALLOW_EXTENSIONS_FILTERS, true ) )
                {
                    return true;
                }
            }
        }

        private function str_contain ( )
        {
            // Add a trailing slash for the purpose of checking the folder exclusion
            $path = $this->current() . DIR_SEPARATOR;
            foreach ( self::$EXCLUDE_FOLDERS_FILTERS as $exclusion )
            {
                $pos = strpos ( $path, DIR_SEPARATOR . $exclusion . DIR_SEPARATOR );
                if( $pos === false )
                {
                    // string $exclusion NOT found in $path
                }
                else
                {
                    // string $exclusion found in $path
                    return true;
                }
            }
            // string $exclusion NOT found in $path
            return false;
        }
    }
<?php

namespace C:\wamp\www\flickrSyncr\include;
    /*
    |--------------------------------------------------------------------------
    |  Flickr Syncr
    |  ============
    |  Written by Ken (kenijo@gmail.com)
    |
    |  This file groups all the functions for Flickr Syncr, a command line script !
    |--------------------------------------------------------------------------
    */

    class flickrSyncrFunc
    {
        public $args;
        private $cfg;
        private $cli;
        private $credentials;
        private $f;
        private $log;

        function __construct ( $cfg, $cli, $f, $log )
        {
            $this->cfg = $cfg;
            $this->cli = $cli;
            $this->f = $f;
            $this->log = $log;
        }

        function processArgs ( )
        {
            // Ckeck if we are in command line
            if ( ! $this->cli->is_cli ( ) )
            {
                $msg = BASENAME . ' is is intended to be run in command line.';
                $this->log->logError ( $msg );
                exit ( $msg );
            }

            // Set default arguments if debug mode is true
            if ( sizeof ( $_SERVER['argv'] ) <= 1 && $this->cfg['dbg_mode'] == true )
            {
                $this->log->logDebug ( 'Debug mode is enbaled, using debug arguments' );
                $_SERVER['argv'] = explode ( ' ', $this->cfg['dbg_arguments'] );
            }

            // Parse all arguments
            $this->log->logInfo ( 'Parsing arguments' );
            $args = $this->cli->getArgs ( $_SERVER['argv'] );

            // If we don't have arguments, we display the help message
            if ( sizeof ( $args ) <= 0 )
            {
                $this->log->logError ( 'Missing arguments' );
                $this->cli->generate_help ( $this->cfg );
                exit ( );
            }
            // If an argument is invalid, we display the help message
            else if ( ! $this->cli->is_argument_valid ( $args ) )
            {
                $this->log->logError ( 'Invalid arguments' );
                $this->cli->generate_help ( $this->cfg );
                exit ( );
            }

            $this->args = $args;
        }

        function getAllowedExtensions ( )
        {
            $this->log->logDebug ( 'Merging the list of allowed extensions' );

            // Merge allowed extension arrays if needed
            if ( array_key_exists ( 'ignore-images', $this->args ) )
            {
                $allowed_filetypes = $this->cfg['allowed_videos'];
            }
            else if ( array_key_exists ( 'ignore-videos', $this->args ) )
            {
                $allowed_filetypes = $this->cfg['allowed_images'];
            }
            else
            {
                $allowed_filetypes = array_merge ( $this->cfg['allowed_images'], $this->cfg['allowed_videos'] );
            }

            return array_map ( 'strtolower', $allowed_filetypes );
        }

        function validatePath ( $arg )
        {
            // Check the validity of the path
            if ( ! is_dir ( $this->args[$arg] ) )
            {
                $this->log->logError ( 'The path is invalid : ' . $this->args[$arg] );
                exit ( );
            }
            // Cleanup the path
            else
            {
                $this->log->logDebug ( 'Cleaning up path to prevent problems' );
                $this->args[$arg] = str_replace ( array ( '//', '\\\\' ), DIR_SEPARATOR, $this->args[$arg] );
                $this->args[$arg] = str_replace ( array ( '/', '\\' ), DIR_SEPARATOR, $this->args[$arg] );
                $this->args[$arg] = rtrim ( $this->args[$arg], DIR_SEPARATOR );
            }
        }

        // We do not use phpFlickr $this->f->auth ( $perms ) because it only works for web apps
        function connectToFlickr ( $perms = 'read' )
        {
            $this->log->logNotice ( 'Checking Flickr authentication' );

            $token_filename = DIRNAME . DIR_SEPARATOR . 'profile.token';

            if ( file_exists ( $token_filename ) )
            {
                // Read the content of the token file and put it in a variable
                $this->log->logNotice ( 'Reading auth token from file' );
                $_SESSION['phpFlickr_auth_token'] = file_get_contents ( $token_filename ) ;
            }

            if ( empty ( $_SESSION['phpFlickr_auth_token'] ) && empty ( $this->f->token ) )
            {
                // The app makes a background call to flickr.auth.getFrob
                $this->log->logDebug ( 'Getting FROB from Flickr' );
                $this->frob = $this->f->auth_getFrob ( );
                if ( $this->frob == false )
                {
                    $this->log->logError ( 'Error getting FROB from Flickr' );
                    exit ( );
                }

                // The user clicks on the link and launches a browser window with the URL
                // The user then will authorize the app
                $this->log->logNotice ( 'Authorizing app on Flickr' );
                echo PHP_EOL;
                echo 'Please authorize this application with Flickr at ' . PHP_EOL;
                echo PHP_EOL;
                $api_sig = md5 ( $this->f->secret . 'api_key' . $this->f->api_key . 'frob' . $this->frob . 'perms' . $perms ) ;
                echo 'http://www.flickr.com/services/auth/?api_key=' . $this->f->api_key . '&frob=' . $this->frob . '&perms=' . $perms . '&api_sig='. $api_sig . PHP_EOL;
                echo PHP_EOL;
                echo 'Press the enter key once you have authorized the application' . PHP_EOL;
                $handle = fopen ( 'php://stdin', 'r' );
                $line = fgets ( $handle );
                echo 'Thank you for authenticating the app' . PHP_EOL;

                // The app makes a background call to flickr.auth.getToken
                $this->log->logDebug ( 'Retrieving credentials from Flickr' );
                $credentials = $this->f->auth_getToken ( $this->frob );
                if ( $credentials == false )
                {
                    $this->log->logError ( 'Credentials could not be retrieved from Flickr' );
                    exit ( );
                }
                else
                {
                    // Write the token to a file for later use
                    $this->log->logDebug ( 'Writing token to file for later use' );
                    if ( ! file_put_contents ( $token_filename, $credentials['token'] ) )
                    {
                        $this->log->logError ( 'There was an issue saving the auth token as a file' );
                        exit ( ) ;
                    }
                }
            }
            else
            {
                $tmp = $this->f->die_on_error;
                $this->f->die_on_error = false;
                $credentials = $this->f->auth_checkToken ( );
                if ( $this->f->error_code !== false )
                {
                    unset ( $_SESSION['phpFlickr_auth_token'] );
                    unlink ( $token_filename );
                    $this->log->logDebug ( 'Re-authorizing app on Flickr' );
                    connectToFlickr ( $this->f, $log, $perms );
                }
                $this->f->die_on_error = $tmp;
            }

            $this->log->logNotice ( 'Connected as ' . $credentials['user']['fullname'] . ' to the account \'' . $credentials['user']['username'] . '\' with permission \'' . $credentials['perms'] . '\'' );
            $this->credentials = $credentials;
            return $credentials;
        }

        function prepareFileInfo ( )
        {
            $pathInfo = pathinfo ( $this->fileInfo );

            $this->file['dirname']      = substr ( $pathInfo['dirname'], strlen ( $this->args['upload'] ), strlen ( $pathInfo['dirname'] ) );
            $this->file['filename']     = $pathInfo['filename'];
            $this->file['extension']    = $pathInfo['extension'];
            $this->file['title']        = $pathInfo['filename'];
            $this->file['description']  = $this->file['dirname'] . DIR_SEPARATOR . $this->file['title'];
            if ( array_key_exists ( 'generate-tags', $this->args ) )
            {
                $this->generateTags ( );
            }

            return $this->file;
        }

        function generateTags ( )
        {
            // Get full path to generate tags
            $this->file['tags']     = $this->file['dirname'];

            // Extract date to generate year and month tags
            $pattern = "/\b([1][9][0-9]{2}|[2][0][0-9]{2})-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])\b/";
            preg_match_all ( $pattern, $this->file['tags'], $matches );
            $timestamp = mktime(0, 0, 0, $matches[2][0], $matches[3][0], $matches[1][0]);
            $extractedYear = date('Y', $timestamp );
            $extractedMonth = date('F', $timestamp );
            $extractedDay = date('d', $timestamp );
            $this->file['tags'] = $extractedMonth . ' ' . $extractedYear . ' ' . $this->file['tags'];

            // Remove the date string to keep only the year and month
            $this->file['tags'] = str_replace($matches[0][0], '',  $this->file['tags']);

            // Remove words that have between 1 and 3 characters only as we assume them to be useless
            $this->file['tags']     = preg_replace ( '/#\b\w{1,3}(|\b)#u/' , ' ' , $this->file['tags'] );

            // Remove non word characters
            $this->file['tags']     = preg_replace ( '/\W/' , ' ' , $this->file['tags'] );

            // Replace whitespace(s) with a space
            $this->file['tags']     = preg_replace( '/\s+/', ' ',$this->file['tags'] );

            // Remove extra spaces
            $this->file['tags']     = trim ( $this->file['tags'] );

            // Remove duplicates
            $file_tags_array  = explode ( ' ', $this->file['tags'] );
            $file_tags_array  = array_unique ( $file_tags_array );
            $this->file['tags']     = implode ( ' ', $file_tags_array );
        }

        function deleteCollections ( )
        {
            $this->log->logNotice ( 'FLICKR - Deleting COLLECTIONS ...' );

            $more = NULL;
            do
            {
                $collections = $this->f->collections_getTree ( );
                if ( isset ( $collections['collections']['collection'] ) && $collections['collections']['collection'] != NULL )
                {
                    $count = sizeof( $collections['collections']['collection'] );
                    $this->log->logNotice ( '         ' . $count . ' ' . $more . ' COLLECTIONS to delete' );

                    for ( $i = 0;  $i < $count;  $i++ )
                    {
                        $result = $this->f->collections_delete ( $collections['collections']['collection'][$i]['id'], true );
                        if ( $result == true )
                        {
                            $this->log->logDebug ( '         COLLECTION is being deleted' );
                            $this->log->logDebug ( '         COLLECTION ' . $collections['collections']['collection'][$i]['title'] );
                            $this->log->logDebug ( '         COLLECTION flickr_id=' . $collections['collections']['collection'][$i]['id'] );
                        }
                        else
                        {
                            $this->log->logError ( '         COLLECTION could not be deleted' );
                            $this->log->logError ( '         ' . $this->f->getError ( ) );
                            $this->log->logError ( '         COLLECTION ' . $collections['collections']['collection'][$i]['title'] );
                            $this->log->logError ( '         COLLECTION flickr_id=' . $collections['collections']['collection'][$i]['id'] );
                        }
                    }

                    $more = ' more';
                }
                else
                {
                    $count = 0;
                    $this->log->logNotice ( '         No' . $more . ' COLLECTION to delete' );
                }
            }
            while ( $count > 0 ) ;
        }

        function deleteSets ( )
        {
            $this->log->logNotice ( 'FLICKR - Deleting SETS ...' );

            $more = NULL;
            do
            {
                $sets = $this->f->photosets_getList ( );
                if ( isset ( $sets['photoset'] ) && $sets['photoset'] != NULL )
                {
                    $count = sizeof( $sets['photoset'] );
                    $this->log->logNotice ( '         ' . $count . ' ' . $more . ' SETS to delete' );

                    for ( $i = 0;  $i < $count;  $i++ )
                    {
                        $this->files = NULL;
                        $this->fileList = $this->f->photosets_getPhotos ( $sets['photoset'][$i]['id'] );
                        foreach ( $this->fileList['photoset']['photo'] as $this->file_key => $this->file_value )
                        {
                            $result = $this->f->photos_delete ( $this->file_value['id'] );
                            if ( $result == true )
                            {
                                $this->log->logDebug ( '         FILE is being deleted' );
                                $this->log->logDebug ( '         FILE ' . $this->file_value['title'] );
                                $this->log->logDebug ( '         FILE flickr_id=' . $this->file_value['id'] );
                            }
                            else
                            {
                                $this->log->logError ( '         FILE could not be deleted' );
                                $this->log->logError ( '         ' . $this->f->getError ( ) );
                                $this->log->logError ( '         FILE ' . $this->file_value['title'] );
                                $this->log->logError ( '         FILE flickr_id=' . $this->file_value['id'] );
                            }
                        }

                        $result = $this->f->photosets_getInfo ( $sets['photoset'][$i]['id'] );
                        if ( $result != false )
                        {
                            $result = $this->f->photosets_delete ( $sets['photoset'][$i]['id'] );
                            if ( $result == true )
                            {
                                $this->log->logDebug ( '         SET is being deleted' );
                                $this->log->logDebug ( '         SET ' . $sets['photoset'][$i]['title'] );
                                $this->log->logDebug ( '         SET flickr_id=' . $sets['photoset'][$i]['id'] );
                            }
                            else
                            {
                                $this->log->logError ( '         SET could not be deleted' );
                                $this->log->logError ( '         ' . $this->f->getError ( ) );
                                $this->log->logError ( '         SET ' . $sets['photoset'][$i]['title'] );
                                $this->log->logError ( '         SET flickr_id=' . $sets['photoset'][$i]['id'] );
                            }
                        }
                    }

                    $more = ' more';
                }
                else
                {
                    $count = 0;
                    $this->log->logNotice ( '         No' . $more . ' SET to delete' );
                }
            }
            while ( $count > 0 ) ;
        }

        function deleteFiles ( )
        {
            $this->log->logNotice ( 'FLICKR - Deleting remaining FILES ...' );

            $more = NULL;
            do
            {
                $this->files = $this->f->people_getPhotos ( $this->credentials['user']['nsid'] );

                if ( isset ( $this->files['photos']['photo'] ) && $this->files['photos']['photo'] != NULL )
                {
                    $count = sizeof( $this->files['photos']['photo'] );
                    $this->log->logNotice ( '         ' . $count . ' ' . $more . ' FILES to delete' );

                    for ( $i = 0;  $i < $count; $i++ )
                    {
                        $this->file = $this->files['photos']['photo'][$i]['id'];

                        $result = $this->f->photos_delete ( $this->file );

                        if ( $result == true )
                        {
                            $this->log->logDebug ( '         FILES are being deleted' );
                            $this->log->logDebug ( '         FILES flickr_id=' . $this->file );
                        }
                        else
                        {
                            $this->log->logError ( '         FILES could not be deleted' );
                            $this->log->logError ( '         ' . $this->f->getError ( ) );
                            $this->log->logError ( '         FILES flickr_id=' . $this->file );
                        }
                    }

                    $more = ' more';
                }
                else
                {
                    $count = 0;
                    $this->log->logNotice ( '         No' . $more . ' FILE to delete' );
                }
            }
            while ( $count > 0 ) ;

            $this->log->logNotice ( 'FLICKR - COLLECTIONS, SETS and FILES deleted' );
        }

        function createCollectionIcons ( )
        {
            $result = $this->f->collections_getTree ( );

            if ( $result != false)
            {
                $result = getRecursiveArray ( $result, 'collection', 'id' );
                if ( $result != NULL )
                {
                    foreach ( $result as $collection_name => $collection_id )
                    {
                        $res = $this->f->collections_getInfo ( $collection_id );

                        if ( $res['collection']['iconphotos'] != NULL )
                        {
                            // Create COLLECTION icons
                            $r = $this->f->collections_getInfosuggestIconPhotos ( $collection_id );

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
                                $this->f->collections_createIcon ( $collection_id, $iconList );
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
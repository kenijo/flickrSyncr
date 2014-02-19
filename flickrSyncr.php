<?php
    /*
    |--------------------------------------------------------------------------
    |  Flickr Syncr
    |  ============
    |  Written by Ken (kenijo@gmail.com)
    |
    |  This is a command line script !
    |
    |  This PHP script allows you to sync a local folder with your Flickr account in command line
    |  Through batch upload / download of photos and video on / from Flickr
    |
    |  Usage: php flickrSyncr.php [arguments]
    |
    |  Example: php flickrSyncr.php --upload --path=/path/to/my/photo --generate-tags
    |
    |  --help            Print Help ( this message ) and exit
    |  --upload          Specify the folder to upload ( default is current directory )
    |  --download        Specify the folder where to download the photos from flickr ( default is current directory )
    |  --path            Specify the folder to use ( default is current directory )
    |  --cleanup-local   Delete local files that are not on Flickr
    |  --cleanup-flickr  Delete Flickr files that are not on the local disk
    |  --ignore-images   Ignore image files
    |  --ignore-videos   Ignore video files when download or uploading
    |  --generate-tags   Generate tags based on the name of the photoset when uploading
    |--------------------------------------------------------------------------
    */

    ///////////////////////////////////////////////////////////////////////////
    // TODO : --download
    // TODO : --cleanup-flickr
    // TODO : Check space available on Flickr
    // TODO : Add function to sent email on upload/download error
    // TODO : Add parameters into flickrSyncr.conf.php and allow to enable batch work for multiple folders / users
    // TODO : Create a way to authorize multiple users and save their token in a dedicated folder.
    //        add a user profile, api_key and api_secret in an array in the flickrSyncr.conf.php
    //        then use --auth-profile=profile to authorize that account
    //        save tokens as profile.flickr.token
    ///////////////////////////////////////////////////////////////////////////

    // Get the path info of the current script
    $pathInfo = pathinfo ( __FILE__ );
    define ( 'DIRNAME', $pathInfo['dirname'] );
    define ( 'BASENAME', $pathInfo['basename'] );

    // Load libraries, class and configuration
    require_once ( 'include/flickrSyncrFunc.class.php' );
    require_once ( 'include/flickrSyncrSQLite.class.php' );
    require_once ( 'include/KLogger.class.php' );
    require_once ( 'include/phpCLI.class.php' );
    require_once ( 'include/phpFlickr.class.php' );
    require_once ( 'include/flickrSyncr.conf.php' );
            
    // New instance of logger
    $log = new KLogger ( $cfg['log_folder'], $cfg['log_level'] );

    $log->writeFreeFormLine ( PHP_EOL );
    $log->logNotice ( '----------------------------------------------' );
    $log->logNotice ( '           STARTING ' . BASENAME );
    $log->logNotice ( '----------------------------------------------' );

    // New instance of command line
    $cli = new phpCLI ( $cfg );

    // New instance of PHPFlickr
    $f = new phpFlickr ( $cfg['api_key'], $cfg['api_secret'] ) ;
   
    // New instance of flickrSyncr functions
    $fsf = new flickrSyncrFunc ( $cfg, $cli, $f, $log );
    // Process arguments or use the debug ones
    $fsf->processArgs ( );

    // New instance of flickrSyncr SQLite
    $db = new flickrSyncrSQLite ( $f, $cfg, $fsf->args, $log );

    // Check Flickr credentials
    $credentials = $fsf->connectToFlickr ( 'delete' );

    // Check if we are trying to authorize the app
    // If we are, this should be done in command line and be the only thing to be executed
    if ( array_key_exists ( 'auth', $fsf->args ) )
    {
        $log->logNotice ( $cfg['app_name'] . ' has been successfully authenticated with Flickr account \'' . $credentials['user']['username'] . '\'.' );
        exit ( );
    }

    // Consolidate the allowed file types
    $allowed_filetypes = $fsf->getAllowedExtensions ( );
    
    // Cleanup log folder
    $dirIterator            = new RecursiveDirectoryIterator ( $cfg['log_folder'], FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS );
    $iterator               = new RecursiveIteratorIterator ( $dirIterator );
    foreach ($iterator as $filePath => $fileInfo)
    {
        $pathInfo = pathinfo ( $fileInfo );
        $path = $pathInfo['dirname'] . DIR_SEPARATOR . $pathInfo['basename'];
        if ( time ( ) - filemtime ( $path ) >= $cfg['log_days'] * 24 * 60 * 60 )
        {
            unlink ( $path );
        }
    }

    // This code is executed if we want to upload files to Flickr
    if ( array_key_exists ( 'upload', $fsf->args ) )
    {
        $fsf->validatePath ( 'upload' );

        //count the number of files uploaded
        $log->logNotice ( 'Uploading files to FLICKR' );

        // Get the list of files, filtered by allowed extensions and sorted alphabetically
        $dirIterator            = new RecursiveDirectoryIterator ( $fsf->args['upload'], FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS );
        $filteredIterator       = new extensionRecursiveFilterIterator ( $dirIterator );
        extensionRecursiveFilterIterator::$ALLOW_EXTENSIONS_FILTERS    = $allowed_filetypes;
        extensionRecursiveFilterIterator::$EXCLUDE_FOLDERS_FILTERS     = $cfg['exclude_folder_list'];
        $iterator               = new RecursiveIteratorIterator ( $filteredIterator );

        $fileList = iterator_to_array ( $iterator , true );
        natsort ( $fileList );
        
        // Parse the list of files returned
        foreach ($fileList as $filePath => $fileInfo)
        {
            // Add the file to the database
            $file = prepareFileInfo ( $fileInfo );
            $db->addFile ( $file );
        }

        createCollectionIcons ( );

        $log->logNotice ( 'Uploading files to FLICKR is done' );
    }

    if ( array_key_exists ( 'download', $fsf->args ) )
    {
        // TODO : count number of files downloaded
        $fsf->validatePath ( 'download' );

        $log->logNotice ( 'Downloading files from FLICKR' );
        $log->logNotice ( 'Downloading files to FLICKR is done' );
    }

    if ( array_key_exists ( 'cleanup-flickr', $fsf->args ) )
    {
        $fsf->validatePath ( 'cleanup-flickr' );

        // TODO : Count number of files deleted
        $log->logNotice ( 'Cleaning up files on FLICKR' );
        $log->logNotice ( 'Cleaning up files on FLICKR is done' );
    }

    if ( array_key_exists ( 'flush-flickr', $fsf->args ) )
    {
        // TODO : Count number of files deleted

        $log->logNotice ( 'Flushing files files from FLICKR' );

        // Delete all collections
        deleteCollections ( $f, $log );

        // Deletes all sets and their files
        deleteSets ( $f, $log );

        // Delete remaining files
        deleteFiles ( $f, $log, $credentials );

        // Delete DB File
        $db->dbClose ( );
        if ( unlink ( DIRNAME . DIR_SEPARATOR . 'flickrSyncr.sqlite3' ) )
        {
            $log->logInfo ( 'DB - Deleting database file' );
        }
        else
        {
            $log->logError ( 'DB - Could not delete database file' );
        }

        $log->logNotice ( 'Flushing files files from FLICKR is done' );
    }

    $db->dbClose ( );
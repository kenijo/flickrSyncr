<?php
    /*
    |--------------------------------------------------------------------------
    |  Flickr Syncr
    |  ============
    |  Written by Ken (kenijo@gmail.com)
    |
    |  This is the configuration file for Flickr Syncr, a command line script !
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Set character encoding to UTF-8
    |--------------------------------------------------------------------------
    */
    mb_internal_encoding ( 'UTF-8' );
    mb_regex_encoding ( 'UTF-8' );

    /*
    |--------------------------------------------------------------------------
    | Define a directory separator
    |--------------------------------------------------------------------------
    */
    define ( 'DIR_SEPARATOR', '/' );

    /*
    |--------------------------------------------------------------------------
    | Application name
    |--------------------------------------------------------------------------
    */
    $cfg['app_name']    = 'flickrSyncr';

    /*
    |--------------------------------------------------------------------------
    | Flickr API key and secret
    |--------------------------------------------------------------------------
    */
    $cfg['api_key']     = '';
    $cfg['api_secret']  = '';

    /*
    |--------------------------------------------------------------------------
    | File extensions that are allowed to be uploaded to Flickr
    | Here is the list of values accepted by Flickr
    |  - Images : 'jpg', 'png', 'jpeg', 'gif', 'bmp'
    |  - Videos : 'avi', 'wmv', 'mov', 'mp4', '3gp', 'ogg', 'ogv', 'mts'
    |--------------------------------------------------------------------------
    */
    $cfg['allowed_images']  = array ( 'jpg', 'png', 'jpeg', 'gif', 'bmp' );
    $cfg['allowed_videos']  = array ( 'avi', 'wmv', 'mov', 'mp4', '3gp', 'ogg', 'ogv', 'mts'  );

    /*
    |--------------------------------------------------------------------------
    | Folders to exclude from uploading to Flickr
    |--------------------------------------------------------------------------
    */
    $cfg['exclude_folder_list'] = array ( 'tmp', 'temp' );

    /*
    |--------------------------------------------------------------------------
    | Define file properties for Flickr upload
    |--------------------------------------------------------------------------
    */
    /* (Optional) Set to 0 for no, 1 for yes. Specifies who can view the photo */
    $cfg['is_public'] = 0;
    /* (Optional) Set to 0 for no, 1 for yes. Specifies who can view the photo */
    $cfg['is_friend'] = 0;
    /* (Optional) Set to 0 for no, 1 for yes. Specifies who can view the photo */
    $cfg['is_family'] = 0;
    /* (Optional) The content type of the photo. Must be one of: 1 for Photo, 2 for Screenshot, and 3 for Other */
    $cfg['content_type'] = 1;
    /* (Optional) The safety level of the photo. Must be one of: 1 for Safe, 2 for Moderate, and 3 for Restricted */
    $cfg['safety_level'] = 1;
    /* (Optional) Whether or not to additionally hide the photo from public searches. Must be either 1 for Yes or 0 for No */
    $cfg['hidden'] = 1;

    /*
    |--------------------------------------------------------------------------
    | List all the allowed arguments and their help message
    |--------------------------------------------------------------------------
    */
    $cfg['cmd_name']            = BASENAME . PHP_EOL . '================';
    $cfg['cmd_description']     = 'This PHP script allows you to sync a local folder with your Flickr account in command line' . PHP_EOL;
    $cfg['cmd_description']     .= 'Through batch upload / download of photos and video on / from Flickr';
    $cfg['cmd_usage']           = 'Usage   : php ' . BASENAME . ' [arguments]';
    $cfg['cmd_example']         = 'Example : php ' . BASENAME . ' --upload --path=/path/to/my/photo --generate-tags';
    $cfg['cmd_arguments']       = array (
        'auth'              =>  'Authenticate the app at www.flickr.com',
        'cleanup-local'     =>  'Delete local files that are not on Flickr',
        'cleanup-flickr'    =>  'Delete Flickr files that are not on the local disk',
        'download'          =>  'Specify the folder where to download the photos from flickr ( default is current directory )',
        'flush-flickr'      =>  'Flush all files from Flickr',
        'generate-tags'     =>  'Generate tags based on the name of the photoset when uploading',
        'help'              =>  'Print Help ( this message ) and exit',
        'ignore-images'     =>  'Ignore image files',
        'ignore-videos'     =>  'Ignore video files when download or uploading',
        'upload'            =>  'Specify the folder to upload ( default is current directory )',
    );

    /*
    |--------------------------------------------------------------------------
    | Log folder name
    |--------------------------------------------------------------------------
    */
    $cfg['log_folder']    = DIRNAME . DIR_SEPARATOR . 'log';

    /*
    |--------------------------------------------------------------------------
    | Log days to keep
    |--------------------------------------------------------------------------
    */
    $cfg['log_days']    = 7;

    /*
    |--------------------------------------------------------------------------
    | Logging level
    |
    |  When you choose a level, all the messages from this level and below will be displayed
    |
    |   KLogger::DEBUG      // Debug: debug messages
    |   KLogger::INFO       // Informational: informational messages
    |   KLogger::NOTICE     // Notice: normal but significant condition
    |   KLogger::ERR        // Error: error messages
    |   KLogger::OFF        // Turn off logging
    |--------------------------------------------------------------------------
    */
    $cfg['log_level']   = KLogger::NOTICE;

    /*
    |--------------------------------------------------------------------------
    | FOR DEBUG
    |--------------------------------------------------------------------------
    | Enable debug mode and set default arguments
    |--------------------------------------------------------------------------
    */
    $cfg['dbg_mode']        = false ;
    $cfg['dbg_path']        = '/volume1/web/Photos/';
    $cfg['dbg_arguments']   = '--upload=' . $cfg['dbg_path'] . ' --generate-tags';

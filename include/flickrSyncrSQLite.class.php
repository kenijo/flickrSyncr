 <?php
    /*
    |--------------------------------------------------------------------------
    |  Flickr Syncr
    |  ============
    |  Written by Ken (kenijo@gmail.com)
    |
    |  This file groups all the SQLite functions for Flickr Syncr, a command line script !
    |--------------------------------------------------------------------------
    */

    class flickrSyncrSQLite extends SQLite3
    {
        private $args;
        private $cfg;
        private $f;
        private $log;

        function __construct ( $args, $cfg, $f, $log )
        {
            $this->args = $args;
            $this->cfg = $cfg;
            $this->f = $f;
            $this->log = $log;

            // Connect to SQlite DB
            $this->open ( DIRNAME . DIR_SEPARATOR . 'flickrSyncr.sqlite3' );

            // Create the tables if the they do not exists already
            $query = '
                CREATE TABLE IF NOT EXISTS collections (
                    /* (Mandatory) The Flickr ID of the collection */
                    flickr_id                       TEXT PRIMARY KEY UNIQUE,
                    /* (Mandatory) The title of the collection */
                    title                           TEXT NOT NULL,
                    /* (Optional) The dexcription of the collection */
                    description                     TEXT,
                    /* (Optional) The parent collection - if it is not the root collection*/
                    parent_collection_flickr_id     TEXT,

                    /* Foreign keys */
                    FOREIGN KEY (parent_collection_flickr_id)
                        REFERENCES collections(flickr_id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );

                CREATE TABLE IF NOT EXISTS files (
                    /* (Mandatory) The Flickr ID of the file */
                    flickr_id                       TEXT PRIMARY KEY UNIQUE,
                    /* (Mandatory) The path to the file to upload */
                    dirname                         TEXT NOT NULL,
                    /* (Mandatory) The name of the file to upload */
                    filename                        TEXT NOT NULL,
                    /* (Mandatory) The extension of the file to upload */
                    extension                       TEXT NOT NULL,
                    /* (Optional) The title of the photo */
                    title                           TEXT NOT NULL,
                    /* (Optional) A description of the photo. May contain some limited HTML */
                    description                     TEXT,
                    /* (Optional) A space-seperated list of tags to apply to the photo */
                    tags                            TEXT,
                    /* (Optional) Set to 0 for no, 1 for yes. Specifies who can view the photo */
                    is_public                       INTEGER NOT NULL DEFAULT 0,
                    /* (Optional) Set to 0 for no, 1 for yes. Specifies who can view the photo */
                    is_friend                       INTEGER NOT NULL DEFAULT 0,
                    /* (Optional) Set to 0 for no, 1 for yes. Specifies who can view the photo */
                    is_family                       INTEGER NOT NULL DEFAULT 0,
                    /* (Optional) The content type of the photo. Must be one of: 1 for Photo, 2 for Screenshot, and 3 for Other */
                    content_type                    INTEGER NOT NULL DEFAULT 1,
                    /* (Optional) The safety level of the photo. Must be one of: 1 for Safe, 2 for Moderate, and 3 for Restricted */
                    safety_level                    INTEGER NOT NULL DEFAULT 1,
                    /* (Optional) Whether or not to additionally hide the photo from public searches. Must be either 1 for Yes or 0 for No */
                    hidden                          INTEGER NOT NULL DEFAULT 1,
                    /* (Optional) The parent set */
                    parent_set_flickr_id            TEXT,

                    /* Foreign keys */
                    FOREIGN KEY (parent_set_flickr_id)
                        REFERENCES sets(flickr_id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );

                CREATE TABLE IF NOT EXISTS sets (
                    /* (Mandatory) The Flickr ID of the set */
                    flickr_id                       TEXT PRIMARY KEY UNIQUE,
                    /* (Mandatory) The title of the set */
                    title                           TEXT NOT NULL,
                    /* (Optional) The dexcription of the set */
                    description                     TEXT,
                    /* (Optional) The parent collection */
                    parent_collection_flickr_id     TEXT,

                    /* Foreign keys */
                    FOREIGN KEY (parent_collection_flickr_id)
                        REFERENCES collections(flickr_id)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE
                );
            ';
            $query = $this->escapeString ( $query );
            $this->exec ( $query );
            $this->log->logDebug ( 'DB - Database created or opened' );
        }

        function addFile ( $file )
        {
            // TODO : Add a pass to put all files not in sets, in sets

            // Check if FILE is already in DB
            $query = $this->prepare ( 'SELECT flickr_id FROM files WHERE dirname = :dirname AND filename = :filename AND extension = :extension' );
            $query->bindValue ( ':dirname', $file['dirname'] );
            $query->bindValue ( ':filename', $file['filename'] );
            $query->bindValue ( ':extension', $file['extension'] );
            $results = $query->execute ( );
            $result = $results->fetchArray ( SQLITE3_ASSOC );

            $path = $file['dirname'] . DIR_SEPARATOR . $file['basename'];

            if ( ! $result )
            {
                $fileFlickrId = $this->uploadFileToFlickr ( $file );

                if ( $fileFlickrId != false )
                {
                    // Insert in FILES
                    $query = $this->prepare ( 'INSERT INTO files VALUES ( :flickr_id, :dirname, :filename, :extension, :title, :description, :tags, :is_public, :is_friend, :is_family, :content_type, :safety_level, :hidden, :parent_set_flickr_id )' );
                    $query->bindValue ( ':flickr_id', $fileFlickrId );
                    $query->bindValue ( ':dirname', $file['dirname'] );
                    $query->bindValue ( ':filename', $file['filename'] );
                    $query->bindValue ( ':extension', $file['extension'] );
                    $query->bindValue ( ':title', $file['title'] );
                    $query->bindValue ( ':description', $file['description'] );
                    $query->bindValue ( ':tags', $file['tags'] );
                    $query->bindValue ( ':is_public', $this->cfg['is_public'] );
                    $query->bindValue ( ':is_friend', $this->cfg['is_friend'] );
                    $query->bindValue ( ':is_family', $this->cfg['is_family'] );
                    $query->bindValue ( ':content_type', $this->cfg['content_type'] );
                    $query->bindValue ( ':safety_level', $this->cfg['safety_level'] );
                    $query->bindValue ( ':hidden', $this->cfg['hidden'] );
                    $query->bindValue ( ':parent_set_flickr_id', NULL );

                    if ( $query->execute ( ) )
                    {
                        $this->log->logInfo ( 'DB - FILE added' );
                        $this->log->logInfo ( '     FILE ' . $path );
                        $this->log->logInfo ( '     FILE flickr_id=' . $fileFlickrId );
                    }
                    else
                    {
                        $this->log->logError ( 'DB - FILE could not be added' );
                        $this->log->logError ( '     FILE ' . $path );
                        return false;
                    }
                }
                else
                {
                    return false;
                }
            }
            else
            {
                $fileFlickrId = $result['flickr_id'];
                $this->log->logDebug ( 'DB - FILE already added' );
                $this->log->logDebug ( '     FILE ' . $path );
                $this->log->logDebug ( '     FILE flickr_id=' . $fileFlickrId );
            }

            // Creates titles for COLLECTIONS and SETS
            $collection['title']  = explode ( DIR_SEPARATOR , $file['dirname'] );
            $set['title'] = end ( $collection['title'] );
            $setDescription = $file['dirname'];

            // For each  COLLECTION
            // We skip the first element because it is null ( from the explode, there is nothing before the first '/')
            // We skip the last element because we use it as a set (that's why we are doing -1)
            $collectionDescription = NULL;
            $lastCollectionFlickrId = NULL;
            for ( $i = 1; $i < sizeof ( $collection['title'] ) - 1; $i++ )
            {
                $collectionDescription .= DIR_SEPARATOR . $collection['title'][$i];

                // Check if COLLECTION is already in DB
                $query = $this->prepare ( 'SELECT flickr_id FROM collections WHERE description = :description' );
                $query->bindValue ( ':description', $collectionDescription );
                $results = $query->execute ( );
                $result = $results->fetchArray ( SQLITE3_ASSOC );

                if ( ! $result )
                {
                    $collectionFlickrId = $this->createCollectionOnFlickr ( $collection['title'][$i], $collectionDescription, $lastCollectionFlickrId );

                    if ( $collectionFlickrId != false )
                    {
                        // Insert in COLLECTIONS
                        $query = $this->prepare ( 'INSERT INTO collections VALUES ( :flickr_id, :title, :description, :parent_collection_flickr_id )' );
                        $query->bindValue ( ':flickr_id', $collectionFlickrId );
                        $query->bindValue ( ':title', $collection['title'][$i] );
                        $query->bindValue ( ':description', $collectionDescription );
                        $query->bindValue ( ':parent_collection_flickr_id', $lastCollectionFlickrId );

                        if ( $query->execute ( ) )
                        {
                            $this->log->logInfo ( 'DB - COLLECTION created' );
                            $this->log->logInfo ( '     COLLECTION ' . $collectionDescription );
                            $this->log->logInfo ( '     COLLECTION flickr_id=' . $collectionFlickrId );
                        }
                        else
                        {
                            $this->log->logError ( 'DB - COLLECTION could not be created' );
                            $this->log->logError ( '     COLLECTION flickr_id=' . $collectionDescription );
                        }

                        $lastCollectionFlickrId = $collectionFlickrId;
                    }
                    else
                    {
                        return false;
                    }
                }
                else
                {
                    $lastCollectionFlickrId = $result['flickr_id'];
                    $this->log->logDebug ( 'DB - COLLECTION already created' );
                    $this->log->logDebug ( '     COLLECTION ' . $collectionDescription );
                    $this->log->logDebug ( '     COLLECTION flickr_id=' . $lastCollectionFlickrId );
                }
            }

            // Check if SET is already in DB
            if ( $set['title'] != NULL )
            {
                $query = $this->prepare ( 'SELECT flickr_id FROM sets WHERE description = :description' );
                $query->bindValue ( ':description', $setDescription );
                $results = $query->execute ( );
                $result = $results->fetchArray ( SQLITE3_ASSOC );

                if ( ! $result )
                {
                    $setFlickrId = $this->createSetOnFlickr ( $set['title'], $setDescription, $fileFlickrId );

                    if ( $setFlickrId == false )
                    {
                        return false;
                    }
                    else
                    {
                        // Insert in SETS
                        $query = $this->prepare ( 'INSERT INTO sets VALUES ( :flickr_id, :title, :description, :parent_collection_flickr_id )' );
                        $query->bindValue ( ':flickr_id', $setFlickrId );
                        $query->bindValue ( ':title', $set['title'] );
                        $query->bindValue ( ':description', $setDescription );
                        $query->bindValue ( ':parent_collection_flickr_id', NULL );

                        if ( $query->execute ( ) )
                        {
                            $this->log->logInfo ( 'DB - SET added' );
                            $this->log->logInfo ( '     SET ' . $setDescription );
                            $this->log->logInfo ( '     SET flickr_id=' . $setFlickrId );
                        }
                        else
                        {
                            $this->log->logError ( 'DB - SET could not created' );
                            $this->log->logError ( '     SET flickr_id=' . $setDescription );
                        }
                    }

                    // Link SET to COLLECTION
                    if ( $lastCollectionFlickrId != NULL)
                    {
                        $query = $this->prepare ( 'UPDATE sets SET parent_collection_flickr_id = :parent_collection_flickr_id WHERE flickr_id = :flickr_id' );
                        $query->bindValue ( ':parent_collection_flickr_id', $lastCollectionFlickrId );
                        $query->bindValue ( ':flickr_id', $setFlickrId );
                        $results = $query->execute ( );
                        $result = $results->fetchArray ( SQLITE3_ASSOC );

                        if ( ! $result )
                        {
                            $this->log->logInfo ( 'DB - SET linked to COLLECTION' );
                            $this->log->logInfo ( '     SET flickr_id=' . $setFlickrId );
                            $this->log->logInfo ( '     COLLECTION flickr_id=' . $lastCollectionFlickrId );

                            if ( $this->linkSetToCollectionOnFlickr ( $setFlickrId , $lastCollectionFlickrId ) == false )
                            {
                                return false;
                            }
                        }
                        else
                        {
                            $this->log->logError ( 'DB - SET could not be linked to COLLECTION' );
                            $this->log->logError ( '     SET flickr_id=' . $setFlickrId );
                            $this->log->logError ( '     COLLECTION flickr_id=' . $lastCollectionFlickrId );
                        }
                    }
                }
                else
                {
                    $setFlickrId = $result['flickr_id'];
                    if ( $this->addToSetOnFlickr ( $setFlickrId, $fileFlickrId ) == false )
                    {
                        return false;
                    }
                }

                // Link FILE to SET
                $query = $this->prepare ( 'UPDATE files SET parent_set_flickr_id = :parent_set_flickr_id WHERE flickr_id = :flickr_id' );
                $query->bindValue ( ':parent_set_flickr_id', $setFlickrId );
                $query->bindValue ( ':flickr_id', $fileFlickrId );
                $results = $query->execute ( );
                $result = $results->fetchArray ( SQLITE3_ASSOC );

                if ( ! $result )
                {
                    $this->log->logInfo ( 'DB - FILE linked to SET' );
                    $this->log->logInfo ( '     FILE flickr_id=' . $fileFlickrId );
                    $this->log->logInfo ( '     SET flickr_id=' . $setFlickrId );
                }
                else
                {
                    $this->log->logError ( 'DB - FILE could not be linked to SET' );
                    $this->log->logError ( '     FILE (flickr_id=' . $fileFlickrId );
                    $this->log->logError ( '      SET (flickr_id=' . $setFlickrId );
                }
            }

            return true;
        }

        function uploadFileToFlickr ( $file )
        {
            $path = $this->args['upload'] . $file['dirname'] . DIR_SEPARATOR . $file['filename'] . '.' . $file['extension'];

            // Upload FILE
            $result = $this->f->sync_upload ( $path, $file['title'], $file['description'], $file['tags'], $this->cfg['is_public'], $this->cfg['is_friend'], $this->cfg['is_family'] );

            if ( $result != false )
            {
                $fileFlickr = $result;
                $this->log->logInfo ( 'FLICKR - FILE uploaded' );
                $this->log->logInfo ( '         FILE ' . $path );
                $this->log->logInfo ( '         FILE flickr_id=' . $fileFlickr );

                // Set FILE content type
                $result = $this->f->photos_setContentType ( $fileFlickr, $this->cfg['content_type'] );
                if ( $result != false )
                {
                    $this->log->logDebug ( 'FLICKR - FILE content type set' );
                }
                else
                {
                    $this->log->logError ( 'FLICKR - FILE content type could not be set' );
                    $this->log->logError ( '         ' . $this->f->getError ( ) );
                }

                // Set FILE safety level
                $result = $this->f->photos_setSafetyLevel ( $fileFlickr, $this->cfg['safety_level'], $this->cfg['hidden'] );
                if ( $result != false )
                {
                    $this->log->logDebug ( 'FLICKR - FILE safety level set' );
                }
                else
                {
                    $this->log->logError ( 'FLICKR - FILE safety level could not be set' );
                    $this->log->logError ( '         ' . $this->f->getError ( ) );
                }
            }
            else
            {
                $fileFlickr = $result;
                // TODO : ad more debugging code
                $this->log->logError ( 'FLICKR - FILE could not be uploaded' );
                $this->log->logError ( '         ' . $this->f->getError ( ) );
                $this->log->logError ( '         FILE ' . $path );
            }

            return $fileFlickr;
        }

        function createSetOnFlickr ( $title, $description, $fileFlickrId )
        {
            $result = $this->f->photosets_create ( $title, $description, $fileFlickrId );

            if ( $result != false )
            {
                $setFlickrId = $result['id'];
                $this->log->logInfo ( 'FLICKR - SET created and FILE added' );
                $this->log->logInfo ( '         SET ' . $description );
                $this->log->logInfo ( '         SET flickr_id=' . $setFlickrId );
                $this->log->logInfo ( '         FILE flickr_id=' . $setFlickrId );
            }
            else
            {
                $setFlickrId = false;
                $this->log->logError ( 'FLICKR - Could not create SET and add FILE' );
                $this->log->logError ( '        ' . $this->f->getError ( ) );
                $this->log->logError ( '         SET ' . $description );
                $this->log->logError ( '         FILE flickr_id=' . $fileFlickrId );
            }

            return $setFlickrId;
        }

        function addToSetOnFlickr ( $setFlickrId, $fileFlickrId )
        {
            $result = $this->f->photosets_addPhoto ( $setFlickrId, $fileFlickrId );

            if ( $result != false )
            {
                $this->log->logInfo ( 'FLICKR - FILE added to SET' );
                $this->log->logInfo ( '         FILE flickr_id=' . $fileFlickrId );
                $this->log->logInfo ( '         SET flickr_id=' . $setFlickrId );
                return true;
            }
            else
            {
                $this->log->logError ( 'FLICKR - FILE could not be added to SET' );
                $this->log->logError ( '         ' . $this->f->getError ( ) );
                $this->log->logError ( '         FILE flickr_id=' . $fileFlickrId );
                $this->log->logError ( '         SET flickr_id=' . $setFlickrId );
                return false;
            }
        }

        function createCollectionOnFlickr ( $title, $description, $parent_id )
        {
            $result = $this->f->collections_create ( $title, $description, $parent_id );

            if ( $result != false )
            {
                $collectionFlickrId = $result['id'];
                $this->log->logInfo ( 'FLICKR - COLLECTION created' );
                $this->log->logInfo ( '         COLLECTION ' . $description );
                $this->log->logInfo ( '         COLLECTION flickr_id=' . $collectionFlickrId );
            }
            else
            {
                $collectionFlickrId = false;
                $this->log->logError ( 'FLICKR - Could not create COLLECTION : ' . $description );
                $this->log->logError ( '         ' . $this->f->getError ( ) );
            }

            return $collectionFlickrId;
        }

        function linkSetToCollectionOnFlickr ( $setFlickrId , $collectionFlickrId )
        {
            $result = $this->f->collections_getTree ( $collectionFlickrId );
            $setList = NULL;

            if ( $result != false )
            {
                if ( isset ( $result['collections']['collection'] ) )
                {
                    foreach ( $result['collections']['collection'] as $collection_key => $collection_value )
                    {
                        if ( isset ( $collection_value['set'] ) )
                        {
                            foreach ( $collection_value['set'] as $set_key => $set_value )
                            {
                                $setList .= $set_value['id'] . ',';
                            }
                        }
                    }
                }
            }

            $setFlickrId = $setList . $setFlickrId;

            $result = $this->f->collections_editSets ( $collectionFlickrId, $setFlickrId );

            if ( $result != false )
            {
                $this->log->logInfo ( 'FLICKR - SET linked to COLLECTION' );
                $this->log->logInfo ( '         SET flickr_id=' . $setFlickrId );
                $this->log->logInfo ( '         COLLECTION flickr_id=' . $collectionFlickrId );
                return true;
            }
            else
            {
                $this->log->logError ( 'FLICKR - SET could not be linked to COLLECTION' );
                $this->log->logError ( '         '. $this->f->getError ( ) );
                $this->log->logError ( '         SET flickr_id=' . $setFlickrId );
                $this->log->logError ( '         COLLECTION flickr_id=' . $collectionFlickrId );
                return false;
            }
        }

        function dbClose ( )
        {
            $this->close();
        }
    }
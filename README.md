FlickrSyncr
===========

Install
=======

To install, download ad decompress the the archive in a folder.

Edit flickrSyncr.API.php and add your Flickr API key and secret

Execute from the command line:

    php flickrSyncr.php --auth

It will provide you a link to authenticate the app on www.flickr.com

Once done, go back to the command line and finish the process.

To use the script execute

    php flickrSyncr.php --upload --path=/path/to/my/photo --generate-tags

You can add this line to a task scheduler / cron job to automate the upload.

Just don't forget to put the full path to 'php' and 'flickrSyncr.php'.

Help
====

This is a command line script !

This PHP script allows you to sync a local folder with your Flickr account in command line

Through batch upload / download of photos and video on / from Flickr

Usage   : php flickrSyncr.php [arguments]

Example : php flickrSyncr.php --upload --path=/path/to/my/photo --generate-tags

    --help            Print Help ( this message ) and exit
    --upload          Specify the folder to upload ( default is current directory )
    --download        Specify the folder where to download the photos from flickr ( default is current directory )
    --path            Specify the folder to use ( default is current directory )
    --cleanup-local   Delete local files that are not on Flickr
    --cleanup-flickr  Delete Flickr files that are not on the local disk
    --ignore-images   Ignore image files
    --ignore-videos   Ignore video files when download or uploading
    --generate-tags   Generate tags based on the name of the photoset when uploading


This script is using the following external PHP classes

    - phpFlickr : http://www.phpflickr.com
    - KLogger   : http://codefury.net

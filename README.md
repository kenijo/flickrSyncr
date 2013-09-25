FlickrSyncr
===========

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

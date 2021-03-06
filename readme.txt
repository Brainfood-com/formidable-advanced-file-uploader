=== Formidable Advanced File Uploader ===
Contributors: ben.moody
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: formidable, formidable file upload, formidable file uploader, formidable uploader, plupload, formidable videos, formidable youtube, youtube uploader, youtube file uploader
Requires at least: 3.0
Tested up to: 3.9.1
Stable tag: 1.0

Chunked Multiple file uploads, Auto upload of videos to YouTube & Brightcove, Files stored in WP Media Library, Advanced options.

== Description ==

[youtube http://www.youtube.com/watch?v=k4cKarrr4aE]

* Need more control over Formidable multiple file uploads. 
* Want to store file uploads in Wordpress media library. 
* Large file support with chunked uploads, get around server upload limits
* Like a choice of upload user interfaces (jQuery UI, Queue, Custom)
* Need advanced control over plupload options
* Would like to store uploaded videos on YouTube account (also Brightcove. Vimeo coming soon!)
* Added security and validation
* Bonus Terms of Service Formidable field with optional submit disable feature
* Creating posts with Formidable? All uploads are added as post attachments and can be displayed with the [get_adv_uploads] shortcode
* Also use the WordPress gallery shortcode to display any images attached to a post

This is the Formidable uploader plugin for those who need a little more than the default multi file upload of Formidable v1.8. 

The plugin options page provides you with granular control over many Plupload parameters from file extension filters to chunked uploading and runtimes.

All files are uploaded to the Wordpress media library on successful form submission making for easy access and management.

If you chose to activate the Video Uploader add-on the plugin will detect any video files being uploaded and automatically send them to your YouTube account as private videos awaiting review (Also includes Brightcove FTP, Vimeo API is on its way!).

For the security conscious among you the plugin takes many steps to protect the server from nasty files:

* filename encryption
* prevention of file execution in tmp folder via htaccess
* validation of both file extension and mime type
* crosscheck mime types against Wordpress mime white list
* filenames changed once added to media library

Large File Support - Enable chunked file uploads to allow for large files uploads and circumvent server uploads limits.

Advanced Customization - If you are a dev and need even more control there are a number of filters and actions to hook into. Also you can make a copy of the ini scripts used to generate each UI. Place them in your theme and just wp_dequeue_script then enqueue_script with your script path and it will have access to all the localized vars.

Please Note -- When using the Video Uploader option, although actual file upload takes place asynchronously. If your server script timeouts are too short you will have problems with larger video files. That said the plugin does try to increase the timeout but it really depends on your hosting setup.

== Installation ==

NOTE: You will require PHP iconv extension installed on server for YouTube uploader to work

1. Upload `prso-formidable-adv-uploader` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to Settings -> Formidable Adv Uploader to set all your awesome options

== Frequently Asked Questions ==

More info over at GitHub (https://github.com/pressoholics/prso-formidable-adv-uploader).

= Change Plupload Language =

Use 'prso_frm_pluploader_i18n_script' filter in themes functions.php to select language for Plupload:

add_filter( 'prso_frm_pluploader_i18n_script', 'plupload_i18n' );
function plupload_i18n( $i18n_filename ) {
	
	//Use fr,js file - remove .js from filename
	$i18n_filename = 'fr';
	
	return i18n_filename;
}

See plugins/prso-formidable-adv-uploader/inc/js/plupload/i18n folder for language file options.

= Entries are not appearing in admin area =

Formidable requires that each form has at least 1 formidable field to show results. So if you have just the uploader in your form try adding a text field or something similar. I will look into a work around in future updates.

= Videos are not uploaded to YouTube =

The YouTube uploader requires PHP iconv extension to work. Ask your host to install it for you.

= Files are uploading but not shown in media library =

This is probably an issue with the file being larger than PHP post size allows. Try enabling chunked uploads, and be sure that the chunked upload size is not larger than your PHP post size on the server (try 1mb if you have problems).

= How can i override the uploader UI javascript =

That depends on the UI you have set in the options:

jQuery UI:

* Copy 'init_plupload_jquery_ui.js' from plugin's js directory. Dequeue 'prso-pluploader-init' then requeue 'prso-pluploader-init' pointing to your copy of the script.

Queue UI:

* Copy 'init_plupload_queue.js' from plugin's js directory. Dequeue 'prso-pluploader-init' then requeue 'prso-pluploader-init' pointing to your copy of the script.

Custom UI:

* Copy 'init_plupload_custom.js' from plugin's js directory. Dequeue 'prso-pluploader-init' then requeue 'prso-pluploader-init' pointing to your copy of the script.

Check out the Plupload docs and you can customize anything.

= The Video Uploader addon does not work with large video files =

This is due to your server script timeout settings. The plugin does attempt to set 'max_execution_time' & 'mysql.connect_timeout', but if your host has disabled these options then i'm afraid you are stuck unless you can ask them to increase these for you or you can add your own php.ini.

= File Chunking doesnt work too well in some older browsers =

This option can be hit and miss in some older browsers, that said it works in most of them. Just test it and see.

== Screenshots ==

1. Shot of jQuery UI version.
2. Shot of Queue UI version.
3. Shot of Custom UI version - you set this badboy up!
4. The options page, lost of param goodness

== Changelog ==

= 1.0 =
* Initial commit to plugin repo

== Upgrade Notice ==


== Hooks ==

Actions:
* 'prso_frm_pluploader_processed_uploads'
* 'wp_ajax_nopriv_prso_frm_youtube_upload_init'
* 'wp_ajax_nopriv_prso_frm_youtube_upload_save_data'
* 'prso_frm_youtube_uploader_pre_get_attachment_data'	-	Allow devs to hook in before getting attachment data

Filters:
* 'prso_frm_pluploader_container'
* 'prso_frm_pluploader_server_validation_args'
* 'prso_frm_pluploader_entry_attachment_links'

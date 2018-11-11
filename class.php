<?php
/*
* Home page: http://vinaget.us
* Blog:	http://blog.vinaget.us
* Script Name: Vinaget
* Version: 2.7.0 Final
* Description:
	- Vinaget is script generator premium link that allows you to download files instantly and at the best of your Internet speed.
	- Vinaget is your personal proxy host protecting your real IP to download files hosted on hosters like RapidShare, megaupload, hotfile...
	- You can now download files with full resume support from filehosts using download managers like IDM etc
	- Vinaget is a Free Open Source, supported by a growing community.
* Code LeechViet by VinhNhaTrang
* Developed by - ..:: [H] ::..
			   - [FZ]
*/

/* Set default timezone */
#date_default_timezone_set('UTC');

// autoload
spl_autoload_register(function ($class_name) {
    include 'classes/' . $class_name . '.class.php';
});

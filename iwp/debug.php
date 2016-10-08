<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

include("includes/app.php");

if($_GET['show'] == 'phpinfo'){
	phpinfo();
}
elseif($_GET['show'] == 'requirements'){
	
	$maximumExecutionTime = 300 + ini_get('max_execution_time');
	@set_time_limit($maximumExecutionTime);//300 => 5 mins
	
	$check = array();
	$check['required']['PHP_VERSION'] 		= '5.2.4';
	$check['required']['PHP_SAFE_MODE'] 	= 0;//should be in off
	$check['required']['PHP_WITH_MYSQL'] 	= 1;
	$check['required']['PHP_WITH_OPEN_SSL'] = 1;
	$check['required']['PHP_WITH_CURL'] 	= 1;
	$check['required']['PHP_FILE_UPLOAD'] 	= 1;
	$check['required']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] = 1;
	$check['required']['FSOCK_SAME_URL_CONNECT_CHECK'] 		= 1;
	//$check['required']['MYSQL_VERSION'] 	= '4.1.2';
	
	
	//======================================================================>
	
	$check['available']['PHP_VERSION'] 			= phpversion();
	
	$phpSafeMode = ini_get('safe_mode');
	$check['available']['PHP_SAFE_MODE'] 		= !empty($phpSafeMode);
	$check['available']['PHP_WITH_MYSQL'] 		= (class_exists('mysqli') or function_exists('mysql_connect'));
	$check['available']['PHP_WITH_OPEN_SSL'] 	= function_exists('openssl_verify');
	$check['available']['PHP_WITH_CURL'] 		= function_exists('curl_exec');
	$check['available']['PHP_FILE_UPLOAD'] 		= ini_get('file_uploads')==1 ? true : false;
	
	
	//checking PHP_MAX_EXECUTION_TIME_CONFIGURABLE
	$check['available']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] = 0;
	if($maximumExecutionTime == ini_get('max_execution_time')){
		$check['available']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] = 1;
	}
	
	
	$appFullURL = APP_FULL_URL;
	$fURL = $appFullURL."/execute.php";
	$fsockSameURLConnectCheckResult = fsockSameURLConnectCheck($fURL);
	$check['available']['FSOCK_SAME_URL_CONNECT_CHECK'] = $fsockSameURLConnectCheckResult['status'];
	
	//======================================================================>
		
			
	$check['final']['PHP_VERSION'] 		    = (version_compare($check['available']['PHP_VERSION'], $check['required']['PHP_VERSION'])  >= 0) ? true: false;
	$check['final']['PHP_SAFE_MODE'] 		= ($check['required']['PHP_SAFE_MODE'] == $check['available']['PHP_SAFE_MODE']) ? true: false;
	$check['final']['PHP_WITH_MYSQL'] 		= ($check['required']['PHP_WITH_MYSQL'] == $check['available']['PHP_WITH_MYSQL']) ? true: false;
	$check['final']['PHP_WITH_OPEN_SSL'] 	= ($check['required']['PHP_WITH_OPEN_SSL'] == $check['available']['PHP_WITH_OPEN_SSL']) ? true: 1;//1 = optional
	$check['final']['PHP_WITH_CURL']		= ($check['required']['PHP_WITH_CURL'] == $check['available']['PHP_WITH_CURL']) ? true: false;
	$check['final']['PHP_FILE_UPLOAD']		= ($check['required']['PHP_FILE_UPLOAD'] == $check['available']['PHP_FILE_UPLOAD']) ? true: false;
	$check['final']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE']  = ($check['required']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] == $check['available']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE']) ? true: false;
	$check['final']['FSOCK_SAME_URL_CONNECT_CHECK'] 		= ($check['required']['FSOCK_SAME_URL_CONNECT_CHECK'] == $check['available']['FSOCK_SAME_URL_CONNECT_CHECK']) ? true: false;
	
	
	//======================================================================>
	
	$check['other']['PHPDisabledFunctions'] = explode(',', ini_get('disable_functions'));	
	array_walk($check['other']['PHPDisabledFunctions'], 'trimValue');
	
	$check['other']['PHPDisabledClasses'] = explode(',', ini_get('disable_classes'));	
	array_walk($check['other']['PHPDisabledClasses'], 'trimValue'); 
	
	echo '<pre>'.print_r($check, true).'</pre>';

	//settings
	$rawSettings = DB::getRow("?:settings", "*", 1);
	$settings = array();
	$settings['general'] = unserialize($rawSettings['general']);
	$settings['notifications'] = unserialize($rawSettings['notifications']);
	echo '<pre>Settings: '.var_export($settings, true).'</pre>';

	//php temp path
	echo '<br>PHP Temp path TMPDIR:'; var_dump(getenv('TMPDIR'));//unix system
	echo '<br>PHP Temp path TMP:'; var_dump(getenv('TMP'));//might be windows
	echo '<br>PHP Temp path TEMP:'; var_dump(getenv('TEMP'));//might be windows
	
} elseif($_GET['reset'] == 'favicons'){
	?>
		<a href="debug.php?reset=confirmDeleteFavicons">Confirm to delete all favicons</a>
	<?php
} else if($_GET['reset'] == 'confirmDeleteFavicons'){
	include_once(APP_ROOT."/includes/favicon.php");
	Favicon::clearAllFavicon();
} elseif ($_GET['reset'] == 'cacheFiles') {
	include_once APP_ROOT.'/includes/fileSystemBase.php';
	include_once APP_ROOT.'/includes/fileSystemDirect.php';
	$fileSystem = new filesystemDirect('');
	$cacheFolderPath = APP_ROOT."/uploads/cache/";
	$result = $fileSystem->rmDir($cacheFolderPath, true);
	if($result == false){
		echo "Cannot clear cache files.";
	} else {
		echo "Cache files cleared.";
	}
	deleteOption('cacheProcessPushDataFailed');
	deleteOption('cacheProcessPanelHashKeys');
	deleteOption('cacheProcessAddonHashKeys');
}elseif ($_GET['show'] == 'appInfo') {
	echo '<pre> APP INSTALL HASH : '.APP_INSTALL_HASH.'</pre>';
}
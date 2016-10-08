<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
 
class manageClientsBackup{
	
	public static function backupProcessor($siteIDs, $params){
					
		$accountInfo = array('account_info' => $params['accountInfo']);
		if((!empty($accountInfo['account_info']['iwp_gdrive'])))
		{
			//$accountInfo['account_info']['iwp_gdrive']['gDriveEmail'] = unserialize(getOption('googleDriveAccessToken'));
			$repoID = $accountInfo['account_info']['iwp_gdrive']['gDriveEmail'];
			if(function_exists('backupRepositorySetGoogleDriveArgs')){
				$accountInfo['account_info']['iwp_gdrive'] = backupRepositorySetGoogleDriveArgs($accountInfo['account_info']['iwp_gdrive']);
			}else{
				addNotification($type='E', $title='Cloud backup Addon Missing', $message="Check if cloud backup addon exists and is active", $state='U', $callbackOnClose='', $callbackReference='');
				return false;
			}
		}
		$config = $params['config'];
		$timeout = (20 * 60);//20 mins
		$type = "backup";
		$action = ($config['mechanism'] == 'multiCall') ? "multiCallNow" : "now";
		$requestAction = "scheduled_backup";
		
		if(empty($config['taskName'])){
			$config['taskName'] = 'Backup Now';
		}
		
			$exclude = explode(',', $config['exclude']);
			$include = explode(',', $config['include']);	
		   	array_walk($exclude, 'trimValue');
			array_walk($include, 'trimValue');
			
			$requestParams = array('task_name' => $config['taskName'],'mechanism' => $config['mechanism'], 'args' => array('type' => $type, 'action' => $action, 'what' => $config['what'], 'optimize_tables' => $config['optimizeDB'], 'exclude' => $exclude, 'exclude_file_size' => (int)$config['excludeFileSize'], 'exclude_extensions' => $config['excludeExtensions'], 'include' => $include, 'del_host_file' => $config['delHostFile'], 'disable_comp' => $config['disableCompression'], 'fail_safe_db' => $config['failSafeDB'], 'fail_safe_files' => $config['failSafeFiles'], 'limit' => $config['limit'], 'backup_name' => $config['backupName']), 'secure' => $accountInfo);
			
			
			// The following lines are used for client side activities log
			if(isset($config['backup_repo_type'])) {
				$requestParams['args']['backup_repo_type'] = $config['backup_repo_type'];
			}			
			if(isset($config['when'])) {
				$requestParams['args']['when'] = $config['when'];
			}
			if(isset($config['at'])) {
				$requestParams['args']['at'] = $config['at'];
			}			
			// The above lines are used for client side activities log
			
			if($action == "multiCallNow")
			{
				//this function set the multicall options value from config.php if available 
				setMultiCallOptions($requestParams);
			}			   			
			$historyAdditionalData = array();
			$historyAdditionalData[] = array('uniqueName' => $config['taskName'], 'detailedAction' => $type);
			  		
		$incTime = 20 * 60;//20 mins		
		$i = 0;
		$lastHistoryID = '';
		if(empty($params['timeScheduled'])){ $params['timeScheduled'] = time(); }
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);
			$istaskRunning = isTaskRunningBySiteID($siteID, 'backup');
			if($istaskRunning){
				$notificationMessage = $siteData['name']." - This site is currently being backed up. So, another backup cannot be initiated till it completes." ;
				addNotification($type='E', $title='SITE IS BACKING UP ALREADY', $message=$notificationMessage, $state='U', $callbackOnClose='', $callbackReference='');
				continue;
			}

			$events=1;
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['timeout'] 		= $timeout;
			$PRP['status'] 			= 'pending';
			$PRP['timeScheduled'] = $params['timeScheduled'];
			
						
			if($lastHistoryID){
				$runCondition = 	array();
				$runCondition['satisfyType'] = 'OR';
				$runCondition['query'] = array('table' => "history_additional_data",
													  'select' => 'historyID',
													  'where' => "historyID = ".$lastHistoryID." AND status IN('success', 'error', 'netError')");
				//$runCondition['maxWaitTime'] = $params['timeScheduled'] + $incTime * $i;
				$PRP['runCondition'] = serialize($runCondition);
				$PRP['status'] = 'scheduled';
			}
			
				$lastHistoryID = prepareRequestAndAddHistory($PRP);
				$i++;
		  }
	}
	
	public static function backupResponseProcessor($historyID, $responseData){

		responseDirectErrorHandler($historyID, $responseData);

				
		if(empty($responseData['success'])){
			return false;
		}
		$where = array(
      		'query' =>  "historyID=':historyID'",
      		'params' => array(
               ':historyID'=>$historyID
				)
		);
		
		$historyData = DB::getRow("?:history", "*", $where);
		$siteID = $historyData['siteID'];
		
		
		if(!empty($responseData['success']['error']) && is_string($responseData['success']['error'])){		
			$where = array(
	      		'query' =>  "historyID=:historyID",
	      		'params' => array(
	               ':historyID'=>$historyID
					)
			);
			DB::update("?:history_additional_data", array('status' => 'error', 'errorMsg' => $responseData['success']['error'], 'error' => $responseData['success']['error_code']), $where);	
			return false;
		}
		else{
			if($historyData['type'] == 'backup' && $historyData['action'] == 'multiCallNow'){
				$historyResponseStatus[$historyID] = "multiCallWaiting";
				Reg::set("historyResponseStatus", $historyResponseStatus);
				
				updateHistory(array('status' => "multiCallWaiting"), $historyID);
				self::triggerRecheck($responseData, $siteID);
			}
			else{
				$where = array(
		      		'query' =>  "historyID=:historyID",
		      		'params' => array(
		               ':historyID'=>$historyID
						)
				);
				DB::update("?:history_additional_data", array('status' => 'success'), $where);
						
				//---------------------------post process------------------------>
				$siteID = DB::getField("?:history", "siteID", $where);
			
				$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
				
				panelRequestManager::handler($allParams);
			}
		}
	}
	
	public static function triggerRecheck($data, $siteID){
		
		$parentHistoryID = (!empty($data['parentHistoryID']) ? $data['parentHistoryID'] : $data['success']['success']['parentHID']);
				
		$allParams = array('action' => 'triggerRecheck', 'args' => array('params' => array('responseData' => $data['success'], 'backupParentHID' => $parentHistoryID), 'siteIDs' => array($siteID)));
				
		panelRequestManager::handler($allParams);
			
	}
	
	public static function triggerRecheckProcessor($siteIDs, $params, $extras){
		
		$type = "backup";
		$action = "trigger";
		$requestAction = "trigger_backup_multi";
		$timeout = 60;
		
		if(empty($params['backupParentHID'])){
			return;	
		}
		$where = array(
	      		'query' =>  "historyID=':historyID'",
	      		'params' => array(
	               ':historyID'=>$params['backupParentHID']
					)
			);
		$parentHistoryIDStatus = DB::getField("?:history", "status", $where);
	
	
		if(($parentHistoryIDStatus != 'multiCallWaiting')){
			return;
		}
		
		$where = array(
	      		'query' =>  "type='backup' AND action = 'trigger' AND parentHistoryID = ':historyID'",
	      		'params' => array(
	               ':historyID'=>$params['backupParentHID']
					)
			);
		$getCount = DB::getField("?:history", "count(historyID)", $where);
		if($getCount >= 300){
			
			updateHistory(array('status' => 'error', 'error' => 'max_trigger_calls_reached'), $params['backupParentHID'], array('status' => 'error', 'error' => 'max_trigger_calls_reached', 'errorMsg' => 'Multi-call limit reached.'));
			
			return;
		}
		$where = array(
	      		'query' =>  "type='backup' AND action = 'trigger' AND parentHistoryID = ':historyID' AND status not IN('completed', 'error', 'netError')",
	      		'params' => array(
	               ':historyID'=>$params['backupParentHID']
					)
			);
		if(DB::getExists("?:history", "historyID", $where)){
			return;
			
		}
		
		$requestParams = array('mechanism' => 'multiCall','backupParentHID' => $params['backupParentHID'], 'params' => $params['responseData']);
		
		$historyAdditionalData = array();
		$historyAdditionalData[] = array('uniqueName' => "backupTrigger", 'detailedAction' => $action);
		
		$doNotShowUser = true;
			
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);
				  		
			$events=1;
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['timeout'] 		= $timeout;
			$PRP['doNotShowUser'] 	= $doNotShowUser;
			$PRP['parentHistoryID'] = $params['backupParentHID'];
			
			prepareRequestAndAddHistory($PRP);

		  }
	}
	
	public static function triggerRecheckResponseProcessor($historyID, $responseData){
		responseDirectErrorHandler($historyID, $responseData);
		
		if(!empty($responseData))
		{
			$where = array(
	      		'query' =>  "historyID=':historyID'",
	      		'params' => array(
	               ':historyID'=>$historyID
					)
			);
			$historyData = DB::getRow("?:history", "*", $where);
			$siteID = $historyData['siteID'];
			
			if($responseData['success']['status'] == 'partiallyCompleted')
			{
				
				DB::update("?:history_additional_data", array('status' => 'success'), $where);
				DB::update("?:history", array('status' => 'completed'), $where);
				
				$allParams = array('action' => 'triggerRecheck', 'args' => array('params' => array('backupParentHID' => $historyData['parentHistoryID']), 'siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
				
				panelRequestManager::handler($allParams);
			}			
			elseif($responseData['success']['status'] == 'completed')
			{
				$where = array(
					'query' =>  "historyID=':historyID'",
					'params' => array(
						':historyID'=>$historyID
					)
				);
				DB::update("?:history_additional_data", array('status' => 'success'), $where);
				$where = array(
					'query' =>  "historyID=':historyID'",
					'params' => array(
						':historyID'=>$historyData['parentHistoryID']
					)
				);

				DB::update("?:history_additional_data", array('status' => 'success'), $where);

				updateHistory(array('status' => 'completed'), $historyData['parentHistoryID']);
				$allParams = false;
				if (method_exists('manageClientsInstallCloneCommon', 'triggerInstallCloneCommonNewSite')) {
					$allParams = manageClientsInstallCloneCommon::triggerInstallCloneCommonNewSite($historyID, $historyData, $responseData, $siteID);
				}

				if ($allParams == false) {
					$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
				}

				panelRequestManager::handler($allParams);
			}
			else
			{
				$where = array(
		      		'query' =>  "historyID=':historyID'",
		      		'params' => array(
		               ':historyID'=>$historyID
						)
				);
				DB::update("?:history_additional_data", array('status' => 'error'), $where);
				
				$errorMsg = isset($responseData['error']) ? $responseData['error'] : 'Unknown error occurred';
				$errorCode = isset($responseData['error_code']) ? $responseData['error_code'] : 'unknown_error_occurred';
				$where = array(
		      		'query' =>  "historyID=:historyID",
		      		'params' => array(
		               ':historyID'=>$historyData['parentHistoryID']
						)
				);
				DB::update("?:history_additional_data", array('status' => 'error', 'errorMsg' => isset($responseData['success']['error']) ? $responseData['success']['error'] : $errorMsg, 'error' => isset($responseData['success']['error_code']) ? $responseData['success']['error_code'] : $errorCode), $where);
				
				updateHistory(array('status' => 'completed'), $historyData['parentHistoryID']);
				
				return;
								
			}
			
			
		}
	}
	
	public static function restoreBackupProcessor($siteIDs, $params){
		
		$type = "backup";
		$action = "restore";
		$requestAction = "restore";
		$timeout = (20 * 60);//20 mins
		
		$requestParams = array('task_name' => $params['taskName'], 'result_id' => $params['resultID']);
		
		$historyAdditionalData = array();
		$historyAdditionalData[] = array('uniqueName' => $params['taskName'], 'detailedAction' => $action);
		
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);		
			
			$events=1;
			
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['timeout'] 		= $timeout;
			
			prepareRequestAndAddHistory($PRP);
		}	
	}
	
	public static function restoreBackupResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		
		if(empty($responseData['success'])){
			return false;
		}
		$where = array(
      		'query' =>  "historyID=':historyID'",
      		'params' => array(
               ':historyID'=>$historyID
				)
		);
		if(!empty($responseData['success']['error'])){
			DB::update("?:history_additional_data", array('status' => 'error', 'errorMsg' => $responseData['success']['error'], 'error' => $responseData['success']['error_code']), $where);
			return;
		}
		
		
		if(!empty($responseData['success'])){
			DB::update("?:history_additional_data", array('status' => 'success'), $where);
		}
		
		//---------------------------post process------------------------>
		$siteID = DB::getField("?:history", "siteID", $where);
	
		$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
		
		panelRequestManager::handler($allParams);
		
	}
	
	public static function removeBackupProcessor($siteIDs, $params, $extras = array()){
		
		$type = "backup";
		$action = "remove";
		$requestAction = "delete_backup";
		
		$requestParams = array('task_name' => $params['taskName'], 'result_id' => $params['resultID']);
		
		$historyAdditionalData = array();
		$historyAdditionalData[] = array('uniqueName' => $params['taskName'], 'detailedAction' => $action);
		
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);	
			$events=1;	
			
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			
			if(isset($extras['doNotShowUser'])){
				$PRP['doNotShowUser'] 	= $extras['doNotShowUser'];
			}
			
			//if(isset($extras['runCondition']) && $extras['runCondition'] == true){
//				$runCondition = 	array();
//				$runCondition['satisfyType'] = 'AND';
//				$runCondition['query'] = array('table' => "history",
//													  'select' => 'historyID',
//													   'where' => "parentHistoryID = ".$params['resultID']." AND status IN('completed', 'error', 'netError') ORDER BY ID DESC LIMIT 1");
//													  /*'where' => "type IN('backup', 'scheduleBackup') AND action IN('multiCallNow, 'now', 'multiCallRunTask', 'runTask') AND status IN('success', 'error', 'netError')");*/
//													  //'where' => "status NOT IN('multiCallWaiting')");
//				$PRP['runCondition'] = serialize($runCondition);
//				$PRP['status'] = 'scheduled';
//			}
		
			prepareRequestAndAddHistory($PRP);
		}	
	}
	
	public static function removeBackupResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		if(empty($responseData['success'])){
			return false;
		}
		$where = array(
      		'query' =>  "historyID=':historyID'",
      		'params' => array(
               ':historyID'=>$historyID
				)
		);
		if(!empty($responseData['success']['error'])){
			DB::update("?:history_additional_data", array('status' => 'error', 'errorMsg' => $responseData['success']['error'], 'error' => $responseData['success']['error_code']), $where);
			return;
		}
		
		if(!empty($responseData['success'])){
			DB::update("?:history_additional_data", array('status' => 'success'), $where);
		}
		
		//---------------------------post process------------------------>
		$siteID = DB::getField("?:history", "siteID", $where);
	
		$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
		
		panelRequestManager::handler($allParams);	
	}
	
	
}
manageClients::addClass('manageClientsBackup');

?>
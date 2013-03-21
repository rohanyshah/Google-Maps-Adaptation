<?php   require_once("../../../config.php");

	//who's logged in? loggedInUserGuid will be an empty string if no user is logged in...
	$loggedInUserGuid = "";
	if(isset($_COOKIE[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_COOKIE[APP_LOGGEDIN_COOKIE_NAME]);
	if($loggedInUserGuid == ""){
		if(isset($_SESSION[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_SESSION[APP_LOGGEDIN_COOKIE_NAME]);
	}
	
	//plugins table constant is different on self hosted servers..
	if(!defined("TBL_PLUGINS")){
		define("TBL_PLUGINS", TBL_BT_PLUGINS);
	}
	
	//end request if user is not logged in...
	if($loggedInUserGuid == ""){
		echo "<span style='color:red;'>Logged out</span>";
		exit();
	}	
		
	//init user object for the logged in user...
	$objLoggedInUser = new User($loggedInUserGuid);
	
	//vars...
	$dtNow = fnMySqlNow();
	$strMessage = "";
	$bolDone = false;
	$bolPassed = true;
	$command = fnGetReqVal("command", "", $myRequestVars);
	$appGuid = fnGetReqVal("appGuid", "", $myRequestVars);
	$BT_childItemId = fnGetReqVal("BT_childItemId", "", $myRequestVars);
	$BT_parentScreenItemId = fnGetReqVal("BT_parentScreenItemId", "", $myRequestVars);

	//required...
	if($appGuid == "" || $BT_parentScreenItemId == "" || $BT_childItemId == ""){
		echo "invalid request";
		exit();
	}
	
	//app object
	$objApp = new App($appGuid);
	
	//make sure the person that is logged in has the privilege to manage this app....
	if($objApp->fnCanManageApp($loggedInUserGuid, $objLoggedInUser->infoArray["userType"], $appGuid, $objApp->infoArray["ownerGuid"])){
		//all good, fnCanManageApp will end exectuion if invalid..
	}
	
	///////////////////////////////////////////////////////
	//form posted (ajax request)
	if($isFormPost){
		
		//childItem we are updating...
		$objChildItem = new Bt_item($BT_childItemId);
		
		//item type of child item...
		$itemType = $objChildItem->infoArray["itemType"];
		
		//validate...
		if($itemType == ""){
			$bolPassed = false;
			$strMessage .= "<br>Item type required.";
		}
		
		//if no good
		if(!$bolPassed){
			echo "<img src='" . APP_URL . "/images/red_dot.png' style='margin-right:5px;'><span style='color:red;'>Changes not saved! " . $strMessage . "</span>";
			exit();
		}
		
		//if good
		if($bolPassed){
		
			//start the JSON with the itemId and itemType
			$jsonVars = "{\"itemId\":\"" . $BT_childItemId . "\", \"itemType\":\"" . $itemType. "\", ";
			
			//loop through all the form vars that begin with "json_" to build the json..
			foreach($_POST as $key => $val){
				if(substr($key, 0, 5) == "json_"){
				
					//magic quotes or not?
					if(get_magic_quotes_gpc() == 1){
						$val = $val;
					}else{
						$val = fnFormInput($val);
					}					
					
					//if we have a value, add it to the jsonVar...
					if($val != ""){
						$jsonVars .= "\"" . str_replace("json_", "", $key) . "\":\"" . $val  . "\", ";
					}
					
				}//end if this form field begins with "json_"					
			}//end for each form field...
			
			//remove the last comma before ending
			$jsonVars = fnRemoveLastChar($jsonVars, ",");
			$jsonVars .= "}";
		
			//update app...
			$objApp->infoArray["modifiedUTC"] = $dtNow;
			$objApp->fnUpdate();
			
			//update child item...
			$objChildItem->infoArray["modifiedUTC"] = $dtNow;
			$objChildItem->infoArray["jsonVars"] = $jsonVars;
			$objChildItem->fnUpdate();

			//done...
			echo "<img src='" . APP_URL . "/images/green_dot.png' style='margin-right:5px;'><b>Saved!</b>";
			echo "<div style='padding-top:5px;color:#000000;'>";
				echo "<b>JSON Data for this item</b> (for reference, copy and paste as needed)";
			echo "</div>";
			echo "<div style='padding-top:5px;padding-bottom:5px;color:#000000;font-family:monospace;'>";
					echo $jsonVars;
			echo "</div>";
			
				
		}//bolPassed	
			
	}//was submitted
	

	//done
	exit();
	
	
?>


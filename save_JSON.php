<?php   require_once("../../../config.php");

	//who's logged in? loggedInUserGuid will be an empty string if no user is logged in...
	$loggedInUserGuid = "";
	if(isset($_COOKIE[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_COOKIE[APP_LOGGEDIN_COOKIE_NAME]);
	if($loggedInUserGuid == ""){
		if(isset($_SESSION[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_SESSION[APP_LOGGEDIN_COOKIE_NAME]);
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
	
	//get the appGuid, BT_itemId, and nickname from the calling screen's hidden form fields...
	$appGuid = fnGetReqVal("appGuid", "", $myRequestVars);
	$BT_itemId = fnGetReqVal("BT_itemId", "", $myRequestVars);
	
	//get the nickname from the calling screen's advanced properties...
	$nickname = fnGetReqVal("json_nickname", "", $myRequestVars);
	
	//get possible right side nav button properties...
	$navBarRightButtonType = fnGetReqVal("json_navBarRightButtonType", "", $myRequestVars);
	$navBarRightButtonTapLoadScreenNickname = fnGetReqVal("json_navBarRightButtonTapLoadScreenNickname", "", $myRequestVars);
	$navBarRightButtonTapLoadScreenItemId = fnGetReqVal("json_navBarRightButtonTapLoadScreenItemId", "", $myRequestVars);
	
	//need an appGuid and a BT_itemId...
	if($appGuid == "" || $BT_itemId == ""){
		echo "invalid request";
		exit();
	}
	
	//init the App object using this app's guid...
	$objApp = new App($appGuid);
	
	//make sure the person that is logged in has the privilege to manage this app....
	if($objApp->fnCanManageApp($loggedInUserGuid, $objLoggedInUser->infoArray["userType"], $appGuid, $objApp->infoArray["ownerGuid"])){
		//all good, fnCanManageApp will end execution if invalid..	
	}
	
	///////////////////////////////////////////////////////
	//form posted (ajax request)
	if($isFormPost){
		
		
		//must have nickname..
		if($nickname == ""){
			$bolPassed = false;
			$strMessage .= "<br/>Nickname required.";
		}
		
		//make sure nickname is available...
		if($bolPassed){
			$strSql = "SELECT id FROM " . TBL_BT_ITEMS . " WHERE nickname = '" . $nickname . "' ";
			$strSql .= " AND appGuid = '" . $appGuid . "'";
			$strSql .= " AND guid != '" . $BT_itemId . "'";
			$strSql .= " AND controlPanelItemType = 'screen' ";
			$tmpId = fnGetOneValue($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
			if($tmpId != ""){
				$bolPassed = false;
				$strMessage .= "<br>The nickname you entered is already in use, no duplicates allowed.";
			}
		}
		
		//if we are using a right-button, we need the id of the screen to load from the nickname entered...
		if($bolPassed && $navBarRightButtonTapLoadScreenNickname != ""){
			$strSql = "SELECT guid FROM " . TBL_BT_ITEMS . " WHERE nickname = '" . $navBarRightButtonTapLoadScreenNickname . "' ";
			$strSql .= " AND appGuid = '" . $appGuid . "'";
			$strSql .= " AND controlPanelItemType = 'screen' ";
			$tmpId = fnGetOneValue($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
			if($tmpId == ""){
				$bolPassed = false;
				$strMessage .= "<br>No \"load screen\" for the nickname for the right-nav-button was found?";
			}else{
				$navBarRightButtonTapLoadScreenItemId = $tmpId;
			}
		}else{
			$navBarRightButtonTapLoadScreenItemId = "";
		}		

		//if right nav button is 'home button' and no load screen nickname was entered.
		if($navBarRightButtonType == "home" && $navBarRightButtonTapLoadScreenItemId == ""){
			$navBarRightButtonTapLoadScreenItemId = "goHome";
		}
		
		//if no good
		if(!$bolPassed){
			echo "<img src='" . APP_URL . "/images/red_dot.png' style='margin-right:5px;'><span style='color:red;'>Changes not saved! " . $strMessage . "</span>";
			exit();
		}
		
		//if good
		if($bolPassed){
		
			//screen object we are updating..
			$objScreen = new Bt_item($BT_itemId);
			$itemType = $objScreen->infoArray["itemType"];
			
			/*
				NOTE: 	There are three properties that are NOT saved in the jsonVars field of the database for each screen. 
						itemId, itemType, itemNickname
						-------------------------------
						This is because these values are added to the json data for each screen when the app's main config.txt
						file is created.  			
			*/
			
			
			
			//start of JSON vars...
			$jsonVars = "{";
			
			//loop through all the form vars that begin with "json_" to build the json..
			foreach($_POST as $key => $val){
				if(substr($key, 0, 5) == "json_"){
				
					//clean up the inputed form field value (prevent SQL injections!)...
					$val = fnFormInput($val);
					
					//if this is the "right button load screen" field, use the id for the screen we found above (on line 88)...
					if($key == "json_navBarRightButtonTapLoadScreenItemId"){
						$val = $navBarRightButtonTapLoadScreenItemId;
					}
					
					//if we have a value, add it to the jsonVar...
					if($val != "" && strtoupper($key) != "JSON_NICKNAME"){
						$jsonVars .= "\"" . str_replace("json_", "", $key) . "\":\"" . $val  . "\", ";
					}
					
				}//end if this form field begins with "json_"					
			}//end for each form field...
			
			//remove the last comma before ending
			$jsonVars = fnRemoveLastChar($jsonVars, ",");
			$jsonVars .= "}";
			
			//update screen...
			$objScreen->infoArray["nickname"] = $nickname;
			$objScreen->infoArray["jsonVars"] = $jsonVars;
			$objScreen->infoArray["modifiedUTC"] = $dtNow;
			$objScreen->fnUpdate();
			
			//update app
			$objApp->infoArray["modifiedUTC"] = $dtNow;
			$objApp->fnUpdate();
			
			//done...
			echo "<img src='" . APP_URL . "/images/green_dot.png' style='margin-right:5px;'><b>Saved!</b>";
			echo "<div style='padding-top:5px;color:#000000;'>";
				echo "<b>JSON Data for this Plugin</b> (for reference, copy and paste as needed)";
			echo "</div>";
			echo "<div style='padding-top:5px;padding-bottom:5px;color:#000000;font-family:monospace;'>";
					$displayJson = "{\"itemId\":\"" . $BT_itemId . "\", \"itemType\":\"" . $itemType . "\", \"itemNickname\":\"" . $nickname . "\", ";
					$displayJson = str_replace("{", $displayJson, $jsonVars);
					echo $displayJson;
			echo "</div>";
			
			
			exit();	
			
		}//bolPassed	
		
	}//was submitted
	
	
	//done
	exit();
	
	
?>


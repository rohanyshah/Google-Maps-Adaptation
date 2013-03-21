<?php   require_once("../../../config.php");

	//who's logged in? loggedInUserGuid will be an empty string if no user is logged in...
	$loggedInUserGuid = "";
	if(isset($_COOKIE[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_COOKIE[APP_LOGGEDIN_COOKIE_NAME]);
	if($loggedInUserGuid == ""){
		if(isset($_SESSION[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_SESSION[APP_LOGGEDIN_COOKIE_NAME]);
	}
			
	//init user object for the logged in user (we may not have a logged in user)...
	$objLoggedInUser = new User($loggedInUserGuid);
	
	//if loggedInUserGuid is empty, kick out the user...
	$objLoggedInUser->fnLoggedInReq($loggedInUserGuid);
	
	//if we do have a logged in user, update their "last page view"...
	$objLoggedInUser->fnUpdateLastRequest($loggedInUserGuid);

	//plugins table constant is different on self hosted servers..
	if(!defined("TBL_PLUGINS")){
		if(defined("TBL_BT_PLUGINS")){
			define("TBL_PLUGINS", TBL_BT_PLUGINS);
		}
	}


	//////////////////////////////////////////////////////////////////////////////
	/*
		The includePath and controlPanelURL variables in this script is used in the HTML on this page to
		account for differences between Self Hosted control panels and buzztouch.com control panels.
	*/
	$includePath = "";
	$controlPanelURL = "";
	if(defined("APP_CURRENT_VERSION")){
		$includePath = rtrim(APP_PHYSICAL_PATH, "/") . "/bt_v15/bt_includes";
		$controlPanelURL = "../../../bt_v15/bt_app";
	}else{
		$includePath = rtrim(APP_PHYSICAL_PATH, "/") . "/app/cp_v20/bt_includes";
		$controlPanelURL = "../../../app/cp_v20/bt_app";
	}
	//////////////////////////////////////////////////////////////////////////////
	
	//the Page class is used to construct all the HTML pages in the control panel...
	$objControlPanelWebpage = new Page();
	
	//the guid of the app comes from the URL (if a GET request) or the hidden form field (if a POST request)...
	$appGuid = fnGetReqVal("appGuid", "", $myRequestVars);
	
	//the guid of the BT_itemId comes from the URL (if a GET request) or the hidden form field (if a POST request)...
	$BT_itemId = fnGetReqVal("BT_itemId", "", $myRequestVars);

	//need an appGuid and a BT_itemId...
	if($appGuid == "" || $BT_itemId == ""){
		echo "invalid request";
		exit();
	}else{
		
		//init the App object using this app's guid...
		$objApp = new App($appGuid);
		
		//the the page object to use the app's name in the webpage's title...
		$objControlPanelWebpage->pageTitle = $objApp->infoArray["name"] . " Control Panel";

		//init the BT_item (the screen object) using this screen's guid...
		$objBT_item = new Bt_item($BT_itemId);
		
		//we need the uniquePluginId for this plugin, it's used in the inc_pluginDetails.php file (included lower in this file's HTML)...
		$uniquePluginId = $objBT_item->infoArray["uniquePluginId"];

		//we need the jsonVars for this plugin, they are used in the advanced properties (included lower in this file's HTML)...
		$jsonVars = $objBT_item->infoArray["jsonVars"];

		//we need the nickname for this plugin, it is used in the advanced properties (included lower in this file's HTML)...
		$nickname = $objBT_item->infoArray["nickname"];

		//make sure the person that is logged in has the privilege to manage this app....
		if($objApp->fnCanManageApp($loggedInUserGuid, $objLoggedInUser->infoArray["userType"], $appGuid, $objApp->infoArray["ownerGuid"])){
			//all good, fnCanManageApp will end exectuion if invalid..
		}
		
	}
	

	///////////////////////////////////////////////////////////////////////////////
	//from previous screen so back button maintains sorting / paging / searching
	$sortUpDown = fnGetReqVal("sortUpDown", "DESC", $myRequestVars);
	$sortColumn = fnGetReqVal("sortColumn", "", $myRequestVars);
	$currentPage = fnGetReqVal("currentPage", "1", $myRequestVars);
	$viewStyle = fnGetReqVal("viewStyle", "gridView", $myRequestVars);
	$search = fnGetReqVal("searchInput", "search...", $myRequestVars);
	$searchPluginTypeUniqueId = fnGetReqVal("searchPluginTypeUniqueId", "search...", $myRequestVars);

	//querystring for links so user can "go back" and without losing paging / sorting / filtering variables...
	$qVars = "&searchInput=" . fnFormOutput($search) . "&searchPluginTypeUniqueId=" . $searchPluginTypeUniqueId;
	$qVars .= "&sortColumn=" . $sortColumn . "&sortUpDown=" . $sortUpDown . "&currentPage=" . $currentPage;
	$qVars .= "&viewStyle=" . $viewStyle;
	///////////////////////////////////////////////////////////////////////////////


	///////////////////////////////////////////////////////////////////////////////
	//childItem logic...
	$BT_childItemId = fnGetReqVal("BT_childItemId", "", $myRequestVars);
	$command = fnGetReqVal("command", "", $myRequestVars);
	$scriptName = "index.php";
	$dtNow = fnMySqlNow();
	$bolDeleted = false;
	$bolDone = false;
	$bolPassed = true;
	$strMessage = "";

	//childItem properties...
	$title = fnGetReqVal("title", "", $myRequestVars);
	$subTitle = fnGetReqVal("subTitle", "", $myRequestVars);
	$latitude = fnGetReqVal("latitude", "", $myRequestVars);
	$longitude = fnGetReqVal("longitude", "", $myRequestVars);
	$calloutTapChoice = fnGetReqVal("calloutTapChoice", "", $myRequestVars);
	$loadScreenNickname = fnGetReqVal("loadScreenNickname", "", $myRequestVars);
	$pinColor = fnGetReqVal("pinColor", "", $myRequestVars);
	$transitionType = fnGetReqVal("transitionType", "", $myRequestVars);

	//for creating new child items...
	$newChildItemGuid = strtoupper(fnCreateGuid());
	$loadScreenItemId = "";

	//if deleting a child item
	if($appGuid != "" && $BT_itemId != "" && $BT_childItemId != "" && $command == "confirmDelete"){
		$strSql = "DELETE FROM " . TBL_BT_ITEMS . " WHERE appGuid = '" . $appGuid . "' ";
		$strSql .= " AND guid = '" . $BT_childItemId . "' ";
		fnExecuteNonQuery($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
		$bolDeleted = TRUE;
	}//if deleting
	

	//if adding a new child BT_item
	if(strtoupper($command) == "ADDITEM"){

		//validate
		if(strlen($title) < 1 || strlen($subTitle) < 1){
			$bolPassed = false;
			$strMessage .= "<br>Title and Sub-Title required.";
		}
		if(!is_numeric($latitude) || !is_numeric($longitude)){
			$bolPassed = false;
			$strMessage .= "<br>Numbers only for latitude / longitude (negative numbers OK).";
		}else{
			if($latitude == "0" || $longitude == "0"){
				$bolPassed = false;
				$strMessage .= "<br>Latitude and longitude cannot be zero.";
			}
		}
		
		if($calloutTapChoice == "loadAnotherScreen" && $loadScreenNickname == ""){
			$bolPassed = false;
			$strMessage .= "<br>Please enter the nickname of the screen you want to load when this location is tapped.";
		}
		if($loadScreenNickname != "" && $calloutTapChoice == "showDirections"){
			$bolPassed = false;
			$strMessage .= "<br>You chose to show driving directions when the callout window is tapped but you also entered the nickname ";
			$strMessage .= "of a screen to load. It's a one-or-the-other deal. When a user taps the location on the map ";
			$strMessage .= "you can either show them directions to the location or you can load another screen, you can't do both.";
		}
		
		
		//if still good, make sure we don't have any duplicate locations (lat / long)
		if($bolPassed){
			$tmp = "SELECT jsonVars FROM " . TBL_BT_ITEMS . " WHERE parentItemGuid = '" . $BT_itemId . "' ";
			$tmp .= " LIMIT 0, 1";
            $res = fnDbGetResult($tmp, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
			if($res){
            	$numRows = mysql_num_rows($res);
                if($numRows == 1){
					while($row = mysql_fetch_array($res)){	
						if($row["jsonVars"] != ""){
						$json = new Json; 
						$decoded = $json->unserialize($row["jsonVars"]);
							if(is_object($decoded)){
								if($decoded->latitude == $latitude || $decoded->longitude == $longitude){
									$bolPassed = false;
									$strMessage .= "<br>This location has already been entered. Only one location per latitude / longitude pair is allowed.";
									break;
								}
							}
						} 						
					}//while
				}//numRows
			}//res
		}
		
		
		//if we entered a screenNickname, get it's guid and item type
		if($bolPassed && $loadScreenNickname != ""){
 			$strSql = " SELECT guid FROM " . TBL_BT_ITEMS;
			$strSql .= " WHERE nickname = '" . $loadScreenNickname . "'";
			$strSql .= " AND appGuid = '" . $appGuid . "'";
			$strSql .= " AND controlPanelItemType = 'screen' ";
			$strSql .= " LIMIT 0, 1";
            $loadScreenItemId = fnGetOneValue($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
			if($loadScreenItemId == ""){
				$bolPassed = false;
				$strMessage .= "<br>No \"load screen\" with this nickname was found?";
			}
		}

		//insert the new childItem 
		if($bolPassed){
			
			//callOutTapLoadScreenWithItemId
			$jsonVarsChildItem = "{\"itemId\":\"" . $newChildItemGuid . "\", \"itemType\":\"BT_mapLocation\",";
			$jsonVarsChildItem .= "\"latitude\":\"" . $latitude . "\", ";
			$jsonVarsChildItem .= "\"longitude\":\"" . $longitude . "\", ";
			$jsonVarsChildItem .= "\"title\":\"" . $title . "\", ";
			$jsonVarsChildItem .= "\"subTitle\":\"" . $subTitle . "\"";
				
				//if pin color...
				if($pinColor != ""){
					$jsonVarsChildItem .= ", \"pinColor\":\"" . $pinColor . "\"";
				}

				//if transition type...	
				if($transitionType != ""){
					$jsonVarsChildItem .= ", \"transitionType\":\"" . $transitionType . "\"";
				}

				//if we are loading a screen when the item is tapped..
				if($loadScreenItemId != ""){
					$jsonVarsChildItem .= ", \"loadScreenWithItemId\":\"" . $loadScreenItemId . "\"";
				}
				if($calloutTapChoice == "showDirections"){
					$jsonVarsChildItem .= ", \"loadScreenWithItemId\":\"showDirections\"";
				}
			
			$jsonVarsChildItem .= "}";
			
			//add the new BT_item for the map location...
			$objChildItem = new Bt_item("");
			$objChildItem->infoArray["guid"] = $newChildItemGuid;
			$objChildItem->infoArray["parentItemGuid"] = $BT_itemId;;
			$objChildItem->infoArray["uniquePluginId"] = "";
			$objChildItem->infoArray["loadClassOrActionName"] = "";
			$objChildItem->infoArray["hasChildItems"] = "0";
			$objChildItem->infoArray["loadItemGuid"] = $loadScreenItemId;
			$objChildItem->infoArray["appGuid"] = $appGuid;
			$objChildItem->infoArray["controlPanelItemType"] = "quizQuestion";
			$objChildItem->infoArray["itemType"] = "BT_locationItem";
			$objChildItem->infoArray["itemTypeLabel"] = "Map Location";
			$objChildItem->infoArray["nickname"] =  $title . ":" . $newChildItemGuid;
			$objChildItem->infoArray["orderIndex"] = "99";
			$objChildItem->infoArray["jsonVars"] = $jsonVarsChildItem;
			$objChildItem->infoArray["status"] = "active";
			$objChildItem->infoArray["dateStampUTC"] = $dtNow;
			$objChildItem->infoArray["modifiedUTC"] = $dtNow;
			$objChildItem->fnInsert();
			
			//update the app's modified date...
			$strSql = " UPDATE " . TBL_APPLICATIONS . " SET modifiedUTC = '" . $dtNow . "' WHERE guid = '" . $appGuid . "'";
			fnExecuteNonQuery($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
			
			//flag, reset
			$bolDone = true;
			$strMessage = "done";
			
			//reset vars...
			$title = "";
			$subTitle = "";
			$latitude = "";
			$longitude = "";
			$calloutTapChoice = "";
			$loadScreenNickname = "";
			$newChildItemGuid = "";
			$loadScreenItemId = "";
			$pinColor = "";
			$transitionType = "";

			
 		}//bolPassed
			
			
	}//if adding a new child BT_item
	//childItem logic...
	///////////////////////////////////////////////////////////////////////////////


	//ask the Page object to print the html to produce the control panel's webpage...
	echo $objControlPanelWebpage->fnGetPageHeaders();
	echo $objControlPanelWebpage->fnGetBodyStart();
	echo $objControlPanelWebpage->fnGetTopNavBar($loggedInUserGuid);
	
?>

<!--do not remove these hidden form fields, they are required for the control panel -->
<input type="hidden" name="appGuid" id="appGuid" value="<?php echo $appGuid;?>">
<input type="hidden" name="BT_itemId" id="BT_itemId" value="<?php echo $BT_itemId;?>">
<input type="hidden" name="sortUpDown" id="sortUpDown" value="<?php echo $sortUpDown;?>">
<input type="hidden" name="sortColumn" id="sortColumn" value="<?php echo $sortColumn;?>">
<input type="hidden" name="currentPage" id="currentPage" value="<?php echo $currentPage;?>">
<input type="hidden" name="viewStyle" id="viewStyle" value="<?php echo $viewStyle;?>">
<input type="hidden" name="search" id="search" value="<?php echo fnFormOutput($search);?>">
<input type="hidden" name="searchPluginTypeUniqueId" id="searchPluginTypeUniqueId" value="<?php echo $searchPluginTypeUniqueId;?>">
<input type="hidden" name="command" id="command" value="" />

<!--load the jQuery files using the Google API -->
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>

<script>

	//lookup latitude, longitude
	function findAddress(){
		var resDiv = document.getElementById("locationLookupResult");
		var theAddress = document.forms[0].subTitle.value;
		resDiv.style.color = "red";
	
		if(theAddress == ""){
			//ignore...
			resDiv.style.color = "white";

		}else{
			resDiv.innerHTML = "working...";
		
			//build the URL
			var theURL = "itemLocationLookup_AJAX.php?address=" + theAddress;
			fetchLocation(theURL);
		
		}
	}
	
	function fetchLocation(strURL) {
		var xmlHttpReq = false;
		var self = this;
		// Mozilla/Safari
		if (window.XMLHttpRequest) {
			self.xmlHttpReq = new XMLHttpRequest();
		}
		// IE
		else if (window.ActiveXObject) {
			self.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
		}
		self.xmlHttpReq.open('POST', strURL, true);
		self.xmlHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		self.xmlHttpReq.onreadystatechange = function() {
			if(self.xmlHttpReq.readyState == 4) {
				updateLatitudeLongitude(self.xmlHttpReq.responseText);
			}
		}
		self.xmlHttpReq.send(strURL);
	}
	
	//called after ajax fetch
	function updateLatitudeLongitude(theResult){
		var resDiv = document.getElementById("locationLookupResult");
		var latitudeEl = document.forms[0].latitude;
		var longitudeEl = document.forms[0].longitude;
		var pos = theResult.indexOf("|");
		if(pos < 1){
			resDiv.style.color = "red";
			resDiv.innerHTML = "Can't find coordinates!";
		}else{
			var temp = theResult.split("|");
			if(temp[0] != "0" && temp[1] != "0"){
				latitudeEl.value = temp[0];
				longitudeEl.value = temp[1];
				resDiv.innerHTML = "Got it!";
				resDiv.style.color = "green";
			}else{
				resDiv.style.color = "red";
				resDiv.innerHTML = "Can't find coordinates!";
			}
		}
	}			


	<!--shows or hides advanced property section -->
	function fnExpandCollapse(hideOrExpandElementId){
		var theBoxToExpandOrCollapse = document.getElementById(hideOrExpandElementId);
		if(theBoxToExpandOrCollapse.style.display == "none"){
			theBoxToExpandOrCollapse.style.display = "block";
		}else{
			theBoxToExpandOrCollapse.style.display = "none";
		}
	}
	
	<!--saves advanced property -->
	function saveAdvancedProperty(showResultsInElementId){
	
		//hide all the previous "saved result" messages...
		var divs = document.getElementsByTagName('div');
		for(var i = 0; i < divs.length; i++){ 
			if(divs[i].id.indexOf("saveResult_", 0) > -1){
				divs[i].innerHTML = "&nbsp;";
			}
		} 	
	
		//show working message in the appropriate <html> element...
		resultsDiv = document.getElementById(showResultsInElementId)
		resultsDiv.innerHTML = "saving entries...";
		resultsDiv.className = "submit_working";
		
		//make sure jQuery is loaded...
		if(jQuery) {  
			
			//vars for the form, it's fields, and it's values...
			var $form = $("#frmMain"),
			
			//select and cache all form fields...
			$inputs = $form.find("input, select, button, textarea"),
			
			//serialize the data in the form...
			serializedData = $form.serialize();
		
			//disable the inputs while we wait for results...
			$inputs.attr("disabled", "disabled");
			
			//POST the ajax request...
		   $.ajax({
				url: "save_JSON.php",
				type: "post",
				data: serializedData,
				
				//function that will be called on success...
				success:function(response, textStatus, jqXHR){
					
					//show response...
					if(response == "invalid request"){
						resultsDiv.className = "submit_working";
					}else{
						resultsDiv.className = "submit_done";
					}
					resultsDiv.innerHTML = response;
				
				},
				
				//function that will be called on error...
				error: function(jqXHR, textStatus, errorThrown){
					resultsDiv.className = "submit_working";
					resultsDiv.innerHTML = "A problem occurred while saving the entries (2)";
				},
				
				//function that will be called on completion (success or error)...
				complete: function(){
					$inputs.removeAttr("disabled");
				}
			});		  
		
		}else{
			//jQuery is not loaded?
			resultsDiv.innerHTML = "jQuery not loaded?";
		}		
		
		
		
	}
	


</script>

<div class='content'>
        
    <fieldset class='colorLightBg'>

        <!-- app control panel navigation--> 
        <div class='pluginNav'>
            <span style='white-space:nowrap;'><a href="<?php echo $controlPanelURL . "/?appGuid=" . $appGuid;?>" title="Application Control Panel">Application Home</a></span>
            &nbsp;&nbsp;|&nbsp;&nbsp;
			<?php echo $objControlPanelWebpage->fnGetControlPanelLinks("application", $appGuid, "inline", $viewStyle); ?>
        </div>

       	<div class='pluginContent colorDarkBg minHeight'>
               
            <!-- breadcrumbs back to screens and actions or this screens parent screen -->
            <div class='contentBand colorBandBg' style='font-size:10pt;'>
            	<?php include($includePath . "/inc_screenBreadcrumbs.php");?>
            </div>

            <!--plugin icon and details on right -->
            <div class="pluginRight colorLightBg">
                <?php include($includePath . "/inc_pluginDetails.php");?>                    
            </div>
                

            <!--screen / action properties on left-->
            <div class="pluginLeft minHeight">

                 <div style='margin:10px;'>   
                    <b>This shows a map with one or more location markers.</b>
                    <div style='padding-top:5px;'>
                    	Selecting a location marker shows a call-out window with title and sub-title. Optionally, 
                        tapping the call-out window may load driving directions or load another
                        screen. You can manually enter individual locations or you can pull locations from
                        a remote file using the Screen Data URL in the advanced settings.
                    </div>
                </div>
                
                
				
                
                <!--
                	############### Developer Notes ###############
                
                	The HTML below is used to allow a user to manipulate the advanced properties of this plugin. It's possible
                    to have a plugin that does not allow the user to change any properties using the coontrol panel.

                    ALL PLUGINS NEED TO ALLOW THE USER TO UPDATE THE SCREEN'S NICKNAME IN THEIR CONTROL PANELS.
                    
                    Each "section" below contains the HTML and form fields for some common advanced properties, along with 
                    a SAVE button. Clicking the SAVE button triggers a javascript function (included higher up in this file) 
                    that POST'S all the form field entries the user made to a .PHP file in this plugins directory. This file is
                    named save_JSON.php. It is this file that is responsible for saving the entries to the database then 
                    returning a "saved" or "error" message when it finishes. 
                    
                    Form Field Names: If you need to add additional form fields to control additional advanced properties for this
                    plugin, prepend "json_" to the name AND id of the form field. Example: If the plugin allows a user to change a title used
                    used by the plugin in the mobile app, the form field may be named: "json_title". Following this methodology allows
                    you to easily add new form fields (new advanced properties) without having to change the save_JSON.php file. This
                    save_JSON.php will automatically create the JSON data for this plugin if it finds form fields with the "json_" prefix.               
                
                -->
                
                
                
                
                
                
                
                <!-- ##################################################### -->                   
				<!-- ############### nickname property ###############-->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_nickname');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Nickname</a>
                    <div id="box_nickname" style="display:none;">
                        
                        <div style='padding-top:10px;'>
                            <b>Enter a Nickname</b><br/>
                            <input type="text" name="json_nickname" id="json_nickname" value="<?php echo fnFormOutput($nickname);?>">
                        </div>
                        
                        <div style='padding-top:5px;'>
                            <input type='button' title="save" value="save" class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_nickname');return false;">
                            <div id="saveResult_nickname" class="submit_working">&nbsp;</div>
                        </div>
            
                    </div>    
                </div>
				<!-- ############### end nickname property ############### -->
                <!-- ##################################################### -->                   
				
 
 
 
                <!-- ##################################################### -->                   
                <!-- ############### navBar properties ############### -->
				<?php
                
                    //if this screen's json has a navBarRightButtonTapLoadScreenItemId we need the name of the screen....	
                    $navBarRightButtonTapLoadScreenNickname = "";
                    $navBarRightButtonTapLoadScreenItemId = fnGetJsonProperyValue("navBarRightButtonTapLoadScreenItemId", $jsonVars);
                    if($navBarRightButtonTapLoadScreenItemId != ""){
                        $strSql = "SELECT nickname FROM " . TBL_BT_ITEMS . " WHERE guid = '" . $navBarRightButtonTapLoadScreenItemId . "' AND appGuid = '" . $appGuid . "'";
                        $navBarRightButtonTapLoadScreenNickname = fnGetOneValue($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
                    }
                
                ?>
                
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_navBar');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Top Navigation Bar</a>
                    <div id="box_navBar" style="display:none;">
                    
                        <table style='margin-top:10px;'>
                            <tr>
                                <td style='vertical-align:top;'>
                    
                                    <b>Nav. Bar Title</b><br/>
                                    <input type="text" name="json_navBarTitleText" id="json_navBarTitleText" value="<?php echo fnFormOutput(fnGetJsonProperyValue("navBarTitleText", $jsonVars));?>">
                            
                                    <br/><b>Nav. Bar Background Color</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerColor.php?formElVal=json_navBarBackgroundColor" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_navBarBackgroundColor" id="json_navBarBackgroundColor" value="<?php echo fnFormOutput(fnGetJsonProperyValue("navBarBackgroundColor", $jsonVars));?>" >
                                
                                    <br/><b>Nav. Bar Style</b><br/>
                                    <select name="json_navBarStyle" id="json_navBarStyle" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>--select--</option>
                                        <option value="solid" <?php echo fnGetSelectedString("solid", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>Solid  navigation bar</option>
                                        <option value="transparent" <?php echo fnGetSelectedString("transparent", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>Transparent navigation bar</option>
                                        <option value="hidden" <?php echo fnGetSelectedString("hidden", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>Hide the navigation bar</option>
                                    </select>
                                    
                                
                                </td>
                                <td style='vertical-align:top;'>
            
                                    <b>Right Button Type</b><br/>
                                    <select name="json_navBarRightButtonType" id="json_navBarRightButtonType" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>--select--</option>
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>No right button in nav bar</option>
                                        <option value="home" <?php echo fnGetSelectedString("home", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Home</option>
                                        <option value="next" <?php echo fnGetSelectedString("next", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Next</option>
                                        <option value="infoLight" <?php echo fnGetSelectedString("infoLight", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Info Light</option>
                                        <option value="infoDark" <?php echo fnGetSelectedString("infoDark", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Info Dark</option>
                                        <option value="details" <?php echo fnGetSelectedString("details", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Details</option>
                                        <option value="done" <?php echo fnGetSelectedString("done", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Done</option>
                                        <option value="cancel" <?php echo fnGetSelectedString("cancel", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Cancel</option>
                                        <option value="save" <?php echo fnGetSelectedString("save", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Save</option>
                                        <option value="add" <?php echo fnGetSelectedString("add", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Add</option>
                                        <option value="addBlue" <?php echo fnGetSelectedString("addBlue", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Add Blue</option>
                                        <option value="compose" <?php echo fnGetSelectedString("compose", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Compose</option>
                                        <option value="reply" <?php echo fnGetSelectedString("reply", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Reply</option>
                                        <option value="action" <?php echo fnGetSelectedString("action", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Action</option>
                                        <option value="organize" <?php echo fnGetSelectedString("organize", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Organize</option>
                                        <option value="bookmark" <?php echo fnGetSelectedString("bookmark", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Bookmark</option>
                                        <option value="search" <?php echo fnGetSelectedString("search", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Search</option>
                                        <option value="refresh" <?php echo fnGetSelectedString("refresh", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Refresh</option>
                                        <option value="camera" <?php echo fnGetSelectedString("camera", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Camera</option>
                                        <option value="trash" <?php echo fnGetSelectedString("trash", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Trash</option>
                                        <option value="play" <?php echo fnGetSelectedString("play", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Play</option>
                                        <option value="pause" <?php echo fnGetSelectedString("pause", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Pause</option>
                                        <option value="stop" <?php echo fnGetSelectedString("stop", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Stop</option>
                                        <option value="rewind" <?php echo fnGetSelectedString("rewind", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Rewind</option>
                                        <option value="fastForward" <?php echo fnGetSelectedString("fastForward", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Fast Forward</option>
                                    </select>
                        
                                    <br/><b>Right Button Load Screen</b>         
                                        &nbsp;&nbsp;
                                        <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                        <a href="<?php echo $controlPanelURL;?>/bt_pickerScreen.php?appGuid=<?php echo $appGuid;?>&formElVal=json_navBarRightButtonTapLoadScreenItemId&formElLabel=json_navBarRightButtonTapLoadScreenNickname" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_navBarRightButtonTapLoadScreenNickname" id="json_navBarRightButtonTapLoadScreenNickname" value="<?php echo fnFormOutput($navBarRightButtonTapLoadScreenNickname);?>">
                                    <input type="hidden" name="json_navBarRightButtonTapLoadScreenItemId" id="json_navBarRightButtonTapLoadScreenItemId" value="<?php echo fnFormOutput(fnGetJsonProperyValue("navBarRightButtonTapLoadScreenItemId", $jsonVars));?>">
                                    
                                    <br/><b>Right Button Transition Type</b> <span class="normal">(iOS Only)</span><br/>
                                    <select name="json_navBarRightButtonTapTransitionType" id="json_navBarRightButtonTapTransitionType" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>--select--</option>
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Default transition</option>
                                        <option value="fade" <?php echo fnGetSelectedString("fade", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Fade</option>
                                        <option value="flip" <?php echo fnGetSelectedString("flip", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Flip</option>
                                        <option value="curl" <?php echo fnGetSelectedString("curl", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Curl</option>
                                        <option value="grow" <?php echo fnGetSelectedString("grow", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Grow</option>
                                        <option value="slideUp" <?php echo fnGetSelectedString("slideUp", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Slide Up</option>
                                        <option value="slideDown" <?php echo fnGetSelectedString("slideDown", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Slide Down</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                                         
                        <div style="padding-top:5px;">
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_navBar');return false;">
                            <div id="saveResult_navBar" class="submit_working">&nbsp;</div>
                        </div>
                    
                    </div>
                </div>
				<!-- ############### end navBar properties ############### -->
                <!-- ##################################################### -->                   


                <!-- ################################################# -->                   
				<!-- ############### child items property ############-->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_childItems');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Map Locations</a>
                    <div id="box_childItems" style="display:block;">
				
						<?php if(strtoupper($command) == "DELETE"){ ?>
                            <div class="errorDiv" style="margin-top:10px;">
                                <br/>
                                <b>Delete this Location?</b>
                                <div style='padding-top:5px;'>
                                    Are you sure you want to do this? This cannot be undone!
                                </div>
                                <div style='padding-top:5px;'>
                                    <a href="<?php $scriptName;?>?appGuid=<?php echo $appGuid . $qVars;?>&BT_itemId=<?php echo $BT_itemId;?>"><img src="<?php echo APP_URL;?>/images/arr_right.gif" alt='arrow'/>No, do not delete this location</a>
                                </div>
                                <div style='padding-top:5px;'>
                                    <a href="<?php $scriptName;?>?appGuid=<?php echo $appGuid . $qVars;?>&BT_itemId=<?php echo $BT_itemId;?>&BT_childItemId=<?php echo $BT_childItemId;?>&command=confirmDelete"><img src="<?php echo APP_URL;?>/images/arr_right.gif" alt='arrow'/>Yes, permanently delete this location</a>
                                </div>
                            </div>
                        <?php } ?>
                            
                        <?php if($bolDeleted){ ?>
                            <div class="doneDiv" style="margin-top:10px;">
                                <b>Location Deleted.</b>
                                <div style='padding-top:5px;'>
                                    <a href="<?php $scriptName;?>?appGuid=<?php echo $appGuid . $qVars;?>&BT_itemId=<?php echo $BT_itemId;?>"><img src="<?php echo APP_URL;?>/images/arr_right.gif" alt='arrow'/>OK, hide this message</a>
                                </div>
                             </div>
                        <?php } ?> 
        
                        <?php if($strMessage != "" && !$bolDone){ ?>
                            <div class='errorDiv' style="margin-top:10px;">
                                <?php echo $strMessage;?>                                
                            </div>
                        <?php } ?> 
                        
                        <?php if($strMessage == "done" && $bolDone){ ?>
                            <div class='doneDiv' style="margin-top:10px;">
                                <b>Success</b>. 
                                The location was added to the map.
                                <div style='padding-top:5px;'>
                                    <a href="<?php $scriptName;?>?appGuid=<?php echo $appGuid . $qVars;?>&BT_itemId=<?php echo $BT_itemId;?>"><img src="<?php echo APP_URL;?>/images/arr_right.gif" alt='arrow'/>OK, hide this message</a>
                                </div>
                            </div>
                        <?php } ?>
                        
                        <?php if($strMessage == "orderUpdated" && $bolDone){ ?>
                            <div class='doneDiv' style="margin-top:10px;">
                                <b>Order Updated</b>. 
                                <div style='padding-top:5px;'>
                                    <a href="<?php $scriptName;?>?appGuid=<?php echo $appGuid . $qVars;?>&BT_itemId=<?php echo $BT_itemId;?>"><img src="<?php echo APP_URL;?>/images/arr_right.gif" alt='arrow'/>OK, hide this message</a>
                                </div>
                            </div>
                        <?php } ?>
                        
               
                
                        <!--list of existing items -->
                        <table cellspacing='0' cellpadding='0' style='width:100%;margin-top:10px;'>
                            
                            <?php
                                
                                //fetch existing rows
                                $cnt = 0;
                                $strSql = " SELECT I.id, I.guid, I.parentItemGuid, I.loadItemGuid, I.appGuid, I.itemType, I.itemTypeLabel, I.nickname, I.orderIndex, ";
                                $strSql .= "I.jsonVars, I.status, I.dateStampUTC, I.modifiedUTC, ";
                                $strSql .= "I2.itemTypeLabel AS loadScreenType, I2.nickname AS tapScreenNickname,  ";
                                $strSql .= "P.uniquePluginId, P.webDirectoryName ";
                                $strSql .= " FROM " . TBL_BT_ITEMS . " AS I ";
                                $strSql .= " LEFT JOIN " . TBL_BT_ITEMS . " AS I2 ON I.loadItemGuid = I2.guid ";
                                $strSql .= " LEFT JOIN " . TBL_PLUGINS . " AS  P ON I2.uniquePluginId = P.uniquePluginId ";
                                $strSql .= " WHERE I.appGuid = '" . $appGuid . "' AND I.parentItemGuid = '" . $BT_itemId . "'";
                                $strSql .= " ORDER BY I.id DESC";
                                $res = fnDbGetResult($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
                                if($res){
                                    $numRows = mysql_num_rows($res);
                                    
                                        //header...
                                        if($numRows > 0){
                                            echo "<tr>";
                                                echo "<td style='border-bottom:1px solid gray;padding:5px;padding-left:10px;'><b>Location Title</b></td>";
                                                echo "<td style='border-bottom:1px solid gray;padding:5px;padding-left:10px;'><b>Tapping this location...</b></td>";
                                                echo "<td style='border-bottom:1px solid gray;padding:5px;'>&nbsp;</td>";
                                            echo "</tr>";
                                        }
                                    
                                        while($row = mysql_fetch_array($res)){
                                            $cnt++;
                                                        
                                            //style
                                            $css = (($cnt % 2) == 0) ? "rowAlt" : "rowNormal" ;
        
                                            //for ech childItem...
                                            $BT_childItemId = $row['guid'];
                                            $thisTitle = "";
                                            $thisScreenTypeLabel = "";
                                            $thisTapLoadScreenWithItemId = "";
                                            $tapScreenNickname = fnFormOutput($row["tapScreenNickname"]);
                                            $loadScreenPluginDirectory = $row['webDirectoryName'];
                                            $thisScreenTypeLabel = "";
                                            $thisLat = "";
                                            $thisLon = "";
                                        
                                            //figure out what link / label to show
                                            if($row["jsonVars"] != ""){
                                                $json = $row["jsonVars"];
                                                $thisTitle = fnGetJsonProperyValue("title", $json);
                                                $thisTapLoadScreenWithItemId = fnGetJsonProperyValue("loadScreenWithItemId", $json);
                                                $thisLat = fnGetJsonProperyValue("latitude", $json);
                                                $thisLon = fnGetJsonProperyValue("longitude", $json);
                                            } 
        
                                            
                                            //loading a screen, showing direction, or doing nothing...
                                            if($thisTapLoadScreenWithItemId == ""){
                                                $thisScreenTypeLabel = "Does <i>nothing</i>";
                                            }
                                            if($thisTapLoadScreenWithItemId != ""){
                                                if($thisTapLoadScreenWithItemId == "showDirections"){
                                                    $thisScreenTypeLabel = "Shows <i>driving directions</i>";
                                                }else{
                                                    $pluginScreenURL = rtrim(APP_URL, "/") . "/" . rtrim(APP_DATA_DIRECTORY, "/") . "/plugins/" . ltrim($loadScreenPluginDirectory, "/") . "/?appGuid=" . $appGuid . "&BT_itemId=" . $thisTapLoadScreenWithItemId . "&BT_previousScreenId=" . $BT_itemId . "&BT_previousScreenNickname=" . urlencode(fnFormOutput($nickname)) . $qVars;
                                                    $thisScreenTypeLabel = "Loads <a href='" . $pluginScreenURL . "' title='properties'>" . $tapScreenNickname . "</a>";
                                                }
                                            }
                                            
                                            //long title?
                                            if(strlen($thisTitle) > 65){
                                                $thisTitle = substr($thisTitle , 0, 65) . "...";
                                            }
                                                                                                
                                            echo "\n\n<tr id='i_" . $row["guid"] . "' class='" . $css . "'>";
                                                echo "\n<td class='data' style='vertical-align:middle;padding-left:10px;'>";
                                                    echo "<a href='itemProperties.php?appGuid=" . $appGuid . "&BT_parentScreenItemId=" . $BT_itemId . $qVars . "&BT_childItemId=" . $BT_childItemId . "' rel=\"shadowbox;height=750;width=950\"><span id='titleText_" . $BT_childItemId . "'>" . fnFormOutput($thisTitle) . "</span></a>";
                                                echo "</td>";
                                                echo "\n<td class='data' style='vertical-align:middle;padding-left:10px;vertical-align:middle;'>";
                                                    echo $thisScreenTypeLabel;
                                                echo "</td>";
                                                echo "\n<td class='data' style='vertical-align:middle;text-align:right;padding-right:10px;'>";
                                                    echo "<a href='" . $scriptName . "?appGuid=" . $appGuid . "&BT_itemId=" . $BT_itemId . $qVars . "&BT_childItemId=" . $BT_childItemId . "&command=delete'>delete</a>";
                                                    if($thisLat != "" && $thisLon != ""){
                                                        echo "&nbsp;&nbsp;|&nbsp;&nbsp;";
                                                        echo "<a href='" . fnGetMapUrl("", "", "", "", $thisLat, $thisLon, urlencode($thisTitle)) . "' target='_blank' title='Show on Map'>show on map</a>";
                                                    }
                                                echo "</td>";
                                            echo "\n</tr>";
                                        
                                        }//end while
                                    }//no res
                             
                            ?>     
                            <?php if($cnt > 1){?>
                                <tr>
                                    <td style='padding:5px;padding-left:10px;border-top:1px solid gray;'>
                                        <?php echo $cnt;?> Locations
                                    </td>
                                    <td style='border-top:1px solid gray;text-align:center;padding-top:3px;'>&nbsp;
                                        
                                    </td>
                                    <td style='border-top:1px solid gray;text-align:center;padding:5px;'>&nbsp;
                                        
                                    </td>
                                </tr>
                            <?php } ?>
        
                        </table>                

                        <!-- add new child item -->
                        <div style='margin-top:10px;margin-bottom:10px;'>      
                            Create locations to show on the map. When a location is tapped a call-out window will open
                            showing the title on the first line and the sub-title on the second line. 
                            For each location, determine what happens when the call-out window is tapped.
                        </div>
                        
				
					
                        <table cellspacing='0' cellpadding='0' style='margin:10px;margin-left:0px;'>
                            <tr>
                                <td>
                                    
                                    <div style='padding-top:5px;'>
                                        <b>Title</b><br/>
                                        <input name='title' id='title' type='text' value="<?php echo fnFormOutput($title, true);?>" />
                                    </div>  
                                    
                         
                                    <div style='padding-top:5px;'>
                                        <b>Sub-Title</b> <span id="locationLookupResult">(2nd line in call-out window)</span><br/>
                                        <input name='subTitle' id='subTitle' type='text' value="<?php echo fnFormOutput($subTitle, true);?>" onblur="findAddress();return false"/>
                                    </div>
                                    
                                    <div style='padding-top:5px;width:300px;'>
                                        Enter an address as a subtitle to automatically populate the
                                        latitude and longitude.
                                    </div>  
        
                                    <div style='padding-top:15px;'>
                                        <b>Latitude / Longitude</b> (36.615, -121.904)<br/>
                                        <input name='latitude' id='latitude' type='text' value="<?php echo fnFormOutput($latitude, true);?>" style="width:120px;"/>
                                        <input name='longitude' id='longitude' type='text' value="<?php echo fnFormOutput($longitude, true);?>" style="width:120px;"/>
                                    </div>
                                    
        
                                </td>
                                <td style='padding-left:10px;'>
                                                
                                    <div style='padding-top:5px;'>
                                        <b>Callout Window Tap Action</b><br/>        
                                        <select name="calloutTapChoice" id="calloutTapChoice" style="width:250px;">
                                            <option value="" <?php echo fnGetSelectedString("", $calloutTapChoice);?>>Do nothing, don't show an icon</option>
                                            <option value="showDirections" <?php echo fnGetSelectedString("showDirections", $calloutTapChoice);?>>Show driving directions</option>
                                            <option value="loadAnotherScreen" <?php echo fnGetSelectedString("loadAnotherScreen", $calloutTapChoice);?>>Load the sreen with the nickname below...</option>
                                        </select>
                                    </div>
                                    
                                    <div id="tapChoice" style='padding-top:5px;'>    
                                        <b>Load Screen With Nickname</b>         
                                        &nbsp;&nbsp;
                                        <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                        <a href="<?php echo $controlPanelURL;?>/bt_pickerScreen.php?appGuid=<?php echo $appGuid;?>&formElVal=loadScreenItemId&formElLabel=loadScreenNickname" rel="shadowbox;height=750;width=950">Select</a>
                                        <br/>
                                        <input type="text" name="loadScreenNickname" id="loadScreenNickname" value="<?php echo fnFormOutput($loadScreenNickname, true);?>">
                                        <input type="hidden" name="loadScreenItemId" id="loadScreenItemId" value="">
                                    </div>
                                    
                                    <div style='padding-top:5px;width:300px;white-space:nowrap;'>
                                        <img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow'/><a href="#" onClick="findAddress();return false">Find the latitude / longitude of the sub-title</a>
                                            &nbsp;&nbsp;
                                            <div style='padding-top:3px;'>
                                            <img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow'/><a href="http://itouchmap.com/latlong.html" target="_blank">Show another latitude and longitude tool</a>
                                        </div>
                                    </div>
        
                                    <div style="margin:10px;margin-top:20px;margin-left:5px;">
                                        <input type='button' id="addButton" title="add" value="add" align='absmiddle' class="buttonSubmit" onClick="document.forms[0].command.value='addItem';document.forms[0].submit();return false;">
                                    </div>   
                                                                         
                                </td>
                                
                                <td style='padding-left:10px;'>
                                              
                                    <div style='padding-top:5px;'>
                                       <b>Pin Color</b><br/>
                                        <select name="pinColor" id="pinColor" style="width:200px;">
                                            <option value="" <?php echo fnGetSelectedString($pinColor, "");?>>Default Pin</option>
                                            <option value="red" <?php echo fnGetSelectedString($pinColor, "red");?>>Red Pin</option>
                                            <option value="green" <?php echo fnGetSelectedString($pinColor, "green");?>>Green Pin</option>
                                            <option value="purple" <?php echo fnGetSelectedString($pinColor, "purple");?>>Purple Pin</option>
                                         </select> <br/>
                                    </div>
                                                
                                    <div style='padding-top:5px;'>
                                        <b>Transition Type</b> (iOS only)<br/>
                                        <select name="transitionType" id="transitionType" style="width:200px;">
                                            <option value="" <?php echo fnGetSelectedString($transitionType, ""); ?>>Default transition type</option>
                                            <option value="fade" <?php echo fnGetSelectedString($transitionType, "fade");?>>Fade</option>
                                            <option value="flip" <?php echo fnGetSelectedString($transitionType, "flip");?>>Flip</option>
                                            <option value="curl" <?php echo fnGetSelectedString($transitionType, "curl");?>>Curl</option>
                                            <option value="grow" <?php echo fnGetSelectedString($transitionType, "grow");?>>Grow</option>
                                            <option value="slideUp" <?php echo fnGetSelectedString($transitionType, "slideUp");?>>Slide Up</option>
                                            <option value="slideDown" <?php echo fnGetSelectedString($transitionType, "slideDown");?>>Slide Down</option>
                                        </select> 
                                        <div style='padding-top:5px;width:200px;'>
                                        	Only applies if tap loads another screen. Does not
                                            apply to driving directions.
                                        </div>
                                    </div>
                      
                                </td>
                                
                                
                            </tr>
                        </table>
                    
                    </div>    
                </div>
				<!-- ############### end child items properties ########## -->
                <!-- ##################################################### -->                   



                <!-- ##################################################### -->                   
				<!-- ############### map behavior properties ############# -->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_mapBehavior');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Map Behavior</a>
                    <div id="box_mapBehavior" style="display:none;">

                        <table style='margin-top:10px;'>
                            <tr>
                                <td style='vertical-align:top;'>
                                    
                                    <b>Show User Location</b><br/>
                                    <select name="json_showUserLocation" id="json_showUserLocation" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("showUserLocation", $jsonVars));?>>--select--</option>
                                        <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("showUserLocation", $jsonVars));?>>No, do not show the users location</option>
                                        <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("showUserLocation", $jsonVars));?>>Yes, show the users location</option>
                                    </select>
            
                                    <br/><b>Show User Location Button</b><br/>
                                    <select name="json_showUserLocationButton" id="json_showUserLocationButton" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("showUserLocationButton", $jsonVars));?>>--select--</option>
                                        <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("showUserLocationButton", $jsonVars));?>>No, do not show the button</option>
                                        <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("showUserLocationButton", $jsonVars));?>>Yes, show the button</option>
                                    </select>
            
            
                                    <br/><b>Default Map Type</b><br/>
                                    <select name="json_defaultMapType" id="json_defaultMapType" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("defaultMapType", $jsonVars));?>>--select--</option>
                                        <option value="standard" <?php echo fnGetSelectedString("standard", fnGetJsonProperyValue("defaultMapType", $jsonVars));?>>Standard map</option>
                                        <option value="terrain" <?php echo fnGetSelectedString("terrain", fnGetJsonProperyValue("defaultMapType", $jsonVars));?>>Terrain, Arial map</option>
                                        <option value="hybrid" <?php echo fnGetSelectedString("hybrid", fnGetJsonProperyValue("defaultMapType", $jsonVars));?>>Hybrid, arial + major roads</option>
                                    </select>
                                
                                </td>
                                <td style='vertical-align:top;padding-left:25px;'>
            
                                    <b>Show Map Type Buttons</b><br/>
                                    <select name="json_showMapTypeButtons" id="json_showMapTypeButtons" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("showMapTypeButtons", $jsonVars));?>>--select--</option>
                                        <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("showMapTypeButtons", $jsonVars));?>>No, do not show the buttons</option>
                                        <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("showMapTypeButtons", $jsonVars));?>>Yes, show the buttons</option>
                                    </select>
                                    
                                    <br/><b>Show Refresh Button</b> (refresh data URL)<br/>
                                    <select name="json_showRefreshButton" id="json_showRefreshButton" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("showRefreshButton", $jsonVars));?>>--select--</option>
                                        <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("showRefreshButton", $jsonVars));?>>No, do not show a refresh button</option>
                                        <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("showRefreshButton", $jsonVars));?>>Yes, show a refresh button</option>
                                    </select>
                                    
                                    
                                    <br/><b>Single Location Default Zoom</b> (1-28)<br/>
                                    <input type="text" name="json_singleLocationDefaultZoom" id="json_singleLocationDefaultZoom" value="<?php echo fnFormOutput(fnGetJsonProperyValue("singleLocationDefaultZoom", $jsonVars), true);?>" >
            
                                </td>
                            </tr>
                        </table>

                        <div style="padding-top:5px;">
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_mapBehavior');return false;">
                            <div id="saveResult_mapBehavior" class="submit_working">&nbsp;</div>
                        </div>
                    
                    </div>
                </div>
				<!-- ############### end map behavior properties ######### -->
                <!-- ##################################################### -->                   


               	<!-- ##################################################### -->                   
				<!-- ############### data URL  properties ################ -->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_dataURL');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Data URL</a>
                    <div id="box_dataURL" style="display:none;">

						<?php
                            
                            /*
                                Data URL's for screens that use them provide different types of data based on the type of 
                                screen being created. This server's API has a getScreenData method that creates the
                                appropriate type of data based on the screen making the request. The controlPanelDataURL
                                variable is used as the default data provider for most screens.
                            */
                            $controlPanelDataURL = rtrim(APP_URL, "/") . "/api/app/?command=getChildItems&appGuid=" . $objApp->infoArray['guid'] . "&screenId=" . $BT_itemId . "&apiKey=" . $objApp->infoArray['apiKey'] . "&apiSecret=" . $objApp->infoArray['appSecret'];
                                
                        ?>

                        <div style='padding-top:10px;'>
                            <b>Data URL</b>
                            &nbsp;&nbsp;
                            <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                            <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&screenGuid=<?php echo $BT_itemId;?>&formEl=json_dataURL&fileNameOrURL=URL&searchFolder=/phpscripts" rel="shadowbox;height=550;width=950">Select</a>
                            &nbsp;&nbsp;
                            |
                            &nbsp;&nbsp;
                            <a href='#' onClick="document.forms[0].json_dataURL.value='<?php echo $controlPanelDataURL;?>';return false;">Connect to control panel</a><br/>
                            
                            <input type="text" name="json_dataURL" id="json_dataURL" value="<?php echo fnFormOutput(fnGetJsonProperyValue("dataURL", $jsonVars));?>" style='width:99%;'>
             
                            <div>
                                <a href='#' onClick="fnExpandCollapse('box_mergeFields');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />About Sending Device Data in URL's</a>                
                            </div>               
              
                            <div id="box_mergeFields" style='padding-top:5px;display:none;'>
                            
                                <div style='padding-top:5px;'>
                                    <b>Use Merge Fields to append device information to the end of the Data URL.</b>
                                    This advanced approach allows a backend script to capture device information before outputting the screens
                                    data. If you use merge fields you will need a backend .php script to process the request.
                                </div>
                                <div style='padding-top:5px;'>
                                   <b>Available Merge Fields</b> 
                                     <ul>
                                        <li>[buzztouchAppId] This is the App Id in your buzztouch control panel</li>
                                        <li>[buzztouchAPIKey] This is the app API Key in your buzztouch control panel</li>
                                        <li>[screenId] The unique id of the current screen (useful for determing the app context)</li>
                                        <li>[userId] The Unique Id of a logged in user (if the app uses login screens)</li>
                                        <li>[userEmail] The email address of a logged in user</li>
                                        <li>[deviceId] A globally unique string value assigned to the device.</li>
                                        <li>[deviceModel] A string value controlled by the device manufacturer.</li>
                                        <li>[deviceLatitude] A latitude coordinate value (if the device is reporting it's location).</li>
                                        <li>[deviceLongitude] A longitude coordinate value (if the device is reporting it's location).</li>
                                    </ul>
                                    
                                    <b>If you used this URL...</b>
                                    <div style='padding-top:5px;'>
                                        http://www.mysite.com/localrestaurants.php?deviceLatitude=[deviceLatitude]&deviceLongitude=[deviceLongitude]&userId=[userId]
                                    </div>
                                    <div style='padding-top:5px;'>
                                        <b>The device would request....</b>
                                    </div>
                                    <div style='padding-top:5px;'>
                                        http://www.mysite.com/localrestaurants.php?latitude=38.4456&longitude=-102.3444&userId=00000
                                    </div>
                                    <hr>
                                </div>
                            
                            
                            </div>
						</div>
                        
                        <div style="padding-top:10px;">
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_dataURL');return false;">
                            <div id="saveResult_dataURL" class="submit_working">&nbsp;</div>
                        </div>
                    
                    </div>
                </div>
				<!-- ############### end data URL properties ############# -->
                <!-- ##################################################### -->                   



                <!-- ##################################################### -->                   
				<!-- ############### login properties #################### -->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_login');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Require Login</a>
                    <div id="box_login" style="display:none;">
                        
                        <div style='padding-top:10px;'>
                            <select name="json_loginRequired" id="json_loginRequired" style="width:250px;">
                                <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("logInRequired", $jsonVars));?>>--select--</option>
                                <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("logInRequired", $jsonVars));?>>No, do not require a login</option>
                                <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("logInRequired", $jsonVars));?>>Yes, require a login</option>
                            </select>
                       </div>
                
                        <div style='padding-top:5px;'>
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_login');return false;">
                            <div id="saveResult_login" class="submit_working">&nbsp;</div>
                        </div>
                 
                    </div>
                </div>
				<!-- ############### end login properties ############### -->
                <!-- ##################################################### -->                   




                <!-- ##################################################### -->                   
				<!-- ############### background properties ############### -->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_backgroundColor');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Background Color</a>
                    <div id="box_backgroundColor" style="display:none;">
                        
                       <table style='padding-top:15px;'>
            
                            <tr>
                                <td style='vertical-align:top;padding-left:0px;'>
                                    Enter "clear" (without quotes) for a transparent background. 
                                    All other colors should be entered in hex format, include the # character like: #FFCC66.
                                </td>                
                            </tr>
                            <tr>
                                <td style='vertical-align:top;padding-left:0px;padding-top:5px;'>
                                     
                                    <b>Color</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerColor.php?formElVal=json_backgroundColor" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundColor" id="json_backgroundColor" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundColor", $jsonVars));?>">
                    
                                </td>
                            </tr>
                        </table>
            
                        <div style='padding-top:5px;padding-left:0px;'>
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_backgroundColor');return false;">
                            <div id="saveResult_backgroundColor" class="submit_working">&nbsp;</div>
                        </div>
                        
                    </div>
                 </div>
                 
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_backgroundImage');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Background Image</a>
                    <div id="box_backgroundImage" style="display:none;">
                            
                        <table style='padding-top:10px;'>
                            
                            <tr>
                                <td colspan='3' style='vertical-align:top;padding-left:0px;'>
                                    Image File Names or Image URL (web address)?
                                    <hr>
                                    Use an Image File Name or an Image URL - NOT BOTH.
                                    If you choose to use an Image File Name (and not a URL), you'll need to add the image to
                                    the Xcode or Eclipse project after you download the code for your app. The Image File Name value you
                                    enter in the control panel must match the file name of the image in your
                                    project. Example: mybackground.png. Do not use image file names that contain spaces or special characters.
                                    <hr>
                                    If you use a URL (and not an Image File Name), the image will be downloaded from the URL then stored on the device
                                    for offline use. The Image URL should end with the name of the image file itself. 
                                    Example: www.mysite.com/images/mybackground.png.  You'll need to figure out whether or not
                                    it's best to include them in the project or use URL's, both approaches make sense, depending on your
                                    design goals.
                                </td>                
                            </tr>
                            
                            
                            <tr>	
                                <td class='tdSort' style='padding-left:0px;font-weight:bold;padding-top:10px;'>Small Device</td>
                                <td class='tdSort' style='padding-left:25px;font-weight:bold;padding-top:10px;'>Large Device</td>
                                <td class='tdSort' style='padding-left:25px;font-weight:bold;padding-top:10px;'>Extras</td>
                            </tr>
                            <tr>
                                <td style='vertical-align:top;padding-left:0px;padding-top:15px;'>
                                
                                   <b>Image File Name</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageNameSmallDevice&fileNameOrURL=fileName&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageNameSmallDevice" id="json_backgroundImageNameSmallDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageNameSmallDevice", $jsonVars));?>">
                                    
                                    <br/><b>Image URL</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageURLSmallDevice&fileNameOrURL=URL&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageURLSmallDevice" id="json_backgroundImageURLSmallDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageURLSmallDevice", $jsonVars));?>">
                                </td>
                                <td style='vertical-align:top;padding-left:25px;padding-top:15px;'>
                                   
                                   <b>Image File Name</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageNameLargeDevice&fileNameOrURL=fileName&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageNameLargeDevice" id="json_backgroundImageNameLargeDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageNameLargeDevice", $jsonVars));?>">
                                    
                                    <br/><b>Image URL</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageURLLargeDevice&fileNameOrURL=URL&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageURLLargeDevice" id="json_backgroundImageURLLargeDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageURLLargeDevice", $jsonVars));?>">
                                 </td>
                                <td style='vertical-align:top;padding-left:25px;padding-top:15px;'>
                                    
                                    <b>Scale / Position</b><br/>
                                    <select name="json_backgroundImageScale" id="json_backgroundImageScale" style="width:150px;">
                                            <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>--select--</option>
                                            <option value="center" <?php echo fnGetSelectedString("center", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>center</option>
                                            <option value="fullScreen" <?php echo fnGetSelectedString("fullScreen", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Full Screen</option>
                                            <option value="fullScreenPreserve" <?php echo fnGetSelectedString("fullScreenPreserve", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Full Screen, Preserve Ratio</option>
                                            <option value="top" <?php echo fnGetSelectedString("top", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Top Middle</option>
                                            <option value="bottom" <?php echo fnGetSelectedString("bottom", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Bottom Middle</option>
                                            <option value="topLeft" <?php echo fnGetSelectedString("topLeft", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Top Left</option>
                                            <option value="topRight" <?php echo fnGetSelectedString("topRight", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Top Right</option>
                                            <option value="bottomLeft" <?php echo fnGetSelectedString("bottomLeft", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Bottom Left</option>
                                            <option value="bottomRight" <?php echo fnGetSelectedString("bottomRight", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Bottom Right</option>
                                    </select>
                                </td>
                                  
                            </tr>
                        </table>
                            
                        <div style='padding-top:5px;padding-left:0px;'>
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_backgroundImage');return false;">
                            <div id="saveResult_backgroundImage" class="submit_working">&nbsp;</div>
                        </div>
                        
                    </div>
                  </div>

				<!-- ############### end background properties ############### -->
                <!-- ######################################################### -->                   


                <!-- ################################################################ -->                   
				<!-- ############### background audio properties #################### -->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_backgroundAudio');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Background Audio</a>
                    <div id="box_backgroundAudio" style="display:none;">
                        
                        <table style='margin-top:10px;'>
                            <tr>
                                <td style='vertical-align:top;'>
                    
                                    <b>Audio File Name</b>
                                        &nbsp;&nbsp;
                                        <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                        <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_audioFileName&fileNameOrURL=fileName&searchFolder=/audio" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_audioFileName" id="json_audioFileName" value="<?php echo fnFormOutput(fnGetJsonProperyValue("audioFileName", $jsonVars));?>">
                                    
                                    <br/><b>Audio File URL</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_audioFileURL&fileNameOrURL=URL&searchFolder=/audio" rel="shadowbox;height=550;width=950">Select</a>
                                    </br>            
                                    <input type="text" name="json_audioFileURL" id="json_audioFileURL" value="<?php echo fnFormOutput(fnGetJsonProperyValue("audioFileURL", $jsonVars));?>" >
                                </td>
                                <td style='vertical-align:top;'>
                                
                                    <b>Audio Number of Loops</b> (number)<br/>
                                    <input type="text" name="json_audioNumberOfLoops" id="json_audioNumberOfLoops" value="<?php echo fnFormOutput(fnGetJsonProperyValue("audioNumberOfLoops", $jsonVars));?>">
                                    
                                    <br/><b>Audio Stops with "Back Button"</b><br/>
                                    <select name="json_audioStopsOnScreenExit" id="json_audioStopsOnScreenExit" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("audioStopsOnScreenExit", $jsonVars));?>>--select--</option>
                                        <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("audioStopsOnScreenExit", $jsonVars));?>>Yes, stop audio on screen exit</option>
                                        <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("audioStopsOnScreenExit", $jsonVars));?>>No, keep playing on screen exit</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        
                        <div style="padding-top:5px;">
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_backgroundAudio');return false;">
                            <div id="saveResult_backgroundAudio" class="submit_working">&nbsp;</div>
                        </div>
                    
                    </div>
                </div>
				<!-- ############### end background audio properties ################ -->
                <!-- ################################################################ -->                   






                <!-- ###################################################### -->                   
				<!-- ############### search properties #################### -->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_search');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Hide from Search Results</a>
                    <div id="box_search" style="display:none;">
                        
                        <div style='padding-top:10px;'>
                            <b>Hide from Search Results</b><br/>
                            <select name="json_hideFromSearch" id="json_hideFromSearch" style='width:300px;'>
                                <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("hideFromSearch", $jsonVars));?>>--select--</option>
                                <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("hideFromSearch", $jsonVars));?>>NO, include this screen in search results</option>
                                <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("hideFromSearch", $jsonVars));?>>YES, hide this screen from search results</option>
                            </select>
                        </div>
             
                        <div style="padding-top:5px;">
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_search');return false;">
                            <div id="saveResult_search" class="submit_working">&nbsp;</div>
                        </div>
                    
                    </div>
                </div>
				<!-- ############### end search properties ################ -->
                <!-- ###################################################### -->                   




            </div>
            
            
            <div style='clear:both;'></div>
        	
        </div>
        
    </fieldset>
        
<?php 
	//ask the Page class to print the bottom navigation bar...
	echo $objControlPanelWebpage->fnGetBottomNavBar();
?>

</div>

<?php 
	//ask the Page class to print the closing body tag...
	echo $objControlPanelWebpage->fnGetBodyEnd(); 
?>






     
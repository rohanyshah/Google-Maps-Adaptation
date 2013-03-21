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
	
		
	//init user object
	$thisUser = new User($loggedInUserGuid);
	$thisUser -> fnLoggedInReq($loggedInUserGuid);
	$thisUser -> fnUpdateLastRequest($loggedInUserGuid);

	//init page object
	$thisPage = new Page();
	
	//add some inline css (in the <head>) for 100% width...
	$inlineCSS = "";
	$inlineCSS .= "html{height:100%;width:100%;margin:0px;padding:0px;} ";
	$inlineCSS .= "body{height:100%;width:100%;margin:0px;padding:0px;} ";
	$inlineCSS .= ".contentWrapper, .contentWrap{height:100%;width:100%;margin:0px;padding:0px;} ";
	$thisPage->cssInHead = $inlineCSS;


	$dtNow = fnMySqlNow();
	$strMessage = "";
	$bolDone = false;
	$bolPassed = true;
	$bolDeleted = false;
	$appGuid = fnGetReqVal("appGuid", "", $myRequestVars);
	$BT_parentScreenItemId = fnGetReqVal("BT_parentScreenItemId", "", $myRequestVars);
	$BT_childItemId = fnGetReqVal("BT_childItemId", "", $myRequestVars);
	
	//need an appguid
	if($appGuid == "" || $BT_childItemId == ""){
		echo "invalid request";
		exit();
	}
	
	//child item object..
	$objChildItem = new Bt_item($BT_childItemId);
	$jsonVars = $objChildItem->infoArray["jsonVars"];
	
	

?>

<?php echo $thisPage->fnGetPageHeaders(); ?>
<?php echo $thisPage->fnGetBodyStart(); ?>

<input type="hidden" name="appGuid" id="appGuid" value="<?php echo $appGuid;?>">
<input type="hidden" name="BT_parentScreenItemId" id="BT_parentScreenItemId" value="<?php echo $BT_parentScreenItemId;?>" />
<input type="hidden" name="BT_childItemId" id="BT_childItemId" value="<?php echo $BT_childItemId;?>" />



<!--load the jQuery files using the Google API -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>

<script>

	<!--saves item properties -->
	function saveItemProperties(showResultsInElementId){
	
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
				url: "save_JSON_itemProperties.php",
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
<input type="hidden" name="json_loadScreenWithItemId" id="json_loadScreenWithItemId" value="<?php echo fnGetJsonProperyValue("loadScreenWithItemId", $jsonVars);?>" />

    <fieldset class='colorLightBg minHeightShadowbox'>
    
       	<div class='contentBox colorDarkBg'>
        
            <div class="contentBand colorBandBg">
                Map Location Properties
            </div>
            <div style='padding:10px;'>
                    
                    <table cellspacing='0' cellpadding='0'>
                        <tr>
                        	<td style="padding:10px;padding-bottom:0px;">
                                
                                <div style='padding-top:5px;'>
                                    <b>Title</b><br/>
                                    <input name='json_title' id='json_title' type='text' value="<?php echo fnGetJsonProperyValue("title", $jsonVars);?>" />
                                </div>  
                                
                     
                                <div style='padding-top:5px;'>
                                    <b>Sub-Title</b> <span id="locationLookupResult">(2nd line in call-out window)</span><br/>
                                    <input name='json_subTitle' id='json_subTitle' type='text' value="<?php echo fnGetJsonProperyValue("subTitle", $jsonVars);?>" />
                                </div>
                                
                                 <div style='padding-top:5px;'>
                                    <b>Latitude / Longitude</b> (36.615, -121.904)<br/>
                                    <input name='json_latitude' id='json_latitude' type='text' value="<?php echo fnGetJsonProperyValue("latitude", $jsonVars);?>" style="width:120px;"/>
                                    <input name='json_longitude' id='json_longitude' type='text' value="<?php echo fnGetJsonProperyValue("longitude", $jsonVars);?>" style="width:120px;"/>
                                </div>
                                
                                
                                
    
                            </td>
                        	<td style="padding:10px;padding-bottom:0px;">
                                          
                                <div style='padding-top:5px;'>
                                   <b>Pin Color</b><br/>
                                    <select name="json_pinColor" id="json_pinColor" style="width:200px;">
                                        <option value="" <?php echo fnGetSelectedString(fnGetJsonProperyValue("pinColor", $jsonVars), "");?>>Default Pin</option>
                                        <option value="red" <?php echo fnGetSelectedString(fnGetJsonProperyValue("pinColor", $jsonVars), "red");?>>Red Pin</option>
                                        <option value="green" <?php echo fnGetSelectedString(fnGetJsonProperyValue("pinColor", $jsonVars), "green");?>>Green Pin</option>
                                        <option value="purple" <?php echo fnGetSelectedString(fnGetJsonProperyValue("pinColor", $jsonVars), "purple");?>>Purple Pin</option>
                                     </select> <br/>
                                </div>
                                            
                            	<div style='padding-top:5px;'>
                                	<b>Transition Type</b> (iOS only)<br/>
                                    <select name="json_transitionType" id="json_transitionType" style="width:200px;">
                                        <option value="" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "");?>>Default transition type</option>
                                        <option value="fade" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "fade");?>>Fade</option>
                                        <option value="flip" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "flip");?>>Flip</option>
                                        <option value="curl" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "curl");?>>Curl</option>
                                        <option value="grow" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "grow");?>>Grow</option>
                                        <option value="slideUp" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "slideUp");?>>Slide Up</option>
                                        <option value="slideDown" <?php echo fnGetSelectedString(fnGetJsonProperyValue("transitionType", $jsonVars), "slideDown");?>>Slide Down</option>
                                    </select>
                                    <div style='padding-top:10px;width:300px;'>
                                      	Only applies if tap loads another screen, does not
                                        apply for driving directions.
                                    </div>
 
                            	</div>
                  
                            </td>
                        </tr>
                    </table>
                    
                <div style='padding:10px;'>
                    <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveItemProperties('saveResult_childItem');return false;">
                    <div id="saveResult_childItem" class="submit_working">&nbsp;</div>
                </div>	
                            
                                
    
    		</div>
        </div>
	</fieldset>

</div>
<?php echo $thisPage->fnGetBodyEnd(); ?>

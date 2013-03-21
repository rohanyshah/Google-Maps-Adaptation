package com.Rohan;

import java.util.ArrayList;
import java.util.List;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.Dialog;
import android.app.ProgressDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.drawable.Drawable;
import android.location.LocationManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Message;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.Menu;
import android.view.View;
import android.view.View.OnClickListener;
import android.view.ViewGroup.LayoutParams;
import android.view.Window;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.RelativeLayout;
import android.widget.Toast;

import com.google.android.maps.GeoPoint;
import com.google.android.maps.ItemizedOverlay;
import com.google.android.maps.MapActivity;
import com.google.android.maps.MapController;
import com.google.android.maps.MapView;
import com.google.android.maps.Overlay;
import com.google.android.maps.OverlayItem;



public class BT_screen_map extends MapActivity{
	
	public boolean didCreate = false;
	private boolean didLoadData = false;
	private String activityName = "BT_activity_map";
	private DownloadScreenDataWorker downloadScreenDataWorker;
	private String JSONData = "";
	private static Activity thisActivity = null;
	public boolean downloadInProgress = false;
	private AlertDialog myAlert = null;
	private ProgressDialog progressBox = null;
	private BT_progressSpinner progressSpinner = null;
	private BT_item screenData = null;
	public RelativeLayout backgroundSolidColorView = null;
	public LinearLayout baseContentView = null;
	public LinearLayout titleContainerView = null;

	//map view
	private MapView mapView = null;
	public LocationManager myLocationManager;
	private ArrayList<BT_item> childItems;
	public int droppedDevicePin = 0;
	private int selectedIndex = -1;
	
	//overlays
	List<Overlay> mapOverlays;
	Drawable locationPin;
	private ArrayList<GeoPoint> childLocationPoints;
	ChildLocationsOverlay itemizedOverlay;	

	//properties from JSON
	public String dataURL = "";
	public String saveAsFileName = "";
	public String showUserLocation = "";
	public String showUserLocationButton = "";
	public String defaultMapType = "";
	public String showMapTypeButtons = "";
	public String showRefreshButton = "";
	public String singleLocationDefaultZoom = "";
	
	//drawables for pins...
	private Drawable youAreHereIcon = null;
	private Drawable pinGreen = null;
	private Drawable pinBlue = null;
	private Drawable pinRed = null;
	private Drawable pinPurple = null;
	
	
	//////////////////////////////////////////////////////////////////////////
	//activity life-cycle events.
	
	//onCreate
    public void onCreate(Bundle savedInstanceState){
		super.onCreate(savedInstanceState);
        this.activityName = "BT_screen_map";
		BT_debugger.showIt(activityName + ":onCreate");	
		
		//remember this activity...
        thisActivity = this;
        
       /*
         * set the screenData for this screen BEFORE setting the content view...
         * If this Activity was started with passed-in payload then we started it from 
         * BT_activity_root when a tab was created. In this we use that tabs
         * home-screen data and not the rootApp.currentScreenData. This is because the
         * rootApp.currentScreenData property has not been set yet.
         */
		
		Intent startedFromIntent = getIntent();
		int tabIndex = startedFromIntent.getIntExtra("tabIndex", -1);
		if(tabIndex > -1){
			
           	//get this tabs home-screen data
        	if(tabIndex == 0) this.screenData = BT_appDelegate.rootApp.getTab0ScreenData();
        	if(tabIndex == 1) this.screenData = BT_appDelegate.rootApp.getTab1ScreenData();
        	if(tabIndex == 2) this.screenData = BT_appDelegate.rootApp.getTab2ScreenData();
        	if(tabIndex == 3) this.screenData = BT_appDelegate.rootApp.getTab3ScreenData();
        	if(tabIndex == 4) this.screenData = BT_appDelegate.rootApp.getTab4ScreenData();
		
		}else{
			
			//set the screen data..
			this.screenData = BT_appDelegate.rootApp.getCurrentScreenData();
		
		}
		
		
  		//hide title bar then set to full-screen before setting content..The AndroidManifest.xml file
        //also uses "full" screen in the application them node. 
		requestWindowFeature(Window.FEATURE_NO_TITLE);
		getWindow().setFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN, WindowManager.LayoutParams.FLAG_FULLSCREEN);
                
		//content view for "base" activity is emtpy (it must be set before sub-classes can set another content view)
        setContentView(R.layout.act_base);
		
		//base layout
		LinearLayout baseView = (LinearLayout)findViewById(R.id.baseView);
	    
		//setup background colors...
		BT_viewUtilities.updateBackgroundColorsForScreen(this, this.screenData);
        
		//setup navigation bar...
		LinearLayout navBar = BT_viewUtilities.getNavBarForScreen(this, this.screenData);
		if(navBar != null){
			baseView.addView(navBar);
		}
		
		//init properties for JSON data...
		childItems = new ArrayList<BT_item>();
		dataURL = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "dataURL", "");
		saveAsFileName = this.screenData.getItemId() + "_screenData.txt";
		
		showUserLocation = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "showUserLocation", "");
		showUserLocationButton = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "showUserLocationButton", "");
		defaultMapType = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "defaultMapType", "");
		showMapTypeButtons = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "showMapTypeButtons", "");
		showRefreshButton = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "showRefreshButton", "");
		singleLocationDefaultZoom = BT_strings.getJsonPropertyValue(this.screenData.getJsonObject(), "singleLocationDefaultZoom", "");
		
		//setup drop-pin graphics..
		youAreHereIcon = BT_fileManager.getDrawableByName("map_youarehere.png");
		pinGreen = BT_fileManager.getDrawableByName("map_marker_green.png");
		pinBlue = BT_fileManager.getDrawableByName("map_marker_blue.png");
		pinRed = BT_fileManager.getDrawableByName("map_marker_red.png");
		pinPurple = BT_fileManager.getDrawableByName("map_marker_purple.png");
		
		//inflate this screens layout file..
		LayoutInflater vi = (LayoutInflater)thisActivity.getSystemService(Context.LAYOUT_INFLATER_SERVICE);
		View thisScreensView = vi.inflate(R.layout.screen_map, null);
		
		//reference to map view..
		mapView = (MapView) thisScreensView.findViewById(R.id.mapView);
		mapView.setBuiltInZoomControls(true);
		
		
		//add the view to the base view...
		baseView.addView(thisScreensView);
		
		//flag as created..
        didCreate = true;
        
        
	}//onCreate

	//onStart
	@Override 
	protected void onStart() {
		//BT_debugger.showIt(activityName + ":onStart");	
		super.onStart();
		
		//ignore this if we already created the screen...
		if(!didLoadData){
			
			if(saveAsFileName.length() > 1){
				
				//check cache...
				String parseData = "";
				if(BT_fileManager.doesCachedFileExist(saveAsFileName)){
					BT_debugger.showIt(activityName + ":onStart using cached screen data");	
					parseData = BT_fileManager.readTextFileFromCache(saveAsFileName);
					parseScreenData(parseData);
				}else{
					//get data from URL if we have one...
					if(this.dataURL.length() > 1){
						BT_debugger.showIt(activityName + ":onStart downloading screen data from URL");	
						refreshScreenData();
					}else{
						//parse with "empty" data...
						BT_debugger.showIt(activityName + ":onStart using data from app's configuration file");	
						parseScreenData("");
					}
				}
					
			}//saveAsFileName
		}//did load data		
		
	}
	
    //onResume
    @Override
    public void onResume() {
       super.onResume();
       	//BT_debugger.showIt(activityName + ":onResume");
    }
    
    //onPause
    @Override
    public void onPause() {
        super.onPause();
        //BT_debugger.showIt(activityName + ":onPause");	
    }
    
	
	//onStop
	@Override 
	protected void onStop(){
		super.onStop();
        //BT_debugger.showIt(activityName + ":onStop");	

	}	
	
	
	//onDestroy
    @Override
    public void onDestroy() {
        super.onDestroy();
        //BT_debugger.showIt(activityName + ":onDestroy");	
    }
    
	//handles keyboard hiding and rotation events
	@Override
	public void onConfigurationChanged(Configuration newConfig) {
	  super.onConfigurationChanged(newConfig);
	  switch(newConfig.orientation){
      		case  Configuration.ORIENTATION_LANDSCAPE:
			BT_debugger.showIt(activityName + ":onConfigurationChanged to landscape");			
			break;
      		case Configuration.ORIENTATION_PORTRAIT:
    			BT_debugger.showIt(activityName + ":onConfigurationChanged to portrait");			
    			break;
      		case Configuration.ORIENTATION_SQUARE:
      			BT_debugger.showIt(activityName + ":onConfigurationChanged is square");			
      			break;
      		case Configuration.ORIENTATION_UNDEFINED:
      			BT_debugger.showIt(activityName + ":onConfigurationChanged is unidentified");			
      			break;
      		default:
      }	  
	} 
    
    
	//end activity life-cycle events
	//////////////////////////////////////////////////////////////////////////
  
	//show alert message
	public void showAlert(String theTitle, String theMessage) {
		if(theTitle == "") theTitle = "No Alert Title?";
		if(theMessage == "") theMessage = "No alert message?";
		myAlert = new AlertDialog.Builder(this).create();
		myAlert.setTitle(theTitle);
		myAlert.setMessage(theMessage);
		myAlert.setIcon(R.drawable.icon);
		myAlert.setCancelable(false);
		myAlert.setButton("OK", new DialogInterface.OnClickListener() {
	      public void onClick(DialogInterface dialog, int which) {
	        myAlert.dismiss();
	    } }); 
		myAlert.show();
	}	
	
	
    //required google maps api method..
	@Override
	protected boolean isRouteDisplayed() {
		return false;
	}
	
	//show toast
	public void showToast(String theMessage, String shortOrLong){
		Toast toast = null;
		if(shortOrLong.equalsIgnoreCase("short")){
			toast = Toast.makeText(getBaseContext(), theMessage, Toast.LENGTH_SHORT);
		}else{
			toast = Toast.makeText(BT_appDelegate.getContext(), theMessage, Toast.LENGTH_LONG);
		}
		toast.show();
	}
	
	
	//show / hide progress (two different types, depending on the message)...
	public void showProgress(String theTitle, String theMessage){
		if(theTitle == null && theMessage == null){
	        progressSpinner = BT_progressSpinner.show(this, null, null, true);
		}else{
			progressBox = ProgressDialog.show(this, theTitle, theMessage, true);
		}
	}
	public void hideProgress(){
		if(progressBox != null){
			progressBox.dismiss();
		}
		if(progressSpinner != null){
			progressSpinner.dismiss();
		}
	}
	
	
    //refresh screenData
    public void refreshScreenData(){
        BT_debugger.showIt(activityName + ":refreshScreenData");	
        showProgress(null, null);
        
        if(dataURL.length() > 1){
	      	downloadScreenDataWorker = new DownloadScreenDataWorker();
        	downloadScreenDataWorker.setDownloadURL(dataURL);
        	downloadScreenDataWorker.setSaveAsFileName(saveAsFileName);
        	downloadScreenDataWorker.setThreadRunning(true);
        	downloadScreenDataWorker.start();
        }else{
            BT_debugger.showIt(activityName + ":refreshScreenData NO DATA URL for this screen? Not downloading.");	
        }
        
    }

	//show map type
	public void showMapType(String theMapType){
		BT_debugger.showIt(activityName + ":showMapType \"" + theMapType + "\"");
		//standard, terrain, hybrid
		
		//terrain
		if(theMapType == "terrain"){
 	        mapView.setSatellite(true);
 		}else{
 			mapView.setSatellite(false);
 		}		
 		
 		//standard or hybrid..
 		if(theMapType == "hybrid"){
 	        mapView.setTraffic(true);
 		}else{
 	        mapView.setTraffic(false);
 		}
 		
 		//invalidate
        mapView.invalidate();  

		
	}
	
	//show device's location
	public void showDeviceLocation(){
		BT_debugger.showIt(activityName + ":showDeviceLocation");
		
	}		
	
	//location bubble..
	public void showLocationBubble(BT_item theItem) {
		BT_debugger.showIt(activityName + ":showLocationBubble");
		
		//BT_item holds details about the location item...
		String theTitle = theItem.getAnnotationTitle();
			if(theTitle.length() < 1) theTitle = "N/A";
		String theSubTitle = theItem.getAnnotationSubTitle();
			if(theSubTitle.length() < 1) theSubTitle = "no details available";
			
		String pinColor = BT_strings.getJsonPropertyValue(theItem.getJsonObject(), "pinColor", "red");
		Drawable pinGraphic = pinRed;
		if(pinColor.equalsIgnoreCase("green")) pinGraphic = pinGreen;
		if(pinColor.equalsIgnoreCase("blue")) pinGraphic = pinBlue;
		if(pinColor.equalsIgnoreCase("purple")) pinGraphic = pinPurple;
		
	    //directtions or details button?
		String loadScreenWithItemId = BT_strings.getJsonPropertyValue(theItem.getJsonObject(), "loadScreenWithItemId", "");
	    String loadScreenWithNickname = BT_strings.getJsonPropertyValue(theItem.getJsonObject(), "loadScreenWithNickname", "");
	 
	    //are we showing the details button on this locations pup-up bubble?
	    boolean showDetailsButton = false;
	    if(loadScreenWithItemId.length() > 1 || loadScreenWithNickname.length() > 1){
	    	showDetailsButton = true;
	    }
	    
	    //if we are showing directions link we are not showing details bubble...
	    if(loadScreenWithItemId.equalsIgnoreCase("showDirections")){
	    	showDetailsButton = false;
	    }
	    
	    //if we did not find load screen from id or nickname, look for a load sceen object...
	    if(!showDetailsButton){
	    	try{
	    		JSONObject obj = theItem.getJsonObject();
	    		if(obj.has("loadScreenObject")){
	    			showDetailsButton = true;
	    		}
	    	}catch(Exception e){
		    	showDetailsButton = false;
	    	}
	    }
	    
		myAlert = new AlertDialog.Builder(this).create();
		myAlert.setTitle(theTitle);
		myAlert.setMessage(theSubTitle);
		myAlert.setIcon(pinGraphic);
		myAlert.setCancelable(false);
		myAlert.setButton2(getString(R.string.ok), new DialogInterface.OnClickListener() {
		      public void onClick(DialogInterface dialog, int which) {
		    	myAlert.dismiss();
		    } }); 
		
		
		//do we show details button?
		if(showDetailsButton){
			myAlert.setButton(getString(R.string.details), new DialogInterface.OnClickListener() {
			      public void onClick(DialogInterface dialog, int which) {
			        myAlert.dismiss();
			        showDetailsScreen();
			 } }); 
		}
		
		//do we show the driving directions button?
		if(loadScreenWithItemId.equalsIgnoreCase("showDirections")){
			myAlert.setButton(getString(R.string.mapDrivingDirections), new DialogInterface.OnClickListener() {
			      public void onClick(DialogInterface dialog, int which) {
			        myAlert.dismiss();
			        showDirections();
			} }); 
		}
		
		//show...
		myAlert.show();
	}
	

	//launches screen fom details tap...
	public void showDetailsScreen(){
		BT_debugger.showIt(activityName + ":showDetailsScreen");
		
		BT_item tappedLocation = (BT_item) childItems.get(selectedIndex);
		
    	//itemId, nickname or object...
        String loadScreenWithItemId = BT_strings.getJsonPropertyValue(tappedLocation.getJsonObject(), "loadScreenWithItemId", "");
        String loadScreenWithNickname = BT_strings.getJsonPropertyValue(tappedLocation.getJsonObject(), "loadScreenWithNickname", "");
        BT_item tapScreenLoadObject = null;
        BT_item tapMenuItemObject = null;
    	if(loadScreenWithItemId.length() > 1 && !loadScreenWithItemId.equalsIgnoreCase("none")){
			BT_debugger.showIt(activityName + ":showDetailsScreen pin-tap button loads screen with itemId: \"" + loadScreenWithItemId + "\"");
    		tapScreenLoadObject = BT_appDelegate.rootApp.getScreenDataByItemId(loadScreenWithItemId);
    	}else{
    		if(loadScreenWithNickname.length() > 1){
				BT_debugger.showIt(activityName + ":showDetailsScreen pin-tap button loads screen with nickname: \"" + loadScreenWithNickname + "\"");
    			tapScreenLoadObject = BT_appDelegate.rootApp.getScreenDataByItemNickname(loadScreenWithNickname);
    		}else{
    			try{
	    			JSONObject obj = tappedLocation.getJsonObject();
		            if(obj.has("loadScreenObject")){
						
		            	BT_debugger.showIt(activityName + ":showDetailsScreen pin-tap button loads screen object configured with JSON object.");
		            	JSONObject tmpLoadScreen = obj.getJSONObject("loadScreenObject");
		            	tapScreenLoadObject = new BT_item();
    		            if(tmpLoadScreen.has("itemId")) tapScreenLoadObject.setItemId(tmpLoadScreen.getString("itemId"));
    		            if(tmpLoadScreen.has("itemNickname")) tapScreenLoadObject.setItemNickname(tmpLoadScreen.getString("itemNickname"));
    		            if(tmpLoadScreen.has("itemType")) tapScreenLoadObject.setItemType(tmpLoadScreen.getString("itemType"));
    		            tapScreenLoadObject.setJsonObject(tmpLoadScreen);
		            }
    			}catch(Exception e){
					BT_debugger.showIt(activityName + ":showDetailsScreen EXCEPTION reading screen-object configured for pin-tap button: " + e.toString());
    			}
    		}
    	}
    	
    	//if we have a screen object to load from the right-button tap, build a BT_itme object...
    	if(tapScreenLoadObject != null){
    		
    		tapMenuItemObject = new BT_item();
    		tapMenuItemObject.setItemId("unused");
    		tapMenuItemObject.setItemNickname("unused");
    		tapMenuItemObject.setItemType("BT_menuItem");
    		
    		//create json object for the BT_item...
    		try{
	    		JSONObject tmpMenuJson = new JSONObject();
	    		tmpMenuJson.put("itemId", "unused");
	    		tmpMenuJson.put("itemNickname", "unused");
	    		tmpMenuJson.put("itemType", "BT_menuItem");
	    		
   	    		//possible transition type
	    		String transitionType = BT_strings.getJsonPropertyValue(tappedLocation.getJsonObject(), "transitionType", "");
	    		if(transitionType.length() > 1){
	    			tmpMenuJson.put("transitionType", transitionType);
	    		}
	    		
   	    		//possible transition type
	    		String soundEffectFileName = BT_strings.getJsonPropertyValue(tappedLocation.getJsonObject(), "soundEffectFileName", "");
	    		if(soundEffectFileName.length() > 1){
	    			tmpMenuJson.put("soundEffectFileName", soundEffectFileName);
	    		}	    		
    		
	    		//set JSON
	    		tapMenuItemObject.setJsonObject(tmpMenuJson);
	    		
    		}catch(Exception e){
				BT_debugger.showIt(activityName + ":showDetailsScreen EXCEPTION creating BT_menuItem: " + e.toString());
    		}
    		
        	//call loadScreenObject (static method in this class)...
   			BT_act_controller.loadScreenObject(this, this.screenData, tapMenuItemObject, tapScreenLoadObject);
   		
    	}else{
			BT_debugger.showIt(activityName + ":showDetailsScreen ERROR. No screen is connected to this pin-tap button?");	
    		BT_activity_base.showAlertFromClass(BT_appDelegate.getApplication().getString(R.string.errorTitle), BT_appDelegate.getApplication().getString(R.string.errorNoScreenConnected));
    	}		
			
	}//showDetailsScreen
	
	//shows directions..
	public void showDirections(){
		BT_debugger.showIt(activityName + ":showDirections");
		
		if(selectedIndex > -1){
			
			//we must have a device location...
			String tmpLatitude = BT_appDelegate.rootApp.getRootDevice().getDeviceLatitude();
			String tmpLongitude = BT_appDelegate.rootApp.getRootDevice().getDeviceLongitude();
			
			//the location that was tapped...
			BT_item tappedLocation = (BT_item) childItems.get(selectedIndex);

			if(tmpLatitude.length() > 3){
			
				try{
				
					//currentDevice.location > Obj_MapLocation.location
					Uri uri = Uri.parse("http://maps.google.com/maps?saddr=" + tmpLatitude + "," + tmpLongitude + "&daddr=" + tappedLocation.getLatitude()  + "," + tappedLocation.getLongitude());
					Intent intent = new Intent(android.content.Intent.ACTION_VIEW, uri);
					startActivity(intent);
				
				}catch(Exception e){
				
					BT_debugger.showIt(activityName + ":showDirections EXCEPTION " + e.toString());
					showAlert(getString(R.string.noNativeAppTitle),getString(R.string.noNativeAppDescription));
				
				}
				
			}else{
				BT_debugger.showIt(activityName + ":showDirections cannot determine location");
				showAlert(getString(R.string.mapLocationErrorTitle),getString(R.string.mapLocationErrorDescription));
			}

		}
	}
		
	
	
	//parse screenData...
    public void parseScreenData(String theJSONString){
        BT_debugger.showIt(activityName + ":parseScreenData");	
        //BT_debugger.showIt(activityName + ":parseScreenData " + theJSONString);
		//parse JSON string
    	try{

    		//empty data if previously filled...
    		childItems.clear();

            //if theJSONString is empty, look for child items in this screen's config data..
    		JSONArray items = null;
    		if(theJSONString.length() < 1){
    			if(this.screenData.getJsonObject().has("childItems")){
        			items =  this.screenData.getJsonObject().getJSONArray("childItems");
    			}
    		}else{
        		JSONObject raw = new JSONObject(theJSONString);
        		if(raw.has("childItems")){
        			items =  raw.getJSONArray("childItems");
        		}
    		}
  
    		//loop items..
    		if(items != null){
                for (int i = 0; i < items.length(); i++){
                	
                	JSONObject tmpJson = items.getJSONObject(i);
                	BT_item tmpItem = new BT_item();
                	if(tmpJson.has("itemId")) tmpItem.setItemId(tmpJson.getString("itemId"));
                	if(tmpJson.has("itemType")) tmpItem.setItemType(tmpJson.getString("itemType"));
                	
                	if(tmpJson.has("title")) {
                		tmpItem.setItemType(tmpJson.getString("title"));
                		tmpItem.setAnnotationTitle(tmpJson.getString("title"));
                	}else{
                		tmpItem.setAnnotationTitle("");
                	}
                   	if(tmpJson.has("subTitle")){
                   		tmpItem.setItemType(tmpJson.getString("subTitle"));
                		tmpItem.setAnnotationSubTitle(tmpJson.getString("subTitle"));
                   	}else{
                		tmpItem.setAnnotationSubTitle("");
                   	}
                   	if(tmpJson.has("latitude")) {
                   		tmpItem.setItemType(tmpJson.getString("subTitle"));
                		tmpItem.setLatitude(Double.parseDouble(tmpJson.getString("latitude")));
                   	}
                   	if(tmpJson.has("longitude")){
                   		tmpItem.setItemType(tmpJson.getString("subTitle"));
                		tmpItem.setLongitude(Double.parseDouble(tmpJson.getString("longitude")));
                   	}
                   	
                   	if(tmpJson.has("latitude") && tmpJson.has("longitude")){
                   		tmpItem.point = getPoint(Double.parseDouble(tmpJson.getString("latitude")), Double.parseDouble(tmpJson.getString("longitude")));
                   	}else{
                   		tmpItem.point = getPoint(36.615763, -121.904234);
                   	}
                	tmpItem.setJsonObject(tmpJson);
                	childItems.add(tmpItem);
     	    	
                	
                }//for
                
                //flag data loaded...
                didLoadData = true;
    			
    		}else{
    			BT_debugger.showIt(activityName + ":parseScreenData NO CHILD ITEMS?");
    			
    		}
    	}catch(Exception e){
			BT_debugger.showIt(activityName + ":parseScreenData EXCEPTION " + e.toString());
    	}
        

        //show how many items...
        if(childItems.size() > 0){
        	showToast(childItems.size() + " " + getString(R.string.mapLocations), "short");
        }
        
        //show pins...
        showMapPins();
        
    }		
	
	//show map pins...
	@SuppressWarnings("static-access")
	public void showMapPins(){
        BT_debugger.showIt(activityName + ":showMapPins");	
    
        //init points array
	    childLocationPoints = new ArrayList<GeoPoint>();

		//we must have a device location...
		String tmpLatitude = BT_appDelegate.rootApp.getRootDevice().getDeviceLatitude();
		String tmpLongitude = BT_appDelegate.rootApp.getRootDevice().getDeviceLongitude();
	    
	    //clear map..
        mapOverlays = mapView.getOverlays();
        locationPin = pinRed;
       	itemizedOverlay = new ChildLocationsOverlay(locationPin);        
       	mapView.invalidate();
        
        //loop data.. (track max / min latitude and longitude so we can zoom...
        
		int i = 0;
		for (i = 0; i < childItems.size(); i++){
			BT_item thisLocation = childItems.get(i);
			
			
			//red map-pin is default...
			String pinColor = BT_strings.getJsonPropertyValue(thisLocation.getJsonObject(), "pinColor", "red");
			Drawable pinGraphic = pinRed;
			if(pinColor.equalsIgnoreCase("green")) pinGraphic = pinGreen;
			if(pinColor.equalsIgnoreCase("blue")) pinGraphic = pinBlue;
			if(pinColor.equalsIgnoreCase("purple")) pinGraphic = pinPurple;
	 			
			//overlay item..
			OverlayItem overlayitem = new OverlayItem(thisLocation.point, thisLocation.getAnnotationTitle(), thisLocation.getAnnotationSubTitle());			
	        overlayitem.setState(pinGraphic, 0);
			pinGraphic.setBounds(0, 0, pinGraphic.getIntrinsicWidth(), pinGraphic.getIntrinsicHeight());
	        itemizedOverlay.addOverlay(overlayitem, pinGraphic);
	        childLocationPoints.add(thisLocation.point);
			
			
	    }//end for..
		
		
		//add device location if we have it and showUserLocation != "0"
		if(tmpLatitude.length() > 4 && !showUserLocation.equalsIgnoreCase("0")){
			
			BT_item deviceLoc = new BT_item();
			try{
				deviceLoc.setLatitude(Double.parseDouble(tmpLatitude));
				deviceLoc.setLongitude(Double.parseDouble(tmpLongitude));
				deviceLoc.setAnnotationTitle(getString(R.string.mapUserLocationTitle));
				deviceLoc.setAnnotationSubTitle(BT_appDelegate.rootApp.getRootDevice().getDeviceModel());
				deviceLoc.point = getPoint(Double.parseDouble(tmpLatitude), Double.parseDouble(tmpLongitude));
				deviceLoc.setJsonObject(new JSONObject("{}"));
			} catch (JSONException e){
			}
			
			//device location marker
			OverlayItem overlayitem = new OverlayItem(deviceLoc.point, deviceLoc.getAnnotationTitle(), deviceLoc.getAnnotationSubTitle());			
			Drawable pinGraphic = youAreHereIcon;
			overlayitem.setState(pinGraphic, 0);
			pinGraphic.setBounds(0, 0, pinGraphic.getIntrinsicWidth(), pinGraphic.getIntrinsicHeight());
			itemizedOverlay.addOverlay(overlayitem, pinGraphic);
			childItems.add(deviceLoc);
			childLocationPoints.add(deviceLoc.point);
				
   		}	
        	            	
		//add itemizedOverlay array to map
		mapOverlays.add(itemizedOverlay);
		
		//rebuild map.
        setMapBoundsToPois(childLocationPoints, 0.1, 0.1);
        mapView.invalidate();  
        
  		
	}
    
	public void setMapBoundsToPois(List<GeoPoint> items, double hpadding, double vpadding){
        BT_debugger.showIt(activityName + ":showMapPins");	
        
        //reference to map controller..
	    MapController mapController = mapView.getController();
	    
	    //if there is only on one location directly animate to that location
	    if(items.size() == 1){
	        
	    	mapController.animateTo(items.get(0));
	    
	    }else{
	     
	    	// find the lat, lon span
	        int minLatitude = Integer.MAX_VALUE;
	        int maxLatitude = Integer.MIN_VALUE;
	        int minLongitude = Integer.MAX_VALUE;
	        int maxLongitude = Integer.MIN_VALUE;

	        // Find the boundaries of the item set
	        for (GeoPoint item : items) {
	            int lat = item.getLatitudeE6(); int lon = item.getLongitudeE6();

	            maxLatitude = Math.max(lat, maxLatitude);
	            minLatitude = Math.min(lat, minLatitude);
	            maxLongitude = Math.max(lon, maxLongitude);
	            minLongitude = Math.min(lon, minLongitude);
	        }

	        // leave some padding from corners
	        // such as 0.1 for hpadding and 0.2 for vpadding
	        maxLatitude = maxLatitude + (int)((maxLatitude-minLatitude)*hpadding);
	        minLatitude = minLatitude - (int)((maxLatitude-minLatitude)*hpadding);

	        maxLongitude = maxLongitude + (int)((maxLongitude-minLongitude)*vpadding);
	        minLongitude = minLongitude - (int)((maxLongitude-minLongitude)*vpadding);

	        // Calculate the lat, lon spans from the given pois and zoom
	        mapController.zoomToSpan(Math.abs(maxLatitude - minLatitude), Math.abs(maxLongitude - minLongitude));

	        // Animate to the center of the cluster of points
	        mapController.animateTo(new GeoPoint((maxLatitude + minLatitude) / 2, (maxLongitude + minLongitude) / 2));
	    }
	    
	    //invalidate to "refresh" map
        mapView.invalidate();
        
        //hide progress
        hideProgress();
          
	} // end of the method

	//remove locations..
	public void removeLocations(){
        BT_debugger.showIt(activityName + ":removeLocations");	
		List<Overlay> listOfOverlays = mapView.getOverlays();
        listOfOverlays.clear();
        mapView.invalidate();
    }    
    
	//creates point
	private GeoPoint getPoint(double lat, double lon) {
		return(new GeoPoint((int)(lat*1000000.0),(int)(lon*1000000.0)));
	}	
    
    
	//////////////////////////////////////////////////////////////////
	//location overlay manager..
	public class ChildLocationsOverlay extends ItemizedOverlay<OverlayItem> {
		private ArrayList<OverlayItem> mOverlays;
		
		public ChildLocationsOverlay(Drawable defaultMarker) {
			super(boundCenterBottom(defaultMarker));
			mOverlays = new ArrayList<OverlayItem>();
			populate();
		}

		public void addOverlay(OverlayItem overlay, Drawable theMarker) {
			//this.boundCenterBottom(theMarker);
			overlay.setMarker(theMarker);
			mOverlays.add(overlay);
		    populate();
		}	
		
		@Override
		protected OverlayItem createItem(int i) {
		  return mOverlays.get(i);
		}

		@Override
		public int size() {
			return mOverlays.size();
		}

		//override draw so we don't get the shadow!
		@Override
		public void draw(android.graphics.Canvas canvas, MapView mapView,  boolean shadow){
			super.draw(canvas, mapView, false);
		}
		
		@Override
		protected boolean onTap(int i) {
			
			//get BT_item for selected index..
			BT_item tappedLocation = (BT_item) childItems.get(i);
			selectedIndex = i;
			
			//bubble depends on which type of pin was tapped. Device's location or regular location
			if(tappedLocation.getIsDeviceLocation()){
				showAlert(getString(R.string.mapUserLocationTitle), getString(R.string.mapUserLocationDescription));
			}else{
				showLocationBubble(tappedLocation);
			}
			
			
			return(true);
		}	
	}
	//////////////////////////////////////////////////////////////////
	
   
    
    ///////////////////////////////////////////////////////////////////
	//DownloadScreenDataThread and Handler
	Handler downloadScreenDataHandler = new Handler(){
		@Override public void handleMessage(Message msg){
			if(JSONData.length() < 1){
				hideProgress();
				showAlert(getString(R.string.errorTitle), getString(R.string.errorDownloadingData));
			}else{
				parseScreenData(JSONData);
			}
		}
	};	   
    
	public class DownloadScreenDataWorker extends Thread{
		 boolean threadRunning = false;
		 String downloadURL = "";
		 String saveAsFileName = "";
		 void setThreadRunning(boolean bolRunning){
			 threadRunning = bolRunning;
		 }	
		 void setDownloadURL(String theURL){
			 downloadURL = theURL;
		 }
		 void setSaveAsFileName(String theFileName){
			 saveAsFileName = theFileName;
		 }
		 @Override 
    	 public void run(){
			
			 //downloader will fetch and save data..Set this screen data as "current" to be sure the screenId
			 //in the URL gets merged properly. Several screens could be loading at the same time...
			 BT_appDelegate.rootApp.setCurrentScreenData(screenData);
			 String useURL = BT_strings.mergeBTVariablesInString(dataURL);
			 BT_debugger.showIt(activityName + ":downloading screen data from " + useURL);
			 BT_downloader objDownloader = new BT_downloader(useURL);
			 objDownloader.setSaveAsFileName(saveAsFileName);
			 JSONData = objDownloader.downloadTextData();
			
			 //save JSONData...
			 BT_fileManager.saveTextFileToCache(JSONData, saveAsFileName);
			 
			 //send message to handler..
			 this.setThreadRunning(false);
			 downloadScreenDataHandler.sendMessage(downloadScreenDataHandler.obtainMessage());
   	 	
		 }
	}	
	//END DownloadScreenDataThread and Handler
	///////////////////////////////////////////////////////////////////	
	
	
     
	/////////////////////////////////////////////////////
	//options menu (hardware menu-button)
	@Override 
	public boolean onPrepareOptionsMenu(Menu menu) { 
		super.onPrepareOptionsMenu(menu); 
		
		 //set up dialog
        final Dialog dialog = new Dialog(this);

        //linear layout holds all the options...
		LinearLayout optionsView = new LinearLayout(this);
		optionsView.setLayoutParams(new LayoutParams(LayoutParams.FILL_PARENT, LayoutParams.FILL_PARENT));
		optionsView.setOrientation(LinearLayout.VERTICAL);
		optionsView.setGravity(Gravity.CENTER_VERTICAL | Gravity.CENTER_HORIZONTAL);
		optionsView.setPadding(20, 0, 20, 20); //left, top, right, bottom
		
		//options have individual layout params
		LinearLayout.LayoutParams btnLayoutParams = new LinearLayout.LayoutParams(400, LayoutParams.WRAP_CONTENT);
		btnLayoutParams.setMargins(10, 10, 10, 10);
		btnLayoutParams.leftMargin = 10;
		btnLayoutParams.rightMargin = 10;
		btnLayoutParams.topMargin = 0;
		btnLayoutParams.bottomMargin = 10;
		
		//holds all the options 
		ArrayList<Button> options = new ArrayList<Button>();

		//cancel...
		final Button btnCancel = new Button(this);
		btnCancel.setText(getString(R.string.okClose));
		btnCancel.setTextColor(Color.RED);
		btnCancel.setOnClickListener(new OnClickListener(){
            public void onClick(View v){
                dialog.cancel();
            }
        });
		options.add(btnCancel);
		
		//refresh screen data...
		if(showRefreshButton.equalsIgnoreCase("1")){
			final Button btnRefreshScreenData = new Button(this);
			btnRefreshScreenData.setText(getString(R.string.refreshScreenData));
			btnRefreshScreenData.setOnClickListener(new OnClickListener(){
	            public void onClick(View v){
	                dialog.cancel();
	            	refreshScreenData();
	            }
	        });
			options.add(btnRefreshScreenData);
		}
		
		//show users location...
		if(showUserLocationButton.equalsIgnoreCase("1")){
			final Button btnShowUsersLocation = new Button(this);
			btnShowUsersLocation.setText(getString(R.string.mapShowUsersLocation));
			btnShowUsersLocation.setOnClickListener(new OnClickListener(){
	            public void onClick(View v){
	                dialog.cancel();
	            	showDeviceLocation();
	            }
	        });
			options.add(btnShowUsersLocation);
		}
		
		//show map type buttons...
		if(showMapTypeButtons.equalsIgnoreCase("1")){
			
			//standard
			final Button btnStandard = new Button(this);
			btnStandard.setText(getString(R.string.mapShowMapTypeStandard));
			btnStandard.setOnClickListener(new OnClickListener(){
	            public void onClick(View v){
	                dialog.cancel();
	                showMapType("standard");
	            }
	        });
			options.add(btnStandard);
			
			//terrrain
			final Button btnTerrain = new Button(this);
			btnTerrain.setText(getString(R.string.mapShowMapTypeTerrain));
			btnTerrain.setOnClickListener(new OnClickListener(){
	            public void onClick(View v){
	                dialog.cancel();
	                showMapType("terrain");
	            }
	        });
			options.add(btnTerrain);
			
			//hybrid
			final Button btnHybrid = new Button(this);
			btnHybrid.setText(getString(R.string.mapShowMapTypeHybrid));
			btnHybrid.setOnClickListener(new OnClickListener(){
	            public void onClick(View v){
	                dialog.cancel();
	                showMapType("hybrid");
	            }
	        });
			options.add(btnHybrid);
			
		}//showMapTypeButtons
		
		//add each option to layout, set layoutParams as we go...
		for(int x = 0; x < options.size(); x++){
            Button btn = (Button)options.get(x);
            btn.setTextSize(18);
            btn.setLayoutParams(btnLayoutParams);
            btn.setPadding(5, 5, 5, 5);
			optionsView.addView(btn);
		}
		
		//set content view..        
        dialog.setContentView(optionsView);
        if(options.size() > 1){
        	dialog.setTitle(getString(R.string.menuOptions));
        }else{
        	dialog.setTitle(getString(R.string.menuNoOptions));
        }
        
        //show
        dialog.show();
		return true;
		
	} 
	//end options menu
	/////////////////////////////////////////////////////
}
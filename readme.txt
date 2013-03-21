This plugin uses the devices native mapping functionality to show a list of locations
on map. Locations are markers with a title and sub-title. An optional icon can be
included for each location that loads another screen or shows driving directions when 
it's tapped. The control panel allows other advanced adjustments to control the maps
behavior and features.

The list of locations on the map may be individually entered in the control panel or
may come from a remote file. If you're showing more than a dozen or so locations it's 
best to use a remote file to supply the location data. 

iOS Project
------------------------
4 Objective-C classes (a total of 8 files) are needed. 
BT_screen_map.m and .h
BT_mapAnnotation.m and .h
BT_mapZoomLevel.m and .h
BT_mapAnnotationView.m and .h

BT_screen_map.m is the main UIViewController that shows the map. The other classes are 
used to produce the individual locations and handle view options and interactivity. 

Android Project
------------------------

AN ADJUSTMENT NEED TO BE MADE TO YOUR PROJECT AFTER YOU DOWNLOAD YOUR CODE
BEFORE YOU CAN USE MAPS IN ANDROID

Before the map will work you'll need to obtain two Google Maps API keys for Google Maps. 
One is called a Debug key and one a Release key. These do not cost 
money but do confuse lots of people. It's easy once you get the hang of it. 

Instructions on how to get the API keys are here:

http://code.google.com/android/add-ons/google-apis/mapkey.html

After getting your Google Maps API keys, you need to enter them in the projects strings.xml
file. This file is included in your project download in the /src/values folder.
Open /src/values/strings.xml with a simple text editor, or in Eclipse, then find two entries: 

googleMapsAPIKeyDebug
googleMapsAPIKeyRelease

Enter your Google Maps API keys in the appropriate sections. Next, you'll need to tell
the screen_map.xml file which key to use. Open screen_map.xml and find the Google Maps API
key entry. The value you here enter depends on how you're compiling. If you're compiling
in Debug mode it should looks like this:

android:apiKey="@string/googleMapsAPIKeyDebug" 

If you're compiling in Release mode it should look like this:

android:apiKey="@string/googleMapsAPIKeyRelease"

Entering these values tells the Android compiler which of the two values you entered in
the strings.xml file to use. Lastly, you'll need to make sure you AndroidManifest.xml 
file knows you are using maps. Open that file and be sure this section is uncommented:

<uses-library android:name="com.google.android.maps"/>

BT_screen_map.java is the Activity class that displays the map. The layout is handled by
screen_map.xml. Additionally, several graphic files are needed to show the locations on the map. 

Version History
-----------------

v1.3	11/10/2012
		Minor syntax changes to accomodate for Xcode 4.5 compiler warnings.
		Minor changes in .java files to accomodate for Anroid (Google) 2.2 API's compiler warnings. 
		Minor UI changes in .php files for control panel.
		Updated Driving Directions routine to use native maps app instead of Google	Maps in Safari.
		

v1.0, 1.1, 1.2 (historical versions, no change details)


JSON Data
------------------------

If you manually enter the location data, the JSON data for this item in the BT_config.txt
includes a child items array holding individual map locations like this....

{
	"itemId":"1111", 
	"itemType":"BT_screen_map", 
	"itemNickname":"Location Map", 
	"navBarTitleText":"Monterey Spots", 
	"childItems":[
		{
			"itemId":"loc_1", 
			"itemType":"BT_locationItem", 
			"title":"Cool Restaurant", 
			"subTitle":"1234 Elm Street Anytown CA", 
			"latitude":"38.4323", 
			"longitude":"-121.2345"
		},
		{
			"itemId":"loc_2", 
			"itemType":"BT_locationItem", 
			"title":"Another Restaurant", 
			"subTitle":"1234 Main Street Anytown CA", 
			"latitude":"38.4223", 
			"longitude":"-121.5645"
		}
	]
}

If you provide location data from a remote file there is not childItems array. Instead, 
the locations are pulled from a remote file then added to the map.

{
	"itemId":"1111", 
	"itemType":"BT_screen_map", 
	"itemNickname":"Location Map", 
	"navBarTitleText":"Monterey Spots", 
	"dataURL":"http://mywebsite.com/locationsForMap.php"
}
	
In this case the locations would come from a backend script at the dataURL. Loading
the dataURL in your browser would produce output like this....

{
	"childItems":[
		{
			"itemId":"D441703507867F328A084DF", 
			"itemType":"BT_mapLocation",
			"latitude":"36.600723", 
			"longitude":"-121.8928338", 
			"title":"Location 1", 
			"subTitle":"234 Del Monte Blvd Monterey CA 93940"
		},
		{
			"itemId":"419993F0FEC3312C797C91E", 
			"itemType":"BT_mapLocation",
			"latitude":"36.615763", 
			"longitude":"-121.904234", 
			"title":"Location 2", 
			"subTitle":"801 Lighthouse Ave Monterey CA 93940"
		},
		{
			"itemId":"11F25D4F6120F19E7F4B220", 
			"itemType":"BT_mapLocation",
			"latitude":"36.5977439", 
			"longitude":"-121.8949035", 
			"title":"Location 3", 
			"subTitle":"823 Alvarado St Monterey CA 93940"
		}
	]
}












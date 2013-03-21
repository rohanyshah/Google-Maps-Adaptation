<?php   

	$address = "";
	if(isset($_GET["address"])) $address = $_GET["address"];
	if(isset($_POST["address"])) $address = $_POST["address"];
	$address = urldecode($address);
	if($address != ""){
		
		//lookup latitude, longitude using google maps
		$gps = "http://maps.google.com/maps/api/geocode/xml?address=" . $address . "&sensor=false";
		$gps = str_replace(" ","%20",$gps);
		$ch = curl_init($gps);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$return_value = curl_exec($ch);  
		curl_close($ch);
		
		$xml = new SimpleXMLElement($return_value);
		$orgLattitude = floatVal($xml->result->geometry->location->lat);
		$orgLongitude = floatVal($xml->result->geometry->location->lng);
		echo $orgLattitude . "|" . $orgLongitude;

	}
	
	//done
	exit();
	
	
?>
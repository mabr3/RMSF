<?php

//Make sure that it is a POST request.
if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0){
    throw new Exception('Request method must be POST!');
}

//Make sure that the content type of the POST request has been set to application/json
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if(strcasecmp($contentType, 'application/json') != 0){
    throw new Exception('Content type must be: application/json');
}

//Receive the RAW post data.
$content = trim(file_get_contents("php://input"));

//Attempt to decode the incoming RAW post data from JSON.
$decoded = json_decode($content, true);




//If json_decode failed, the JSON is invalid.
if(!is_array($decoded)){
    echo ('Received content contained invalid JSON!<br><br>');
}

//GET THE DEVICE ID FROM JSON
$deviceRef = $decoded['dev_id'];	

$PlayerLat = $decoded['payload_fields']['location']['latitude'];

$PlayerLon = $decoded['payload_fields']['location']['longitude'];

$func = $decoded['payload_fields']['location']['func'];

$orient = $decoded['payload_fields']['location']['orient'];

$downlink = $decoded['downlink_url'];

//INITIATE CONNECTION TO DATABSE
$servername = 'db.ist.utl.pt';

$username = 'ist176176';

$password = 'cuzo7054';

$dbname = 'ist176176';

//$conn = new mysqli($servername, $username, $password, $dbname);
$conn = new PDO("mysql:host=" . $servername. ";dbname=" . $dbname, $username, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));


$sql="SELECT * FROM Player WHERE reference='$deviceRef'";
$result = $conn->query($sql);

$num = $result->rowCount();



if( $num == 0 ){ //SE AINDA NAO EXISTIR O USER, CRIA O USER

	$sql="INSERT INTO Player (reference,kills, deaths, lat,lon) VALUES ('$deviceRef', 0, 0, $PlayerLat, $PlayerLon)";
	$result = $conn->query($sql);
}



if($func == 0){	//PERIODIC UPDATE
	
	killCheckByMine($downlink,$deviceRef, $PlayerLat, $PlayerLon, $conn);
	
}elseif($func==2){  // CLICK ON Mine BUTTON

	addMine($deviceRef, $PlayerLat, $PlayerLon, $conn);

}elseif($func==1){	//CLICK ON SHOOT BUTTON

	killCheckByShoot($downlink,$deviceRef, $PlayerLat, $PlayerLon,$orient, $conn);
}



function killCheckByMine($downlink,$reference, $PlayerLat, $PlayerLon, $conn){

	$earthRadius = 6371000;
	
	$sql="UPDATE Player SET lat = $PlayerLat, lon = $PlayerLon, lastUpdateTime = CURRENT_TIMESTAMP() WHERE reference ='$reference'";
	$result = $conn->query($sql);
	
	//VER SE HA MINAS COLOCADAS POR OUTROS JOGADORES
	$sql = "SELECT reference,lat,lon FROM Mine WHERE reference NOT IN ('$reference')";
	$result = $conn->query($sql);
	$columns = array();
	$resultset = array();


	while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			if (empty($columns)) {
				$columns = array_keys($row);
			}
			$resultset[] = $row;
		}

		if( count($resultset) > 0 ) {
			
			
			echo "<table border=1><thead><tr>";
			foreach ($columns as $k => $column_name ) :
				echo "<th> <font color=	#FF6347>$column_name</font></th>";
			endforeach;
			echo "</tr></thead>";
			
			echo "<tbody>";
			
			foreach($resultset as $index => $row) {
				$column_counter =0;
				
				echo "<tr align=center>";

				for ($i=0; $i < count($columns); $i++):
				
					echo"<td>".$row[$columns[$column_counter++]]."</td>";
				endfor;
			echo"</tr>";
			}
			echo"</tbody></table>";
		}
		
		
		
	if( count($resultset) > 0 ) {
		
		//CALCULATE ALL DISTANCES BETWEEN Player LOCATION AND Mine LOCCATION

		$dArray=$resultset;
		
		$distanceMin = 2*$earthRadius;
		
		for ($i=0; $i < count($resultset); $i++){
			
			
			
			$lat = $resultset[$i]['lat'];
			$lon = $resultset[$i]['lon'];
			$referenceKiller = $resultset[$i]['reference'];
			$distance = haversineGreatCircleDistance( $PlayerLat, $PlayerLon, $lat, $lon,  $earthRadius);
			
			if($distance < $distanceMin)
			{
			//guarda dados da mina mais prox
				$distanceMin = $distance;
				$refDeployerMin = $referenceKiller;
				$latMin = $lat;
				$lonMin = $lon;
			}
			
		}
		
	  }
	 
	 
	  
	   if($distanceMin <= 10){
	  
			
			
			$sql ="DELETE FROM Mine WHERE reference='$refDeployerMin' AND lat=$latMin AND lon=$lonMin";
			$result = $conn->query($sql);
	  
			$sql ="INSERT INTO DeathLog (referenceKiller, referenceKilled,method, lat,lon, deathTime) VALUES ('$refDeployerMin', '$reference', 'Mine',$PlayerLat,$PlayerLon ,CURRENT_TIMESTAMP())";
			$result = $conn->query($sql);
	  
			$sql="UPDATE Player SET kills=(kills+1) WHERE reference ='$refDeployerMin'";
			$result = $conn->query($sql);
	  
			$sql="UPDATE Player SET deaths=(deaths+1) WHERE reference ='$reference'";
			$result = $conn->query($sql);
		
			
			//ENVIAR JSON PARA O ARDUINO A INDICAR QUE MORREU POR MINA


			//FUN 1 = MQ==, PARA QUANDO O ARDUINO RECEBE QUE MORREU COM UMA MINA

			sendJson($downlink,$reference,'MQ==');

			

			//FUN 3 = Mw==, PARA QUANDO O OUTRO ARDUINO RECEBE QUE MATOU COM UMA MINA

			sendJson($downlink,$refDeployerMin,'Mw==');
	  }
	
}



function addMine($reference, $MineLat, $MineLon, $conn){
	


	$sql = "INSERT INTO Mine (reference, lat, lon, MineTime) VALUES ('$reference', '$MineLat', '$MineLon', CURRENT_TIMESTAMP())";
	$result = $conn->query($sql);

	if ($result === TRUE) {
		
		echo "<br><br>Mine Deployed Successfully";
	} else {
		echo "<br><br>Error: " . $sql . "<br>" . $conn->error;
	}

}

function killCheckByShoot($downlink,$reference, $PlayerLat, $PlayerLon,$orient, $conn){

	
	
	$earthRadius = 6371000;
	
	$sql="UPDATE Player SET lat = $PlayerLat, lon = $PlayerLon, lastUpdateTime = CURRENT_TIMESTAMP() WHERE reference ='$reference'";
	$result = $conn->query($sql);
	
	$sql = "SELECT lastUpdateTime FROM Player WHERE reference ='$reference'";
	$lastUpdateTime=$conn->query($sql);
	
	$columns1 = array();
	$resultset1 = array();
	while ($row1 = $lastUpdateTime->fetch(PDO::FETCH_ASSOC)) {
			if (empty($columns1)) {
				$columns1 = array_keys($row1);
			}
			$resultset1[] = $row1;
		}
	
	$dateShooter = $resultset1[0]['lastUpdateTime'];
	
	
	//VER SE HA OUTROS PlayerS EM JOGO ALGURES
	$sql = "SELECT * FROM Player WHERE reference NOT IN ('$reference')";
	$result = $conn->query($sql);
	$columns = array();
	$resultset = array();


	while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
			if (empty($columns)) {
				$columns = array_keys($row);
			}
			$resultset[] = $row;
		}

		if( count($resultset) > 0 ) {
			
			
			
			
			echo "<table border=1><thead><tr>";
			foreach ($columns as $k => $column_name ) :
				echo "<th> <font color=	#FF6347>$column_name</font></th>";
			endforeach;
			echo "</tr></thead>";
			
			echo "<tbody>";
			
			foreach($resultset as $index => $row) {
				$column_counter =0;
				
				echo "<tr align=center>";

				for ($i=0; $i < count($columns); $i++):
				
					echo"<td>".$row[$columns[$column_counter++]]."</td>";
				endfor;
			echo"</tr>";
			}
			echo"</tbody></table>";
		}
		
		
		
	if( count($resultset) > 0 ) {
		
		

		$dArray=$resultset;
		
		$distanceMin = 2*$earthRadius;
		
		for ($i=0; $i < count($resultset); $i++){
			
			$dateTarget = $resultset[$i]['lastUpdateTime'];
		
			$dateT= strtotime($dateTarget);
			$dateS = strtotime($dateShooter);
			 
			//Calculate the difference.
			$difference = $dateS - $dateT;
 

			if($difference<=5){
			
				$lat = $resultset[$i]['lat'];
				$lon = $resultset[$i]['lon'];
				$referenceKilled = $resultset[$i]['reference'];
				
				$bearing = (rad2deg(atan2(sin(deg2rad($lon) - deg2rad($PlayerLon)) * cos(deg2rad($lat)), cos(deg2rad($PlayerLat)) * sin(deg2rad($lat)) - sin(deg2rad($PlayerLat)) * cos(deg2rad($lat)) * cos(deg2rad($lon) - deg2rad($PlayerLon)))) + 360) % 360;
				
				
				
				if(getCompassDirection($bearing)!=$orient){
					
					echo"<br><br>Shoot Missed<br><br>";
				
				}else{
				
					$distance = haversineGreatCircleDistance( $PlayerLat, $PlayerLon, $lat, $lon,  $earthRadius);
				
				
					array_push($dArray[$i],$distance);
			
				
					if($distance < $distanceMin)
					{
					//guarda dados da mina mais prox
					$distanceMin = $distance;
					$PlayerDead = $referenceKilled;
					$latMin = $lat;
					$lonMin = $lon;
					}
				}
			}
		}
		
	  }


	  
	   if($distanceMin <= 50){
	  
		
			$sql ="INSERT INTO DeathLog (referenceKiller, referenceKilled,method, lat,lon, deathTime) VALUES ('$reference','$PlayerDead' , 'shoot',$latMin,$lonMin ,CURRENT_TIMESTAMP())";
			$result = $conn->query($sql);
	  
			$sql="UPDATE Player SET kills=(kills+1) WHERE reference ='$reference'";
			$result = $conn->query($sql);
	  
			$sql="UPDATE Player SET deaths=(deaths+1) WHERE reference ='$PlayerDead'";
			$result = $conn->query($sql);
		
			
			

			//FUNÇÃO 2 = Mg==, PARA QUANDO O ARDUINO RECEBE QUE MATOU COM UMA SHOOT

			sendJson($downlink,$reference,'Mg==');

			
	

			//FUNÇÃO 0 = Mw==, PARA QUANDO O OUTRO ARDUINO RECEBE QUE MORREU COM UM SHOOT

			sendJson($downlink,$PlayerDead,'MA==');
	  }
	
	

	

	$conn=null;
}

//Obtem direcção a que os utilizadores estão
function getCompassDirection($bearing) {
   $tmp = round($bearing / 45);
   switch($tmp) {
      case 1:
         $direction = "NE";
         break;
      case 2:
         $direction = "E";
         break;
      case 3:
         $direction = "SE";
         break;
      case 4:
         $direction = "S";
         break;
      case 5:
         $direction = "SW";
         break;
      case 6:
         $direction = "W";
         break;
      case 7:
         $direction = "NW";
         break;
      default:
         $direction = "N";
   }
   return $direction;
 }

 
//SEE WHAT TO PUT ON LATITUDETO BECAUSE WE HAVE TO GO THROUGH ALL THE DATABASE.
function haversineGreatCircleDistance( $PlayerLat, $PlayerLon, $latitudeTo, $longitudeTo, $earthRadius)
{
  // convert from degrees to radians
  $latFrom = deg2rad($PlayerLat);
  $lonFrom = deg2rad($PlayerLon);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}

function sendJson($downlink,$reference,$funcaoStr){
				
	
	$data=array('dev_id'=>$reference,'port'=>1,'confirmed'=>true,'payload_raw'=>$funcaoStr);
	
	
	echo "Non-associative array output as object: ".$encoded. "\n\n";

	//API Url
	$url = $downlink;
	//$url = 'http://vps520359.ovh.net/api/miguel/rcv';
	
	//Initiate cURL.
	$ch = curl_init($url);

	//Encode the array into JSON.
	$jsonDataEncoded = json_encode($data);
	

	//Tell cURL that we want to send a POST request.
	curl_setopt($ch, CURLOPT_POST, 1);

	//Attach our encoded JSON string to the POST fields.
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

	//Set the content type to application/json
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 

	//Execute the request
	$result = curl_exec($ch);
}

?>

<?php

$reference = $_GET["reference"];

echo "referencia::$reference";

$servername = 'db.ist.utl.pt';
$username = 'ist176176';
$password = 'cuzo7054';
$dbname = 'ist176176';

$conn = new PDO("mysql:host=" . $servername. ";dbname=" . $dbname, $username, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
// Check connection


//if ($conn->connect_error) {
//	echo("error:: DB connection");
//	die("Connection failed: " . $conn->connect_error);
//} 
//else {
	//echo ("Connection Successful");
//}


#SHOW JOGADOR------------------------------------------------------
if(showTable($conn, "SELECT * FROM Player WHERE reference='$reference'", "PLAYER") > 0){
	
	#SHOW MINAS------------------------------------------------------
	showTable($conn, "SELECT * FROM Mine WHERE reference='$reference'", "MINES");
	
	#SELECT MATOU------------------------------------------------------
	showTable($conn, "SELECT referenceKilled, method, lat, lon, deathTime FROM DeathLog WHERE referenceKiller='$reference'", "KILLS");

	
	#SELECT MORREU------------------------------------------------------
	showTable($conn, "SELECT referenceKiller, method, lat, lon, deathTime FROM DeathLog WHERE referenceKilled='$reference'", "DEATHS");
	
	
}
else {
	
	echo "<i style='color:red;font-size:20px;font-family:calibri ;'>
		  <br>User not registered, please create a new user </i> ";
		  
	 
} 


$conn = null;


//SHOWTABLE DESCRICAO: recebe uma conn, a string a executar e o titulo da tabela
//RETURN: retorna o numero de rows do set
function showTable($conn, $queryString, $title){

	$sql = $queryString;
	$result = $conn->query($sql);
	$columns = array();
	$resultset = array();

	# Set columns and results array
	//while ($row = mysqli_fetch_assoc($result)) {
	while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
		if (empty($columns)) {
			$columns = array_keys($row);
		}
		$resultset[] = $row;
	}
	
	if( count($resultset) > 0 ) {
		
		
		echo "<br> <font color=#8B0000>$title</font><br>";
		
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
	
	return count($resultset);
}


?>

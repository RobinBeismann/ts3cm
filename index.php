<?php

	# TS3 Query Config:

	$server_ip="127.0.0.1";
	$server_query_port="10011";
	$server_user="username";
	$server_pw="pw";
	$server_port="9987";

	# MySQL Database Config:

	$db_host = 'dbhost';
	$db_user = 'dbuser';
	$db_pass = 'dbpass';
	$db_name = 'ts3cm';
	$sql_conn = mysqli_connect($db_host,$db_user,$db_pass);
	$sql_conn->select_db($db_name);

	# TS3 Specific Config:
	$chlgrp_admin_id=5; #Channel Admin Group ID (Default 5)

	# Table creation:

	$sql = "CREATE TABLE IF NOT EXISTS `ts3cm_assoc` (
	  `client_id` int(11) DEFAULT NULL,
	  `channel_id` int(11) DEFAULT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

	$sql_conn->query($sql);

	
	global $srv;
	session_start();

	$clip = "";

	if(isset($_POST["clientid"]) && !empty($_POST["clientid"])){
		setcookie( "NWDE_CLDBID", ($_POST["clientid"]) );
	}
	
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$clip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$clip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$clip = $_SERVER['REMOTE_ADDR'];
	}

	require_once("TeamSpeak3/TeamSpeak3.php");

	$srv = TeamSpeak3::factory("serverquery://".$server_user.":".$server_pw."@".$server_ip.":".$server_query_port."/?server_port=".$server_port);

	#$ts3_UpdateServer = TeamSpeak3::factory("update");

	$cl=getClient($clip);
	echo "Hallo " . $cl["client_nickname"] . "!<br/>";
	$clientdbid = $cl["client_database_id"];
	$clientcurid = $cl["clid"];

	$query = "SELECT channel_id, client_id FROM ts3cm_assoc WHERE client_id = " . $clientdbid;
	$result = $sql_conn->query($query);

	if($result->num_rows==1)
	{
		$fresult = $result->fetch_object();
		$channel_id = $fresult->channel_id;

		if(!array_key_exists((string) $channel_id, $srv->channelList()))
		{
			$sql_conn->query("DELETE FROM `ts3cm_assoc` WHERE (`client_id`='".$clientdbid."') AND (`channel_id`='".$channel_id."')");
			die("Es sieht so aus, als ob dein Channel gelöscht wurde.<br/>Bitte aktualisiere diese Seite um einen neuen zu erstellen!");
		}
		
		$channelname = $srv->channelGetById($fresult->channel_id)->channel_name;
		if(isset($_REQUEST["admrights"]))
		{
			echo("Die Admin Rechte wurden erneut zugeteilt.");
			$srv->clientSetChannelGroup($clientdbid,$channel_id,5);
			$srv->clientMove($clientcurid,$channel_id,null);
			die();
		}
		
		echo "Du hast bereits einen Channel:<br/>" . $channelname;
		
		echo '<form action="index.php" method="post" id="controlpanel">';
		echo '<input type="hidden" value="1" name="admrights"></input>';
		echo '<button type="submit" value="Submit">Admin Rechte wieder erteilen.</button>';
		echo '</form>';
			
	}
	else 
	{
		echo "Da Du noch keinen Channel besitzt, werden wir nun einen Channel f&uuml;r dich erstellen.<br>Bitte denke daran, für diesen Channel ein Passwort einzustellen!";
		$cl->message("Dein Channel wurde erstellt!");
		$properties = array();
		$properties["channel_flag_permanent"] = 1;
		$properties["channel_name"] = $cl["client_nickname"] . "'s Channel";
		$channel_id = $srv->channelCreate($properties);
		$srv->clientMove($clientcurid,$channel_id,null);
		$srv->clientSetChannelGroup($clientdbid,$channel_id,5);
		$sql_conn->query("INSERT INTO `ts3cm_assoc` (`client_id`, `channel_id`) VALUES ('" . $clientdbid . "', '".$channel_id."')");
	}



	/* ### Functions ### */

	function getClient($_clip,$id = null){
		global $srv;
		$propcls = array();

		foreach($srv->clientList() as $ts3_Client)
		{
			if($ts3_Client["client_type"]) continue;
			$clarray = $ts3_Client->infoDb();
			if(!isset($id)){
				$ip= ($ts3_Client->connection_client_ip->toString())."";
				if(($ip == $_clip) ){
					array_push($propcls, $ts3_Client);
				}
			}else{
				$cid = $clarray["client_database_id"];
				if(($cid == $id) ){
					array_push($propcls, $ts3_Client);
				}		
			}
			
		}

		$ccls=count($propcls);
		if($ccls===0){
			die("There is no client connected from ".$_clip."!");
		}elseif($ccls>1){
			if(isset($_COOKIE["NWDE_CLDBID"])){
				return getClient($_clip,$_COOKIE["NWDE_CLDBID"]);
			}else{
				echo("<form action='index.php' method='post'>");
				foreach($propcls as $cl){
					$clientdbid = $cl["client_database_id"];
					$clientcurid = $cl["clid"];
					echo("<button type=\"submit\" name='clientid' value=\"". $clientdbid ."\">".$cl["client_nickname"]."</button><br/>");
				}
				echo("</form>");
			}
			die("There are more then one client connected from ".$_clip."!</br> this Function isn't implemented yet!");
		}
		return $propcls[0];
	}
?>

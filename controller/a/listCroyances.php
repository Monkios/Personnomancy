<?php
	$list_croyances = Dictionary::GetCroyances( FALSE, FALSE );
	
	$croyanceRepository = new CroyanceRepository();
	$croyances = array();
	foreach( $list_croyances as $id => $nom ){
		$croyances[] = $croyanceRepository->Find( $id );
	}
	
	if( isset( $_POST["add_croyance"] ) ){
		$croyance = $croyanceRepository->Create( array( "nom" => Security::FilterInput( $_POST["croyance_nom"] ) ) );
		
		if( $croyance !== FALSE ){
			header( "Location: ?s=admin&a=updateCroyance&i=" . $croyance->id );
			die();
		}
	}
	
	include "./views/top.php";
	include "./views/a/listCroyances.php";
	include "./views/bottom.php";
?>
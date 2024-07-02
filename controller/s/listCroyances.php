<?php
	$list_croyances = Dictionary::GetCroyances( FALSE, FALSE );
	
	$croyance_repository = new CroyanceRepository();
	foreach( $list_croyances as $id => $nom ){
		$list_croyances[ $id ] = $croyance_repository->Find( $id );
	}
	
	if( isset( $_POST["add_croyance"] ) ){
		$croyance = $croyance_repository->Create( array( "nom" => Security::FilterInput( $_POST["croyance_nom"] ) ) );
		
		if( $croyance !== FALSE ){
			header( "Location: ?s=super&a=updateCroyance&i=" . $croyance->id );
			die();
		}
	}
	
	include "./views/top.php";
	include "./views/s/listCroyances.php";
	include "./views/bottom.php";
?>
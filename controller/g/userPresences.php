<?php
	$joueurs = Community::GetPlayerList();
	
	$personnage_repository = new PersonnageRepository();
	$personnages = $personnage_repository->FindAllAlives( "player" );
	
	define( "PDF_FILL_COLOR", 191 );
	
	include "./views/g/userPresences.php";
?>
<?php
	$personnage_repository = new PersonnageRepository();
	$chars = $personnage_repository->GetAllCharacters();
	$players = Community::GetPlayerList();
	
	// Transfert des personnages
	if( isset( $_POST['transfer_character'] ) &&
			isset( $_POST['player_id'] ) && is_numeric( $_POST['player_id'] ) ){
		$players_chars = $personnage_repository->GetAllCharactersByPlayer( $_POST['player_id'] );
		$identity = new Identity( $_POST['player_id'] );
		if( $identity ){
			foreach( $_POST['character_id'] as $char_id ){
				if( is_numeric( $char_id ) &&
						array_key_exists( $char_id, $chars ) &&
						!array_key_exists( $char_id, $players_chars ) &&
						$identity->AssignCharacter( $char_id ) ){
					$player = Community::GetPlayer( $_POST['player_id'] );
					$chars[ $char_id ]->JoueurId = $player->Id;
					$chars[ $char_id ]->JoueurNom = $player->GetFullName();
					
					Message::Notice( "Le personnage #" . $char_id . " a été transféré à " . $player->GetFullName() . "." );
				}
			}
		}
	}

	include "./views/top.php";
	include "./views/s/characterTransfer.php";
	include "./views/bottom.php";
?>
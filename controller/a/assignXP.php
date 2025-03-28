<?php
	$player_list = Community::GetPlayerList( TRUE );
	$character_list = array();
	$reasons_list = Dictionary::GetExperienceChanges();
	
	$reload_page = FALSE;
	$raison_xp = "";
	
	foreach( $player_list as $player ){
		if( $player->IsActive ){
			$character_list[ $player->Id ] = Roster::GetAllCharactersByPlayer( $player->Id, TRUE );
		} else {
			$character_list[ $player->Id ] = array();
		}
	}
	
	if( isset( $_POST[ "change_xp" ] ) ){
		if( isset( $_POST[ "character_id" ] ) && count( $_POST[ "character_id" ] ) > 0 &&
				isset( $_POST[ "quantity_xp" ] ) && is_numeric( $_POST[ "quantity_xp" ] ) ){
			if( isset( $_POST[ "reasons_list" ] ) && array_key_exists( $_POST[ "reasons_list" ], $reasons_list ) ){
				$raison_xp = $reasons_list[ $_POST[ "reasons_list" ] ];
			} else {
				if( isset( $_POST[ "raison_xp" ] ) ){
					$raison_xp = Security::FilterInput( $_POST[ "raison_xp" ] );
				}
			}
			
			foreach( $_POST[ "character_id" ] as $char_id ){
				if( is_numeric( $char_id ) ){
					$char = new CharacterSheet();
					if( $char->ManageExperience( $char_id, $_POST[ "quantity_xp" ], FALSE, TRUE, $raison_xp ) ){
						$character = $char->Load( $char_id );
						
						$reload_page = TRUE;
						Message::Notice( "Le personnage " . $character->nom . " a reçu " . $_POST[ "quantity_xp" ] . " XP." );
					}
				} else {
					Message::Erreur( "Identifiant de personnage invalide." );
				}
			}
		} else {
			Message::Notice( "Veuillez sélectionner au moins un personnage et un nombre de points d'expérience valide." );
		}
	}
	
	if( $reload_page ){
		header( "Location: ?s=admin&a=assignXP" );
		die();
	}
	
	include "./views/top.php";
	include "./views/a/assignXP.php";
	include "./views/bottom.php";
?>
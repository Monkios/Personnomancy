<?php
	$is_animateur = $user_identity->HasAccess( Identity::IS_ANIMATEUR );
	$is_administrateur = $user_identity->HasAccess( Identity::IS_ADMINISTRATEUR );
	
	if( is_numeric( $_GET["c"] ) ){
		$character_id = $_GET["c"];
		$char_sheet = new CharacterSheet;
		$personnage = $char_sheet->Load( $_GET['c'], TRUE );
		
		if( $personnage != FALSE ){
			// Bloque l'acces au personnage d'autrui sauf pour les animateurs
			if( !$is_animateur && $personnage->joueur_id != $_SESSION[ SESSION_KEY ][ "User" ]->Id ){
				Message::Fatale( "Vous n'avez pas le droit d'accéder à ce personnage.",
						"U" . $_SESSION[ SESSION_KEY ][ "User" ]->Id . " / C" . $_GET['c'] );
			}

			$can_change_bases = $personnage->est_cree == FALSE;//( $personnage->est_cree == FALSE && ( empty( $personnage->race_id ) || $personnage->race_id == -1 ) ) || $is_animateur;
			$can_change_race = FALSE;
			$has_choices = FALSE;
			
			// Les administrateurs peuvent changer les points d'expérience des personnages créés
			// Les animateurs peuvent changer les points d'expérience de leurs personnages créés
			$can_change_xp = $personnage->est_cree && ( $is_administrateur || ( $is_animateur && $personnage->joueur_id == $_SESSION[ SESSION_KEY ][ "User" ]->Id ));
			$xp_change_min = 0;
			$xp_change_max = 0;
			if( $can_change_xp ){
				$xp_change_min = ( $personnage->px_totaux - CHARACTER_BASE_XP ) * -1;
				$xp_change_max = CHARACTER_MAX_XP_INVESTED - $personnage->px_totaux;
			}
			// Les administrateurs peuvent détruire les personnages
			// Les animateurs peuvent détruire leurs personnages
			$can_destroy = $is_administrateur || ( $is_animateur && $personnage->joueur_id == $_SESSION[ SESSION_KEY ][ "User" ]->Id );
			//$can_rebuild = $can_rebuild_perte = $can_destroy;
			$can_add_comment = $is_animateur;
			$can_add_notes = $is_administrateur || $personnage->joueur_id == $_SESSION[ SESSION_KEY ][ "User" ]->Id;

			$list_journal = CharacterLog::GetByCharacter( $personnage->id );
			$last_log = CharacterLog::GetLastByCharacter( $personnage->id );
			
			$list_voies = Dictionary::GetVoies();
			$list_capacites = array();
			foreach( $list_voies as $voie_id => $voie_desc ){
				$list_capacites[ $voie_id ] = Dictionary::GetCapacitesByVoie( $voie_id );
			}

			$list_connaissances = Dictionary::GetConnaissances();
			$list_connaissances_completes = array();
			$connaissance_repository = new ConnaissanceRepository();
			// Remplace les connaissances par les entités complètes
			$list_connaissances_completes[ $voie_id ] = array();
			foreach( $list_connaissances as $connaissance_id => $connaissance_desc ){
				$connaissance = $connaissance_repository->Find( $connaissance_id );
				if( !array_key_exists( $connaissance->prereq_voie_primaire, $list_connaissances_completes ) ){
					$list_connaissances_completes[ $connaissance->prereq_voie_primaire ] = array();
				}
				$list_connaissances_completes[ $connaissance->prereq_voie_primaire ][ $connaissance_id ] = $connaissance;
			}
			
			// Liste les capacités raciales disponibles au personnage
			$list_capacites_raciales = array();
			if( !$personnage->est_cree && is_numeric( $personnage->race_id ) && $personnage->race_id >= 0 ){
				$race_repository = new RaceRepository();
				$race_capacites_raciales = $race_repository->GetCapacitesRacialesByRace( $personnage->race_id );
				foreach( $race_capacites_raciales as $cr_id => $cr_infos ){
					if( !array_key_exists( $cr_id, $personnage->capacites_raciales ) && $personnage->pc_raciales >= $cr_infos[ 1 ] ){
						$list_capacites_raciales[ $cr_id ] = $cr_infos[ 0 ] . " (" . $cr_infos[ 1 ] . "cr)";
					}
				}
			}
			
			// Liste les capacités au choix du personnage
			$list_choix_capacites = array();
			if( count( $personnage->choix_capacites ) > 0 ){
				$choix_capacite_repository = new ChoixCapaciteRepository();
				foreach( $personnage->choix_capacites as $choix_id => $choix_nom ){
					$list_choix_capacites[ $choix_id ] = $choix_capacite_repository->GetCapacitesByChoixId( $choix_id );
				}
			}
			
			// Liste les capacités raciales au choix du personnage
			$list_choix_capacites_raciales = array();
			if( count( $personnage->choix_capacites_raciales ) > 0 ){
				$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
				foreach( $personnage->choix_capacites_raciales as $choix_id => $choix_nom ){
					$list_choix_capacites_raciales[ $choix_id ] = $choix_capacite_raciale_repository->GetCapacitesRacialesByChoixId( $choix_id );
				}
			}
			
			// Liste les connaissances au choix du personnage
			$list_choix_connaissances = array();
			if( count( $personnage->choix_connaissances ) > 0 ){
				$choix_connaissance_repository = new ChoixConnaissanceRepository();
				foreach( $personnage->choix_connaissances as $choix_id => $choix_nombre ){
					$list_choix_connaissances[ $choix_id ] = $choix_connaissance_repository->GetConnaissancesByChoixId( $choix_id );
				}
			}

			// Liste les voies au choix du personnage
			$list_choix_voies = array();
			if( count( $personnage->choix_voies ) > 0 ){
				$choix_voie_repository = new ChoixVoieRepository();
				foreach( $personnage->choix_voies as $choix_id => $choix_nombre ){
					$list_choix_voies[ $choix_id ] = $choix_voie_repository->GetVoiesByChoixId( $choix_id );
				}
			}
			
			// S'il a au moins un item au choix, le personnage a "des choix à faire"
			$has_choices = count( $list_choix_capacites ) > 0
					|| count( $list_choix_capacites_raciales ) > 0
					|| count( $list_choix_connaissances ) > 0
					|| count( $list_choix_voies ) > 0;
			
			// Liste seulement les informations de base si elles peuvent etre changees
			if( $can_change_bases ){
				if( $is_animateur ){
					$list_joueurs = Community::GetPlayerList( TRUE );
				}
				if( $personnage->pc_raciales == CHARACTER_BASE_PCR ){
					$can_change_race = TRUE;
					$list_races = Dictionary::GetRaces();
				}
				$list_cites_etats = Dictionary::GetCitesEtats();
				$list_croyances = Dictionary::GetCroyances();

				// Garde en mémoire s'ils doivent être modifiés
				$perso_nom = $personnage->nom;
				$perso_cite_etat = $personnage->cite_etat_id;
				$perso_race = $personnage->race_id;
				$perso_race_secondaire = $personnage->race_secondaire_id;
				$perso_croyance = $personnage->croyance_id;
			}

			// Gestion des cases à cocher de la fiche de personnage
			if( isset( $_GET["st"] ) ){
				if( $personnage->est_vivant ){
					// Sauvegarde des informations d'identification du personnage et changement de race
					if( $_GET["st"] == "bases" && isset( $_POST["save_bases"] ) ){
						$erreur = FALSE;
						if( $can_change_bases ){
							// Valide que le nom est bon
							if( empty( Security::FilterInput( $_POST['perso_nom'] ) ) ){
								Message::Erreur( "Vous n'avez fourni aucun nom pour ce personnage." );
								$erreur = TRUE;
							} else {
								$perso_nom = Security::FilterInput( $_POST['perso_nom'] );
							}
							// Valide que la cite_etat est bonne
							if( empty( $_POST['perso_cite_etat'] )
									|| !is_numeric( $_POST['perso_cite_etat'] )
									|| !array_key_exists( $_POST['perso_cite_etat'], $list_cites_etats ) ){
								Message::Erreur( "Vous n'avez choisi aucune cité-État pour ce personnage." );
								$erreur = TRUE;
							} else {
								$perso_cite_etat = $_POST['perso_cite_etat'];
							}
							if( $can_change_race ){
								// Valide que la race est bonne
								if( empty( $_POST['perso_race'] )
										|| !is_numeric( $_POST['perso_race'] )
										|| !array_key_exists( $_POST['perso_race'], $list_races ) ){
									Message::Erreur( "Vous n'avez choisi aucune race pour ce personnage." );
									$erreur = TRUE;
								} else {
									$perso_race = $_POST['perso_race'];
								}
							}
							// Valide que la croyance est bonne
							if( empty( $_POST['perso_croyance'] )
									|| !is_numeric( $_POST['perso_croyance'] )
									|| !array_key_exists( $_POST['perso_croyance'], $list_croyances ) ){
								Message::Erreur( "Vous n'avez choisi aucune croyance pour ce personnage." );
								$erreur = TRUE;
							} else {
								$perso_croyance = $_POST['perso_croyance'];
							}
						}
						
						// Ne fait pas la modification si l'un des champ est incorrect
						if( !$erreur ){
							// Ne fait la modification que si un des champ a change
							if( $perso_nom != $personnage->nom
									|| $perso_cite_etat != $personnage->cite_etat_id
									|| $perso_race != $personnage->race_id
									|| $perso_croyance != $personnage->croyance_id ){
								$personnage = $char_sheet->UpdateBases( $personnage->id, $perso_nom, $perso_race, $perso_cite_etat, $perso_croyance );
								
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite en enregistrant les informations d'identification du personnage." );
									$erreur = true;
								} else {
									Message::Notice( "Les informations d'identification du personnage ont été enregistrées." );
								}
							}
						}
					}
					
					// Alternativement, on peut aussi changer les points d'experience lorsque c'est permit
					if( $can_change_xp && isset( $_POST['change_xp'] ) && is_numeric( $_POST[ 'change_xp' ] )
							&& $_POST[ 'change_xp' ] >= $xp_change_min && $_POST[ 'change_xp' ] <= $xp_change_max){
						if( $char_sheet->ManageExperience( $personnage->id, $_POST[ 'change_xp' ], FALSE, TRUE, "Modification manuelle." ) ){
							Message::Notice( "Les points d'expérience du personnage ont été modifiés de " . $_POST['change_xp'] . "." );
						}
					}
					
					// Sauvegarde des selections de capacites raciales
					if( $_GET["st"] == "capacites_raciales" && isset( $_POST["perso_capacite_raciale"] ) ){
						if( is_numeric( $_POST["perso_capacite_raciale"] ) ){
							if( array_key_exists( $_POST["perso_capacite_raciale"], $list_capacites_raciales ) && !array_key_exists( $_POST["perso_capacite_raciale"], $personnage->capacites_raciales ) ){
								$personnage = $char_sheet->BuyCapaciteRaciale( $personnage->id, $_POST["perso_capacite_raciale"] );
								
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de l'ajout de la capacité raciale." );
								} else {
									Message::Notice( "La capacité raciale a été ajoutée." );
								}
							} else {
								Message::Erreur( "Le personnage possède déjà cette capacité raciale.", $_POST["perso_capacite_raciale"] );
							}
						}
					}
					
					// Sauvegarde des selections de choix de capacites
					if( $_GET["st"] == "choix_capacites" && isset( $_POST["perso_choix_capacites"] ) && count( $_POST["perso_choix_capacites"] ) > 0 ){
						// Bouclera sur chaque liste mais ne s'arretera pas sur celles qui ne retournent rien
						foreach( $_POST["perso_choix_capacites"] as $choix_capacites_list => $choix_capacites_selectionnee ){
							if( is_numeric( $choix_capacites_list )
									&& array_key_exists( $choix_capacites_list, $list_choix_capacites )
									&& array_key_exists( $choix_capacites_selectionnee, $list_choix_capacites[ $choix_capacites_list ] ) ){
								$personnage = $char_sheet->BuyChoixCapacite( $personnage->id, $choix_capacites_list, $choix_capacites_selectionnee );
						
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de la sélection de capacité." );
								} else {
									Message::Notice( "La capacité a été sélectionnée." );
								}
							}
						}
					}

					// Sauvegarde des selections de choix de capacités raciales
					if( $_GET["st"] == "choix_capacites_raciales" && isset( $_POST["perso_choix_capacites_raciales"] ) && count( $_POST["perso_choix_capacites_raciales"] ) > 0 ){
						// Bouclera sur chaque liste mais ne s'arretera pas sur celles qui ne retournent rien
						foreach( $_POST["perso_choix_capacites_raciales"] as $choix_capacites_raciales_list => $choix_capacites_raciales_selectionnee ){
							if( is_numeric( $choix_capacites_raciales_list )
									&& array_key_exists( $choix_capacites_raciales_list, $list_choix_capacites_raciales )
									&& array_key_exists( $choix_capacites_raciales_selectionnee, $list_choix_capacites_raciales[ $choix_capacites_raciales_list ] ) ){
								$personnage = $char_sheet->BuyChoixCapaciteRaciale( $personnage->id, $choix_capacites_raciales_list, $choix_capacites_raciales_selectionnee );
						
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de la sélection de capacité raciale." );
								} else {
									Message::Notice( "La capacité raciale a été sélectionnée." );
								}
							}
						}
					}

					// Sauvegarde des selections de choix de connaissances
					if( $_GET["st"] == "choix_connaissances" && isset( $_POST["perso_choix_connaissances"] ) && count( $_POST["perso_choix_connaissances"] ) > 0 ){
						// Bouclera sur chaque liste mais ne s'arretera pas sur celles qui ne retournent rien
						foreach( $_POST["perso_choix_connaissances"] as $choix_connaissances_list => $choix_connaissances_selectionnee ){
							if( is_numeric( $choix_connaissances_list )
									&& array_key_exists( $choix_connaissances_list, $list_choix_connaissances )
									&& array_key_exists( $choix_connaissances_selectionnee, $list_choix_connaissances[ $choix_connaissances_list ] ) ){
								$personnage = $char_sheet->BuyChoixConnaissance( $personnage->id, $choix_connaissances_list, $choix_connaissances_selectionnee );
						
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de la sélection de connaissance." );
								} else {
									Message::Notice( "La connaissance a été sélectionnée." );
								}
							}
						}
					}

					// Sauvegarde des selections de choix de voies
					if( $_GET["st"] == "choix_voies" && isset( $_POST["perso_choix_voies"] ) && count( $_POST["perso_choix_voies"] ) > 0 ){
						// Bouclera sur chaque liste mais ne s'arretera pas sur celles qui ne retournent rien
						foreach( $_POST["perso_choix_voies"] as $choix_voies_list => $choix_voies_selectionnee ){
							if( is_numeric( $choix_voies_list )
									&& array_key_exists( $choix_voies_list, $list_choix_voies )
									&& array_key_exists( $choix_voies_selectionnee, $list_choix_voies[ $choix_voies_list ] ) ){
								$personnage = $char_sheet->BuyChoixVoie( $personnage->id, $choix_voies_list, $choix_voies_selectionnee );
						
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de la sélection de voie." );
								} else {
									Message::Notice( "La voie a été sélectionnée." );
								}
							}
						}
					}
					
					// Activation du personnage
					if( $_GET["st"] == "activation"
							&& isset( $_POST["activate_character"] )
							&& $personnage->pc_raciales == 0
							&& !$has_choices ){
							
						if( !$char_sheet->Activate( $personnage->id ) ){
							Message::Erreur( "Une erreur s'est produite lors d'activation de ce personnage." );
						} else {
							Message::Notice( "Le personnage a été activé." );
						}
					}
					
					
					if( $personnage->est_cree ){
						// Sauvegarde de l'achat d'un point de capacite
						if( $_GET["st"] == "buy_capacite" ){
							if( isset( $_POST["perso_voie"] )
									&& is_numeric( $_POST["perso_voie"] )
									&& array_key_exists( $_POST["perso_voie"], $list_voies ) 
									&& !in_array( $_POST["perso_voie"], $personnage->voies ) ){
								$personnage = $char_sheet->BuyVoie( $personnage->id, $_POST["perso_voie"] );
								
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de l'ajout de la voie." );
								} else {
									Message::Notice( "La voie a été ajoutée." );
								}
							}
							
							if( isset( $_POST["perso_capacite"] ) ){
								foreach( $_POST["perso_capacite"] as $capacite_id => $new_score ){
									if( is_numeric( $capacite_id )
											&& is_numeric( $new_score )
											&& array_key_exists( $capacite_id, $personnage->capacites ) ){
										$diff = $new_score - $personnage->capacites[ $capacite_id ];
											
										if( $diff > 0 ){
											$personnage = $char_sheet->BuyCapacite( $personnage->id, $capacite_id, $diff );
											
											if( $personnage == FALSE ){
												Message::Erreur( "Une erreur s'est produite lors de l'augmentation de la capacité." );
											} else {
												Message::Notice( "La capacité a été augmentée." );
											}
										}
									}
								}
							}
						}
						
						// Sauvegarde de l'achat d'une connaissance
						if( $_GET["st"] == "buy_connaissance" ){
							if( isset( $_POST["perso_connaissance"] )
									&& is_numeric( $_POST["perso_connaissance"] )
									&& array_key_exists( $_POST["perso_connaissance"], $list_connaissances ) 
									&& !in_array( $_POST["perso_connaissance"], $personnage->connaissances ) ){
								$personnage = $char_sheet->BuyConnaissance( $personnage->id, $_POST["perso_connaissance"] );
								
								if( $personnage == FALSE ){
									Message::Erreur( "Une erreur s'est produite lors de l'ajout de la connaissance." );
								} else {
									Message::Notice( "La connaissance a été ajoutée." );
								}
							}
						}
					}
					
					// Retour en arriere
					if( $_GET["st"] == "rollback"
							&& isset( $_POST["perso_rollback_id"] )
							&& is_numeric( $_POST["perso_rollback_id"] )
							&& $_POST["perso_rollback_id"] == $last_log->Id
							&& $last_log->CanBacktrack ){
						if( $last_log->Quoi == CharacterSheet::RECORD_XP && !$is_administrateur ){
							Message::Erreur( "Le gain d'XP ne peut être annulé par un retour en arrière." );
						} else {
							$personnage = $char_sheet->RollBack( $personnage );
							if( $personnage == FALSE ){
								Message::Erreur( "Une erreur s'est produite lors du retour en arrière." );
							}
						}
					}
					
					if( $_GET["st"] == "kill" ){
						$personnage = $char_sheet->Deactivate( $personnage->id );
						if( $personnage == FALSE ){
							Message::Erreur( "Une erreur s'est produite lors de la désactivation." );
						}
					}
				}

				if( $_GET["st"] == "comment" && isset( $_POST["perso_save_comment"] ) && isset( $_POST["perso_comment"] ) ){				
					if( $can_add_comment ){
						if( !$char_sheet->SaveCommentaire( $personnage, Security::FilterInput( $_POST["perso_comment"] ) ) ){
							Message::Erreur( "Une erreur s'est produite en enregistrant le commentaire." );
						} else {
							Message::Notice( "Le commentaire a été enregistré." );
						}
					}
				}
				
				if( $_GET["st"] == "notes" && isset( $_POST["perso_save_notes"] ) && isset( $_POST["perso_notes"] ) ){
					if( $can_add_notes ){
						if( !$char_sheet->SaveNotes( $personnage, Security::FilterInput( $_POST["perso_notes"] ) ) ){
							Message::Erreur( "Une erreur s'est produite en enregistrant les notes." );
						} else {
							Message::Notice( "Les notes ont été enregistrées." );
						}
					}
				}
				
				if( $_GET["st"] == "delete" && isset( $_POST["perso_delete"] ) ){
					if( $can_destroy ){
						if( !$char_sheet->Destroy( $personnage->id ) ){
							Message::Erreur( "Une erreur s'est produite lors de la suppression." );
						} else {
							Message::Notice( "La suppression s'est déroulée avec succès." );
							header( "Location: ?s=player" );
							die();
						}
					} else {
						Message::Erreur( "Vous n'avez pas les droits de suppression sur ce personnage." );
					}
				}
				
				/*if( $_GET["st"] == "rebuild" && isset( $_POST["perso_rebuild"] ) ){
					if( $can_rebuild ){
						$personnage_id = $char_sheet->RebuildComplet( $personnage->id );
						if( $personnage_id == FALSE ){
							Message::Erreur( "Une erreur s'est produite lors du rebuild." );
						} else {
							$character_id = $personnage_id;
							Message::Notice( "Le rebuild s'est déroulé avec succès." );
						}
					} else {
						Message::Erreur( "Vous n'avez pas les droits de rebuild sur ce personnage." );
					}
				}
				
				if( $_GET["st"] == "rebuild_perte" && isset( $_POST["perso_rebuild_perte"] ) ){
					if( $can_rebuild_perte ){
						$personnage_id = $char_sheet->RebuildPerte( $personnage->id );
						if( $personnage_id == FALSE ){
							Message::Erreur( "Une erreur s'est produite lors du rebuild à perte." );
						} else {
							$character_id = $personnage_id;
							Message::Notice( "Le rebuild à perte s'est déroulé avec succès." );
						}
					} else {
						Message::Erreur( "Vous n'avez pas les droits de rebuild à perte sur ce personnage." );
					}
				}
				*/
				
				header( "Location: ?s=player&a=characterUpdate&c=" . $character_id );
				die();
			}
		} else {
			Message::Erreur( "Personnage inexistant." );
			header( "Location: ?s=player" );
			die();
		}
	} else {
		Message::Fatale( "L'identifiant de personnage doit être numérique." );
	}
	
	include "./views/top.php";
	include "./views/p/characterUpdate.php";
	include "./views/bottom.php";
?>
<?php
	class PersonnageRepository implements IRepository {
		private $_db = FALSE;
		
		public function __construct(){
			$this->_db = new Database();
		}

		public function Find( $i ){ die("USE ROSTER." ); }
		
		public function Create( array $opts = array() ){
			if( !array_key_exists( "player_id", $opts ) || !is_numeric( $opts[ "player_id" ] ) ){
				Message::Fatale( "Création impossible : Le joueur n'a pu être enregistré.", func_get_args() );
				return FALSE;
			}
			if( !array_key_exists( "nom", $opts ) || $opts[ "nom" ] == "" ){
				Message::Fatale( "Création impossible : Le nom n'a pu être enregistré.", func_get_args() );
				return FALSE;
			}
			if( !array_key_exists( "race_id", $opts ) || $opts[ "race_id" ] != "" && !is_numeric( $opts[ "race_id" ] ) ){
				Message::Fatale( "Création impossible : La race n'a pu être enregistrée.", func_get_args() );
				return FALSE;
			}
			if( !array_key_exists( "race_secondaire_id", $opts ) || $opts[ "race_id" ] != "" && !is_numeric( $opts[ "race_id" ] ) ){
				Message::Fatale( "Création impossible : La race secondaire n'a pu être enregistrée.", func_get_args() );
				return FALSE;
			}
			if( !array_key_exists( "cite_etat_id", $opts ) || $opts[ "cite_etat_id" ] != "" && !is_numeric( $opts[ "cite_etat_id" ] ) ){
				Message::Fatale( "Création impossible : La cité-État n'a pu être enregistrée.", func_get_args() );
				return FALSE;
			}
			if( !array_key_exists( "croyance_id", $opts ) || !is_numeric( $opts[ "croyance_id" ] ) ){
				Message::Fatale( "Création impossible : La croyance n'a pu être enregistrée.", func_get_args() );
				return FALSE;
			}
			
			// Insertion
			$sql = "INSERT INTO personnage (
							joueur, nom, race_id, race_secondaire_id, cite_etat_id, croyance_id,
							point_capacite_raciale, point_experience, total_experience,
							commentaire, notes )
						VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
			$params = array(
					$opts[ "player_id" ],
					$opts[ "nom" ],
					$opts[ "race_id" ],
					( $opts[ "race_secondaire_id" ] != 0 ) ? $opts[ "race_secondaire_id" ] : NULL,
					$opts[ "cite_etat_id" ],
					$opts[ "croyance_id" ],
					CHARACTER_BASE_PCR,
					CHARACTER_BASE_XP,
					CHARACTER_BASE_XP,
					CHARACTER_DEFAULT_COMMENTS,
					CHARACTER_DEFAULT_NOTES
			);
			
			$this->_db->Query( $sql, $params );
			$insert_id = $this->_db->GetInsertId();
			
			$character = Roster::GetCharacterComplete( $insert_id );
			$error_occured = FALSE;
			if( $character ){
				// Ajout des bases obtenus automatiquement par tous les personnages
				$automatics = json_decode( CHARACTER_BASE_AUTOMATICS, true );
				if( array_key_exists( "voies", $automatics ) ){
					foreach( $automatics[ "voies" ] as $id_voie ){
						if( $this->AddVoie( $character, $id_voie ) == FALSE ){
							Message::Erreur( "CREATION : Une erreur s'est produite lors de l'ajout de la voie #" . $id_voie );
							$error_occured = TRUE;
						}
					}
				}
				if( array_key_exists( "capacites", $automatics ) ){
					foreach( $automatics[ "capacites" ] as $id_capacite ){
						if( $this->AddCapacite( $character, $id_capacite, 1, true ) == FALSE ){
							Message::Erreur( "CREATION : Une erreur s'est produite lors de l'ajout de la capacité #" . $id_capacite );
							$error_occured = TRUE;
						}
					}
				}
				if( array_key_exists( "connaissances", $automatics ) ){
					foreach( $automatics[ "connaissances" ] as $id_connaissance ){
						if( $this->AddConnaissance( $character, $id_connaissance, true ) == FALSE ){
							Message::Erreur( "CREATION : Une erreur s'est produite lors de l'ajout de la connaissance #" . $id_connaissance );
							$error_occured = TRUE;
						}
					}
				}
				if( array_key_exists( "capacites_raciales", $automatics ) ){
					foreach( $automatics[ "capacites_raciales" ] as $id_pouvoir ){
						if( $this->AddCapaciteRaciale( $character, $id_pouvoir, true ) == FALSE ){
							Message::Erreur( "CREATION : Une erreur s'est produite lors de l'ajout du pouvoir #" . $id_pouvoir );
							$error_occured = TRUE;
						}
					}
				}
			}
			
			if( !$character || $error_occured ){
				$this->Delete( $insert_id );
				return FALSE;
			}
			
			return $character;
		}
		
		public function Save( GenericEntity $personnage ){
			$sql = "UPDATE personnage SET
					joueur = ?,
					nom = ?,
					race_id = ?,
					croyance_id = ?,
					cite_etat_id = ?,
					point_capacite_raciale = ?,
					point_experience = ?,
					total_experience = ?,
					est_vivant = ?,
					est_cree = ?,
					commentaire = ?,
					notes = ?
				WHERE est_detruit = '0' AND id = ?";
			$params = array(
					$personnage->joueur_id,
					$personnage->nom,
					$personnage->race_id,
					$personnage->croyance_id,
					$personnage->cite_etat_id,
					$personnage->pc_raciales,
					$personnage->px_restants,
					$personnage->px_totaux,
					$personnage->est_vivant ? 1 : 0,
					$personnage->est_cree ? 1 : 0,
					$personnage->commentaire,
					$personnage->notes,
					$personnage->id
			);
			
			$this->_db->Query( $sql, $params );
			$personnage = Roster::GetCharacter( $personnage->id );
			
			return $personnage != FALSE;
		}
		
		public function Activate( Personnage &$personnage ){
			if( $personnage->est_cree == FALSE ){
				$personnage->est_cree = TRUE;
				
				return $this->Save( $personnage );
			}
			
			return FALSE;
		}
		
		public function Deactivate( Personnage &$personnage ){
			if( $personnage->est_vivant == TRUE ){
				$personnage->est_vivant = FALSE;
				
				return $this->Save( $personnage );
			}
			
			return FALSE;
		}
		
		public function Delete( int $id ){
			if( is_numeric( $id ) ){
				$sql = "UPDATE personnage SET est_detruit = '1' WHERE id = ?";
				$this->_db->Query( $sql, array( $id ) );
				
				return TRUE;
			}
			
			return FALSE;
		}
		
		public function BuyCapaciteRaciale( Personnage &$personnage, $id_capacite_raciale ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				$race_repository = new RaceRepository();
				$list_capacites_raciales = $race_repository->GetCapacitesRacialesByRace( $personnage->race_id );
				if( array_key_exists( $id_capacite_raciale, $list_capacites_raciales )
						&& $list_capacites_raciales[ $id_capacite_raciale ][ 1 ] <= $personnage->pc_raciales
						&& $this->AddCapaciteRaciale( $personnage, $id_capacite_raciale ) ){
					$personnage->pc_raciales -= $list_capacites_raciales[ $id_capacite_raciale ][ 1 ];
					return $this->Save( $personnage );
				}
			}
			
			return FALSE;
		}

		public function RefundCapaciteRaciale( Personnage &$personnage, $id_capacite_raciale, $cout = FALSE ){
			if( $personnage
					&& $personnage->est_vivant
					&& $personnage->est_complet
					&& array_key_exists( $id_capacite_raciale, $personnage->capacites_raciales )
					&& $this->RemoveCapaciteRaciale( $personnage, $id_capacite_raciale ) ){
				// Si l'ancien cout a ete fourni, on l'utilise
				if( $cout !== FALSE ){
					$personnage->pc_raciales += $cout;
				// Sinon, on prend celui de la race
				} else {
					$race_repository = new RaceRepository();
					$list_capacites_raciales = $race_repository->GetCapacitesRacialesByRace( $personnage->race_id );
					$personnage->pc_raciales -= $list_capacites_raciales[ $id_capacite_raciale ][ 1 ];
				}
				return $this->Save( $personnage );
			}
			
			return FALSE;
		}

		private function AddCapaciteRaciale( Personnage $personnage, $capacite_raciale_id, $force = FALSE ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				$crr = new CapaciteRacialeRepository();
				$capacite_raciale = $crr->Find( $capacite_raciale_id );

				if( $capacite_raciale == FALSE ){
					Message::Fatale( "Capacité raciale invalide.", func_get_args() );
					return FALSE;
				} elseif( $personnage->race_id != $capacite_raciale->race_id && !$force ) {
					Message::Fatale( "La race de la capacité raciale ne correspond pas à la race du personnage.", func_get_args() );
					return FALSE;
				}
				
				if( !in_array( $capacite_raciale_id, $personnage->capacites_raciales ) ){
					$sql = "INSERT INTO personnage_capacite_raciale ( personnage_id, capacite_raciale_id )
							VALUES ( ?, ? )";
					$this->_db->Query( $sql, array( $personnage->id, $capacite_raciale_id ) );

					if( $capacite_raciale->choix_capacite_bonus_id != 0 ){
						if( $this->AddChoixCapacite( $personnage, $capacite_raciale->choix_capacite_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors de l'ajout du choix de capacité " . $capacite_raciale->choix_capacite_bonus_id );
							return FALSE;
						}
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_capacite_repository = new ChoixCapaciteRepository();
						$liste_capacites = $choix_capacite_repository->GetCapacitesByChoixId( $capacite_raciale->choix_capacite_bonus_id );
						if( count( $liste_capacites ) == 1 ){
							$this->BuyChoixCapacite( $personnage, $capacite_raciale->choix_capacite_bonus_id, array_key_first( $liste_capacites ) );
						}
					}
					if( $capacite_raciale->choix_capacite_raciale_bonus_id != 0 ){
						if( $this->AddChoixCapaciteRaciale( $personnage, $capacite_raciale->choix_capacite_raciale_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors de l'ajout du choix de capacité raciale " . $capacite_raciale->choix_capacite_raciale_bonus_id );
							return FALSE;
						}
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
						$liste_capacites_raciales = $choix_capacite_raciale_repository->GetCapacitesRacialesByChoixId( $capacite_raciale->choix_capacite_raciale_bonus_id );
						if( count( $liste_capacites_raciales ) == 1 ){
							$this->BuyChoixCapacitesRaciales( $personnage, $capacite_raciale->choix_capacite_raciale_bonus_id, array_key_first( $liste_capacites_raciales ) );
						}
					}
					if( $capacite_raciale->choix_connaissance_bonus_id != 0 ){
						if( $this->AddChoixConnaissance( $personnage, $capacite_raciale->choix_connaissance_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors de l'ajout du choix de connaissance " . $capacite_raciale->choix_connaissance_bonus_id );
							return FALSE;
						}
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_connaissance_repository = new ChoixConnaissanceRepository();
						$liste_connaissances = $choix_connaissance_repository->GetConnaissancesByChoixId( $capacite_raciale->choix_connaissance_bonus_id );
						if( count( $liste_connaissances ) == 1 ){
							$this->BuyChoixConnaissance( $personnage, $capacite_raciale->choix_connaissance_bonus_id, array_key_first( $liste_connaissances ) );
						}
					}
					if( $capacite_raciale->choix_voie_bonus_id != 0 ){
						if( $this->AddChoixVoie( $personnage, $capacite_raciale->choix_voie_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors de l'ajout du choix de voie " . $capacite_raciale->choix_voie_bonus_id );
							return FALSE;
						}
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_voie_repository = new ChoixVoieRepository();
						$liste_voies = $choix_voie_repository->GetVoiesByChoixId( $capacite_raciale->choix_voie_bonus_id );
						if( count( $liste_voies ) == 1 ){
							$this->BuyChoixVoie( $personnage, $capacite_raciale->choix_voie_bonus_id, array_key_first( $liste_voies ) );
						}
					}

					$personnage = Roster::GetCharacter( $personnage->id );
					return $personnage != FALSE;
				}
			}
			
			return FALSE;
		}

		private function RemoveCapaciteRaciale( Personnage $personnage, $capacite_raciale_id ){
			if( $personnage && $personnage->est_vivant ){
				$crr = new CapaciteRacialeRepository();
				$capacite_raciale = $crr->Find( $capacite_raciale_id );

				if( $capacite_raciale == FALSE ){
					Message::Fatale( "Capacité raciale invalide.", func_get_args() );
					return FALSE;
				}
				
				if( !in_array( $capacite_raciale_id, $personnage->capacites_raciales ) ){
					$sql = "DELETE FROM personnage_capacite_raciale
							WHERE personnage_id = ? AND capacite_raciale_id = ?";
					$this->_db->Query( $sql, array( $personnage->id, $capacite_raciale_id ) );

					if( $capacite_raciale->choix_capacite_bonus_id != 0 ){
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_capacite_repository = new ChoixCapaciteRepository();
						$liste_capacites = $choix_capacite_repository->GetCapacitesByChoixId( $capacite_raciale->choix_capacite_bonus_id );
						if( count( $liste_capacites ) == 1 ){
							$this->RefundChoixCapacite( $personnage, $capacite_raciale->choix_capacite_bonus_id, array_key_first( $liste_capacites ) );
						}

						if( $this->RemoveChoixCapacite( $personnage, $capacite_raciale->choix_capacite_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors du retrait du choix de capacité " . $capacite_raciale->choix_capacite_bonus_id );
							return FALSE;
						}
					}
					if( $capacite_raciale->choix_capacite_raciale_bonus_id != 0 ){
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
						$liste_capacites_raciales = $choix_capacite_raciale_repository->GetCapacitesRacialesByChoixId( $capacite_raciale->choix_capacite_raciale_bonus_id );
						if( count( $liste_capacites_raciales ) == 1 ){
							$this->RefundChoixCapaciteRaciale( $personnage, $capacite_raciale->choix_capacite_raciale_bonus_id, array_key_first( $liste_capacites_raciales ) );
						}

						if( $this->RemoveChoixCapaciteRaciale( $personnage, $capacite_raciale->choix_capacite_raciale_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors du retrait du choix de capacité raciale " . $capacite_raciale->choix_capacite_raciale_bonus_id );
							return FALSE;
						}
					}
					if( $capacite_raciale->choix_connaissance_bonus_id != 0 ){
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_connaissance_repository = new ChoixConnaissanceRepository();
						$liste_connaissances = $choix_connaissance_repository->GetConnaissancesByChoixId( $capacite_raciale->choix_connaissance_bonus_id );
						if( count( $liste_connaissances ) == 1 ){
							$this->RefundChoixConnaissance( $personnage, $capacite_raciale->choix_connaissance_bonus_id, array_key_first( $liste_connaissances ) );
						}

						if( $this->RemoveChoixConnaissance( $personnage, $capacite_raciale->choix_connaissance_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors du retrait du choix de connaissance " . $capacite_raciale->choix_connaissance_bonus_id );
							return FALSE;
						}
					}
					if( $capacite_raciale->choix_voie_bonus_id != 0 ){
						// Si la liste est d'exactement 1, ajoute l'élément automatiquement
						$choix_voie_repository = new ChoixVoieRepository();
						$liste_voies = $choix_voie_repository->GetVoiesByChoixId( $capacite_raciale->choix_voie_bonus_id );
						if( count( $liste_voies ) == 1 ){
							$this->RefundChoixVoie( $personnage, $capacite_raciale->choix_voie_bonus_id, array_key_first( $liste_voies ) );
						}

						if( $this->RemoveChoixVoie( $personnage, $capacite_raciale->choix_voie_bonus_id ) == FALSE ){
							Message::Erreur( "CAPACITÉ RACIALE : Une erreur s'est produite lors du retrait du choix de voie " . $capacite_raciale->choix_voie_bonus_id );
							return FALSE;
						}
					}
					
					$personnage = Roster::GetCharacter( $personnage->id );
					return $personnage != FALSE;
				}
			}
			
			return FALSE;
		}

		private function AddChoixCapacite( Personnage &$personnage, $choix_capacite_id ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetChoixCapacites( $choix_capacite_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité invalide.", func_get_args() );
					return FALSE;
				}
				if( array_key_exists( $choix_capacite_id, $personnage->choix_capacites ) ){
					Message::Fatale( "Le choix de capacité est déjà présent." );
					return FALSE;
				}

				$sql = "INSERT INTO personnage_choix_capacite ( personnage_id, choix_capacite_id )
							VALUES ( ?, ? )";
				$this->_db->Query( $sql, array( $personnage->id, $choix_capacite_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}

		public function BuyChoixCapacite( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixCapacites( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité invalide.", func_get_args() );
					return FALSE;
				}
				
				if( array_key_exists( $list_choix_id, $personnage->choix_capacites ) ){
					$choix_capacite_repository = new ChoixCapaciteRepository();
					$choix_capacite = $choix_capacite_repository->Find( $list_choix_id );
					if( $choix_capacite && $choix_capacite->active ){
						$liste_capacites = $choix_capacite_repository->GetCapacites( $choix_capacite );
						if( array_key_exists( $choix_id, $liste_capacites ) ){
							if( $this->AddCapacite( $personnage, $choix_id, 1, true ) ){
								if( $this->RemoveChoixCapacite( $personnage, $list_choix_id ) == FALSE ){
									$this->RemoveCapacite( $personnage, $choix_id, 1 );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}
		
		private function RemoveChoixCapacite( Personnage &$personnage, $choix_capacite_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixCapacites( $choix_capacite_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité invalide.", func_get_args() );
					return FALSE;
				}
				
				if( !array_key_exists( $choix_capacite_id, $personnage->choix_capacites ) ){
					Message::Fatale( "Le personnage n'a pas ce choix de capacité à retirer.", func_get_args() );
					return FALSE;
				}

				$sql = "DELETE FROM personnage_choix_capacite
						WHERE personnage_id = ? AND choix_capacite_id = ?";
				$this->_db->Query( $sql, array( $personnage->id, $choix_capacite_id ) );

				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}
		
		public function RefundChoixCapacite( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixCapacites( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité invalide.", func_get_args() );
					return FALSE;
				}
				
				if( array_key_exists( $choix_id, $personnage->capacites ) ){
					$choix_capacite_repository = new ChoixCapaciteRepository();
					$choix_capacite = $choix_capacite_repository->Find( $list_choix_id );
					if( $choix_capacite ){
						$liste_capacites = $choix_capacite_repository->GetCapacites( $choix_capacite );
						if( array_key_exists( $choix_id, $liste_capacites ) ){
							if( $this->RemoveCapacite( $personnage, $choix_id, 1 ) ){
								if( $this->AddChoixCapacite( $personnage, $list_choix_id ) == FALSE ){
									$this->AddCapacite( $personnage, $choix_id, 1, true );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}

		public function AddCapacite( Personnage &$personnage, $capacite_id, $nb_selections, $force = FALSE ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetCapacites( $capacite_id ) ) == 0 ){
					Message::Fatale( "Identifiant de capacité invalide.", func_get_args() );
					return FALSE;
				}
				if( !is_numeric( $nb_selections )
						|| $nb_selections <= 0
						|| ( array_key_exists( $capacite_id, $personnage->capacites ) && ( $nb_selections + $personnage->capacites[ $capacite_id ] ) > CHARACTER_MAX_SELECTION_COUNT ) ){
					Message::Fatale( "Nombre de sélections de capacités à ajouter invalide." );
					return FALSE;
				}

				if( $force || $this->HasPrerequisCapacite( $personnage, $capacite_id ) ){
					$is_premiere_selection = false;

					if( !array_key_exists( $capacite_id, $personnage->capacites ) || $personnage->capacites[ $capacite_id ] == 0 ){
						$sql = "INSERT INTO personnage_capacite( niveau, personnage_id, capacite_id )
								VALUES ( ?, ?, ? )";
						$is_premiere_selection = true;
					} else {
						$sql = "UPDATE personnage_capacite
								SET niveau = ( niveau * 1 ) + ?
								WHERE personnage_id = ? AND capacite_id = ?";
					}
					$this->_db->Query( $sql, array( $nb_selections, $personnage->id, $capacite_id ) );
					$personnage = Roster::GetCharacterComplete( $personnage->id );

					$error_occured = FALSE;
					if( $is_premiere_selection ){
						// Ajout des connaissances obtenues en sélectionnant la capacité pour la première fois
						$automatics = json_decode( CHARACTER_BONUS_CHOIX_CONN_VIA_CAPACITE, true );
						if( array_key_exists( $capacite_id, $automatics ) ){
							foreach( $automatics[ $capacite_id ] as $choix_connaissance_id ){
								if( $this->AddChoixConnaissance( $personnage, $choix_connaissance_id ) == FALSE ){
									Message::Erreur( "AJOUT DE CAPACITÉ : Une erreur s'est produite lors de l'ajout du choix de connaissance #" . $choix_connaissance_id );
									$error_occured = TRUE;
									break;
								}
							}
						}
					}
					
					return $error_occured == FALSE;
				}
			}
			
			return FALSE;
		}
		
		public function RemoveCapacite( Personnage &$personnage, $id_capacite, $nb_selections ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetCapacites( $id_capacite ) ) == 0 ){
					Message::Fatale( "Identifiant de capacité invalide.", func_get_args() );
					return FALSE;
				}
				if( !array_key_exists( $id_capacite, $personnage->capacites ) || $personnage->capacites[ $id_capacite ] == 0 ){
					Message::Fatale( "Le personnage doit posséder la capacité à retirer.", func_get_args() );
					return FALSE;
				}
				
				if( is_numeric( $nb_selections )
						&& $nb_selections <= $personnage->capacites[ $id_capacite ] ){
					$is_premiere_selection = false;

					if( $personnage->capacites[ $id_capacite ] == $nb_selections ){
						$sql = "DELETE FROM personnage_capacite
								WHERE niveau = ?
									AND personnage_id = ?
									AND capacite_id = ?";
						$is_premiere_selection = true;
					} else {
						$sql = "UPDATE personnage_capacite
								SET niveau = ( niveau * 1 ) - ?
								WHERE personnage_id = ? AND capacite_id = ?";
					}
					$this->_db->Query( $sql, array( $nb_selections, $personnage->id, $id_capacite ) );
					$personnage = Roster::GetCharacterComplete( $personnage->id );

					$error_occured = FALSE;
					if( $is_premiere_selection ){
						// Ajout des connaissances obtenues en sélectionnant la capacité pour la première fois
						$automatics = json_decode( CHARACTER_BONUS_CHOIX_CONN_VIA_CAPACITE, true );
						if( array_key_exists( $id_capacite, $automatics ) ){
							foreach( $automatics[ $id_capacite ] as $choix_connaissance_id ){
								if( $this->RemoveChoixConnaissance( $personnage, $choix_connaissance_id ) == FALSE ){
									Message::Erreur( "RETRAIT DE CAPACITÉ : Une erreur s'est produite lors du retrait du choix de connaissance #" . $choix_connaissance_id );
									$error_occured = TRUE;
									break;
								}
							}
						}
					}
					
					return $error_occured == FALSE;
				}
			}
			
			return FALSE;
		}

		private function AddChoixCapaciteRaciale( Personnage &$personnage, $choix_capacite_raciale_id ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetChoixCapacitesRaciales( $choix_capacite_raciale_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité raciale invalide.", func_get_args() );
					return FALSE;
				}
				if( array_key_exists( $choix_capacite_raciale_id, $personnage->choix_capacites_raciales ) ){
					Message::Fatale( "Le choix de capacité raciale est déjà présent." );
					return FALSE;
				}

				$sql = "INSERT INTO personnage_choix_capacite_raciale ( personnage_id, choix_capacite_raciale_id )
							VALUES ( ?, ? )";
				$this->_db->Query( $sql, array( $personnage->id, $choix_capacite_raciale_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}

		public function BuyChoixCapaciteRaciale( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixCapacitesRaciales( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité raciale invalide.", func_get_args() );
					return FALSE;
				}
				
				if( array_key_exists( $list_choix_id, $personnage->choix_capacites_raciales ) ){
					$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
					$choix_capacite_raciale = $choix_capacite_raciale_repository->Find( $list_choix_id );
					if( $choix_capacite_raciale && $choix_capacite_raciale->active ){
						$liste_capacites_raciales = $choix_capacite_raciale_repository->GetCapacitesRaciales( $choix_capacite_raciale );
						if( array_key_exists( $choix_id, $liste_capacites_raciales ) ){
							if( $this->AddCapaciteRaciale( $personnage, $choix_id, true ) ){
								if( $this->RemoveChoixCapaciteRaciale( $personnage, $list_choix_id ) == FALSE ){
									$this->RemoveCapaciteRaciale( $personnage, $choix_id );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}
		
		private function RemoveChoixCapaciteRaciale( Personnage &$personnage, $choix_capacite_raciale_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixCapacitesRaciales( $choix_capacite_raciale_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité raciale invalide.", func_get_args() );
					return FALSE;
				}
				
				if( !array_key_exists( $choix_capacite_raciale_id, $personnage->choix_capacites_raciales ) ){
					Message::Fatale( "Le personnage n'a pas ce choix de capacité raciale à retirer.", func_get_args() );
					return FALSE;
				}
				
				$sql = "DELETE FROM personnage_choix_capacite_raciale
						WHERE personnage_id = ? AND choix_capacite_raciale_id = ?";
				$this->_db->Query( $sql, array( $personnage->id, $choix_capacite_raciale_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}
		
		public function RefundChoixCapaciteRaciale( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixCapacitesRaciales( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de capacité raciale invalide.", func_get_args() );
					return FALSE;
				}
				
				if( array_key_exists( $choix_id, $personnage->capacites_raciales ) ){
					$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
					$choix_capacite_raciale = $choix_capacite_raciale_repository->Find( $list_choix_id );
					if( $choix_capacite_raciale ){
						$liste_capacites_raciales = $choix_capacite_raciale_repository->GetCapacitesRaciales( $choix_capacite_raciale );
						if( array_key_exists( $choix_id, $liste_capacites_raciales ) ){
							if( $this->RemoveCapaciteRaciale( $personnage, $choix_id ) ){
								if( $this->AddChoixCapaciteRaciale( $personnage, $list_choix_id ) == FALSE ){
									$this->AddCapaciteRaciale( $personnage, $choix_id, true );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}

		private function AddChoixConnaissance( Personnage &$personnage, $choix_connaissance_id ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetChoixConnaissances( $choix_connaissance_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de connaissance invalide.", func_get_args() );
					return FALSE;
				}
				if( array_key_exists( $choix_connaissance_id, $personnage->choix_connaissances ) ){
					Message::Fatale( "Le choix de connaissance est déjà présent." );
					return FALSE;
				}

				$sql = "INSERT INTO personnage_choix_connaissance ( personnage_id, choix_connaissance_id )
							VALUES ( ?, ? )";
				$this->_db->Query( $sql, array( $personnage->id, $choix_connaissance_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}

		public function BuyChoixConnaissance( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixConnaissances( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de connaissance invalide.", func_get_args() );
					return FALSE;
				}
				
				if( array_key_exists( $list_choix_id, $personnage->choix_connaissances ) ){
					$choix_connaissance_repository = new ChoixConnaissanceRepository();
					$choix_connaissance = $choix_connaissance_repository->Find( $list_choix_id );
					if( $choix_connaissance && $choix_connaissance->active ){
						$liste_connaissances = $choix_connaissance_repository->GetConnaissances( $choix_connaissance );
						if( array_key_exists( $choix_id, $liste_connaissances ) ){
							if( $this->AddConnaissance( $personnage, $choix_id, true ) ){
								if( $this->RemoveChoixConnaissance( $personnage, $list_choix_id ) == FALSE ){
									$this->RemoveConnaissance( $personnage, $choix_id );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}
		
		private function RemoveChoixConnaissance( Personnage &$personnage, $choix_connaissance_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixConnaissances( $choix_connaissance_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de connaissance invalide.", func_get_args() );
					return FALSE;
				}
				
				if( !array_key_exists( $choix_connaissance_id, $personnage->choix_connaissances ) ){
					Message::Fatale( "Le personnage n'a pas ce choix de connaissance à retirer.", func_get_args() );
					return FALSE;
				}
				
				$sql = "DELETE FROM personnage_choix_connaissance
						WHERE personnage_id = ? AND choix_connaissance_id = ?";
				$this->_db->Query( $sql, array( $personnage->id, $choix_connaissance_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}
		
		public function RefundChoixConnaissance( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixConnaissances( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de connaissance invalide.", func_get_args() );
					return FALSE;
				}
				
				if( in_array( $choix_id, $personnage->connaissances ) ){
					$choix_connaissance_repository = new ChoixConnaissanceRepository();
					$choix_connaissance = $choix_connaissance_repository->Find( $list_choix_id );
					if( $choix_connaissance ){
						$liste_connaissances = $choix_connaissance_repository->GetConnaissances( $choix_connaissance );
						if( array_key_exists( $choix_id, $liste_connaissances ) ){
							if( $this->RemoveConnaissance( $personnage, $choix_id ) ){
								if( $this->AddChoixConnaissance( $personnage, $list_choix_id ) == FALSE ){
									$this->AddConnaissance( $personnage, $choix_id, true );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}

		public function AddConnaissance( Personnage &$personnage, $connaissance_id, $force = FALSE ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetConnaissances( $connaissance_id ) ) == 0 ){
					Message::Fatale( "Identifiant de connaissance invalide.", func_get_args() );
					return FALSE;
				}

				if( $force || $this->HasPrerequisConnaissance( $personnage, $connaissance_id ) ){
					$sql = "INSERT INTO personnage_connaissance( personnage_id, connaissance_id )
							VALUES ( ?, ? )";
					$this->_db->Query( $sql, array( $personnage->id, $connaissance_id ) );
						
					$personnage = Roster::GetCharacterComplete( $personnage->id );
					return $personnage != FALSE;
				}
			}
			
			return FALSE;
		}
		
		public function RemoveConnaissance( Personnage &$personnage, $id_connaissance ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetConnaissances( $id_connaissance ) ) == 0 ){
					Message::Fatale( "Identifiant de connaissance invalide.", func_get_args() );
					return FALSE;
				}
				
				$sql = "DELETE FROM personnage_connaissance
							WHERE personnage_id = ? AND connaissance_id = ?";
				$this->_db->Query( $sql, array( $personnage->id, $id_connaissance ) );
						
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}

		private function AddChoixVoie( Personnage &$personnage, $choix_voie_id ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetChoixVoies( $choix_voie_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de voie invalide.", func_get_args() );
					return FALSE;
				}
				if( array_key_exists( $choix_voie_id, $personnage->choix_voies ) ){
					Message::Fatale( "Le choix de voie est déjà présent." );
					return FALSE;
				}

				$sql = "INSERT INTO personnage_choix_voie ( personnage_id, choix_voie_id )
							VALUES ( ?, ? )";
				$this->_db->Query( $sql, array( $personnage->id, $choix_voie_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}

		public function BuyChoixVoie( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixVoies( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de voie invalide.", func_get_args() );
					return FALSE;
				}
				
				if( array_key_exists( $list_choix_id, $personnage->choix_voies ) ){
					$choix_voie_repository = new ChoixVoieRepository();
					$choix_voie = $choix_voie_repository->Find( $list_choix_id );
					if( $choix_voie && $choix_voie->active ){
						$liste_voies = $choix_voie_repository->GetVoies( $choix_voie );
						if( array_key_exists( $choix_id, $liste_voies ) ){
							if( $this->AddVoie( $personnage, $choix_id ) ){
								if( $this->RemoveChoixVoie( $personnage, $list_choix_id ) == FALSE ){
									$this->RemoveVoie( $personnage, $choix_id );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}
		
		private function RemoveChoixVoie( Personnage &$personnage, $choix_voie_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixVoies( $choix_voie_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de voie invalide.", func_get_args() );
					return FALSE;
				}
				
				if( !array_key_exists( $choix_voie_id, $personnage->choix_voies ) ){
					Message::Fatale( "Le personnage n'a pas ce choix de voie à retirer.", func_get_args() );
					return FALSE;
				}
				
				$sql = "DELETE FROM personnage_choix_voie
						WHERE personnage_id = ? AND choix_voie_id = ?";
				$this->_db->Query( $sql, array( $personnage->id, $choix_voie_id ) );
				
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}
		
		public function RefundChoixVoie( Personnage &$personnage, $list_choix_id, $choix_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetChoixVoies( $list_choix_id ) ) == 0 ){
					Message::Fatale( "Identifiant de choix de voie invalide.", func_get_args() );
					return FALSE;
				}
				
				if( in_array( $choix_id, $personnage->voies ) ){
					$choix_voie_repository = new ChoixVoieRepository();
					$choix_voie = $choix_voie_repository->Find( $list_choix_id );
					if( $choix_voie ){
						$liste_voies = $choix_voie_repository->GetVoies( $choix_voie );
						if( array_key_exists( $choix_id, $liste_voies ) ){
							if( $this->RemoveVoie( $personnage, $choix_id ) ){
								if( $this->AddChoixVoie( $personnage, $list_choix_id ) == FALSE ){
									$this->AddVoie( $personnage, $choix_id );
									return FALSE;
								}
								
								$personnage = Roster::GetCharacterComplete( $personnage->id );
								return $personnage != FALSE;
							}
						}
					}
				}	
			}
			
			return FALSE;
		}

		public function AddVoie( Personnage &$personnage, $voie_id ){
			if( $personnage && $personnage->est_vivant && $personnage->est_complet ){
				if( count( Dictionary::GetVoies( $voie_id ) ) == 0 ){
					Message::Fatale( "Identifiant de voie invalide.", func_get_args() );
					return FALSE;
				}

				$sql = "INSERT INTO personnage_voie( personnage_id, voie_id )
						VALUES ( ?, ? )";
				$this->_db->Query( $sql, array( $personnage->id, $voie_id ) );
					
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}
		
		public function RemoveVoie( Personnage &$personnage, $id_voie ){
			if( $personnage && $personnage->est_vivant ){
				if( count( Dictionary::GetVoies( $id_voie ) ) == 0 ){
					Message::Fatale( "Identifiant de voie invalide.", func_get_args() );
					return FALSE;
				}
				
				$sql = "DELETE FROM personnage_voie
							WHERE personnage_id = ? AND voie_id = ?";
				$this->_db->Query( $sql, array( $personnage->id, $id_voie ) );
						
				$personnage = Roster::GetCharacterComplete( $personnage->id );
				return $personnage != FALSE;
			}
			
			return FALSE;
		}
		
		private function HasPrerequisCapacite( Personnage &$personnage, $id_capacite ){
			if( $personnage ){
				$capacite_repository = new CapaciteRepository();
				$capacite = $capacite_repository->Find( $id_capacite );
				
				return $capacite !== FALSE && $capacite->active
						&& in_array( $capacite->voie_id, $personnage->voies );
			}
			return FALSE;
		}

		private function HasPrerequisConnaissance( Personnage &$personnage, $id_connaissance ){
			if( $personnage ){
				$connaissance_repository = new ConnaissanceRepository();
				$connaissance = $connaissance_repository->Find( $id_connaissance );
				
				return $connaissance !== FALSE && $connaissance->active
						&& $connaissance->cout <= $personnage->GetRealCurrentXP();
			}
			return FALSE;
		}
	}
?>
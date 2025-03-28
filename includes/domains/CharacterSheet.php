<?php
	class CharacterSheet {
		public function __construct(){}
		
		public static function GetSelectionCompoundCost( $selection, $current = 0 ){
			$cost = 0;
			for( $i = $current; $i < $selection; $i++ ){
				$cost += $i + 1;
			}
			return $cost;
		}
		
		public function Load( $character_id ){
			if( is_numeric( $character_id ) ){
				$c = Roster::GetCharacterComplete( $character_id );
				
				if( $c !== FALSE ){
					return $c;
				}
			} else {
				$character_id = "NUM";
			}
			
			Message::Erreur( "Identifiant de personnage #" . $character_id . " invalide." );
			return FALSE;
		}
		
		public function Create( $player_id, $nom, $race_id, $race_secondaire_id, $cite_etat_id, $croyance_id ){
			if( !Community::GetPlayer( $player_id ) ){
				Message::Fatale( "Identifiant de joueur invalide.", func_get_args() );
				return FALSE;
			}
			if( count( Dictionary::GetRaces( $race_id ) ) == 0 ){
				Message::Fatale( "Identifiant de race invalide.", func_get_args() );
				return FALSE;
			}
			if( $race_secondaire_id != 0 && count( Dictionary::GetRaces( $race_secondaire_id ) ) == 0 ){
				Message::Fatale( "Identifiant de race secondaire invalide.", func_get_args() );
				return FALSE;
			}
			if( count( Dictionary::GetCitesEtats( $cite_etat_id ) ) == 0 ){
				Message::Fatale( "Identifiant de cité-État invalide.", func_get_args() );
				return FALSE;
			}
			if( count( Dictionary::GetCroyances( $croyance_id ) ) == 0 ){
				Message::Fatale( "Identifiant de croyance invalide.", func_get_args() );
				return FALSE;
			}
			
			$personnage_repository = new PersonnageRepository();
			$c = $personnage_repository->Create( array( "player_id" => $player_id, "nom" => $nom, "race_id" => $race_id, "race_secondaire_id" => $race_secondaire_id, "cite_etat_id" => $cite_etat_id, "croyance_id" => $croyance_id ) );
			
			if( $c !== FALSE ){
				$character_id = $c->id;
				
				$this->RecordAction( $c->id, CharacterSheet::RECORD_MESSAGE, 0, 0, "Création du personnage", FALSE );
				$this->RecordAction( $c->id, CharacterSheet::RECORD_XP, 1, CHARACTER_BASE_XP, "Gain de " . CHARACTER_BASE_XP . "  points d'expérience. (Création du personnage)", FALSE );
				
				return $c;
				
			}
			
			return FALSE;
		}
		
		public function UpdateBases( $character_id, $nom, $race_id, $cite_etat_id, $croyance_id ){
			if( count( Dictionary::GetRaces( $race_id ) ) == 0 ){
				Message::Fatale( "Identifiant de cité-État invalide.", func_get_args() );
				return FALSE;
			}
			if( count( Dictionary::GetCitesEtats( $cite_etat_id ) ) == 0 ){
				Message::Fatale( "Identifiant de cité-État invalide.", func_get_args() );
				return FALSE;
			}
			if( count( Dictionary::GetCroyances( $croyance_id ) ) == 0 ){
				Message::Fatale( "Identifiant de croyance invalide.", func_get_args() );
				return FALSE;
			}

			$c = Roster::GetCharacter( $character_id );
			
			$c->nom = $nom;
			$c->race_id = $race_id;
			$c->cite_etat_id = $cite_etat_id;
			$c->croyance_id = $croyance_id;
			
			$personnage_repository = new PersonnageRepository();
			$personnage_repository->Save( $c );

			return $c;
		}
		
		public function BuyCapaciteRaciale( $character_id, $new_cr ){
			$c = Roster::GetCharacterComplete( $character_id );
			// Valide que le personnage peut acheer des capacite raciales
			if( $c != FALSE ){
				$capacite_raciale_repository = new CapaciteRacialeRepository();
				$cr = $capacite_raciale_repository->Find( $new_cr );
				
				$race_repository = new RaceRepository();
				$list_capacites_raciales = $race_repository->GetCapacitesRacialesByRace( $c->race_id );
				if( $c->pc_raciales >= $list_capacites_raciales[ $new_cr ][ 1 ] ){
					$personnage_repository = new PersonnageRepository();
					if( $personnage_repository->BuyCapaciteRaciale( $c, $new_cr ) ){
						$this->RecordAction( $c->id, CharacterSheet::RECORD_RACIALE_CAPACITE, $new_cr, $list_capacites_raciales[ $new_cr ][ 1 ], "Ajout de la capacité raciale : " . $cr->nom, TRUE );
						return $c;
					}
				}
			}
			
			return FALSE;
		}
		
		private function RefundCapaciteRaciale( Personnage &$character, $old_cr, $cout = FALSE ){
			// Valide que le personnage possede cette capacite raciale
			if( $character != FALSE && array_key_exists( $old_cr, $character->capacites_raciales ) ){
				$personnage_repository = new PersonnageRepository();
				if( $personnage_repository->RefundCapaciteRaciale( $character, $old_cr, $cout ) ){
					return $character;
				}
			}
			
			return FALSE;
		}
		
		public function BuyChoixCapacite( $character_id, $list_id, $choix_id ){
			$c = Roster::GetCharacterComplete( $character_id );
			// Valide que le personnage peut acheter de obtenir cette capacite
			if( $c != FALSE && array_key_exists( $list_id, $c->choix_capacites ) ){
				$choix_capacite_repository = new ChoixCapaciteRepository();
				$choix_capacite = $choix_capacite_repository->Find( $list_id );
				if( $choix_capacite && $choix_capacite->active ){
					$liste_capacites = $choix_capacite_repository->GetCapacites( $choix_capacite );
					if( array_key_exists( $choix_id, $liste_capacites ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->BuyChoixCapacite( $c, $list_id, $choix_id ) ){
							$this->RecordAction( $c->id, CharacterSheet::RECORD_CHOIX_CAPACITE, $list_id, $choix_id, "Sélection de la capacité : " . $liste_capacites[ $choix_id ]->nom, TRUE );
							return $c;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundChoixCapacite( Personnage &$character, $list_id, $choix_id ){
			// Valide que le personnage possede cette capacite
			if( $character != FALSE && array_key_exists( $choix_id, $character->capacites ) && $character->capacites[ $choix_id ] > 0 ){
				$choix_capacite_repository = new ChoixCapaciteRepository();
				$choix_capacite = $choix_capacite_repository->Find( $list_id );
				if( $choix_capacite ){
					$liste_capacites = $choix_capacite_repository->GetCapacites( $choix_capacite );
					if( array_key_exists( $choix_id, $liste_capacites ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->RefundChoixCapacite( $character, $list_id, $choix_id ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}

		public function BuyChoixCapaciteRaciale( $character_id, $list_id, $choix_id ){
			$c = Roster::GetCharacterComplete( $character_id );
			// Valide que le personnage peut obtenir cette capacité raciale
			if( $c != FALSE && array_key_exists( $list_id, $c->choix_capacites_raciales ) ){
				$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
				$choix_capacite_raciale = $choix_capacite_raciale_repository->Find( $list_id );
				if( $choix_capacite_raciale && $choix_capacite_raciale->active ){
					$liste_capacites_raciales = $choix_capacite_raciale_repository->GetCapacitesRaciales( $choix_capacite_raciale );
					if( array_key_exists( $choix_id, $liste_capacites_raciales ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->BuyChoixCapaciteRaciale( $c, $list_id, $choix_id ) ){
							$this->RecordAction( $c->id, CharacterSheet::RECORD_CHOIX_CAPACITE_RACIALE, $list_id, $choix_id, "Sélection de la capacité raciale : " . $liste_capacites_raciales[ $choix_id ]->nom, TRUE );
							return $c;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundChoixCapaciteRaciale( Personnage &$character, $list_id, $choix_id ){
			// Valide que le personnage possede cette capacité raciale
			if( $character != FALSE && array_key_exists( $choix_id, $character->capacites_raciales ) ){
				$choix_capacite_raciale_repository = new ChoixCapaciteRacialeRepository();
				$choix_capacite_raciale = $choix_capacite_raciale_repository->Find( $list_id );
				if( $choix_capacite_raciale ){
					$liste_capacites_raciales = $choix_capacite_raciale_repository->GetCapacitesRaciales( $choix_capacite_raciale );
					if( array_key_exists( $choix_id, $liste_capacites_raciales ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->RefundChoixCapaciteRaciale( $character, $list_id, $choix_id ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}

		public function BuyChoixConnaissance( $character_id, $list_id, $choix_id ){
			$c = Roster::GetCharacterComplete( $character_id );
			// Valide que le personnage peut obtenir cette connaissance
			if( $c != FALSE && array_key_exists( $list_id, $c->choix_connaissances ) ){
				$choix_connaissance_repository = new ChoixConnaissanceRepository();
				$choix_connaissance = $choix_connaissance_repository->Find( $list_id );
				if( $choix_connaissance && $choix_connaissance->active ){
					$liste_connaissances = $choix_connaissance_repository->GetConnaissances( $choix_connaissance );
					if( array_key_exists( $choix_id, $liste_connaissances ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->BuyChoixConnaissance( $c, $list_id, $choix_id ) ){
							$this->RecordAction( $c->id, CharacterSheet::RECORD_CHOIX_CONNAISSANCE, $list_id, $choix_id, "Sélection de la connaissance : " . $liste_connaissances[ $choix_id ]->nom, TRUE );
							return $c;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundChoixConnaissance( Personnage &$character, $list_id, $choix_id ){
			// Valide que le personnage possede cette connaissance
			if( $character != FALSE && in_array( $choix_id, $character->connaissances ) ){
				$choix_connaissance_repository = new ChoixConnaissanceRepository();
				$choix_connaissance = $choix_connaissance_repository->Find( $list_id );
				if( $choix_connaissance ){
					$liste_connaissances = $choix_connaissance_repository->GetConnaissances( $choix_connaissance );
					if( array_key_exists( $choix_id, $liste_connaissances ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->RefundChoixConnaissance( $character, $list_id, $choix_id ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}

		public function BuyChoixVoie( $character_id, $list_id, $choix_id ){
			$c = Roster::GetCharacterComplete( $character_id );
			// Valide que le personnage peut obtenir cette voie
			if( $c != FALSE && array_key_exists( $list_id, $c->choix_voies ) ){
				$choix_voie_repository = new ChoixVoieRepository();
				$choix_voie = $choix_voie_repository->Find( $list_id );
				if( $choix_voie && $choix_voie->active ){
					$liste_voies = $choix_voie_repository->GetVoies( $choix_voie );
					if( array_key_exists( $choix_id, $liste_voies ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->BuyChoixVoie( $c, $list_id, $choix_id ) ){
							$this->RecordAction( $c->id, CharacterSheet::RECORD_CHOIX_VOIE, $list_id, $choix_id, "Sélection de la voie : " . $liste_voies[ $choix_id ]->nom, TRUE );
							return $c;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundChoixVoie( Personnage &$character, $list_id, $choix_id ){
			// Valide que le personnage possede cette voie
			if( $character != FALSE && in_array( $choix_id, $character->voies ) ){
				$choix_voie_repository = new ChoixVoieRepository();
				$choix_voie = $choix_voie_repository->Find( $list_id );
				if( $choix_voie ){
					$liste_voies = $choix_voie_repository->GetVoies( $choix_voie );
					if( array_key_exists( $choix_id, $liste_voies ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->RefundChoixVoie( $character, $list_id, $choix_id ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function BuyCapacite( $character_id, $capacite_id, $nb_selections ){
			$c = Roster::GetCharacterComplete( $character_id );
			if( $c != FALSE && $c->est_vivant
					&& array_key_exists( $capacite_id, $c->capacites ) ){
				$capacite_repository = new CapaciteRepository();
				$capacite = $capacite_repository->Find( $capacite_id );
				$curr_score = $c->capacites[ $capacite_id ];
				
				if( $nb_selections > 0
						&& $curr_score + $nb_selections <= CHARACTER_MAX_SELECTION_COUNT
						&& $capacite && $capacite->active
						&& in_array( $capacite->voie_id, $c->voies ) ){
					$xp_cost = self::GetSelectionCompoundCost( $curr_score + $nb_selections, $curr_score );
					if( $c->CanAfford( $xp_cost ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->AddCapacite( $c, $capacite_id, $nb_selections ) ){
							$c->px_restants -= $xp_cost;
						
							if( $personnage_repository->Save( $c ) ){
								$this->RecordAction( $c->id, CharacterSheet::RECORD_CAPACITE, $capacite_id, $nb_selections, "Augmentation de la capacité " . $capacite->nom . " à " . ( $curr_score + $nb_selections ) . " (-" . $xp_cost . " XP)", TRUE );
							
								return $c;
							}
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundCapacite( Personnage &$character, $capacite_id, $nb_selections ){
			if( $character != FALSE
					&& array_key_exists( $capacite_id, $character->capacites ) ){
				$curr_score = $character->capacites[ $capacite_id ];
				
				$xp_cost = CharacterSheet::GetSelectionCompoundCost( $curr_score, $curr_score - $nb_selections );
				if( $character->px_restants + $xp_cost <= $character->px_totaux ){
					$personnage_repository = new PersonnageRepository();
					if( $personnage_repository->RemoveCapacite( $character, $capacite_id, $nb_selections ) ){
						$character->px_restants += $xp_cost;
						
						if( $personnage_repository->Save( $character ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function BuyVoie( $character_id, $voie_id ){
			$c = Roster::GetCharacterComplete( $character_id );
			if( $c != FALSE && $c->est_vivant
					&& !in_array( $voie_id, $c->voies ) ){
				$voie_repository = new VoieRepository();
				$voie = $voie_repository->Find( $voie_id );
				
				if( $voie && $voie->active ){
					$xp_cost = $c->GetNextVoieCost();
					if( $c->CanAfford( $xp_cost ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->AddVoie( $c, $voie_id ) ){
							$c->px_restants -= $xp_cost;
						
							if( $personnage_repository->Save( $c ) ){
								$this->RecordAction( $c->id, CharacterSheet::RECORD_VOIE, $voie_id, 1, "Ajout de la voie " . $voie->nom . " (-" . $xp_cost . " XP)", TRUE );
							
								return $c;
							}
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundVoie( Personnage $character, $voie_id ){
			if( $character->est_vivant
					&& in_array( $voie_id, $character->voies ) ){
				$xp_cost = $character->GetRefundVoieCost();
				if( $character->px_restants + $xp_cost <= $character->px_totaux ){
					$personnage_repository = new PersonnageRepository();
					if( $personnage_repository->RemoveVoie( $character, $voie_id ) ){
						$character->px_restants += $xp_cost;
					
						if( $personnage_repository->Save( $character ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function BuyConnaissance( $character_id, $connaissance_id ){
			$c = Roster::GetCharacterComplete( $character_id );
			if( $c != FALSE && $c->est_vivant
					&& !in_array( $connaissance_id, $c->connaissances ) ){
				$connaissance_repository = new ConnaissanceRepository();
				$connaissance = $connaissance_repository->Find( $connaissance_id );
				
				if( $connaissance && $connaissance->active ){
					if( $c->CanAfford( $connaissance->cout ) ){
						$personnage_repository = new PersonnageRepository();
						if( $personnage_repository->AddConnaissance( $c, $connaissance_id ) ){
							$c->px_restants -= $connaissance->cout;
						
							if( $personnage_repository->Save( $c ) ){
								$this->RecordAction( $c->id, CharacterSheet::RECORD_CONNAISSANCE, $connaissance_id, 1, "Ajout de la connaissance " . $connaissance->nom . " (-" . $connaissance->cout . " XP)", TRUE );
							
								return $c;
							}
						}
					}
				}
			}
			
			return FALSE;
		}
		
		public function RefundConnaissance( Personnage $character, $connaissance_id ){
			if( $character->est_vivant
					&& in_array( $connaissance_id, $character->connaissances ) ){
				$connaissance_repository = new ConnaissanceRepository();
				$connaissance = $connaissance_repository->Find( $connaissance_id );

				if( $character->px_restants + $connaissance->cout <= $character->px_totaux ){
					$personnage_repository = new PersonnageRepository();
					if( $personnage_repository->RemoveConnaissance( $character, $connaissance_id ) ){
						$character->px_restants += $connaissance->cout;
					
						if( $personnage_repository->Save( $character ) ){
							return $character;
						}
					}
				}
			}
			
			return FALSE;
		}

		public function SaveCommentaire( Personnage $character, $commentaire ){
			$personnage_repository = new PersonnageRepository();
			
			// Ne fait pas la mise à jour si le commentaire est identique
			if( $character->commentaire == $commentaire ){
				return $character;
			}
			
			$character->commentaire = $commentaire;
			if( $personnage_repository->Save( $character ) ){
				return $character;
			}
			
			return FALSE;
		}
		
		public function SaveNotes( Personnage $character, $notes ){
			$personnage_repository = new PersonnageRepository();
			
			// Ne fait pas la mise à jour si les notes sont identiques
			if( $character->notes == $notes ){
				return $character;
			}
			
			$character->notes = $notes;
			if( $personnage_repository->Save( $character ) ){
				return $character;
			}
			
			return FALSE;
		}
		
		public function Activate( $id ){
			$personnage_repository = new PersonnageRepository();
			$c = Roster::GetCharacter( $id );
			
			if( $c
					&& !$c->est_cree
					&& $c->pc_raciales == 0
					&& count( $c->choix_capacites ) == 0
					&& count( $c->choix_capacites_raciales ) == 0
					&& count( $c->choix_connaissances ) == 0
					&& count( $c->choix_voies == 0 )
			){
				if( $personnage_repository->Activate( $c ) ){
					$this->RecordAction( $c->id, CharacterSheet::RECORD_ACTIVATE, 0, 0, "Activation du personnage", FALSE );
					return TRUE;
				}
			}
			
			return FALSE;
		}

		public function Deactivate( $id ){
			$personnage_repository = new PersonnageRepository();
			$c = Roster::GetCharacter( $id );
			
			if( $c && $c->est_vivant ){
				if( $personnage_repository->Deactivate( $c ) ){
					$this->RecordAction( $c->id, CharacterSheet::RECORD_DEACTIVATE, 0, 0, "Désactivation du personnage", FALSE );
					return TRUE;
				}
			}
			
			return FALSE;
		}
		
		public function Destroy( $id ){
			$personnage_repository = new PersonnageRepository();
			$c = Roster::GetCharacter( $id );
			
			if( $c && !$c->est_vivant ){
				if( $personnage_repository->Delete( $c->id ) ){
					$this->RecordAction( $c->id, CharacterSheet::RECORD_DESTROY, 0, 0, "Destruction de " . $c->nom, FALSE );
					return TRUE;
				}
			}
			
			return FALSE;
		}
		
		/*public function RebuildComplet( $id, $suffix = FALSE ){
			$personnage_repository = new PersonnageRepository();
			$c = Roster::GetCharacter( $id );
			
			if( !$suffix ){
				$suffix = " [REMPLACÉ " . date( "Y-m-d" ) . "]";
			}
			
			if( $c ){
				return $this->Rebuild( $c, $c->px_totaux, $suffix );
			}
			return FALSE;
		}
		
		public function RebuildPerte( $id, $suffix = FALSE ){
			$personnage_repository = new PersonnageRepository();
			$c = Roster::GetCharacter( $id );
			
			if( !$suffix ){
				$suffix = " [REMPLACÉ (à perte) " . date( "Y-m-d" ) . "]";
			}
			
			if( $c ){
				return $this->Rebuild( $c, $c->GetPerteXP(), $suffix );
			}
			return FALSE;
		}
		
		private function Rebuild( $c, $xp_after_rebuild, $suffix ){
			$rebuild = $this->Create( $c->joueur_id, $c->nom, $c->alignement_id, $c->cite_etat_id, $c->croyance_id, $c->race_id );
			
			$this->RecordAction( $c->id, 6, $rebuild->id, $rebuild->px_totaux, "Personnage copié.", FALSE );
			if( $rebuild ){
				// La différence entre l'XP que le personnage doit avoir et celui qu'il a déjà
				$nouvel_xp = $xp_after_rebuild - $rebuild->px_totaux;
				if( $nouvel_xp <= 0 || $this->ManageExperience( $rebuild->id, $nouvel_xp, FALSE, TRUE, "Transfert des points d'expérience de l'ancien personnage vers le nouveau." ) ){
					if( $nouvel_xp > 0 ){
						$rebuild->px_restants += $nouvel_xp;
						$rebuild->px_totaux += $nouvel_xp;
					}
					
					// Bloque les modifications
					$this->RecordAction( $rebuild->id, 0, 0, $c->id, "Copie du personnage.", FALSE );
					
					// Laisse une trace sur l'ancetre
					$this->UpdateBases( $c->id, $c->nom . $suffix, $c->alignement_id, $c->cite_etat_id, $c->croyance_id );
					$this->Deactivate( $c->id );
					
					$this->SaveCommentaire( $rebuild, "Rebuild de " . $c->nom . " (" . date( "Y-m-d" ) . ")" );
					
					return $rebuild->id;
				} else {
					Message::Erreur( "Incapable d'ajouter les points d'expérience au personnage." );
				}
			} else {
				Message::Erreur( "Incapable de créer le nouveau personnage à partir de l'ancien." );
			}
			return FALSE;
		}
		*/
		
		public function ManageExperience( $character_id, $modificateur, $silent = FALSE, $modif_total = FALSE, $raison = "" ){
			if( is_numeric( $modificateur ) ){
				$personnage_repository = new PersonnageRepository();
				$c = Roster::GetCharacter( $character_id );
				
				if( $c && $c->est_vivant ){
					$c->px_restants += $modificateur;
					
					if( $modif_total ){
						$c->px_totaux += $modificateur;
					}
					
					if( $personnage_repository->Save( $c ) ){
						if( !$silent ){
							// Ajoute des parenthese au commentaire
							if( $raison != "" ){
								$raison = " (" . $raison . ")";
							}
						
							// La raison recue a deja ete decodee par le controleur
							$record_msg = ( $modificateur >= 0 ? "Gain" : "Perte" ) . " de " . abs( $modificateur ) . " points d'expérience" . $raison;
							$this->RecordAction( $c->id, CharacterSheet::RECORD_XP, 0, $modificateur, $record_msg, TRUE );
						}
						
						return TRUE;
					}
				}
			}
		}
		
		public function Rollback( Personnage &$character ){
			$rolled_back = FALSE;
			$last_modif = CharacterLog::GetLastByCharacter( $character->id );
			
			if( $last_modif != FALSE && $last_modif->CanBacktrack ){
				switch( $last_modif->Quoi ){
					case CharacterSheet::RECORD_XP :
						// Modification des XP
						if( $this->ManageExperience( $character->id, ( $last_modif->Combien * -1 ), TRUE, TRUE ) ){
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_RACIALE_CAPACITE :
						// Achat d'une capacite raciale
						if( $this->RefundCapaciteRaciale( $character, $last_modif->Pourquoi, $last_modif->Combien ) ){
							Message::Notice( "La capacité raciale a été retirée." );
							$rolled_back = true;
						}
						break;
					/*
					//TODO: case CharacterSheet::RECORD_REBUILD :
					//TODO: case CharacterSheet::RECORD_DESTROY :
					//TODO: case CharacterSheet::RECORD_ACTIVATE :
					//	break;
					*/
					case CharacterSheet::RECORD_VOIE :
						// Achat d'une voie
						if( $this->RefundVoie( $character, $last_modif->Pourquoi ) ){
							Message::Notice( "La voie a été retiré." );
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_CAPACITE :
						// Achat d'un niveau de capacité
						if( $this->RefundCapacite( $character, $last_modif->Pourquoi, $last_modif->Combien ) ){
							Message::Notice( "La capacité a été retiré." );
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_CONNAISSANCE :
						// Achat d'une connaissance
						if( $this->RefundConnaissance( $character, $last_modif->Pourquoi ) ){
							Message::Notice( "La connaissance a été retiré." );
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_CHOIX_CAPACITE :
						// Achat d'un choix de capacite
						if( $this->RefundChoixCapacite( $character, $last_modif->Pourquoi, $last_modif->Combien ) ){
							Message::Notice( "La sélection de capacité a été retirée." );
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_CHOIX_CAPACITE_RACIALE :
						// Achat d'un choix de capacite raciale
						if( $this->RefundChoixCapaciteRaciale( $character, $last_modif->Pourquoi, $last_modif->Combien ) ){
							Message::Notice( "La sélection de capacité raciale a été retirée." );
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_CHOIX_CONNAISSANCE :
						// Achat d'un choix de connaissance
						if( $this->RefundChoixConnaissance( $character, $last_modif->Pourquoi, $last_modif->Combien ) ){
							Message::Notice( "La sélection de connaissance a été retirée." );
							$rolled_back = true;
						}
						break;
					case CharacterSheet::RECORD_CHOIX_VOIE :
						// Achat d'un choix de voie
						if( $this->RefundChoixVoie( $character, $last_modif->Pourquoi, $last_modif->Combien ) ){
							Message::Notice( "La sélection de voie a été retirée." );
							$rolled_back = true;
						}
						break;
					default :
						Message::Fatale( "Type de modification inconnue.", func_get_args() );
						break;
				}
				
				if( $rolled_back ){
					$sql = "Update personnage_journal Set active = '0' Where personnage_id = ? And id = ?";
					$db = new Database();
					$db->Query( $sql, array( $character->id, $last_modif->Id ) );
					
					return TRUE;
				} else {
					// Sinon, remet le personnage comme il etait
					$personnage_repository = new PersonnageRepository();
					$character = Roster::GetCharacterComplete( $character->id );
				}
			}
			
			return FALSE;
		}
		
		const RECORD_MESSAGE = 0;
		const RECORD_XP = 1;
		const RECORD_RACIALE_CAPACITE = 4;
		//const RECORD_REBUILD = 6;
		const RECORD_DESTROY = 7;
		const RECORD_ACTIVATE = 8;
		const RECORD_DEACTIVATE = 9;
		const RECORD_VOIE = 10;
		const RECORD_CAPACITE = 11;
		const RECORD_CONNAISSANCE = 12;
		const RECORD_CHOIX_CAPACITE = 21;
		const RECORD_CHOIX_CAPACITE_RACIALE = 22;
		const RECORD_CHOIX_CONNAISSANCE = 23;
		const RECORD_CHOIX_VOIE = 24;
		private function RecordAction( $character_id, $quoi, $pourquoi, $combien, $note = "", $can_backtrack = true ){
			$personnage_repository = new PersonnageRepository();
			$c = Roster::GetCharacter( $character_id );
			
			if( $c ) {
				if( is_numeric( $quoi ) && is_numeric( $pourquoi ) && is_numeric( $combien ) ){
					// Si aucune note n'a été donnée, on la créé nous-même
					if( $note == "" ){
						$note = "Quoi : " . $quoi . ", Pourquoi : " . $pourquoi . ", Combien : " . $combien . ", Backtrack : " . (  ( $can_backtrack ) ? "true" : "false" );
					}
					
					$db = new Database();
					$sql = "Insert Into personnage_journal
								( personnage_id, quoi, pourquoi, combien, note, backtrack, joueur_id )
							Values
								( ?, ?, ?, ?, ?, ?, ? )";
					$db->Query( $sql, array( $c->id, $quoi, $pourquoi, $combien, $note, ( $can_backtrack ? '1' : '0' ), $_SESSION[ SESSION_KEY ][ "User" ]->Id ) );
					
					return TRUE;
				}
			}
			return FALSE;
		}
	}
?>
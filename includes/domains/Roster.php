<?php
	class Roster {
		private function __construct(){}
		
		public static function GetCharacter( int $id ){
			if( !is_numeric( $id ) ){
				Message::Fatale( "Bad personnage entity ID.", func_get_args() );
				return FALSE;
			}
			
			$where = "p.id = ?";
			$order_by = "";
			
			$characters_list = self::FetchCharacterList( $order_by, $where, array( $id ) );
			return array_shift( $characters_list );
		}

		public static function GetCharacterComplete( int $id ) {
			$personnage = self::GetCharacter( $id );
			if( $personnage && self::Complete( $personnage, TRUE ) ){
				return $personnage;
			}
			return FALSE;
		}

        /*		
		public static function GetAllCharacters( string $sort_by = "character" ){
			$order_by = "";
			switch( $sort_by ) {
				case "character" :
					$order_by = "TRIM( p.nom ), TRIM( CONCAT( j.prenom, ' ', j.nom ) ), pj.quand DESC";
					break;
				case "player" :
					$order_by = "TRIM( CONCAT( j.prenom, ' ', j.nom ) ), TRIM( p.nom ), pj.quand DESC";
					break;
				default :
					Message::Fatale( "Unknown sort : " . $sort_by );
			}
			return self::FetchCharacterList( $order_by );
		}
		*/
		
		public static function GetAllCharactersByPlayer( $player_id ){
			if( !is_numeric( $player_id ) ){
				Message::Fatale( "Bad personnage entity ID.", func_get_args() );
				return FALSE;
			}
			
			$where = "p.joueur = ?";
			$order_by = "p.est_vivant DESC, TRIM( p.nom ), pj.quand DESC";
			
			return self::FetchCharacterList( $order_by, $where, array( $player_id ) );
		}
		
		/*
		public static function GetAllCharactersAlive( $sort_by = "character" ){
			$where = "p.est_vivant = '1'";
			switch( $sort_by ) {
				case "character" :
					$order_by = "TRIM( p.nom ), TRIM( CONCAT( j.prenom, ' ', j.nom ) ), pj.quand DESC";
					break;
				case "player" :
					$order_by = "TRIM( CONCAT( j.prenom, ' ', j.nom ) ), TRIM( p.nom ), pj.quand DESC";
					break;
				default :
					Message::Fatale( "Unknown sort : " . $sort_by );
			}
			return self::FetchCharacterList( $order_by, $where );
		}
		
		public static function GetAllCharactersDead(){
			$where = "p.est_vivant != '1'";
			$order_by = "TRIM( p.nom ), TRIM( CONCAT( j.prenom, ' ', j.nom ) ), pj.quand DESC";
			
			return self::FetchCharacterList( $order_by, $where );
		}
		*/

        public static function GetCharacterCountByPlayer( $player_id ){
			if( !is_numeric( $player_id ) ){
				Message::Fatale( "Identifiant de joueur invalide.", func_get_args() );
				return FALSE;
			}
			
			$where = "p.est_vivant = '1' AND p.joueur = ?";
			$order_by = "";
			return count( self::FetchCharacterList( $order_by, $where, array( $player_id ) ) );
		}

        private static function FetchCharacterList( $order_by = "", $where = "", $params = array() ){
			$list = array();
			
			$sql = "SELECT p.id, p.nom, p.est_vivant, p.est_cree,
						p.point_experience, p.total_experience, p.point_capacite_raciale,
						pj.quand AS changement_date,
						CONCAT( u.prenom, ' ', u.nom ) AS changement_par,
						j.id AS joueur_id,
						CONCAT( j.prenom, ' ', j.nom ) AS joueur_nom,
						ce.id AS cite_etat_id,
						ce.nom AS cite_etat_nom,
						ra.id AS race_id,
						ra.nom AS race_nom,
						rs.id AS race_secondaire_id,
						rs.nom AS race_secondaire_nom,
						cr.id AS croyance_id,
						cr.nom AS croyance_nom,
						p.notes, p.commentaire
					FROM personnage AS p
						LEFT JOIN personnage_journal pj ON pj.id = ( SELECT id FROM personnage_journal WHERE active = '1' AND personnage_id = p.id ORDER BY quand DESC LIMIT 1 )
						LEFT JOIN joueur u ON pj.joueur_id = u.id
						LEFT JOIN joueur j ON p.joueur = j.id
						LEFT JOIN cite_etat ce ON p.cite_etat_id = ce.id
						LEFT JOIN race ra ON p.race_id = ra.id
						LEFT JOIN race rs ON p.race_secondaire_id = rs.id
						LEFT JOIN croyance cr ON p.croyance_id = cr.id
					WHERE p.est_detruit = '0'";
			
			if( $where != "" ){
				$sql .= " AND " . $where;
			} else {
				$params = array();
			}
			
			if( $order_by != "" ){
				$sql .= " ORDER BY " . $order_by;
			}
			
			$db = new Database();
			$db->Query( $sql, $params );
			while( $result = $db->GetResult() ){
				$entity = new Personnage();
				$entity->id = $result[ "id" ];

				$entity->nom = $result[ "nom" ];
				$entity->active = $result[ "est_vivant" ] == 1;
				$entity->est_vivant = $result[ "est_vivant" ] == 1;
				$entity->est_cree = $result[ "est_cree" ] == 1;
				
				$entity->joueur_id = $result[ "joueur_id" ];
				$entity->joueur_nom = $result[ "joueur_nom" ];
				
				$entity->px_restants = $result[ "point_experience" ];
				$entity->px_totaux = $result[ "total_experience" ];
				$entity->pc_raciales = $result[ "point_capacite_raciale" ];
				
				$entity->dernier_changement_date = $result[ "changement_date" ];
				$entity->dernier_changement_par = $result[ "changement_par" ];
				
				$entity->cite_etat_id = $result[ "cite_etat_id" ];
				$entity->cite_etat_nom = $result[ "cite_etat_nom" ];
				$entity->race_id = $result[ "race_id" ];
				$entity->race_nom = $result[ "race_nom" ];
				$entity->race_secondaire_id = $result[ "race_secondaire_id" ];
				$entity->race_secondaire_nom = $result[ "race_secondaire_nom" ];
				$entity->croyance_id = $result[ "croyance_id" ];
				$entity->croyance_nom = $result[ "croyance_nom" ];
				
				$entity->notes = $result[ "notes" ];
				$entity->commentaire = $result[ "commentaire" ];
				
				$list[ $result[ "id" ] ] = $entity;
			}
			
			return $list;
		}
		
		private static function Complete( Personnage &$personnage, $force = FALSE ){
			if( !$force ){
				// Le personnage a déjà été complété
				return true;
			}
			// Chaine les appels de construction
			if(
                    self::FetchCapacitesRaciales( $personnage ) &&
					self::FetchChoixCapacites( $personnage ) &&
					self::FetchChoixCapacitesRaciales( $personnage ) &&
					self::FetchChoixConnaissances( $personnage ) &&
					self::FetchChoixVoies( $personnage ) &&
					self::FetchCapacites( $personnage ) &&
					self::FetchVoies( $personnage ) &&
					self::FetchConnaissances( $personnage ) &&
					self::FetchConnaissancesAccessibles( $personnage )
			){
				$personnage->est_complet = true;
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchCapacitesRaciales( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT cr.id, cr.nom
						FROM personnage p
							LEFT JOIN personnage_capacite_raciale pcr ON pcr.personnage_id = p.id
							LEFT JOIN capacite_raciale cr ON cr.id = pcr.capacite_raciale_id
						WHERE p.id = ?
						ORDER BY cr.nom";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->capacites_raciales[ $result[ "id" ] ] = $result[ "nom" ];
				}
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchChoixCapacites( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT cc.id, cc.nom
						FROM choix_capacite AS cc
							LEFT JOIN personnage_choix_capacite AS x ON cc.id = x.choix_capacite_id
						WHERE x.personnage_id = ? And cc.active = '1' And cc.supprime = '0'
						ORDER BY cc.nom";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->choix_capacites[ $result[ "id" ] ] = $result[ "nom" ];
				}
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchChoixCapacitesRaciales( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT ccr.id, ccr.nom
						FROM choix_capacite_raciale AS ccr
							LEFT JOIN personnage_choix_capacite_raciale AS x ON ccr.id = x.choix_capacite_raciale_id
						WHERE x.personnage_id = ? And ccr.active = '1' And ccr.supprime = '0'
						ORDER BY ccr.nom";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->choix_capacites_raciales[ $result[ "id" ] ] = $result[ "nom" ];
				}
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchChoixConnaissances( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT cc.id, cc.nom
						FROM choix_connaissance AS cc
							LEFT JOIN personnage_choix_connaissance AS x ON cc.id = x.choix_connaissance_id
						WHERE x.personnage_id = ? And cc.active = '1' And cc.supprime = '0'
						ORDER BY cc.nom";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->choix_connaissances[ $result[ "id" ] ] = $result[ "nom" ];
				}
				return TRUE;
			}
			return FALSE;
		}

		private static function FetchChoixVoies( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT cv.id, cv.nom
						FROM choix_voie AS cv
							LEFT JOIN personnage_choix_voie AS x ON cv.id = x.choix_voie_id
						WHERE x.personnage_id = ? And cv.active = '1' And cv.supprime = '0'
						ORDER BY cv.nom";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->choix_voies[ $result[ "id" ] ] = $result[ "nom" ];
				}
				return TRUE;
			}
			return FALSE;
		}

		private static function FetchCapacites( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				// Sélectionne aussi les capacités que le personnage n'a pas en leur attribuant un niveau 0
				$sql = "SELECT c.id AS capacite_id, COALESCE( pc.niveau, 0 ) AS niveau
						FROM capacite AS c
							LEFT JOIN personnage_capacite pc ON c.id = pc.capacite_id AND pc.personnage_id = ?
						WHERE c.active = '1' AND c.supprime = '0'";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->capacites[ $result[ "capacite_id" ] ] = $result[ "niveau" ];
				}
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchConnaissances( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT pc.connaissance_id
						FROM personnage_connaissance pc
						WHERE pc.personnage_id = ?";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->connaissances[] = $result[ "connaissance_id" ];
				}
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchConnaissancesAccessibles( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT c.id
						FROM connaissance c,
							personnage p
						WHERE p.id = ?
								AND c.active = '1' AND c.supprime = '0'
								AND c.prereq_voie_primaire IN ( SELECT voie_id FROM personnage_voie WHERE personnage_id = p.id )
								AND (
									( c.prereq_voie_secondaire IS NULL AND c.prereq_capacite IS NULL ) -- AVANCÉE + MAÎTRE
									OR c.prereq_capacite IN ( SELECT capacite_id FROM personnage_capacite WHERE personnage_id = p.id AND niveau >= '" . CHARACTER_CONN_LEGENDAIRE_TRESHOLD . "' ) -- LÉGENDAIRE
									OR c.prereq_voie_secondaire IN ( SELECT voie_id FROM personnage_voie WHERE personnage_id = p.id ) -- SYNERGIQUE
								)
						ORDER BY c.nom";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->connaissances_accessibles[] = $result[ "id" ];
				}
				return TRUE;
			}
			return FALSE;
		}
		
		private static function FetchVoies( Personnage &$personnage ){
			if( $personnage ){
                $db = new Database();
				$sql = "SELECT voie_id
						FROM personnage_voie
						WHERE personnage_id = ?";
				$db->Query( $sql, array( $personnage->id ) );
				while( $result = $db->GetResult() ){
					$personnage->voies[] = $result[ "voie_id" ];
				}
				return TRUE;
			}
			return FALSE;
		}
	}
?>
<?php
	class Dictionary {
		private static function GetResult( $sql, $args = array() ){
			$result = array();
			
			$db = new Database();
			$db->Query( $sql, $args );
			while( $row = $db->GetResult() ){
				$result[ $row[ "id" ] ] = $row;
			}
			
			return $result;
		}
		
		private static function GetResultByName( $sql, $args = array() ){
			$result = array();
			
			$db = new Database();
			$db->Query( $sql, $args );
			while( $row = $db->GetResult() ){
				$result[ $row[ "id" ] ] = $row[ "nom" ];
			}
			
			return $result;
		}
		
		public static function GetCapacites( $id = FALSE, $activeOnly = TRUE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM capacite
					WHERE supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			if( $activeOnly ){
				$sql .= " AND active = '1'";
			}
			$sql .= " ORDER BY nom";
			return self::GetResultByName( $sql, $params );
		}
		
		public static function GetCapacitesByVoie( $id ){
			$sql = "SELECT id, nom
					FROM capacite
					WHERE voie = ? AND active = '1' AND supprime = 0
					ORDER BY nom";
			return self::GetResultByName( $sql, array( $id ) );
		}
		
		public static function GetChoixCapacites( $id = FALSE, $activeOnly = TRUE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM capacite_categorie
					WHERE supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			if( $activeOnly ){
				$sql .= " AND active = '1'";
			}
			$sql .= " ORDER BY nom";
			return self::GetResultByName( $sql, $params );
		}
		
		public static function GetCitesEtats( $id = FALSE, $activeOnly = TRUE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM cite_etat
					WHERE supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			if( $activeOnly ){
				$sql .= " AND active = '1'";
			}
			$sql .= " ORDER BY nom";
			return self::GetResultByName( $sql, $params );
		}
		
		public static function GetConnaissances( $id = FALSE, $activeOnly = TRUE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM connaissance
					WHERE supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			if( $activeOnly ){
				$sql .= " AND active = '1'";
			}
			$sql .= " ORDER BY nom";
			return self::GetResultByName( $sql, $params );
		}
		
		public static function GetCroyances( $id = FALSE, $activeOnly = TRUE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM croyance
					WHERE supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			if( $activeOnly ){
				$sql .= " AND active = '1'";
			}
			$sql .= " ORDER BY nom";
			return self::GetResult( $sql, $params );
		}
		
		public static function GetRaces( $id = FALSE, $activeOnly = TRUE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM race
					WHERE supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			if( $activeOnly ){
				$sql .= " AND active = '1'";
			}
			$sql .= " ORDER BY nom";
			return self::GetResultByName( $sql, $params );
		}
		
		public static function GetVoies( $id = FALSE ){
			$params = array();
			$sql = "SELECT id, nom
					FROM voie
					WHERE active = '1' AND supprime = '0'";
			if( $id !== FALSE ){
				$sql .= " AND id = ?";
				$params[] = $id;
			}
			$sql .= " ORDER BY ordre_affichage, nom";
			return self::GetResultByName( $sql, $params );
		}
		
		public static function GetExperienceChanges(){
			$sql = "SELECT MIN( j.id ) AS id, j.note AS nom
					FROM personnage_journal j
					WHERE j.quoi = '1' AND j.active = '1'
					GROUP BY j.note
					ORDER BY j.note";
			$tmp = self::GetResultByName( $sql );
			
			$dict = array();
			foreach( $tmp as $note ){
				if( preg_match( "/.*?\((.*)\)$/i", $note, $matches ) === 1 && !in_array( $matches[ 1 ], $dict ) ){
					$dict[] = $matches[ 1 ];
				}
			}
			sort( $dict );
			
			return $dict;
		}
	}
?>
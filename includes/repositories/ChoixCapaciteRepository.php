<?php
	class ChoixCapaciteRepository implements IRepository {
		public function Find( $id ){
			if( !is_numeric( $id ) ){
				Message::Fatale( "Bad choix capacité entity ID." );
			}
			
			$db = new Database();
			$sql = "SELECT nom, active
					FROM choix_capacite 
						WHERE id = ? AND supprime = '0'";
			$db->Query( $sql, array( $id ) );
			if( $result = $db->GetResult() ){
				$entity = new ChoixCapacite();
				$entity->id = $id;

				$entity->nom = $result[ "nom" ];
				$entity->active = $result[ "active" ] == 1;

				return $entity;
			}
			
			return FALSE;
		}
		
		public function Create( $opts = array() ){
			$db = new Database();
			$sql = "INSERT INTO choix_capacite ( nom )
					VALUES ( ? )";
			
			$db->Query( $sql, array( $opts[ "nom" ] ) );
			
			$insert_id = $db->GetInsertId();
			return $this->Find( $insert_id );
		}
		
		public function Save( $choix_capacite ){
			$db = new Database();
			$sql = "UPDATE choix_capacite SET
					nom = ?,
					active = ?
				WHERE supprime = '0' AND id = ?";
			$params = array(
					$choix_capacite->nom,
					$choix_capacite->active ? 1 : 0,
					$choix_capacite->id
			);
			
			$db->Query( $sql, $params );
			$choix_capacite = $this->Find( $choix_capacite->id );
			
			return $choix_capacite != FALSE;
		}
		
		public function Delete( $id ){ die( "Not implemented exception." ); }
		
		public function GetCapacites( ChoixCapacite $choix_capacite ){
			$capacites = array();
			$capacite_repository = new CapaciteRepository();
			
			$db = new Database();
			$sql = "SELECT ccc.capacite_id
					FROM choix_capacite_capacite ccc
							LEFT JOIN capacite c ON ccc.capacite_id = c.id
					WHERE c.supprime = '0' AND ccc.choix_capacite_id = ?
					ORDER BY c.nom";
			$db->Query( $sql, array( $choix_capacite->id ) );
			while( $result = $db->GetResult() ){
				$capacites[ $result[ "capacite_id" ] ] = $capacite_repository->Find( $result[ "capacite_id" ] );
			}
			
			return $capacites;
		}

		public function GetCapacitesByChoixId( $choix_id ){
			$choix_capacite = $this->Find( $choix_id );
			if( $choix_capacite == FALSE){
				Message::Erreur( "Le choix de capacité doit être existant pour récupérer la liste des capacités." );
				return FALSE;
			}
			return $this->GetCapacites( $choix_capacite );
		}
		
		public function AddCapacite( ChoixCapacite $choix_capacite, $capaciteId ){
			$capacites = $this->GetCapacites( $choix_capacite );
			
			if( !isset( $capacites[ $capaciteId ] ) ){
				$db = new Database();
				$sql = "INSERT INTO choix_capacite_capacite ( choix_capacite_id, capacite_id )
						VALUE ( ?, ? )";
				$params = array(
					$choix_capacite->id,
					$capaciteId
				);
				
				$db->Query( $sql, $params );
				return TRUE;
			}
			return FALSE;
		}
		
		public function RemoveCapacite( ChoixCapacite $choix_capacite, $capaciteId ){
			$capacites = $this->GetCapacites( $choix_capacite );
			
			if( isset( $capacites[ $capaciteId ] ) ){
				$db = new Database();
				$sql = "DELETE FROM choix_capacite_capacite
						WHERE choix_capacite_id = ? AND capacite_id = ?";
				$params = array(
					$choix_capacite->id,
					$capaciteId
				);
				
				$db->Query( $sql, $params );
				return TRUE;
			}
			return FALSE;
		}
	}
?>
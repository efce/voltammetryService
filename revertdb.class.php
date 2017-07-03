<?php

class revertdb {


	protected $database, $dbrevert, $dbrevert_counter;


	function __construct($host, $user, $password, $database)
	{
		$this->dbrevert_counter=0;
		$this->dbrevert=Array();
		$this->database = new mysqli($host, $user, $password, $database);
	        if ( $errno=mysqli_connect_errno() ) {
        	        throw new Exception('Could not connect to host:' . $host . ', database:' . $database . ', errono:' . $errno , 90 );
	        }

	        if ( ! $this->database->query('SET NAMES UTF8') ) {
	                throw new Exception('Could not change charset..', 90 );
	        }
	}

	function __destruct()
	{
		$this->database->close();
		unset($this->database, $this->dbchanges, $this->dbchanges_counter);
	}

	function get($table, $WHERE=null) 
	{
		if ( empty($table) )
			throw new Exception('Table name can not be empty..', 91 );

	    	if ( !empty($WHERE) )
       		        $dbquery='SELECT * FROM `' . $table . '` WHERE ' . $WHERE;
	     	else
                	$dbquery='SELECT * FROM `' . $table . '`';

		if( $dban=$this->database->query($dbquery) ) {
               		$i=0;
	                while ( $dbpos=$dban->fetch_assoc() ) {
        	                $result[$i]=$dbpos;
                	        $i++;
	                }
	                if ( isset($result) ) {
	                        return $result;
	                } else {
        	                return false;
	                }
		} else {
			return false;
		}
	}


	function ins($table, $what, $prepare_revert=true)
	{
		if ( strpos($table,'(') !== false ) {
			$tmp=explode('(',$table);
			$tname=$tmp[0];
			$table=trim($tname);
			$tmp[0]='';
			$cells=implode($tmp);
			$cells=substr($cells,0,-1);
			$dbquery='INSERT INTO `' . $table . '` (' . $cells . ')  VALUES(' . $what . ')';
		} else {
			$dbquery='INSERT INTO `' . $table . '` VALUES(' . $what . ')';
		}
        	if ( $this->database->query($dbquery) ) {
			$id=$this->database->insert_id;
//			$stan = $this->get($table);
//			throw new exception('tak wyglada moja baza: '. print_r($stan, true) .' a podobno wsadzilem cos pod: '. $id, 489640386689);
			if ( isset($id) && is_numeric($id) ) {
				if ( $prepare_revert == true ) {
					if ( !$autoinc=$this->findAIField($table) )
						throw new Exception('Could not find auto_increment field in talbe: ' . $table, 92);

					$this->dbrevert[$this->dbrevert_counter]='DELETE FROM `' . $table . '` WHERE ' . $autoinc['field']['Field'] . '=' . $id;
					$this->dbrevert_counter=$this->dbrevert_counter+1;

					// prepared //
				}
				return $id;
			} else {
				return true;
			}
		} else {
			throw new Exception ('Database insert error... insert text: ' . $dbquery, 90 );
		}
	}


	function del($table, $WHERE, $prepare_revert=true)
	{
		$rev_ok=false;
		if ( strpos($WHERE,'%') !== false || empty($WHERE) )
			return false;
		if ( $prepare_revert == true && $rev=$this->get($table, $WHERE) ) {
			// prepare revert //
			$fields=$this->getTableFields($table);
			foreach ( $rev as $r ) {
				$sr=count($r);
				if ( $sr == count($fields) ) {
					$this->dbrevert[$this->dbrevert_counter]='INSERT INTO `' . $table . '` VALUES(';
					for ( $i=0; $i<$sr; $i++ ) {
						if ( strpos($fields[$i]['Type'],'int') !== false )
							$this->dbrevert[$this->dbrevert_counter].= $this->esc($r[$fields[$i]['Field']]) .',';
						else
							$this->dbrevert[$this->dbrevert_counter].= '"' . $this->esc($r[$fields[$i]['Field']]) .'",';
					}
					$this->dbrevert[$this->dbrevert_counter]=substr($this->dbrevert[$this->dbrevert_counter],0,-1) . ')';
					$rev_ok=true;
				}
			}	
		}
		$query='DELETE FROM `' . $table . '` WHERE ' . $WHERE;
		if ( $this->database->query($query) ) {
			if ( $rev_ok )
				$this->dbrevert_counter++;
			return true;
		} else {
			return false;
		}
	}


	function upd($table, $what, $WHERE, $prepare_revert=true)
	{
		$rev_ok=false;
		if ( $prepare_revert == true && $get=$this->get($table, $WHERE) ) {
			$fields=$this->getTableFields($table);
			$autoinc=$this->findAIField($table, $fields);
			
			if ( !empty($fields) && ( count($get[0]) == count($fields) ) && $autoinc != false ) {
				$size=count($fields);
				foreach ( $get as $g ) {
					$this->dbrevert[$this->dbrevert_counter]='UPDATE `' . $table . '` SET ';
					for ( $i=0; $i<$size; $i++) {
						if ( strpos($fields[$i]['Type'],'int') !== false )
							$this->dbrevert[$this->dbrevert_counter] .= $fields[$i]['Field'] . '=' . $this->esc($g[$fields[$i]['Field']]).',';
						else
							$this->dbrevert[$this->dbrevert_counter] .= $fields[$i]['Field'] . '=' . '"' . $this->esc($g[$fields[$i]['Field']]).'",';
					}
					$this->dbrevert[$this->dbrevert_counter]=substr($this->dbrevert[$this->dbrevert_counter],0,-1) . ' WHERE ' . $autoinc['field']['Field'] . '=' . $g[$autoinc['field']['Field']];
				}
				$rev_ok=true;
			}
		}
		$query='UPDATE `' . $table . '` SET ' . $what . ' WHERE ' . $WHERE;
		if ( $this->database->query($query) ) {
			if ( $rev_ok )
				$this->dbrevert_counter++;
                        return true;
                } else {
                        return false;
		}
	}

	
	function getEnumValues($table, $col) 
	{
		$EnumColum = $this->query('SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME="' . $table . '" AND COLUMN_NAME="' . $col.'"', true); 
		$subs = substr($EnumColum[0]['COLUMN_TYPE'],5,-1);
		$subs = str_replace('\'','',$subs);
		$newArray = explode(',',$subs);
		return $newArray;
	}


	function query($query, $fetch = false)
	{
		// not recommended - no revert etc... //
		if ( !$fetch ) {
			return $this->database->query($query);
		} else {
			if( $dban=$this->database->query($query) ) {
				$i=0;
				while ( $dbpos=$dban->fetch_assoc() ) {
					$result[$i]=$dbpos;
					$i++;
				}
				if ( isset($result) ) {
					return $result;
				} else {
					return false;
				}
			}
		}
	}

	function revert($user_revert=null)
	{
//		throw new exception('asdasdasdadasdasdasd',124546754);
		if ( !empty($user_revert) ) 
			$revert_data=$user_revert;
		elseif ( $this->dbrevert_counter == 0 )
			return true;
		else
			$revert_data=$this->dbrevert;

		$err_no=0;
		//print_r($this->dbrevert);
		$size=count($revert_data)-1;
		for ( $i=$size; $i>=0; $i-- ) {
			$query=$revert_data[$i];
			if ( $this->database->query($query) ) {
				continue;
			} else {
				$revert_err[$err_no]=$query;
				++$err_no;
				//TODO: mailto
				throw new Exception('Could not revert database changes ...'."\n".$query, 91);
			}
		}
		if ( empty($user_revert) ) {
			unset($this->dbrevert);
			$this->dbrevert_counter=0;
		}
		if ( isset($revert_err) ) { 
			//TODO: error handling //
			print_r($revert_err);
			return false;
		} else {
			return true;
		}
	}

	function esc($string)
	{
		if ( !is_array($string) )
			return $this->database->real_escape_string($string);
		
		else
			foreach ( $string as $s )
				$ret=$this->database->real_escape_string($s);
		return $ret;
	}

	function get_revert_data()
	{
		return $this->dbrevert;
	}
	
	function getTableFields($table)
	{
		$query = 'DESCRIBE `' . $table . '`';
		if ( ! $query = $this->database->query($query) ) {
			throw new Exception('Table ' . $table . ' could not be described...', 91);
		}
                $fields=Array();
                $i=0;
                while( $row = $query->fetch_assoc() ) {
	             	$fields[$i]['Type']=$row['Type'];
		        $fields[$i]['Field']=$row['Field'];
			$fields[$i]['Extra']=$row['Extra'];
	        	++$i;
                }
		if ( !isset($fields[0]['Type']) )
			return false;
		else
			return $fields;
	}

	protected function findAIField($table, $fields=null)
	{
		if ( empty($fields) )
			if ( !$fields=$this->getTableFields($table) )
				return false;

		$size=count($fields);
		for ( $i=0; $i<$size; $i++ ) {
			if ( strpos($fields[$i]['Extra'], 'auto_increment') !== false ) {
				return Array( 'field_number' => $i, 'field' => $fields[$i] );
			}
		}
		return false;
	}

}
?>

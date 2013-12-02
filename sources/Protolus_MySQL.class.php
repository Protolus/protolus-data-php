<?php
    require_once(dirname(__FILE__).'/../Protolus_DataSource.class.php');
    class Protolus_MySQL extends Protolus_DataSource{
        
        public static $debug = false;
    
        public static function SQLType($column, $options, $object){
            $comment = $options['comment'];
            $identifier = $options['identifier'];
            $required = $options['required'];
            $size = $options['size'];
            switch(strtolower($object->type($column))){
                case 'binary' :
                    $type = 'LONGBLOB';
                    break;
                case 'integer' :
                    $type = 'INT';
                    break;
                case 'float' :
                case 'instant' :
                    $type = 'DATETIME';
                    break;
                case 'string' :
                default :
                    if(!$size) $size = 255;
                    if($size < 256) $type = 'VARCHAR('.$size.')';
                    else if($size > 256){
                        $isSmall = false;
                        if($size < 65536){
                            if($size < 16777216){
                                if($size < 4294967296){
                                    $type = 'LONGTEXT';
                                }else throw new Exception('String length is too large('.$size.' bytes)');
                            }else $type = 'MEDIUMTEXT';
                        }else $type = 'TEXT';
                    }else $type = 'TINYTEXT';
            }
            $sql = '`'.$column.'` '.$type.($required?' NOT NULL':'').($identifier?' AUTO_INCREMENT PRIMARY KEY':'').$comment;
            return $sql;
        }
		
        public function __construct($options){
			$server = $options['host'];
			$login = $options['user'];
			$password = $options['password'];
			$dbname = $options['database'];
			
			if(!isset($options['mode'])) $options['mode'] = 'mysql';
            switch(strtolower($options['mode'])){
                case 'mysql' :
                   $mode = "mysql";
                    break;
                case 'mysqli' :
					$mode = "mysqli";
					MySQLData::$mysql_iMode = true;
				break;
                default : throw new Exception('unsupported MySQL connection mode('.$options['mode'].')!');
            }

	        if(Protolus_Data::$debug) Protolus_Data::$logger->log('Creating connection to  '.$server.'@'.$dbname.' with user '.$login.'.<br/>');
	        if($mode == "mysqli"){
	            //$link = mysqli_init();
	            //mysqli_real_connect($link, $server, $login, $password, $dbname);
	            $link = mysqli_connect($server, $login, $password, $dbname);
	            //mysql_select_db($link, $dbname);
	            mysqli_query('set names "utf-8"');
	            mysqli_query('set character set "utf8"');
	            mysqli_query('set character_set_server="utf8"');
	            mysqli_query('set collation_connection="utf8_general_ci"');
	        }else{

	            if(count($_SERVER["argc"]) < 2){ //this is a web request
	                $link = mysql_connect($server, $login, $password, TRUE);
	            }else{ //this is a commandline request
	                $link = odbc_connect($server, $login, $password, TRUE);
	            }
	            if(!mysql_select_db($dbname, $link)){
	                echo('database '.$dbname.' could not be selected!');
	                exit();
	            }
	        }
	        if (!$link){
	            throw new Exception("Error connecting to database!");
	            return false;
	        }
	
		        $this->link = $link;

            if($options['name']){
                Protolus_Data::$datasources[$options['name']] = $this;
                if(Protolus_Data::$debug) Protolus_Data::$logger->log('Initialized the DB('.$options['database'].' @ '.$options['host'].') as \''.$options['name'].'\'');
            }else{
                //todo: warn
                if(Protolus_Data::$debug) Protolus_Data::$logger->log('Initialized the DB('.$options['database'].' @ '.$options['host'].') without registering it');
            }
        }
	
	   //TODO: experimental js function to stored procedure translator (func_name + src_hash) to perform a limited sort of distributed search on shards
        public function query($type, $predicate, $incomingOptions=false){
            if(!$incomingOptions) $incomingOptions = Array();
            if(!$this->options) $this->options = Array();
            $options = array_merge($incomingOptions, $this->options);
	        $fieldSelector = (!$options['return']?'*':implode($options['return'], ','));
	        if( $options['count'] ) {
	            $statement = 'SELECT SQL_CALC_FOUND_ROWS '.$fieldSelector.' FROM '.$type;
	        } else {
	            $statement = 'SELECT '.$fieldSelector.' FROM '.$type;
	        }

	        $statement .= ' WHERE ';
	        if($options['active']){
	            $statement .= $options['active']." =".(
	                $options['active_value']?"'".$options['active_value']."'":'true'
	            )." AND";
	        }
	        $statement .= $this->buildWhere($predicate);
	        if($options['order']){
	            $statement .= 'ORDER BY '.$options['order'].' '.($options['direction']?' '.$options['direction']:' ASC');
	        }
	        if($options['limit']){
	            $statement .= ' LIMIT '.$options['limit'];
	        }else if($options['paginate']){
	            //todo
	        }
	        $dummy = Protolus_Data::$objectTypes[$type];
	        return Protolus_MySQL::executeSQL($statement, $dummy, $this->link);
        }

        protected function initialize(){ //generate new table
            $fields = array_merge(Data::$core_fields, $object->fields);
            $statement = 'CREATE TABLE '.type." (\n";
            foreach($fields as $index=>$field){
                $statement .= $object->SQLType($field, $object->getFieldOptions($field, $object), $object).
                    ( (($index+1) != sizeOf($fields))?", \n":"\n" );
            }
            $statement .= ' '.")\n";
            Protolus_Data::$logger->log('SQL table initialized '.$statement);
            MySQLData::executeSQL($statement);
        }
        
        public function expandType($type, $column, $object){
            $sql = 'ALTER TABLE `'.$type.'` ADD COLUMN '.$object->SQLType($column, $object->getFieldOptions($column, $object), $object);
            Protolus_Data::$logger->log('SQL table initialized '.$statement);
            MySQLData::executeSQL($sql);
        }
        
        public function save($items){
            $returnIDs = Array();
			foreach($items as $object){
			    $data = $object->changed();
			    if(!$object->new){
			        $setters = Array();
			        foreach($data as $field => $value){
			            if (get_magic_quotes_gpc()) $value = stripslashes($value);
                        $update .= $sep. '`' . $field . '`' ."='".mysql_real_escape_string($value)."' ";
                        if($sep == '') $sep = ', ';
			            $setters[] = '`' . $field . '`' ."='".mysql_real_escape_string($value)."' ";;
			        }
			        $statement = 'UPDATE '.$object->type.' SET '.implode(', ', $setters);
			        $statement .= ' WHERE '.$object->primaryKey." = '".$object->get($object->primaryKey)."'";
	                Protolus_MySQL::executeSQL($statement, $object, $this->link);
	                $returnIDs[] = $object->get($object->primaryKey);
			    }else{
			        $names = '';
	                $values ='';
	                $sep='';
			        foreach($data as $field => $value){
			            if (get_magic_quotes_gpc()) $value = stripslashes($value);
			            $values .= $sep."'".mysql_real_escape_string($value)."' ";
	                    $names .= $sep . '`' . $field . '`';
	                    if($sep == '') $sep = ', ';
			        }
			        $statement = 'INSERT INTO '.$object->type.' ( '.$names.' ) VALUES ( '.$values.' )';
			        Protolus_MySQL::executeSQL($statement, $object, $this->link);
			        $object->new = false;
			        $returnIDs[] = mysql_insert_id($this->link);
			    }
			}
			return $returnIDs;
        }
        
        public function delete($items){
            $returnIDs = Array();
			foreach($items as $object){
			    $data = $object->changed();
			    if(!$object->new){
			        $statement = 'DELETE FROM '.$object->type.' ';
			        $statement .= ' WHERE '.$object->primaryKey." = '".$object->get($object->primaryKey)."'";
	                Protolus_MySQL::executeSQL($statement, $object, $this->link);
			    }else{
			        //do nothing, it's not in the db
			    }
			    //$returnIDs[] = mysql_insert_id($this->link);
			}
			return $returnIDs;
        }
        
        public function buildWhere($parsedWhere){
            $phrase = WhereParser::construct($parsedWhere);
            return $phrase;
        }
	
	    public static function executeSQL($statement, $requestingObject, $database=null, $depth=0){
	        // /*  <- uncomment to shut off queries and output SQL
	        if(Protolus_MySQL::$debug){
	            Protolus_Data::$logger->log($statement.'<br/>');
	            //$start = Logger::processing_time();
	        }
	        if(!$database) throw new Exception("SQL Error:" . 'DB Link is not set!');
			/*
	        if(MySQLData::$masterSlaveMode){
	          $isSelect = preg_match('~^[ ]*[sS][eE][lL][eE][cC][tT] ~', $statement);
	          if($isSelect){
	              // we have a select statement so we are using the read-only DB
	              $database = MySQLData::$slaveDB;
	              if(!isset(MySQLData::$db)) throw new Exception("SQL Error:" . 'Slave DB Link is not set!');
	              Protolus_Data::$logger->log('Using the Slave DB Link');
	          }else{
	              Protolus_Data::$logger->log('Using the Master DB Link['.print_r($isSelect, true).']');
	          }
	        }
			*/
	        try{
	            $results = array();
	            if(! ( $SQLResult = mysql_query( $statement, $database ) ) ) {
	                throw new Exception("SQL Error:" . mysql_error($database));
	            }
	            $affected_rows = mysql_affected_rows( $database );
	            if($SQLResult === true){
	                if(Protolus_MySQL::$debug) Protolus_Data::$logger->log('Query has no results<br/><br/>');
	                return array(); //if there are no results return here
	            }	            
	            //get the results (we don't support streaming sets)
		        if($database->mysql_iMode) while ($row = mysqli_fetch_assoc($SQLResult) ) $results[] = $row;
		        else while($row = mysql_fetch_assoc($SQLResult) ) $results[] = $row;
	            if(Protolus_MySQL::$debug){
	                //$time = Logger::processing_time($start);
	                Protolus_Data::$logger->log('Query has '.count($results).' results in '.$time.' seconds.<br/>');
	            }
	            if($database->mysql_iMode){

		        }else{
		            mysql_free_result($SQLResult);
		        }
	            return $results;
	        }catch(Exception $ex){ //ERROR Handling
                if(Protolus_MySQL::$debug){
			        if($database->mysql_iMode){
                        
			        }else{
			            Protolus_Data::$logger->log(mysql_error($database).'<br/><br/>'.$statement.'<br/><br/>');  
			        }
				}
				$message = $ex->getMessage();
				echo($message);
				exit();
                if(preg_match("~SQL Error:Table '.*?\.dt_link-(.*?)-(.*?)' doesn't exist~", $message, $matches)){   // let's see if we are missing a link table
                    //TODO: implement linking
                    //if(Datasource::$debug) Protolus_Data::$logger->log('Link Store from '.$matches[1].' to '.$matches[2].' does not exist, the MySQLDatasource is creating one!<br/>');
                    //MySQLDatasource::initializeLink($matches[1], $matches[2]);
                    //MySQLDatasource::executeSQL($statement, $database, true);
                }else if(preg_match("~SQL Error:Table '.*?\.(.*?)' doesn't exist~", $message, $matches)){           // let's see if we are missing a storage table
                    if(Protolus_MySQL::$debug) Protolus_Data::$logger->log('Object Store for '.$matches[1].' does not exist, the MySQLData source is creating one!<br/>');
                    MySQLData::initializeType($matches[1], $requestingObject);
                    MySQLData::executeSQL($statement, $requestingObject, $database, true);
                }else if(preg_match("~SQL Error:Unknown column '(.*?)' in~", $message, $matches)){           // let's see if we are missing a storage table
                    $column = $matches[1];
                    //get the table
                    if($table = (preg_match("~^INSERT INTO ([a-zA-Z0-9_-]*) \\(.*$~", $statement, $matches))? $matches[1] :
                        (preg_match("~.*FROM `([a-zA-Z0-9_-]*)` WHERE.*$~", $statement, $matches)) ? $matches[1] : false
                    ){
                        MySQLData::expandType($table, $column, $requestingObject);
                        MySQLData::executeSQL($statement, $requestingObject, $database, true);
                    }else{
                        //todo: handle nicely
                    }
                }else throw $ex;
	        }
	    }
    }
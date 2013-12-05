<?php
    require_once(dirname(__FILE__).'/../Protolus_DataSource.class.php');
    class Protolus_Mongo extends Protolus_DataSource{
        
        public static $debug = false;
        protected static $functionalMode = false;
		
        public function __construct($options){
			$server = $options['host'];
			$login = $options['user'];
			$password = $options['password'];
			$dbname = $options['database'];
			
                $location = 'mongodb://'.($options['host']?$options['host']:'localhost').':'.($options['port']?$options['port']:'27017');
                try{
                    $connection = new Mongo($location);
                }catch(Exception $ex){
                    echo('{{{'.$ex->getMessage().'}}}');
                }
            if(isset($options['database'])){
                $databaseName = $options['database'];
            }else{
                $databaseName = 'default_db';
            }
            $db = $connection->selectDB($databaseName);
            if($options['name']){
                Protolus_Data::$datasources[$options['name']] = $this;
                Protolus_Data::$logger->log('Initialized the DB('.$db.' @ '.$options['host'].') as \''.$options['name'].'\'');
            }else{
                //todo: warn
                Protolus_Data::$logger->log('Initialized the DB('.$db.' @ '.$options['host'].') without registering it');
            }
	
		    $this->link = $db;

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
	        
            $dummyObject = Protolus_Data::$objectTypes[$type];
            $primary_key = $dummyObject->primaryKey;
            try{
                $operatorMapping = array(
                    '=' => '==',
                    '>' => '>',
                    '<' => '<',
                    '=>' => '=>',
                    '=<' => '=<',
                    '!=' => '!=',
                    '<>' => '!=',
                );
                $discriminants = $predicate;
                $discText = array();
                if(Protolus_Mongo::$functionalMode){
                    foreach($discriminants as $discriminant){
                        if($discriminant[0] == $primary_key){
                            $discText[] = 'this.'.$primary_key.' '.$operatorMapping[trim($discriminant[1])].' '.(is_numeric($discriminant[2]) ? $discriminant[2] : "'".$discriminant[2]."'");
                        }else{
                            $discText[] = 'this.'.$discriminant[0].' '.$operatorMapping[trim($discriminant[1])].' '.$discriminant[2];
                        }
                    }
                    //this is totally naive, but will work fine for simple selection
                    $array = array();
                    $js = 'function(){ return '.implode(' && ', $discText).'; }';
                    $collection = $db->$type;
                    $cursor = $collection->find(array('$where' => $js));
                    $this->lastQuery = $js;
                    $array = iterator_to_array($cursor);
                    return $array;
                }else{
                    //the new way
                    $where = array();
                    foreach($discriminants as $discriminant){
                        if(preg_match('~[\'"](.*)[\'"]~', $discriminant[2], $matches)){
                            $discriminant[2] = $matches[1];
                        }
                        if($discriminant[0] != '') $where[$discriminant[0]] = $discriminant[2];
                    }
                    $collection = $this->link->$type;
                    $cursor = $collection->find($where);
                    //$cursor = $collection->find();
                    $this->lastQuery = '$collection->find('.print_r($where, true).')';
                    $array = iterator_to_array($cursor);
                    return $array;
                }
            }catch(Exception $ex){
                Protolus_Data::$logger->log('There was a Mongo error['.$ex->getMessage().'] from query :'.$this->lastQuery);
                echo '<PRE>' . __FILE__ . '>' . __LINE__ . ' ' . print_r($ex->getMessage(),true) . '</PRE>';
            }
	        return $array;
        }

        protected function initialize(){ }
        
        public function expandType($type, $column, $object){ }
        
        public function save($items){
            $returnIDs = Array();
			foreach($items as $object){
			    $name = $object->type;
			    $data = $object->changed();
			    if(!$object->new){
			        unset($data[$object->primaryKey]);
			        $selector = Array( $object->primaryKey => $object->get($object->primaryKey) );
			        $this->link->$name->update( $selector, $data );
			    }else{
			        $id = $this->link->$name->insert( $data );
			    }
			    $returnIDs[] = $id;
			}
			return $returnIDs;
        }
        
        public function delete($items){
            $returnIDs = Array();
			foreach($items as $object){
			    $name = $object->type;
			    $data = $object->changed();
			    if(!$object->new){
			        $this->link->$name->remove( Array( $object->primaryKey => $object->get($object->primaryKey) ) );
			    }else{
			        //do nothing, it's not in the db
			    }
			    //$returnIDs[] = mysql_insert_id($this->link);
			}
			return $returnIDs;
        }
        
    }
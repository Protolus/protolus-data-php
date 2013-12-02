<?php

require(dirname(__FILE__).'/WhereParser.class.php');

class Protolus_Data_Logger{
    public function log($message, $level=0){
        echo($message."\n");
    }
}

class Protolus_Data{

    public static $objectTypes = Array();
    public static $datasources = Array();
    public static $debug = false;
    protected static $meta;
    public static $logger;
    
    public static function enable($type){
        switch(strtolower($type)){
            case 'mysql':
                require(dirname(__FILE__).'/sources/Protolus_MySQL.class.php');
                break;
            case 'mongo':
                require(dirname(__FILE__).'/sources/Protolus_Mongo.class.php');
                break;
        }
    }
    
    public static function register($class){
        Protolus_Data::$objectTypes[strtolower($class)] = new $class();
    }
    
    public static function datasourceFor($type){
        $type = strtolower($type);
        //if(!$types[$type]) return false;
        if(Protolus_Data::$objectTypes[$type] && !Protolus_Data::$datasources[$type]){
            $type = Protolus_Data::$objectTypes[$type]->datasource;
        }
        if(!Protolus_Data::$datasources[$type]){
            return false;
        }
        return Protolus_Data::$datasources[$type];
    }
    
    public static function search($datatype, $query=null, $fields=array()){
        $source = Protolus_Data::datasourceFor($datatype);
        $queryResults = Protolus_Data::query($datatype, $query, $fields);
        $results = Array();
        foreach($queryResults as $queryResult){
            $result = new $datatype();
            $result->new = false;
            $result->data = $queryResult;
            $results[] = $result;
        }
        return $results;
    }
    
    
    public static function query($datatype, $query=null, $fields=array()){
        $source = Protolus_Data::datasourceFor($datatype);
        $parsedQuery = WhereParser::parse($query);
        $type = Protolus_Data::$objectTypes[strtolower($datatype)]->type;
        $results = $source->query($type, $parsedQuery);
        return $results;
    }
    
    public static function getFieldOptions($field, $object){
        ($comment = $object->option($field, 'comment'))?$comment:'';
        ($identifier = $object->option($field, 'identifier'))?true:false;
        ($required = $object->option($field, 'required'))?($required == 't'||$required == 'true'):false;
        ($size = (int)$object->option($field, 'size'));
        ($hidden = $object->option($field, 'hidden'));
        ($object_type = $object->option($field, 'object_type'));
        ($query = $object->option($field, 'query'));
        ($readonly = $object->option($field, 'readonly'));
        ($type = $object->option($field, 'type'));
        $options = array(
            'comment' => $comment,
            'identifier' => $identifier,
            'required' => $required,
            'size' => $size,
            'hidden' => $hidden,
            'readonly' => $readonly,
            'object_type' => $object_type,
            'query' => $query,
            'type' => $type
        );
        if($position = $object->option($field, 'position')) $options['position'] = $position;
        return $options;
    }
    
    //instance implementations
    public $data = array();
    public $types = array(); //if not set, assumption is 'string'
    public $options = array(); //[type][option_name] = value
    public $new = true;
    public $primaryKey = 'id';
    public $key_type = 'uuid'; // uuid, integer, autoincrement
    
    public function __construct($value=null, $field=null){
        $this->type = strtolower(get_class($this));
        if($value != null && !empty($value)){
            $this->data = $this->load($value, $field);
            $class = get_class($this);
            if(Protolus_Data::$debug) Logger::log('Loading '.($class::$name).' '.$value);
        }
    }
    
    public function type($column){
        if($type = $this->types[strtolower($column)]){
            return $type;
        }else if($type = Data::$core_options[strtolower($column)]['type']){
            return $type;
        }else return false;
    }
    
    public function option($column, $name){
        if( ($options = $this->options[strtolower($column)]) && ($option = $options[$name]) ){
            return $option;
        }
        if($option = Data::$core_options[strtolower($column)][$name]){
            return $option;
        }
        return false;
    }
    
    public function load($id){
        $source = Protolus_Data::datasourceFor($this->datasource);
        $results = $source->query($this->primaryKey.' = \''.$id.'\'');
        if(count($results) > 0) $this->data = $results[0];
        else throw new Exception('item not found!');
        $this->new = false;
        //todo: warn if we have more results than expected
    }
    
    public function save(){
        $source = Protolus_Data::datasourceFor($this->datasource);
        if(Protolus_Data::$meta) Protolus_Data::$meta->set($this);
        $isNew = $this->new;
        $ids = $source->save(Array($this));
        if($isNew && $ids[0]) $this->set($this->primaryKey, $ids[0]);
        $this->new = false; //just in case
    }
    
    public function delete(){
        $source = Protolus_Data::datasourceFor($this->datasource);
        $source->delete(Array($this));
        // should data be cleared?
    }
    
    public function changed(){
        return $this->data;
    }
    
    public function get($key){
        //todo: we should shortcut if there's no descention
        $parts = explode('.', $key);
        $current = $this->data;
        $currentPath = array();
        foreach($parts as $part){
            $currentPath[] = $part;
            if(!is_array($current) && !array_key_exists($part, $current)){
                $warning_text = 'Value does not exist('.implode('.', $currentPath).')!';
                switch($empty_field_mode){
                    case 'notice': //issues notice then drops through to null return 
                        trigger_error($warning_text, E_USER_NOTICE);
                    case 'null':
                        return null;
                    case 'empty':
                        return '';
                    case 'exception':
                        throw new Exception($warning_text);
                }
            }else{
                $current = &$current[$part];
            }
        }
        return $current;
    }
    
    public function set($key, $value){
        //todo: we should shortcut if there's no descention
        $parts = explode('.', $key);
        $parts = array_reverse($parts);
        $current = &$this->data;
        if(sizeof($parts) == 0) throw new Exception('Cannot set an empty value!');
        while(sizeof($parts) > 1){
            $thisPart = array_pop($parts);
            if(!is_array($current) && !array_key_exists($thisPart, $current)){
                $current[$thisPart] = array();
            }else{
                $current = &$current[$thisPart];
            }
        }
        $thisPart = array_pop($parts);
        if($value == null){
            unset($current[$thisPart]);
        }else{
            $current[$thisPart] = $value;
        }
    }
}

Protolus_Data::$logger = new Protolus_Data_Logger();

class Protolus_MetaData{

    public function __construct($options){
        $this->registry = Array();
    }
    
    public function register($field, $setter){
        $this->registry[$field] = $setter;
    }
    
    public function set($object, $field){
        if(!$field){
            $fields = array_keys($this->registry);
            $fields[] = 'modified_at';
            if(!$object->data['created_at']) $fields[] = 'created_at';
        }else{
            if($this->registry[$field]){
                $object->data[$field] = $this->registry[$field]($object->data[$field]);
            }else switch(strtolower($field)){
                case 'created_at' : 
                    $object['created_at'] = date("Y-m-d H:i:s");
                    break;
                case 'modified_at' : 
                    $object['modified_at'] = date("Y-m-d H:i:s");
                    break;
                
            }
        }
    }
}
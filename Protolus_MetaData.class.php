<?php

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
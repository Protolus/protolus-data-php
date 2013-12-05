<?php
class User extends Protolus_Data{
    function __construct(){
        $this->datasource = 'mysqldatabase';
        parent::__construct();
        $this->type = 'users';
    }
}
Protolus_Data::register(User);
class Uzer extends Protolus_Data{
    function __construct(){
        $this->datasource = 'mongodatabase';
        parent::__construct();
        $this->type = 'users';
    }
}
Protolus_Data::register(Uzer);
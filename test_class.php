<?php
class User extends Protolus_Data{
    function __construct(){
        $this->datasource = 'maindatabase';
        parent::__construct();
        $this->type = 'users';
    }
}
Protolus_Data::register(User);
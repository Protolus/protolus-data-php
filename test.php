<?php
require_once('./Protolus_Data.class.php');
//require_once('./shim.php');
//require_once(dirname(__FILE__) . '/simpletest/autorun.php');

//default mysql config, for ease
$db = 'test';
$host = 'localhost';
$username = 'root';
$pass = '';
$links = Array();
$user;

class_alias('Protolus_Data', 'Data');

class TestOfProtolus_Data extends PHPUnit_Framework_TestCase {
    
    function testLogIntoMySQL() {
        global $db, $host, $username, $pass, $links;
        Data::enable('MySQL');
        //Protolus_MySQL::$debug = true;
        new Protolus_MySQL(Array(
            'name' => 'maindatabase',
            'host' => $host,
            'user' => $username,
            'password' => $pass,
            'database' => $db
        ));
    }
    
    function testSaveObject(){
        require('./test_class.php');
        $user = new User();
        $user->set('first_name', 'Ed');
        $user->set('last_name', 'Beggler');
        $user->set('email', 'foo@bar.com');
        $user->save();
        $id = $user->get('id');
        $this->assertGreaterThan(0, $id);
    }
    
    function testSelectAndAlter(){
        $results = Data::search('User', "first_name = 'Ed'");
        $this->assertGreaterThan(0, count($results));
        foreach($results as $result){
            $result->set('email', 'doozer@fraggle.rock');
            $result->save();
        }
        $results = Data::search('User', "first_name = 'Ed'");
        $this->assertGreaterThan(0, count($results));
        $this->assertEquals('doozer@fraggle.rock', $results[0]->get('email'));
    }
    
    function testSelectAndDelete(){
        $results = Data::search('User', "first_name = 'Ed'");
        $this->assertGreaterThan(0, count($results));
        foreach($results as $result){
            $result->delete();
        }
    }
    
    function testRemovalWorked(){
        $results = Data::search('User', "first_name = 'Ed'");
        $this->assertEquals(0, count($results));
    }
}
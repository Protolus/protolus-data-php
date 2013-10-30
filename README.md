protolus-data-php
===========

A PHP data layer supporting mysql/mongo using a single SQL based query syntax

Usage
-----

First you'll need to register at least one datasource in your configuration file:

    "DB:appdata":{
        "type":"mysql",
        "database" : "appdata",
        "host" : "localhost",
        "user" : "root",
        "password" : "",
        "mode":"mysql",
        "session":"true",
        "session_hook":"php"
    }
    

The basic data pattern looks like:

    class User extends MySQLData{
        public static $fields = array(
            'email',
            'password',
            'company',
            'first_name',
            'last_name',
            'phone',
            'address',
            'city',
            'state',
            'zip',
            'country'
        );

        public static $name = 'users';

        function __construct($id = null, $field = null){
            $this->database = 'appdata';
            $this->tableName = self::$name;
            parent::__construct($id, $field);
        }
    }
    
You would use this class like this:
    
    $userObj = new User();
    $userObj->set("first_name", "Joe");
    $userObj->set("email", "test@example.com");
    $userObj->save();

    $name = $userObj->get("first_name");
    
And you would search for a set in mysql using:

    $users = Data::query("User", "first_name='joe'");
A search in mongo would look like this:
    $users = Data::query("User", array("key"=>"first_name", "operator"=>"\=", value"=>"joe"));
    
    
Direct Access
-------------

Other Datasource specific features must be accessed from the DB driver which may be accessed directly:
    $results = MySQLData::executeSQL("SELECT * FROM USER_TABLE WHERE FIRST_NAME='joe'");
   

But when you do this you are circumventing the data layer (other than letting protolus negotiate the connection for you).

If you find any rough edges, please submit a bug!

Enjoy,

-Abbey Hawk Sparrow
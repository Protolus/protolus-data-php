protolus-data.php
===========

A PHP data layer supporting mysql/mongo/rabbit using a single SQL based query syntax

Usage
-----

First you'll need to register at least one datasource:

    Data::enable('MySQL');
    new Protolus_MySQL(Array(
        'name' => 'maindatabaseid',
        'host' => 'localhost',
        'user' => 'dbuser',
        'password' => 'P455W0RD',
        'database' => 'mysqldbname'
    ));
    
or 

    Data::enable('Mongo');
    new Protolus_Mongo(Array(
        'name' => 'otherdatabaseid',
        'host' => 'localhost',
        'database' => 'mongodbname'
    ));

The basic data pattern looks like:

    class MyObject extends Protolus_Data{
        function __construct(){
            $this->datasource = 'maindatabaseid';
            parent::__construct();
        }
    }
    Protolus_Data::register(MyObject);
    
which will store data in the 'User' mysql table
    
You would use this class like this:

    $myInstance = new MyObject();
    $myInstance->set('somefield', 'somevalue');
    $myInstance->set('anotherfield', 49);
    $myInstance->save();
    
And you would search for a set using:

    Data::search('MyObject', "somefield == 'searchvalue' || (somefield > 24 && somefield < 38)");
    
or if you only wanted the data payload (not a set of objects)

    Data::query('MyObject', "somefield == 'searchvalue' || (somefield > 24 && somefield < 38)");
    
One thing to note: This data layer is designed to discourage both streaming data sets and joins while normalizing query syntax across datasources. If you need these features or you find this level of indirection uncomfortable you should probably manipulate the DB directly and skip the whole data layer (or even better, interface with an API). 

Virtuals
--------
Sometimes you want to address a field as another field (we use this feature with mongo so you can address the primary key as 'id' rather than '_id'), but you want that reference to be symbolic as far as the DB is concerned. This is simply accomplished:

    $myInstance->virtualAlias(virtualName, fieldName);
    
Direct Access
-------------

Other Datasource specific features (for example MapReduce under mongo) must be accessed from the DB driver which may be accessed directly:

    Data::Source('myAwesomeDatasource')->connection;

But when you do this you are circumventing the data layer (other than letting protolus negotiate the connection for you).

Testing
-------
Tests use mocha/should to execute the tests from root

    mocha

If you find any rough edges, please submit a bug!

Enjoy,

-The Protolus team
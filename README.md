#Seat-PHP (0.1)
PHP [CouchDB][1] wrapper: elegant and lightweight REST interface to CouchDB datastore.

----------
##License
MIT - See [LICENSE][2]

----------
##Getting Started
1. **Install dependencies**

    $ sudo pear install Net_URL2-0.3.1

    $ sudo pear install HTTP_Request2-0.5.2

2. **Instantiate class**
	
	require_once('seat.php');

**Without username/password**

	$db = new Seat('http://localhost:5984/example-db');
	
	$db = new Seat('example-db');
	
**With username/password**

	$db = new Seat('http://username:password@localhost:5984/example-db');
		
	$db = new Seat('example-db', 'username', 'password');
		
*Note: By default, Seat will use host localhost, port 5984, and does not check for the existence of the database you specify.*

----------
##Usage Basics
###Check to see if database exists

	$db->get();

If database does not exist, Seat will return:

	stdClass Object
	(
	    [error] => not_found
	    [reason] => no_db_file
	)

###Create a new database
	
	$db->put();

Return result:

	stdClass Object
	(
	    [ok] => 1
	)
	
*Note: The database you wish to create is defined upon instantiation; see Getting Started.*

Now that our database exists, $db->get(); should return something like this:

	stdClass Object
	(
	    [db_name] => example-db
	    [doc_count] => 0
	    [doc_del_count] => 0
	    [update_seq] => 0
	    [purge_seq] => 0
	    [compact_running] => 
	    [disk_size] => 79
	    [instance_start_time] => 1286489228968637
	    [disk_format_version] => 5
	    [committed_update_seq] => 0
	)

###Working with documents

**Create a new document**

With a database created, let's put our first document in the database.

	$doc = array(
			'_id'=>'users.kennypowers',
			'type'=>'user',
			'username'=>'kennypowers'
		);
	
	$db->put($doc);
	
Return result:

	stdClass Object
	(
	    [ok] => 1
	    [id] => users.kennypowers
	    [rev] => 1-67804b626c2fecc05930163787bd691a
	)

**Retrieve, change, and update a document**

	$doc = $db->get('users.kennypowers'); //retrieve
	
	$doc->number = 55; //add field
	
	$db->put($doc); //update
	
Return result:

	stdClass Object
	(
	    [ok] => 1
	    [id] => users.kennypowers
	    [rev] => 2-43b83da320e429919c9b9519bd1f1694
	)
	
Finally, it's easy to add another document to an existing database. Let's add another user.

	$doc = array(
			'_id'=>'users.steviejanowski',
			'type'=>'user',
			'username'=>'steviejanowski'
		);
		
	$db->put($doc);
	
**Deleting a document**

	$user = $db->get('users.kennypowers'); //retrieve
	$db->delete($user); //delete
	
Return result:

	stdClass Object
	(
	    [ok] => 1
	    [id] => users.kennypowers
	    [rev] => 3-b413192ae1ea1b52063fd9f72cf25ab9
	)
	
----------
##Working With Views

**Introduction**

>Views are the primary tool used for querying and reporting on CouchDB documents.

With this in mind, Seat makes it easy to use views. In the same directory as seat.php there is a "views" folder. 
These views can be pushed to the database, created simply by adding a *map.js* and an optional *reduce.js* within the following directory structure.

	./views/[database-name]/[design-document]/[view]/map|reduce.js

*Note: As of v0.1 there is currently no function to pull views from the database, but this should be available in the future.*

**Creating a view document**

Using our example database (example-db), let's create a view (by_username) in order to retrieve users (users) by their username.

Define the map function in ./views/example-db/users/by_username/map.js

	function (doc) {
		if (doc.type == 'user') {
			emit(doc.username, doc);
		}
	}
	
Push this view to the database:

	$db->pushViews();
	
This will return an array of any views that were updated:

	Array
	(
	    [0] => stdClass Object
	        (
	            [_id] => _design/users
	            [_rev] => 1-4324321b4ddc8a13e9086b0391f4d224
	            [language] => javascript
	            [views] => stdClass Object
	                (
	                    [by_username] => stdClass Object
	                        (
	                            [map] => function (doc) {
									if (doc.type=='user') {
										emit(doc.username, doc);
									}
								}
	                        )
	                )
	        )
	)

**Retrieving data using views**

Having created and pushed our view to CouchDB, we can use this view to retrieve users by username (using a little PHP magic of course).

	$db->users->by_username('key="kennypowers"');
	
	//or similarly, by passing an array
	
	$db->users->by_username(array(
			'key'=>'"kennypowers"'
		));

Return result:

	stdClass Object
	(
	    [total_rows] => 2
	    [offset] => 1
	    [rows] => Array
	        (
	            [0] => stdClass Object
	                (
	                    [id] => users.kennypowers
	                    [key] => kennypowers
	                    [value] => stdClass Object
	                        (
	                            [_id] => users.kennypowers
	                            [_rev] => 2-43b83da320e429919c9b9519bd1f1694
	                            [type] => user
	                            [username] => kennypowers
	                            [number] => 55
	                        )
	                )
	        )
	)

----------
##Extended Functionality

Since Seat makes use of REST, it's possible to send POST requests to a path relative to the database.

For example, if we want to compact the database:
	
	$db->post('_compact');
	
Return result:

	stdClass Object
	(
	    [ok] => 1
	)
	
*Note: At this point, all requests are relative to the database defined when you instantiate the class.  In the future we're hoping to add support for non-database specific requests.*

For example:

	Seat::get('_all_dbs');

  [1]: http://couchdb.apache.org/
  [2]: http://github.com/stackd/seat-php/blob/master/LICENSE
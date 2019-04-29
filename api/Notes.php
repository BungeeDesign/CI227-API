<?php
// REST API for commenting on the V&A Musem Object API. Designed & Deveoped by James Rogers with ❤
class Notes {
    // DB Paramaters
    private $host = 'localhost';
    private $dbName = 'jsr24_museum_objects';
    private $username = 'root';
    private $password = ''; // Dev
    private $db = null;
    private $dbFail = false;
    // Comment results array
    private $comments_data = [];
    
    // Contructor contains the PDO Database connection
    // This is also known as a magic function and will run upon instantiating the Notes Object
    public function __construct() {
        // Create connection
        $this->db = new mysqli($this->host, $this->username, $this->password, $this->dbName);

        // Check connection
        if ($this->db->connect_error) {
            http_response_code(500);
            // Set dbFail flag to true
            $this->dbFail = true;
            exit(0); // Exit so we don't run handlerRequest()
        } else {
            http_response_code(200);
        }
    }

    // Destructor will be called when there is no more refrences to a object
    function __destruct() {
        // Nothing more to run so close the Database connection
        if ($this->dbFail) {
            // Nothing to close as DB never connected
        } else {
            $this->db->close();
        }
    }

    // Handle the request. 
    function handleRequest() {
        if($_SERVER['REQUEST_METHOD'] === 'GET') {
            // It is a GET request we want to call getComments() function to get the comment/s
            $this->getComments();
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // It is a POST request so we want to call the sanitize function to validate the incoming data before making the POST createComment() request
            $this->sanitize();
        } else {
            // If none of the above request types are met then we return with a 400 Response Code which indicates a bad request
            http_response_code(400);
        } 
    }

    // getComments() called if a GET request is made
    function getComments() {
        if (isset($_GET['oid'])) { // If the objectID (oid) parameter is set within the request URL ?oid=O34343
            // SQL Query - this will get the name and notes from the comments table where it matches the oid (ObjectID)
            $oid = $_GET['oid']; // Setting the oid to the oid variable instead of placing the $oid = $_GET['oid']; within the SQL statement
            $query = "SELECT name, notes FROM objects WHERE oid = '" . $oid . "'";

            // Populate the results from the above SQL query into the res (result) variable
            // We are passing in the mysqli_result Object which contains the field count and number of rows (num_rows)
            $res = $this->db->query($query);

            // Check the mysqli_result Object in $res to see if there are any results in the DB agaist the requested ObjectID 
            if ($res->num_rows) {
                while($row = $res->fetch_assoc()) {
                    extract($row); // Extract each row to access the data
                    $comment_item = array(
                      'name' => $name,
                      'notes' => $notes
                    );
                    // Push to "comments_data" array
                    array_push($this->comments_data, $comment_item);
                }
                // Set correct headers for & Turn to JSON & Output
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode($this->comments_data, JSON_PRETTY_PRINT);
            }
        }
    }

    // Sanitizeation Function this will check POST data for comment creation
    function sanitize() {
        // If all parameters are set within the request payload (form data)        
        if (isset($_POST['oid'], $_POST['name'], $_POST['notes'])) {
            
            // For extra security we could use
            // $oid = mysqli_real_escape_string($this->db, $_POST['oid']);
            // This stop SQL Injection by escaping ' characters
            // However as we are using mysqli along with prepared statements which we bind the values to it is not needed

            // Set our params to our local variables as we need to pass to the createComments if data is valid
            $oid = $_POST['oid'];		
            $name = $_POST['name'];  	
            $notes = $_POST['notes'];
            
            // Check for NULL values
            if ($oid == null || $name == null || $notes == null) {
                // No NULL values are accepted so return a 400 (Bad Request)
                http_response_code(400);
            } else {
                // Values are Not NULL so final check for HTML Chars if present remove via strip_tags
                $oid = htmlspecialchars(strip_tags($oid));
                $name = htmlspecialchars(strip_tags($name));
                $notes = htmlspecialchars(strip_tags($notes));
    
                // Once data has been stripped of any HTML Chars call createComments()
                $this->createComments($oid, $name, $notes);
            }
        } else {
            // Missing parameters so return a 400
            http_response_code(400);
        }
    }

    function createComments($oid, $name, $notes) {
        // Data is all valid now INSERT the data to the Database
        // Create query then prepare and bind
        $stmt = $this->db->prepare("INSERT INTO objects (oid, name, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $oid, $name, $notes);

        // Execute query
        if ($stmt->execute()) {
            // If succsessful return 201 (Ok, Content Created)
            http_response_code(201);
        } else {
            // If query has failed return 500
            http_response_code(500);
        }

        // Close after complete
        $stmt->close();
    }
}

// Instantiate Notes
$api = new Notes();
// Call hdanleRequest() function to handle the incoming request from the client
$api->handleRequest();
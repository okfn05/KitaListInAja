<?php

class Database {
    private $host = "localhost";
    private $db_name = "kitalistinaja";
    private $username = "root";
    private $password = "";    
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

class Task {
    private $conn;
    private $table_name = "tasks";

    public $id;
    public $user_id;
    public $title;
    public $category;
    public $priority;
    public $status;
    public $due_date;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    function readAll() {
        $query = "SELECT t.*, u.username 
                 FROM " . $this->table_name . " t
                 LEFT JOIN users u ON t.user_id = u.id
                 WHERE t.user_id = :user_id
                 ORDER BY t.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        return $stmt;
    }

    function create() {
        $query = "INSERT INTO " . $this->table_name . "
                 SET title=:title, category=:category, priority=:priority, 
                     status=:status, due_date=:due_date, user_id=:user_id";

        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        $this->status = htmlspecialchars(strip_tags($this->status));

        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    function update() {
        $query = "UPDATE " . $this->table_name . "
                 SET title=:title, category=:category, priority=:priority, 
                     status=:status, due_date=:due_date
                 WHERE id=:id AND user_id=:user_id";

        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->priority = htmlspecialchars(strip_tags($this->priority));
        $this->status = htmlspecialchars(strip_tags($this->status));


        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    function getStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo
                 FROM " . $this->table_name . " 
                 WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}


class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $email;
    public $password;

    public function __construct($db) {
        $this->conn = $db;
    }

    function login($username, $password) {
        $query = "SELECT id, username, email, password FROM " . $this->table_name . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password'])) {
                return $row;
            }
        }
        return false;
    }
}

?>
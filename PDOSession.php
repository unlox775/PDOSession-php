<?php

/********************
 *
 * PDOSession - Simple-vanilla database sessioning with PDO
 *
 * 2015 by Dave Buchanan - http://joesvolcano.net/
 *
 * GitHub: https://github.com/unlox775/PDOSession-php
 *
 ********************/

class PDOSession {
    public static $pdo;
    public static $table = 'session';

    public function __construct($db) {
        // Get a database connection
        self::$pdo = $db;

        // Start session
        session_set_save_handler(array(__CLASS__, '_open'),
                                 array(__CLASS__, '_close'),
                                 array(__CLASS__, '_read'),
                                 array(__CLASS__, '_write'),
                                 array(__CLASS__, '_destroy'),
                                 array(__CLASS__, '_gc'));
        session_start();
    }

#    public function __destruct()
#    {
#        session_write_close();
#    }

    public static function fetchSession($id) {
        $stmt = self::$pdo->prepare('SELECT id, data FROM '.self::$table.' WHERE id = :id');
        $stmt->execute(array(':id' => $id));
        $sessions = $stmt->fetchAll();

        return empty($sessions) ? false : $sessions[0];
    }

    public static function _open($savePath, $sessionName) { return true; }

    public static function _close() { return true; }

    public static function _read($id) {
        $session = self::fetchSession($id);
        return ($session === false) ? false : $session['data'];
    }

    public static function _write($id, $sessionData) {
        $session = self::fetchSession($id);

        if($session === false) {
            $stmt = self::$pdo->prepare('INSERT INTO '.self::$table.' (id, data, modified, lifetime) VALUES (:id, :data, :time, 604800)');
        } else {
            $stmt = self::$pdo->prepare('UPDATE '.self::$table.' SET data = :data, modified = :time WHERE id = :id');
        }
        $stmt->execute(array(
                        ':id' => $id,
                        ':data' => $sessionData,
                        ':time' => time()
                        ));
    }

    public static function _destroy($id) {
        $stmt = self::$pdo->prepare('DELETE FROM '.self::$table.' WHERE id = :id');
        $stmt->execute(array(':id' => $id));
    }

    public static function _gc($maxlifetime) {
        $stmt = self::$pdo->prepare('DELETE FROM '.self::$table.' WHERE modified < :time');
        $stmt->execute(array(':time' => (time() - (int) $maxlifetime)));
    }
}

if ( class_exists('Zend_Session_SaveHandler_Interface') ) {
	class PDOSession__ZendHandler implements Zend_Session_SaveHandler_Interface {
	    public function __construct($db) {
	        PDOSession::$pdo = $db;
	    }
	    public function open($savePath, $sessionName) { return PDOSession::_open($savePath, $sessionName); }
	    public function close()                       { return PDOSession::_close(); }
	    public function read($id)                     { return PDOSession::_read($id); }
	    public function write($id, $sessionData)      { return PDOSession::_write($id, $sessionData); }
	    public function destroy($id)                  { return PDOSession::_destroy($id); }
	    public function gc($maxlifetime)              { return PDOSession::_gc($maxlifetime); }
	}
}

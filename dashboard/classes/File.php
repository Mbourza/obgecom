<?php 

class File {

	private $_db;

	public function __construct(){

		$this->_db = DB::getInstance();
	}

	public function create($table, $fields = array()){

		if(!$this->_db->insert($table, $fields)){

			throw new Exception('There Was A Problem uploadin to '.$table);
		}
	}
}

?>
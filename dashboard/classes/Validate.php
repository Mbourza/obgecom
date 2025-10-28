<?php 


class Validate{

	private $_passed = false,
	        $_errors = array(),
	        $_db = null;

	public function __construct(){

		$this->_db = DB::getInstance();
	}

	public function check($source, $items = array()){

		foreach($items as $item => $rules){

			foreach($rules as $rule => $rule_value){

				$value = trim($source[$item]);
				$item  = escape($item);

				if($rule === 'required' && empty($value)){

					$this->addError("{$item} is required");

				}else if(!empty($value)){

					switch($rule){

						case 'min':

						if($value < $rule_value){

							$this->addError("{$item} must be at least {$rule_value} characters");
						}
						break;

						case 'max':

						if($value > $rule_value){

							$this->addError("{$item} must be less than {$rule_value} characters");
						}
						break;

						case 'unique':

						$check = $this->_db->get($rule_value, array($item, '=', $value));

						if($check->count()){

							$this->addError("this {$item} belong to someone  else..");
						}
						break;
					}
				}
			}
		}

		if(empty($this->_errors)){

			$this->_passed = true;
		}

		return $this;
	}

	private function addError($error){

		$this->_errors[] = $error;
	}
	public function errors(){

		return $this->_errors;
	}
	public function passed(){

		return $this->_passed;
	}
}


?>
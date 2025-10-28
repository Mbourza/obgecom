<?php 

class Config{

    /**
     * @param null $path
     * @return bool
     */
    public static function get($path = null){

		if(isset($path)){

			$config = $GLOBALS['config'];
			$path = explode('/', $path);

			foreach ($path as $bit) {

				if(isset($config[$bit])){

					$config = $config[$bit];
				}
			}
			return $config;
		}
		return false;
	}
}


?>
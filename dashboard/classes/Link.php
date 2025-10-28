<?php 

class Link {

    public static function checkLink($cookie, $id) {

        $valid = false;
        
        if(isset($_COOKIE[$cookie]) && $_COOKIE[$cookie] == $id) {
            $valid = true;
        } else {

            $expiration = time() - 3600; 
            $path = "/";

            setcookie($cookie, $id, $expiration, $path);

        } return $valid;
    }

    public static function UnsetAllFromCookies($selected) {

        $keys = array_keys($_COOKIE);

        foreach($keys as $key){

            if($key != "" && $key != $selected) {
                unset($_COOKIE[$key]);
            }
        }
    }
}?>
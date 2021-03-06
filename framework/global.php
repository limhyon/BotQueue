<?
  /*
    This file is part of BotQueue.

    BotQueue is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    BotQueue is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with BotQueue.  If not, see <http://www.gnu.org/licenses/>.
  */

	// figure out the base dir, we're two directories past the base dir
	// so just pop them off the end
	$parts = explode("/", __FILE__);
	array_pop($parts);
	array_pop($parts);
	$base_dir = join('/', $parts);
	
	//define some useful constants.
	$constants = array(
		'HOME_DIR'			=>	$base_dir,
		'WEB_DIR' 			=>	$base_dir.'/web/', 
		'BASE_DIR' 			=>  $base_dir.'/framework/',
		'EXTENSIONS_DIR' 	=>  $base_dir.'/extensions/',
		'CLASSES_DIR' 		=>  $base_dir.'/classes/',
		'VIEWS_DIR'			=>	$base_dir.'/views/',
		'CONTROLLERS_DIR'	=>	$base_dir.'/controllers/',
		'MODELS_DIR'		=>	$base_dir.'/models/',
	);
	foreach($constants as $c => $v)
		define($c,$v);
	
	//simply include all our files...
	include(BASE_DIR . "model.php");
	include(BASE_DIR . "view.php");
	include(BASE_DIR . "controller.php");
	include(BASE_DIR . "collection.php");
	include(BASE_DIR . "db.php");
	include(BASE_DIR . "exceptions.php");
	include(BASE_DIR . "file.php");
	include(BASE_DIR . "cachebot.php");
	
	// create our own loader class
	class BotQueue_Loader {  	
  	static public function __autoload($class)
  	{
  		$class = strtolower($class);
		
  		$file = MODELS_DIR . "$class.php";
  		if (is_file($file))
  		{
  			include($file);
  			return true;
  		}
		
  		$file = CLASSES_DIR . "$class.php";
  		if (is_file($file))
  		{
  			include($file);
  			return true;
  		}
		
  		return false;
  	}	
	}
	
	spl_autoload_register(array('BotQueue_Loader', '__autoload'));
?>

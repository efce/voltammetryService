<?php

include 'config.php'
include 'revertdb.class.php';
include 'sessionManager.class.php';
include 'DataHandler.class.php';

class mainPage { 

	global $dbHost, $dbUser, $dbPass, $dbPrefix, $dbDatabase;
	protected $client_ip, $uri, $PageName, $dbpages, $PageFull, $PageStruct, $Layout, $Footer, $Modules, $notices, $loginStyle;
	public $sesMan;
	public $db;
	public $dh;
	public $dbprepend = $dbPrefix.'_';
	public $plotDir = './user_plots';
	private static $thisInstance = null;

	private function __construct() {
		$this->db=new revertdb($dbHost, $dbUser,$dbPass,$dbDatabase);
		$this->client_ip = $_SERVER['REMOTE_ADDR'];
		$this->uri = $_SERVER['REQUEST_URI'];
		( isset($_GET['name']) ? $this->PageName = $_GET['name'] : $this->PageName = 'index' );

		define('T_USERS', $this->dbprepend . 'users');
		define('T_PAGES', $this->dbprepend . 'pages');
		define('T_METADATA', $this->dbprepend . 'metadata');
		define('T_MESDATA', $this->dbprepend . 'mesdata');
		define('T_CALDATA', $this->dbprepend. 'caldata');
		define('T_ANALYTES', $this->dbprepend . 'analytes');
		define('T_PROC_METHODS', $this->dbprepend . 'proc_methods');
		define('T_CAL_METHODS', $this->dbprepend . 'cal_methods');
		define('T_CONC_UNITS', $this->dbprepend . 'conc_units');
		define('PLOT_WIDTH', 850);
		define('PLOT_HEIGHT', 500);
		define('LEGEND_WIDTH', 150);

		$this->sesMan = new sessionManager($this);
		define('OK',0);
		define('NOTICE',1);
		define('ERROR',2);
		$this->notices = '';
		DataHandler::spawnInstance();
		$this->dh = DataHandler::getInstance();

		$this->loginStyle = '';
	}

	public static function spawnInstance()
	{
		if ( static::$thisInstance == null ) {
			static::$thisInstance = new mainPage();
		}
	}

	public static function &getInstance()
	{
		return static::$thisInstance;
	}

	function __destruct() {
	}

	function getUri() 
	{
		return $this->uri;
	}

	function Load() {
		$this->PageFull = $this->db->get(T_PAGES, 'name="' . $this->db->esc($this->PageName) . '"');
		if ( !isset($this->PageFull[0]) || isset($this->PageFull[1]) ) {
			throw new Exception("Pages Database Error");
		}

		$this->Layout = $this->generateTop();

		if ( $this->PageFull[0]['only_logged'] == 1 ) {
			if ( !is_numeric($this->sesMan->isLoggedAs()) ) {
				$this->setNotice('The page you were about to visit is for logged in users only. Please log in to continue.', NOTICE);
				$this->PageName	= 'index';
				$this->PageFull = $this->db->get(T_PAGES, 'name="' . $this->db->esc($this->PageName) . '"');
				if ( !isset($this->PageFull[0]) || isset($this->PageFull[1]) ) {
					throw new Exception("Pages Database Error");
				}
			}
		}
		$iterator = 0;
		$anchCnt=0;
		$strpos=0;
		$this->PageStruct = unserialize($this->PageFull[0]['contents']);
		$Body = $this->PageStruct['Body'];
		while ( ($pos=strpos($this->PageStruct['Body'],'{ULTRA_')) !== false ) {
			/* jak nie musimy sprwadzac pojedynczego wpisu,
			 * tylko narysowac cala zawartosc strony i sprawdzic
			 * wszystkie zakotwiczenia w tekscie to robimy to tutaj:
			 * w petli przeszukujemy tekst strony za zakotwiczeniami 
			 * i je podstawiamy 
			 */
			$this->PageStruct['Body']=substr($this->PageStruct['Body'],$pos);
			$typeend=strpos($this->PageStruct['Body'],'}');
			$anchorType=substr($this->PageStruct['Body'], 7, $typeend-7);
			if ( $anchorType == 'DataHandler' ) {
				$this->Modules[$iterator] = $this->dh;
				$iterator++;
				$this->PageStruct['Body'] = substr($this->PageStruct['Body'],7+strlen($anchorType));
			} else {
				include $anchorType . '.class.php';
				eval('$this->Modules[$iterator] = new '. $anchorType  .'($this);');
				$iterator++;
				$this->PageStruct['Body'] = substr($this->PageStruct['Body'],7+strlen($anchorType));
			}
		}
		$this->PageStruct['Body']=$Body;
		if (!isset($this->PageStruct['Style']))
			$this->PageStruct['Style'] = '';
		$this->Footer = '';
	}

	function Check() {
		try {
			if ( isset($this->Modules[0]) ) {
				foreach ( $this->Modules as &$m ) {
					$m->Check();
					$mdata = $m->Draw();
					( isset($mdata['Scripts'])?$this->PageStruct['Scripts'].=$mdata['Scripts']:'' );
					( isset($mdata['Style'])?$this->PageStruct['Style'].=$mdata['Style']:'' );
					( isset($mdata['Head'])?$this->PageStruct['Head'].=$mdata['Head']:'' );
					$this->PageStruct['Body'] = str_replace('{ULTRA_' . get_class($m) . '}', $mdata['Body'], $this->PageStruct['Body']);
				}
			}
		} catch (Exception $e) {
			$this->db->revert();
			$this->setNotice('Error was caugh: ' . $e->getMessage() . '. No changes made.', ERROR);
			//header('Location: ?name=index');
			exit;
		}
	}

	function generateMenu()
	{
		$ret = '<div id="actions"><p class="menitem">Actions:</p>';
		$ret .= '<button class="menitem" onclick="window.location = \'./?name=index\';">Index Page</button>';
		$ret .= '<button class="menitem" onclick="window.location = \'./?name=manage\';">Browse Data</button>';
		$ret .= '<button class="menitem" onclick="window.location = \'./?name=uploadfile\';">Upload File</button></div>';
		return $ret;
	}

	function generateTop()
	{
		$this->loginStyle = '
@font-face {
	font-family: LiberationSans-Regular;
	src: url(\'./fonts/LiberationSans-Regular.ttf\');
}
body, input {
	font-family: LiberationSans-Regular;
}
input {
	font-size: 12px;
}
body {
	width: 1100px;
	margin: auto;
	border: 1px solid black;
}
#topDiv {
	height: 50px;
	width: 100%;
	margin-bottom: 5px;
	padding-bottom: 10px;
	border-bottom: 1px black solid;
}
#loginData, #loginButton, .menitem {
    font-size: 15px;
    border: 1px solid white;
    float: left;
    min-height: 40px;
    min-width: 120px;
    margin: 5px 0px 0px 10px;
} 
p.menitem {
	display: block;
	min-width: 40px;
	margin-left: 20px;
	padding-left: 15px;
	border-left: 1px black solid;
}
#loginData {
	width: 175px;
}
#loginData input {
	width: 170px;
}
#loginButton {
	width: 100px;
}
';

		$uid = $this->sesMan->isLoggedAs();
	
		if ( isset($_POST['try_logout']) ) {
			$this->logoutUser();
			$uid = $this->sesMan->isLoggedAs();
		}

		if ( isset($_POST['try_login']) && !is_numeric($uid) ) {
			$this->tryLoginUser();
			$uid = $this->sesMan->isLoggedAs();
		}
			
		$ret = '<div id="topDiv">';
		if ( is_numeric($uid) ) {
			$ret .= $this->drawLoggedIn($uid);
		} else {
			$ret .= $this->drawLogIn();
		}
		$ret .= $this->generateMenu();
		$ret .= '</div>';
		return $ret;
	}

	function logoutUser()
	{
		if ( is_numeric($this->sesMan->isLoggedAs()) )
			$this->sesMan->Logout();
		if ( !is_numeric($this->sesMan->isLoggedAs()) )
			$this->setNotice('Logout success.',OK);
		else
			$this->setNotice('Logout failed.' , ERROR);
		header('Location: ' . $this->uri);
		exit;
	}

	function drawLoggedIn($uid)
	{
		$userdata = $this->db->get(T_USERS, 'id=' . $uid);
		if ( !isset($userdata[0]) )
			return $this->drawLogIn();
		$ret = '<p id="loginData">Logged in as:<br> ' . $userdata[0]['name'];
		$ret .= '<form action="?name=index" method="POST" id="do_logout">';
		$ret .= '<input type="submit" name="try_logout" value="Logout" id="loginButton">';
		$ret .= '</form>';
		return $ret;
	}

	function drawLogIn()
	{
		$ret = '<form action="' . $this->uri .'" method="POST" id="doLogin">';
			$ret .= '<div id="loginData"><input type="text" name="email" value="" placeholder="Email"><br>';
		$ret .= '<input type="password" name="password" value="" placeholder="Password"></div>';
		$ret .= '<input type="submit" name="try_login" value="Login" id="loginButton"></form>';
		return $ret;
	}

	function tryLoginUser()
	{
		if ( !isset($_POST['try_login']) || !isset($_POST['email']) || !isset($_POST['password']) ) {
			$this->setNotice('Login data malformed.', ERROR);
			return;
		}

		$udata = $this->db->get(T_USERS, 'email="'.$this->db->esc(strtolower($_POST['email'])).'"');
		if ( !isset($udata) ) {
			$this->setNotice('Incorrect email or password.', ERROR);
			return;
		}

		$passedsalt = md5(strtolower($udata[0]['email']) . $_POST['password']);
		if ( $passedsalt != $udata[0]['password'] ) {
			$this->setNotice('Incorrect email or password.', ERROR);
			return;
		}

		if ( $udata[0]['status'] != 'ok' ) {
			$this->setNotice('Account is not activated or is blocked.', ERROR);
			return;
		}
		
		$this->sesMan->LoginUser($udata[0]['id']);
		$this->setNotice('Login success.',OK);
		header('Location: ' . $this->uri);
		exit;
	}

	function Draw() {
		echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<meta author=\"fc\">\n";
		echo $this->PageStruct['Head'];
		echo '<script src="./js/jquery.min.js" type="text/javascript"></script>';
		echo '<script src="./js/jquery-ui.min.js" type="text/javascript"></script>';
		echo '<script type="text/javascript">';
		echo $this->PageStruct['Scripts'];
		echo "</script>\n";
		echo "<style>\n";
		echo "table tr:nth-child(odd) td { background-color:#EEE; } table tr:nth-child(even) td{ background-color:#FFF; }\n";
		echo ".CNotice { background-color:#99F; margin: 5 5 5 5; padding: 0 }\n";
		echo ".COK { background-color:#9F9; margin: 5 5 5 5; padding: 0 }\n";
		echo ".CError { background-color:#F99; margin: 5 5 5 5; padding: 0 }\n";
		echo $this->PageStruct['Style'];
		echo $this->loginStyle;
		echo "</style>";
		echo "</head>\n";
		echo "<body>\n";
		echo $this->Layout;
		echo '<div id="notices">' . $this->getNotices() . '</div>';
		echo '<div id="contents">' . $this->PageStruct['Body'] . '</div>'."\n";
	//	echo $this->Footer;
		echo "</body>\n</html>\n";
	}

	function PostDraw() {
		//echo "PostDrawed";
	}
	
	function setNotice($string, $severity)
	{
		if ( $this->sesMan->HasValue('mainNotices') ) {
			@$notices = unserialize($this->sesMan->GetValue('mainNotices'));
		}

		$notices[] = array('text'=>$string,'severity'=>$severity);
		$this->sesMan->SetValue('mainNotices',serialize($notices));
	}
	
	function getNotices()
	{
		if ( !$this->sesMan->HasValue('mainNotices') )
			return '';

		$notices = unserialize($this->sesMan->GetValue('mainNotices'));

		$ret = '';
		foreach ($notices as $notice) {
			switch 	($notice['severity']) {
			case NOTICE:
				$class='CNotice';
				break;
			case ERROR:
				$class="CError";
				break;
			case OK:
				$class="COK";
				break;
			default:
				$class="CNotice";
				break;
			}
			$ret .= '<p class="'.$class.'">'.$notice['text'].'</p>';
		}
		$this->sesMan->UnsetValue('mainNotices');
		return $ret;
	}

	function signifNumbers($inp, $nrofsig = 4) 
	{
		$dec = log(abs($inp),10);
		if ( $dec >= 0 ) {
			$dec = ceil($dec);
			$rndto = $dec-$nrofsig+1;
			if ( $rndto >= 0 )
				$rndto = -0;
		} else {
			$dec = floor($dec);
			$rndto = $dec-$nrofsig+1;
		}
		return number_format($inp, (-1*$rndto), '.', '');
	}

	public function getCurrentURL() 
	{
		return 'http'.(($_SERVER['SERVER_PORT'] == 443)?'s://':'://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	}

	public function reloadPage() 
	{
		header('Location: ' . $this->getCurrentURL());
		die();
	}
}

?>

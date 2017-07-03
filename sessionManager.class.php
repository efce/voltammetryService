<?php
/* Klasa obslugujaca przebieg sesji danego uzytkownika, generalnie
 * moze zostawic troche smieci na dysku, wiec trza by dodac
 * jakas mozliwosc ich uprzatania ktora by nei zwalniala wykonywania
 * strone, ale narazie problem nie jest duzy
 */
class sessionManager {
	private $attempts, $id, $service, $main, $dir;

	public function __construct($Page) {
		$this->main=$Page;
		$session_force=20*60; //czas recznego utrzymywania sesji w sek.
		ini_set("session.cookie_lifetime","$session_force");
		!isset($this->attempts) ? $this->attempts=0 : '';
		$ses_id=session_id();
		if ( empty($ses_id) ) {
			if ( isset($_GET['PHPSESID']) && !empty($_GET['PHPSESID']) ) {
				if (isset($_SERVER['REMOTE_HOST'])) {
					$ses_file = './emergency_session/' . $_SERVER['REMOTE_HOST'] . $_GET['PHPSESID'] . '.Session';
					if ( is_file( $ses_file ) && filemtime($ses_file) > date()-$session_force ) {
						$coded_sess = file_get_contents( $ses_file );
						unlink( $ses_file );
						if ( session_decode($coded_sess) ) {
							unset($coded_sess);
							return;
						} else {
							unset($coded_sess);
						}
					} else {
						is_file($ses_file)?unlink($ses_file):'';
					}
				}
			}
			if ( !session_start() )
				throw new sesException('Unable to start session', 07);
			$ses_id_new=session_id();
			if ( empty($ses_id_new) )
				throw new sesException('Start session failed', 08);
			$_SESSION['sesManager']['started']=time();
			$this->attempts=$this->attempts+1;
		} else {
			if ( isset($_SESSION['sesManager']['started']) && $_SESSION['sesManager']['started'] != false ) {
				return;
			} else {
				switch ( $this->attempts ) {
				case 1:
				case 0:
					$this->__construct();
					break;
				default:
					throw new sesException('Session critical error...', 09);
				}
			}
		}
		$_SESSION['sesManager']['lastAlive']=time();
		$this->id=session_id();
		if ( isset($_SESSION['sesManager']['previousAlive']) 
		&& isset($_SESSION['sesManager']['logged']) 
		&& $_SESSION['sesManager']['logged'] 
		&& $_SESSION['sesManager']['previousAlive'] < $_SESSION['sesManager']['lastAlive']-$session_force ) {
			$this->Logout();
//			$Page->NotifyUser('Zostałeś wylgowany z powodu braku aktywności',NOTIFY_ERROR);
		}
		$_SESSION['sesManager']['previousAlive']=$_SESSION['sesManager']['lastAlive'];
	}
	public function __destruct() {
		if ( !isset($_SESSION['sesManager']['logout']) || $_SESSION['sesManager']['logout'] == false ) {
			if ( $this->id != false && isset($_SERVER['REMOTE_HOST']) ) {
				$ses_file = './emergency_session/'.$_SERVER['REMOTE_HOST'] . $this->id . '.tSession';
				!is_file($ses_file)?file_put_contents($ses_file, session_encode):'';
			}
			session_write_close();
		} else {
			if ( isset($_SESSION['sesManager'][ $_SESSION['sesManager']['logout'] ]) ) {
				unset($_SESSION['sesManager'][ $_SESSION['sesManager']['logout'] ]);
			}
			unset($_SESSION['sesManager']['logout']);
			session_write_close();
		}
	}
	public function isLoggedAs() {
		/* spradzenie czy uzytkownik jest zalogowany */
		if ( isset($_SESSION['sesManager']['logged']) && $_SESSION['sesManager']['logged'] === true && isset($_SESSION['sesManager']['userid']) && is_numeric($_SESSION['sesManager']['userid']) )
			return $_SESSION['sesManager']['userid'];
		else
			return false;
	}

	public function LoginTeacher($id, $role) {
		/* tutaj ma byc przekazane potwierdzneie ze user sie zalogowal,
		* razem z ID ktore ma byc w klasie przetrzymywanie.
		* ID MUSI byc numeryczne.
		 */
		//TODO
		//TODO: weryfikuj IP jeszcze raz !
		//TODO
		if ( is_numeric($id) ) {
			$_SESSION['sesManager']['logged']=true;
			$_SESSION['sesManager']['userid']=$id;
			$_SESSION['sesManager']['loggedAt']=time();
			$_SESSION['sesManager']['group'] = $role;
			$_SESSION['sesManager']['teacher']=1;
			if ( $_SESSION['sesManager']['userid'] == $id )
				return true;
			else
				throw new sesException('Session fatal error while logging in', 11);
		} else {
			return false;
		}
	}

	public function LoginUser($id) {
		/* tutaj ma byc przekazane potwierdzneie ze user sie zalogowal,
		* razem z ID ktore ma byc w klasie przetrzymywanie.
		* ID MUSI byc numeryczne.
		 */
		//TODO
		//TODO: weryfikuj IP jeszcze raz !
		//TODO
		if ( is_numeric($id) ) {
			$_SESSION['sesManager']['logged']=true;
			$_SESSION['sesManager']['userid']=$id;
			$_SESSION['sesManager']['loggedAt']=time();
			//$_SESSION['sesManager']['group']='students';
			//$_SESSION['sesManager']['teacher']=0;
			if ( $_SESSION['sesManager']['userid'] == $id )
				return true;
			else
				throw new sesException('Session fatal error while logging in', 11);
		} else {
			return false;
		}
	}

	public function Logout() {
		/* jak chcesz wylogowac uzytkownika to 
		 * wywolaj ta funkcje a na koniec strony bedzie
		 * zdezintegrowany true jezeli sie udalo wylogowac
		 * false jak pojawil sie jakis problem
		 */
		$_SESSION['sesManager']['logged']=false;
		unset($_SESSION['sesManager']['userid']);
		$_SESSION['sesManager']['logout']=$this->service;
		if ( $_SESSION['sesManager']['logout'] == $this->service && !isset($_SESSION['sesManager']['userid']) )
			return true;
		else
			return false;
	}

	public function GetSessionGET() {
		return 'PHPSESID=' . urlencode($this->id);
	}

	public function GetLoginTime() {
		return $_SESSION['sesManager']['loggedAt'];
	}

	public function GetPrevHistory() {
		if ( isset($_SESSION['sesManager']['History'][0]) && isset($_SESSION['sesManager']['HistCounter']) && $_SESSION['sesManager']['HistCounter'] > 1 )
			return $_SESSION['sesManager']['History'][$_SESSION['sesManager']['HistCounter']-2];
		elseif ( isset($_SESSION['sesManager']['History'][0]) )
			return $_SESSION['sesManager']['History'][0];
		else
			return $this->main->GetPageAddress();
	}

	public function SetValue($name, $value) {
		$_SESSION['Values'][$name] = $value;
		if ( $_SESSION['Values'][$name] == $value )
			return true;
		else 
			return false;
	}

	public function GetValue($name) {
		if ( isset($_SESSION['Values'][$name]) )
			return $_SESSION['Values'][$name];
		else
			throw new Exception('Value "'.$name.'" was not set in the session...'.print_r($_SESSION['Values'],true) , 323);
	}

	public function UnsetValue($name) {
		if ( isset($_SESSION['Values'][$name]) ) {
			unset($_SESSION['Values'][$name]);
			if ( !isset($_SESSION['Values'][$name]) ) {
				return true;
			} else {
				return false;
			}
		} 
		return true;
	}

	public function HasValue($name) {
		if ( isset($_SESSION['Values'][$name]) )
                        return true;
                else
                        return false;
	}
}

<?php

class auth
{	
	public $mysqli;
	public $errormsg;
	public $successmsg;
	
	// Configuration
	
	private $db_host = "localhost";
	private $db_user = "root";
	private $db_pass = "";
	private $db_name = "auth";
	
	private $conf_cookie_name = "auth_session";
	private $conf_cookie_expire = "+1 month";
	
	
	function __construct()
	{
		// Start a new MySQLi Connection
	
		$this->mysqli = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
	}
	
	/*
	* Log user in via MySQL Database
	* @param string $username
	* @param string $password
	* @return boolean
	*/
	
	function login($username, $password)
	{
		if(!isset($_COOKIE[$conf_cookie_name]))
		{
			// Input verification :
		
			if(strlen($username) == 0) { $this->errormsg[] = "Username / Password is invalid !"; return false; }
			elseif(strlen($username) > 30) { $this->errormsg[] = "Username / Password is invalid !"; return false; }
			elseif(strlen($username) < 3) { $this->errormsg[] = "Username / Password is invalid !"; return false; }
			elseif(strlen($password) == 0) { $this->errormsg[] = "Username / Password is invalid !"; return false; }
			elseif(strlen($password) > 30) { $this->errormsg[] = "Username / Password is invalid !"; return false; }
			elseif(strlen($password) < 3) { $this->errormsg[] = "Username / Password is invalid !"; return false; }
			else 
			{
				// Input is valid
			
				$password = $this->hashpass($password);
			
				$query = $this->mysqli->prepare("SELECT isactive FROM users WHERE username=? AND password=?");
				$query->bind_param("ss", $username, $password);
				$query->bind_result($isactive);
				$query->execute();
				$query->store_result();
				$count = $query->num_rows;
				$query->fetch();
				$query->close();
			
				if($count == 0)
				{
					// Username and / or password are incorrect
				
					$this->errormsg[] = "Username / Password is incorrect !";
					
					return false;
				}
				else 
				{
					// Username and password are correct
					
					if($isactive == "0")
					{
						// Account is not activated
						
						$this->errormsg[] = "Account is not activated !";
						
						return false;
					}
					else
					{
						// Account is activated
					
						$this->newsession($username);				
				
						$this->successmsg[] = "You are now logged in !";
						
						return true;
					}
				}
			}
		}
		else 
		{
			// User is already logged in
			
			$this->errormsg[] = "You are already logged in !";
			
			return false;
		}
	}
	
	/*
	* Register a new user into the database
	* @param string $username
	* @param string $password
	* @param string $verifypassword
	* @param string $email
	* @return boolean
	*/
	
	function register($username, $password, $verifypassword, $email)
	{
		if(!isset($_COOKIE[$conf_cookie_name]))
		{
			// Input Verification :
		
			if(strlen($username) == 0) { $this->errormsg[] = "Username field is empty !"; }
			elseif(strlen($username) > 30) { $this->errormsg[] = "Username is too long !"; }
			elseif(strlen($username) < 3) { $this->errormsg[] = "Username is too short !"; }
			if(strlen($password) == 0) { $this->errormsg[] = "Password field is empty !"; }
			elseif(strlen($password) > 30) { $this->errormsg[] = "Password is too long !"; }
			elseif(strlen($password) < 3) { $this->errormsg[] = "Password is too short !"; }
			elseif($password !== $verifypassword) { $this->errormsg[] = "Passwords don't match !"; }
			elseif(strstr($password, $username)) { $this->errormsg[] = "Password cannot contain the username !"; }
			if(strlen($email) == 0) { $this->errormsg[] = "Email field is empty !"; }
			elseif(strlen($email) > 100) { $this->errormsg[] = "Email is too long !"; }
			elseif(strlen($email) < 5) { $this->errormsg[] = "Email is too short !"; }
			elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->errormsg[] = "Email address is invalid !"; }
		
			if(count($this->errormsg) == 0)
			{
				// Input is valid
			
				$query = $this->mysqli->prepare("SELECT * FROM users WHERE username=?");
				$query->bind_param("s", $username);
				$query->execute();
				$query->store_result();
				$count = $query->num_rows;
				$query->close();
			
				if($count != 0)
				{
					// Username already exists
				
					$this->errormsg[] = "Username is already taken !";
					
					return false;
				}
				else 
				{
					// Username is not taken
					
					$query = $this->mysqli->prepare("SELECT * FROM users WHERE email=?");
					$query->bind_param("s", $email);
					$query->execute();
					$query->store_result();
					$count = $query->num_rows;
					$query->close();
				
					if($count != 0)
					{
						// Email address is already used
					
						$this->errormsg[] = "Email is already associated to another account !";
						
						return false;					
					}
					else 
					{
						// Email address isn't already used
					
						$password = $this->hashpass($password);
						$activekey = $this->randomkey(15);				
					
						$query = $this->mysqli->prepare("INSERT INTO users (username, password, email, activekey) VALUES (?, ?, ?, ?)");
						$query->bind_param("ssss", $username, $password, $email, $activekey);
						$query->execute();
						$query->close();
						
						$message_from = "no-reply@website.com";
						$message_subj = "Account activation required !";
						$message_cont = "Hello $username<br/><br/>";
						$message_cont .= "You recently registered a new account on [WEBSITE NAME]<br/>";
						$message_cont .= "To activate your account please click the following link<br/><br/>";
						$message_cont .= "<a href=\"http://www.example.com/auth/?page=activate&username=$username&key=$activekey\">Activate my account</a>";
						$message_head = "From: $message_from" . "\r\n";
						$message_head .= "MIME-Version: 1.0" . "\r\n";
						$message_head .= "Content-type: text/html; charset=iso-8859-1" . "\r\n";
						
						mail($email, $message_subj, $message_cont, $message_head);
					
						$this->successmsg[] = "New Account Created ! Activation email sent to your email address.";
						
						return true;					
					}
				}			
			}
			else 
			{
				return false;
			}
		}
		else 
		{
			// User is already logged in
		
			$this->errormsg[] = "You are already logged in !";
			
			return false;
		}
	}
	
	/*
	* Creates a new session for the provided username and sets cookie
	* @param string $username
	*/
	
	function newsession($username)
	{
		$hash = md5(microtime());
		
		// Fetch User ID :		
		
		$query = $this->mysqli->prepare("SELECT id FROM users WHERE username=?");
		$query->bind_param("s", $username);
		$query->bind_result($uid);
		$query->execute();
		$query->fetch();
		$query->close();
		
		// Delete all previous sessions :
		
		$query = $this->mysqli->prepare("DELETE FROM sessions WHERE username=?");
		$query->bind_param("s", $username);
		$query->execute();
		$query->close();
		
		$ip = $_SERVER['REMOTE_ADDR'];
		$expiredate = date("Y-m-d H:i:s", strtotime($config_cookie_expire));
		$expiretime = strtotime($expiredate);
		
		$query = $this->mysqli->prepare("INSERT INTO sessions (uid, username, hash, expiredate, ip) VALUES (?, ?, ?, ?, ?)");
		$query->bind_param("issss", $uid, $username, $hash, $expiredate, $ip);
		$query->execute();
		$query->close();
		
		setcookie($conf_cookie_name, $hash, $expiretime);
	}
	
	/*
	* Deletes the user's session based on hash
	* @param string $hash
	*/
	
	function deletesession($hash)
	{
		$query = $this->mysqli->prepare("SELECT username FROM sessions WHERE hash=?");
		$query->bind_param("s", $hash);
		$query->bind_result($username);
		$query->execute();
		$query->store_result();
		$count = $query->num_rows;
		$query->close();
		
		if($count == 0)
		{
			// Hash doesn't exist
		
			$this->errormsg[] = "Invalid Session Hash !";
			
			setcookie($conf_cookie_name, $hash, time() - 3600);
		}
		else 
		{
			// Hash exists, Delete all sessions for that username :
			
			$query = $this->mysqli->prepare("DELETE FROM sessions WHERE username=?");
			$query->bind_param("s", $username);
			$query->execute();
			$query->close();
			
			setcookie($conf_cookie_name, $hash, time() - 3600);
		}
	}
	
	/*
	* Provides an associative array of user info based on session hash
	* @param string $hash
	* @return array $session
	*/
	
	function sessioninfo($hash)
	{
		$query = $this->mysqli->prepare("SELECT uid, username, expiredate, ip FROM sessions WHERE hash=?");
		$query->bind_param("s", $hash);
		$query->bind_result($session['uid'], $session['username'], $session['expiredate'], $session['ip']);
		$query->execute();
		$query->store_result();
		$count = $query->num_rows;
		$query->fetch();
		$query->close();
		
		if($count == 0)
		{
			// Hash doesn't exist
		
			$this->errormsg[] = "Invalid Session Hash !";
			setcookie($conf_cookie_name, $hash, time() - 3600);
			
			return false;
		}
		else 
		{
			// Hash exists
		
			return $session;			
		}
	}
	
	/* 
	* Checks if session is valid (Current IP = Stored IP + Current date < expire date)
	* @param string $hash
	* @return bool
	*/
	
	function checksession($hash)
	{
		$query = $this->mysqli->prepare("SELECT username, expiredate, ip FROM sessions WHERE hash=?");
		$query->bind_param("s", $hash);
		$query->bind_result($username, $db_expiredate, $db_ip);
		$query->execute();
		$query->store_result();
		$count = $query->num_rows;
		$query->fetch();
		$query->close();
		
		if($count == 0)
		{
			// Hash doesn't exist
			
			setcookie($conf_cookie_name, $hash, time() - 3600);
			
			return false;
		}
		else
		{
			if($_SERVER['REMOTE_ADDR'] != $db_ip)
			{
				// Hash exists, but IP has changed
			
				$query = $this->mysqli->prepare("DELETE FROM sessions WHERE username=?");
				$query->bind_param("s", $username);
				$query->execute();
				$query->close();
				
				setcookie($conf_cookie_name, $hash, time() - 3600);
				
				return false;
			}
			else 
			{
				$expiredate = strtotime($db_expiredate);
				$currentdate = strtotime(date("Y-m-d H:i:s"));
				
				if($currentdate > $expiredate)
				{
					// Hash exists, IP is the same, but session has expired
				
					$query = $this->mysqli->prepare("DELETE FROM sessions WHERE username=?");
					$query->bind_param("s", $username);
					$query->execute();
					$query->close();
					
					setcookie($conf_cookie_name, $hash, time() - 3600);
					
					return false;
				}
				else 
				{
					// Hash exists, IP is the same, date < expiry date
				
					return true;
				}
			}
		}
	}
	
	/*
	* Returns a random string, length can be modified
	* @param int $length
	* @return string $key
	*/
	
	function randomkey($length = 10)
	{
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
		$key = "";
		
		for($i = 0; $i < $length; $i++)
		{
			$key .= $chars{rand(0, strlen($chars) - 1)};
		}
		
		return $key;
	}
	
	/*
	* Activate a user's account
	* @param string $username
	* @param string $key
	* @return boolean
	*/
	
	function activate($username, $key)
	{
		// Input verification
	
		if(strlen($username) == 0) { $this->errormsg[] = "Invalid URL !"; return false; }
		elseif(strlen($username) > 30) { $this->errormsg[] = "Invalid URL !"; return false; }
		elseif(strlen($username) < 3) { $this->errormsg[] = "Invalid URL !"; return false; }
		elseif(strlen($key) > 15) { $this->errormsg[] = "Invalid URL !"; return false; }
		elseif(strlen($key) < 15) { $this->errormsg[] = "Invalid URL !"; return false; }
		else
		{
			// Input is valid
			
			$query = $this->mysqli->prepare("SELECT isactive, activekey FROM users WHERE username=?");
			$query->bind_param("s", $username);
			$query->bind_result($isactive, $activekey);
			$query->execute();
			$query->store_result();
			$count = $query->num_rows;
			$query->fetch();
			$query->close();
			
			if($count == 0)
			{
				// User doesn't exist
				
				$this->errormsg[] = "Username is incorrect !";
				
				return false;
			}
			else
			{
				// User exists
				
				if($isactive == 1)
				{
					// Account is already activated
					
					$this->errormsg[] = "Account is already activated !";
					
					return true;
				}
				else
				{
					// Account isn't activated
					
					if($key == $activekey)
					{
						// Activation keys match
						
						$new_isactive = 1;
						$new_activekey = "0";
						
						$query = $this->mysqli->prepare("UPDATE users SET isactive=?, activekey=? WHERE username=?");
						$query->bind_param("iss", $new_isactive, $new_activekey, $username);
						$query->execute();
						$query->close();
						
						$this->successmsg[] = "Account successfully activated !";
						
						return true;						
					}
					else
					{
						// Activation Keys don't match
						
						$this->errormsg[] = "Activation Key is incorrect !";
						
						return false;
					}
				}
			}
		}
	}

	/*
	* Hash user's password with SHA512 and base64_encode
	* @param string $password
	* @return string $password
	*/
	
	function hashpass($password)
	{
		$password = hash("SHA512", base64_encode(hash("SHA512", $password)));
		return $password;
	}
}

?>
<?php

require_once("dao/Users.dao.php");
require_once("dao/Emails.dao.php");
require_once('libs/facebook-php-sdk/src/facebook.php');
require_once('libs/SessionManager.php');

class LoginController{

	// Create our Application instance (replace this with your appId and secret).
	private static $_facebook;
	
	
	private static function getFacebookInstance(){
		
		if(is_null(self::$_facebook)){
			self::$_facebook = new Facebook(array(
				'appId'  => OMEGAUP_FB_APPID,
				'secret' => OMEGAUP_FB_SECRET
			));
		} 

		return self::$_facebook;
	}


	private static $_sessionManager;

    public static function getSessionManagerInstance()
    {
        if(is_null(self::$_sessionManager))
        {
            self::$_sessionManager = new SessionManager();
        }
        
        return self::$_sessionManager;
    }


	static function testUserCredentials(
		$email_or_username, 
		$pass
	){
                Logger::debug("Testing user via username:" . $email_or_username);
                $user_query = new Users();
                $user_query->setUsername( $email_or_username );        
                $results = UsersDAO::search( $user_query );

                if(sizeof($results) === 1)
                {
                              
                    Logger::debug("User was found via username.");
                    $this_user = $results[0];                

                }
                else
                {
                    Logger::debug("Not found via username. Testing user via email" . $email_or_username);
                    $email_query = new Emails();
                    $email_query->setEmail( $email_or_username );

                    $result = EmailsDAO::search( $email_query );


                    if( sizeof($result) == 0)
                    {
                            //email does not even exist
                            Logger::debug("User was not found via email. Failing testUserCredentials()");
                            return false;
                    }

                    Logger::debug("User was found via email.");
                    $this_user 	= UsersDAO::getByPK( $result[0]->getUserId() );
                }

		//test passwords
		return $this_user->getPassword() === md5( $pass ) ;
		
	}



	/**
	 * 
	 * 
	 * 
	 * */
	static function login(
		$email_or_username, 
		$google_token = null
	){
		Logger::debug("LoginController::Login() started...");
		
                Logger::debug("Loging user via username:" . $email_or_username);
                $user_query = new Users();
                $user_query->setUsername( $email_or_username );        
                $results = UsersDAO::search( $user_query );

                if(sizeof($results) === 1)
                {
                              
                    Logger::debug("User was found via username.");
                    $this_user = $results[0];                

                }
                else
                {
                
                    //google says valid user, look for it in email's table
                    $email_query = new Emails();
                    $email_query->setEmail( $email_or_username );

                    $result = EmailsDAO::search( $email_query );
                    $this_user = null;


                    if( sizeof($result) == 0)
                    {
                            // WARNING: Following code asumes that we have an email
                            $email = $email_or_username;


                            //create user
                            $this_user 	= new Users();
			    $dude_temp_username = strstr($email, '@', true);
                            $this_user->setUsername( $dude_temp_username );
                            $this_user->setSolved( 0 );			
                            $this_user->setSubmissions( 0 );



                            //save this user
                            try{
                                    UsersDAO::save( $this_user );

                            }catch(Exception $e){
                                    Logger::error($e);
                                    return false;

                            }


                            //create email
                            $this_user_email = new Emails();
                            $this_user_email ->setUserId( $this_user->getUserId() );
                            $this_user_email ->setEmail( $email );

                            //save this user
                            try{
                                    EmailsDAO::save( $this_user_email );

                            }catch(Exception $e){
                                    die($e);
                                    return false;

                            }

			    // Save the email into user's main email ID
                            $this_user->setMainEmailId($this_user_email->getEmailId());
                            //save this user
                            try{
                                    UsersDAO::save( $this_user );

                            }catch(Exception $e){
                                    Logger::error($e);
                                    return false;

                            }

                    }else{
                            // he's been here man !
                            $this_user 	= UsersDAO::getByPK( $result[0]->getUserId() );

                            //save user so  his
                            //last_access gets updated
                            $this_user->setLastAccess(time());

                            try {
                                    UsersDAO::save( $this_user );
                            } catch(Exception $e) {
                                    Logger::error($e);

                                    return false;
                            }
                    }
                }

		/**
		 * Ok, passwords match !
		 * Create the auth_token. Auth tokens will be valid for 24 hours.
		 * */
		 $auth_token = new AuthTokens();
		 $auth_token->setUserId( $this_user->getUserId() );

		 /**
		  * auth token consists of:
		  * current time: to validate obsolete tokens
		  * user who logged in:
		  * some salted md5 string: to validate that it was me who actually made this token
		  * 
		  * */
		 $time = time();
		 $auth_str = $time . "-" . $this_user->getUserId() . "-" . md5( OMEGAUP_MD5_SALT . $this_user->getUserId() . $time );
		 $auth_token->setToken($auth_str);

		 session_start();
		 
		 if (!is_null($this_user))
		 {
			$_SESSION['omegaup_user'] = array('id' => $this_user->getUserId(), 'name' => $this_user->getName(), 'email' => $email_or_username);
		 }

		 try
		 {
		    AuthTokensDAO::save( $auth_token );
		 }
		 catch(Exception $e)
		 {
		    throw new ApiException(ApiHttpErrors::invalidDatabaseOperation(), $e);    
		 }

		 setcookie('auth_token', $auth_str, time()+60*60*24, '/');
		 
		 return true;
	}
	
	/**
	 * 
	 * */
	static function isLoggedIn(
	){
		Logger::debug("isLoggedIn() started");
		
		//there are two ways of knowing if a user is logged in
		//the first option is if $_SESSION["LOGGED_IN"] is 
		//defined and contains true
		//var_dump($_COOKIE);
		
		$sesion = self::getSessionManagerInstance();
		$auth_token = $sesion->GetCookie("auth_token");
		
		if( !is_null($auth_token) ) {
			Logger::debug("There is a session token in the cookie, lets test it.");
			
			$user = AuthTokensDAO::getUserByToken($auth_token);
			
			if(is_null($user)){
				Logger::warn("auth_token was not found in the db, why is this?");
				
			}else{
				Logger::debug("auth_token validated, it belongs to user_id=" . $user->getUserId());
				return true;			
			}
		}
		
		//ok, the user does not have any auth token
		//if he wants to test facebook login
		//Facebook must send me the state=something
		//query, so i dont have to be testing 
		//facebook sesions on every single petition
		//made from the front-end
		if(!isset($_GET["state"])){
			Logger::debug("Not logged in and no need to check for fb session");
			return false;
		}
		
		
		Logger::debug("There is no auth_token cookie, testing for facebook sesion.");

		
		//if that is not true, may still be logged with
		//facebook, lets test that
		$facebook = self::getFacebookInstance();
		
		// Get User ID
		$fb_user = $facebook->getUser();


		// We may or may not have this data based on whether the user is logged in.
		//
		// If we have a $fb_user id here, it means we know the user is logged into
		// Facebook, but we don't know if the access token is valid. An access
		// token is invalid if the user logged out of Facebook.
		/*var_dump($fb_user);*/
		
		if ($fb_user) {
			try {
				// Proceed knowing you have a logged in user who's authenticated.
				$fb_user_profile = $facebook->api('/me');
				
			} catch (FacebookApiException $e) {
				$fb_user = null;
				Logger::error("FacebookException:" . $e);
			}
		}
		/*var_dump($fb_user);*/
		
		// Now we know if the user is authenticated via facebook
		if (is_null($fb_user)) {
			Logger::debug("No facebook sesion... ");
			return false;
		}


		//ok we know the user is logged in,
		//lets look for his information on the database
		//if there is none, it means that its the first
		//time the user has been here, lets register his info
		Logger::debug("User is logged in via facebook !!");
		
		$results = UsersDAO::search( new Users( array( "facebook_user_id" => $fb_user_profile["id"] ) ) );
		
		if(count($results) == 1){
			//user has been here before with facebook!
			
		}else{
			//the user has never been here before, lets
			//register him
			$new_user = UsersController::registerNewUser( 
											$fb_user_profile["name"],
											$fb_user_profile["email"],
											NULL,
											$fb_user_profile["id"]);			
		}
		
		//since we got here, this user does not have
		//any auth token, lets give him one
		//so we dont have to call facebook to see
		//if he is still logged in, and he can call
		//the api 
		
		return self::login($fb_user_profile["email"]);

		
		
	}

	
	
	public static function getFacebookLoginUrl(){
		
		$facebook = self::getFacebookInstance();

		return $facebook->getLoginUrl(array("scope" => "email"));
		
	}
	
	
	
	/**
	 * 
	 * 
	 * 
	 * */
	static function logout(
		$redirect = false
	){

		Logger::debug("LoginController::logout()");	
		
		

		
		//double check that he really is logged in
		if(self::isLoggedIn()){
			
			//delete the auth_token from db
			$sm = self::getSessionManagerInstance();
			
			$auth_token = $sm->GetCookie( "auth_token" );

			$token_to_delete = new AuthTokens(array( "token" => $auth_token ));
			try{
				AuthTokensDAO::delete( $token_to_delete );
				
			}catch(Exception $e){
				//return false;
			}
			
		}
		
		//unset the cookie
		setcookie('auth_token', 'deleted', 1, '/');

		session_start();
		$_SESSION = array();
		session_destroy();

		if($redirect){
			/*
			Log out from facebook ?
			
			$facebook = self::getFacebookInstance();
			
			
			die(header("Location: " . $facebook->getLogoutUrl( array( 'next' =>  "http://omegaup.com/" )) ));
			
			//prevent loopback redirections
			
			$next_url = str_replace ( "%3Frequest%3Dlogout" , "%3Fsbso%3Dtrue" , $facebook->getLogoutUrl( ) );
			
			Logger::debug("LoginController::logout() redirection to " . $next_url );	
			
			die(header("Location: " . $next_url ));
			*/
		}

		return true;
	}
	
	
	static function hideEmail($email)
	{
		$s = explode("@", $email);
		return substr( $s[0], 0, strlen($s[0]) - 3 )  . "...@" . $s[1];
	}
	

	
	static function getCurrentUser(
	){
		
		Logger::debug("LoginController::getCurrentUser()");
		
		if(self::isLoggedIn()){
			$sm = self::getSessionManagerInstance();
			$auth_token = $sm->GetCookie( "auth_token" );
			
			return AuthTokensDAO::getUserByToken($auth_token);
			
		}else
			return NULL;
	}
	
	
	
}
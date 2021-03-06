<?php

require_once(BASE_PATH . "triton/client.php");

class User
{
	public static function getGameClient($user_id = null)
	{
		if ($user_id == null)
		{
			$user_id = $_SESSION['id'];
		}

		ob_start();
		$user = dbQuery("SELECT * FROM user WHERE id = ?", array($user_id));
		$client = new TritonClient($user[0]['username'], $user[0]['password']);

		if ($client->logged_in)
		{
			return $client;
		}

		if (loadFromCache($user_id))
		{
			$client->auth_cookie = $user[0]['cookie'];
			$client->logged_in = true;
			return $client;
		}

		// authenticate with triton
		$auth = $client->authenticate();
		ob_end_clean();

		if ($auth)
		{
			$result = dbQuery("UPDATE user SET ltime = NOW(), cookie = ? WHERE id = ?", array($client->auth_cookie, $user_id));
			if ($result === false)
			{
				// TODO detect error correctly
				// return array('success'=>false, 'output'=>array(
				//  	"message"=>"Failed to create user."
				// ));
			}
		}

		return null;
	}

	public static function login($username, $password)
	{
		ob_start();
		$username = strtolower($username);
		if (isset($_SESSION['id']))
		{
			return array('success'=>true, 'output'=>array(
				"message"=>"Already logged in."
			));
		}

		$user = dbQuery("SELECT id, username, password, player_id, admin, ltime FROM user WHERE username = ?", array($username));
		$new_user = count($user) === 0;

		// create triton client, don't auth yet
		$client = new TritonClient($username, $password);

		// user has logged in before and the passwords match and we want to load from cache
		// or we're already logged in
		if (
			(!$new_user && $password === $user[0]['password'] && loadFromCache($user[0]['id'])) ||
			$client->logged_in
		)
		{
			session_start();
			$_SESSION['id'] = $user[0]['id'];
			$_SESSION['username'] = $username;
			$_SESSION['admin'] = $user[0]['admin'];
			$_SESSION['player_id'] = $user[0]['player_id'];

			return array('success'=>true);
		}

		// authenticate with triton
		$auth = $client->authenticate();
		ob_end_clean();

		if (!$auth)
		{
			return array('success'=>false, 'output'=>array(
			 	"message"=>"Invalid alias/email and password."
			));
		}

		if ($new_user)
		{
			$server = $client->GetServer();
			if (!$server)
			{
				return array('success'=>false, 'output'=>array(
				 	"message"=>"Failed to fetch from Triton's server."
				));
			}

			$player = $server->GetPlayer();
			if (!$player)
			{
				return array('success'=>false, 'output'=>array(
				 	"message"=>"Failed to fetch from Triton's server."
				));
			}

			// create new user
			$result = dbQuery("INSERT INTO user(username, password, player_id, utime, ltime, cookie) VALUES (?, ?, ?, NOW(), NOW(), ?)", array($username, $password, $player['user_id'], $client->auth_cookie));
			if ($result === false)
			{
				// TODO detect error correctly
				// return array('success'=>false, 'output'=>array(
				//  	"message"=>"Failed to create user."
				// ));
			}

			$user = dbQuery("SELECT id, username, player_id, admin FROM user WHERE username = ?", array($username));
		}
		else
		{
			if ($password === $user[0]['password'])
			{
				// only update ltime
				$result = dbQuery("UPDATE user SET ltime = NOW(), cookie = ? WHERE id = ?", array($client->auth_cookie, $user[0]['id']));
				if ($result === false)
				{
					// TODO detect error correctly
					// return array('success'=>false, 'output'=>array(
					//  	"message"=>"Failed to make db call."
					// ));
				}
			}
			else
			{
				// update password and utime since a new password was given
				$result = dbQuery("UPDATE user SET password = ?, utime = NOW(), ltime = NOW(), cookie = ? WHERE id = ?", array($password, $client->auth_cookie, $user[0]['id']));
				if ($result === false)
				{
					// TODO detect error correctly
					// return array('success'=>false, 'output'=>array(
					//  	"message"=>"Failed to make db call."
					// ));
				}
			}
		}

		session_start();

		$_SESSION['id'] = $user[0]['id'];
		$_SESSION['username'] = $username;
		$_SESSION['admin'] = $user[0]['admin'];
		$_SESSION['player_id'] = $user[0]['player_id'];

		return array('success'=>true);
	}

	public static function logout()
	{
		session_start();
		session_unset();
		session_destroy();
		return array('success'=>true);
	}

	public static function getGames()
	{

	}
}

?>

<?php

class CUser
{
    private $acronym = null;
	private $db = null;
    private $name = null;
	private $find_by_username_and_password_sql = null;

	public function __construct($dbconfig = null)
	{
		if ($dbconfig) {
			$this->db = new CDatabase($dbconfig);
			if ($this->db) {
				$this->find_by_username_and_password_sql = $this->db->Prepare(
					'SELECT id, acronym, name
					 FROM USER
					 WHERE acronym = ?
						AND password = md5(concat(?, salt))');
			}
		}
	}

	public function handle_options($options, $target)
	{
		if (!isset($options))
			return;

		if (isset($options['logout'])) {
			$this->logout();
			header('Location: ' . $target);
			exit;
		}

		$username = isset($options['username']) ? strip_tags($options['username']) : "";
		//var_dump($username);
		$password = isset($options['password']) ? strip_tags($options['password']) : "";
		//var_dump($password);
		$status_text = null;
		if (!empty($username) && !empty($password)) {
			/* $user = */ $this->login($username, $password);
			header('Location: ' . $target);
			exit;
		}
	}

	public function get_login_form()
	{
		return <<<EOD
<form method="post">
  <fieldset>
	<p><label>Användarnamn:</label> <input type="text" name="username"/></p>
	<p><label>Lösenord:</label> <input type="password" name="password"/></p>
	<p><input type='submit' name='login' value='Logga in'/></p>
  </fieldset>
</form>
EOD;
	}

	private function login($username, $password)
	{
		$res = $this->db->ExecutePrepared($this->find_by_username_and_password_sql,
			true, [ $username, $password ]);
		//var_dump($res);
		$user = empty($res) ? null : $res[0];
		$_SESSION['user'] = $user;
		return $user;
	}

	public static function logout()
	{
		$_SESSION['user'] = null;
	}

	private static function get_user()
	{
		return isset($_SESSION['user']) ? $_SESSION['user'] : null;
	}

	public static function is_authenticated()
	{
		$user = self::get_user();
		return !empty($user);
	}

	public function get_username()
	{
		$user = $this->get_user();
		return $user ? $user->acronym : null;
	}

	public function get_name()
	{
		$user = $this->get_user();
		return $user ? $user->name : null;
	}

}


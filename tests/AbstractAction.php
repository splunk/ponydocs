<?php

abstract class AbstractAction extends PHPUnit_Extensions_SeleniumTestCase
{
	/**
	 * An array of one or more products to use for this test.
	 */
	protected $_products = array();
	
	/**
	 * An array of usergroups and whether or not they should be allowed to perform this test with a 1 or a 0.
	 */
	protected $_users = array();
	
	/**
	 * An array of users with their username and password combinations.
	 */
	protected static $_passwords = array
	(
		'admin'		  => array('username' => 'Admin',			 'password' => 'useradmin'),
		'logged_in'	  => array('username' => 'RandomUser',		'password' => 'userrandom'),
		'splunk_preview' => array('username' => 'SplunkPreviewUser', 'password' => 'usersplunkpreview'),
		'storm_preview'  => array('username' => 'StormPreviewUser',  'password' => 'userstormpreview'),
		'employee'	   => array('username' => 'EmployeeUser',	  'password' => 'useremployee'),
		'docteam'		=> array('username' => 'DocteamUser',	   'password' => 'userdocteam'),
		'splunk_docteam' => array('username' => 'SplunkDocteamUser', 'password' => 'usersplunkdocteam'),
		'storm_docteam'  => array('username' => 'StormDocteamUser',  'password' => 'userstormdocteam')
	);
	
	public function setUp()
	{
		$this->setBrowserUrl('http://' . TEST_HOST);
	}
	
	protected function _login($user)
	{
		$this->open('http://' . TEST_HOST . '/index.php?title=Special:UserLogin', $user);
		$this->waitForPageToLoad('10000', $user);
		$this->assertEquals('Log in / create account - PonyDocs', $this->getTitle(), $user);
		$this->type('wpName1', self::$_passwords[$user]['username'], $user);
		$this->type('wpPassword1', self::$_passwords[$user]['password'], $user);
		$this->click('wpLoginAttempt', $user);
		$this->waitForPageToLoad('10000', $user);
		$this->assertEquals('Main Page - PonyDocs', $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent('link=Log out'), $user);
	}
	
	public function testMain()
	{
		$class = get_class($this);
		
		error_log('--- Testing: [' . $class . '] ---');
		
		foreach ($this->_users as $user => $allowed)
		{
			$perm  =  $allowed ? 'Allowed' : 'Not Allowed';
			
			error_log('User: ' . $user . ' -> ' . $perm);
			
			if ($user != 'anonymous') $this->_login($user);
			
			($allowed) ? $this->_allowed($user) : $this->_notAllowed($user);
			
			if ($user != 'anonymous') $this->_logout();
			
			$this->deleteAllVisibleCookies();
			$this->tearDown();
			$this->open('/');
			$this->waitForPageToLoad('10000');
		}
		
		error_log('--- Done: [' . $class . '] ---');
	}
	
	abstract protected function _allowed($user);
	abstract protected function _notAllowed($user);
	
	protected function _logout()
	{
		$this->open('http://' . TEST_HOST . '/index.php?title=Special:UserLogout');
	}
	
	public function tearDown()
	{
		$dir	  = dirname(__FILE__);
		$response = shell_exec('cd ' . escapeshellarg($dir)
				  . DIRECTORY_SEPARATOR
				  . 'sql && mysql -u root ' . DB_NAME . ' < ponydocs.sql');
	}
}
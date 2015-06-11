<?php

class Any_SearchUserContribs extends AbstractAction {
	public function setUp() {

		parent::setUp();

		$this->_users = array
		(
			'admin'		  => TRUE,
			'anonymous'	  => TRUE,
			'logged_in'	  => TRUE,
			'splunk_preview' => TRUE,
			'storm_preview'  => TRUE,
			'employee'	   => TRUE,
			'splunk_docteam' => TRUE,
			'storm_docteam'  => TRUE,
			'docteam'		=> TRUE
		);

	}
	
	protected function _allowed($user)
	{
		$this->open("/Main_Page");
		$this->click("link=Special pages");
		$this->waitForPageToLoad("10000");
		$this->click("link=User contributions");
		$this->waitForPageToLoad("10000");
		$this->type("target", "Admin");
		$this->click("css=input[type=submit]");
		$this->waitForPageToLoad("10000");
		// Search succeeded
		$this->assertEquals("22:43, 1 June 2011", $this->getText("link=exact:22:43, 1 June 2011"), $user);
	}
	
	protected function _notAllowed($user)
	{
	}
}
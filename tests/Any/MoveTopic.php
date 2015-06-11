<?php

class Any_MoveTopic extends AbstractAction {
	public function setUp() {

		parent::setUp();

		$this->_users = array
		(
			'admin'		  => TRUE,
			'anonymous'	  => FALSE,
			'logged_in'	  => FALSE,
			'splunk_preview' => FALSE,
			'storm_preview'  => FALSE,
			'employee'	   => FALSE,
			'splunk_docteam' => TRUE,
			'storm_docteam'  => TRUE,
			'docteam'		=> FALSE
		);

	}
	
	protected function _allowed($user)
	{
		$this->open("/Main_Page");
		$this->select("docsManualSelect", "label=Splunk User Manual");
		$this->waitForPageToLoad("10000");
		$this->click("link=Ways to access Splunk");
		$this->waitForPageToLoad("10000");
		$this->click("link=Move");
		$this->waitForPageToLoad("10000");
		$this->type("wpNewTitle", "Documentation:Splunk:User:WaystoaccessSplunktest:1.0");
		$this->click("wpMove");
		$this->waitForPageToLoad("10000");
		// Topic moved
		$this->assertEquals("Move succeeded", $this->getText("firstHeading"), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->open("/Main_Page");
		$this->select("docsManualSelect", "label=Splunk User Manual");
		$this->waitForPageToLoad("10000");
		$this->click("link=Ways to access Splunk");
		$this->waitForPageToLoad("10000");
		$this->assertFalse($this->isElementPresent("link=Move"), $user);
		$this->open("/Special:MovePage/Documentation:Splunk:User:WaystoaccessSplunk:1.0");
		
		if ($user == 'anonymous')
		{
			$this->assertTrue($this->isTextPresent("You do not have permission to do that"), $user);
		}
		else
		{
			$this->type("wpNewTitle", "Documentation:Splunk:User:WaystoaccessSplunktest:1.0");
			$this->click("wpMove");
			$this->waitForPageToLoad("10000");
			// Topic not allowed to be moved
			$this->assertTextPresent("You are not allowed to execute the action you have requested.", $user);
		}
	}
}
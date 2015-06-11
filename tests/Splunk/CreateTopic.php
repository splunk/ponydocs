<?php

class Splunk_CreateTopic extends AbstractAction
{
	public function setUp()
	{
		parent::setUp();
		
		$this->_users = array
		(
			'admin'		  => TRUE,
			'anonymous'	  => FALSE,
			'logged_in'	  => FALSE,
			'splunk_preview' => FALSE,
			'storm_preview'  => FALSE,
			'employee'	   => TRUE,
			'splunk_docteam' => TRUE,
			'storm_docteam'  => FALSE,
			'docteam'		=> FALSE
		);
	}
	
	protected function _allowed($user)
	{
		// Create
		$this->open('/Documentation:Splunk:Manuals');
		$this->assertEquals("Documentation:Splunk:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->click("link=Splunk Installation Manual");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Splunk:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->click("link=Edit");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Editing Documentation:Splunk:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->type("wpTextbox1", "Welcome to the Splunk Installation Manual\n* {{#topic:Whats in Splunk Installation Manual}}\n* {{#topic:System Requirements for Splunk}}\n* {{#topic:Let's Party!}}\n\n[[Category:V:Splunk:1.0]]");
		$this->click("wpSave");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Splunk:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent("link=Let's Party!"), $user);
		
		// Delete
		$this->click("link=Edit");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Editing Documentation:Splunk:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->type("wpTextbox1", "Welcome to the Splunk Installation Manual\n* {{#topic:Whats in Splunk Installation Manual}}\n* {{#topic:System Requirements for Splunk}}\n\n[[Category:V:Splunk:1.0]]");
		$this->click("wpSave");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Splunk:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent("link=Let's Party!"), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->open('/Documentation:Splunk:Manuals');
		$this->assertEquals("Documentation:Splunk:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->click("link=Splunk Installation Manual");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Splunk:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent("link=Edit"), $user);
	}
}
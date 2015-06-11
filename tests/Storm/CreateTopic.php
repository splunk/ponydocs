<?php

class Storm_CreateTopic extends AbstractAction
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
			'splunk_docteam' => FALSE,
			'storm_docteam'  => TRUE,
			'docteam'		=> FALSE
		);
	}
	
	protected function _allowed($user)
	{
		// Create
		$this->open("/Documentation:Storm:Manuals");
		$this->assertEquals("Documentation:Storm:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->click("link=Storm Installation Manual");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Storm:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent("link=Edit"), $user);
		$this->click("link=Edit");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Editing Documentation:Storm:InstallationTOC1.0 - PonyDocs", $this->getTitle());
		$this->type("wpTextbox1", "Welcome to the Storm Installation Manual\n* {{#topic:Whats in the Storm Installation Manual}}\n* {{#topic:System Requirements for Storm}}\n* {{#topic:Let's Party!}}\n\n[[Category:V:Storm:1.0]]");
		$this->click("wpSave");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Storm:InstallationTOC1.0 - PonyDocs", $this->getTitle());
		$this->assertTrue($this->isElementPresent("link=Let's Party!"));
		
		// Delete
		$this->assertTrue($this->isElementPresent("link=Edit"), $user);
		$this->click("link=Edit");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Editing Documentation:Storm:InstallationTOC1.0 - PonyDocs", $this->getTitle());
		$this->type("wpTextbox1", "Welcome to the Storm Installation Manual\n* {{#topic:Whats in the Storm Installation Manual}}\n* {{#topic:System Requirements for Storm}}\n\n[[Category:V:Storm:1.0]]");
		$this->click("wpSave");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Storm:InstallationTOC1.0 - PonyDocs", $this->getTitle());
		$this->assertFalse($this->isElementPresent("link=Let's Party!"));
	}
	
	protected function _notAllowed($user)
	{
		$this->open("/Documentation:Storm:Manuals");
		$this->assertEquals("Documentation:Storm:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->click("link=Storm Installation Manual");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Storm:InstallationTOC1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent("link=Edit"), $user);
	}
}
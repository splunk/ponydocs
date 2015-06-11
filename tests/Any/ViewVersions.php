<?php

class Any_ViewVersions extends AbstractAction {
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
			'splunk_docteam' => FALSE,
			'storm_docteam'  => FALSE,
			'docteam'		=> TRUE
		);

	}

	protected function _allowed($user)
	{
		$this->open("/Documentation:Splunk:Versions");
		// Content can be seen
		$this->assertTrue($this->isTextPresent("Version 1.1 (released)"), $user);
		$this->open("/index.php?title=Documentation:Splunk:Versions&action=edit");
		// Content can be edited
		$this->assertEquals("{{#version:1.0|released}}\n{{#version:1.1|released}}\n{{#version:2.0|preview}}\n{{#version:2.1|preview}}\n{{#version:3.0|unreleased}}\n{{#version:3.1|unreleased}}", $this->getValue("wpTextbox1"), $user);
		$this->open("/index.php?title=Documentation%3ASplunk%3AVersions&action=historysubmit&diff=148&oldid=49");
		$this->waitForPageToLoad("10000");
		// Revision comparison can be seen
		$this->assertTrue($this->isTextPresent("Version 1.0 (released)"), $user);
	}

	protected function _notAllowed($user)
	{
		$this->open("/Documentation:Splunk:Versions");
		// Content cannot be seen
		$this->assertFalse($this->isTextPresent("Version 1.1 (released)"), $user);
		$this->open("/index.php?title=Documentation:Splunk:Versions&action=edit");
		// Content cannot be seen
		$this->assertNotEquals("{{#version:1.0|released}}\n{{#version:1.1|released}}\n{{#version:2.0|preview}}\n{{#version:2.1|preview}}\n{{#version:3.0|unreleased}}\n{{#version:3.1|unreleased}}", $this->getValue("wpTextbox1"), $user);
		$this->open("/index.php?title=Documentation%3ASplunk%3AVersions&action=historysubmit&diff=148&oldid=49");
		$this->waitForPageToLoad("10000");
		// Revision comparison cannot be seen
		$this->assertFalse($this->isTextPresent("Version 1.0 (released)"), $user);
	}
}

?>
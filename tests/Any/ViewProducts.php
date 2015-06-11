<?php

class Any_ViewProducts extends AbstractAction
{
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
		$this->open("/Documentation:Products");
		// Content can be seen
		$this->assertTrue($this->isTextPresent("Splunk (Splunk)"), $user);
		$this->open("/index.php?title=Documentation:Products&action=edit");
		// Content can be edited
		$this->assertEquals("{{#product:Splunk|Splunk}}\n{{#product:Storm|Storm}}", $this->getValue("wpTextbox1"), $user);
		// TODO need to edit this page at least once and then update the SQL w/ the new data, so we can compare revisions
/*		$this->open("/index.php?title=Documentation%3ASplunk%3AVersions&action=historysubmit&diff=148&oldid=49");
		$this->waitForPageToLoad("10000");
		// Revision comparison can be seen
		$this->assertTrue($this->isTextPresent("Version 1.0 (released)")); */
	}

	protected function _notAllowed($user)
	{
		$this->open("/Documentation:Products");
		// Content cannot be seen
		$this->assertFalse($this->isTextPresent("Splunk (Splunk)"), $user);
		$this->open("/index.php?title=Documentation:Products&action=edit");
		// Content cannot be seen
		$this->assertNotEquals("{{#product:Splunk|Splunk}}\n{{#product:Storm|Storm}}", $this->getValue("wpTextbox1"), $user);
		
		// TODO need to edit this page at least once and then update the SQL w/ the new data, so we can compare revisions
/*		$this->open("/index.php?title=Documentation%3ASplunk%3AVersions&action=historysubmit&diff=148&oldid=49");
		$this->waitForPageToLoad("10000");
		// Revision comparison cannot be seen
		$this->assertFalse($this->isTextPresent("Version 1.0 (released)")); */
	}
}

?>
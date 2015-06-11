<?php

class Any_ViewLatest extends AbstractAction
{
	public function setUp()
	{
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
		$this->select("docsProductSelect", "label=Storm");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation/Storm - PonyDocs", $this->getTitle(), $user);
		$this->select("docsVersionSelect", "label=1.0");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation/Storm/1.0 - PonyDocs", $this->getTitle(), $user);
		$this->open("/Documentation/Storm/latest/Installation/WhatsintheStormInstallationManual");
		$this->assertEquals("Documentation:Storm:Installation:WhatsintheStormInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user); // Inherited
	}
	
	protected function _notAllowed($user)
	{
	}
}
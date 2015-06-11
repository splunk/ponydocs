<?php

class Any_ViewUnreleasedVersion extends AbstractAction
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
			'storm_docteam'  => TRUE,
			'docteam'		=> TRUE
		);

	}
	
	protected function _allowed($user)
	{
		$this->open("/Main_Page");
		// Unreleased version (3.0) is in the dropdown
		$this->assertEquals("1.0 (latest release)2.03.0", $this->getText("docsVersionSelect"), $user);
		$this->select("docsVersionSelect", "label=3.0");
		$this->click("css=option[value=3.0]");
		$this->waitForPageToLoad("10000");
		$this->select("docsManualSelect", "label=Splunk Installation Manual");
		$this->waitForPageToLoad("10000");
		// Unreleased Manual is viewable
		$this->assertEquals("Whats in Splunk Installation Manual", $this->getText("Whats_in_Splunk_Installation_Manual"), $user);
		$this->click("link=System Requirements for Splunk");
		$this->waitForPageToLoad("10000");
		// Unreleased Topic is viewable
		$this->assertEquals("System Requirements for Splunk", $this->getText("System_Requirements_for_Splunk"), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->open("/Main_Page");
		// Unreleased version (3.0) is NOT in the dropdown
		$this->assertNotEquals("1.0 (latest release)2.03.0", $this->getText("docsVersionSelect"), $user);
		$this->open("/Documentation/Splunk/3.0/Installation/WhatsinSplunkInstallationManual");
		// Unreleased topic is not viewable
		$this->assertElementNotPresent("Whats_in_Splunk_Installation_Manual", $user);
				//TODO Add a product with no released versions and test against that
	}
}
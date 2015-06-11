<?php

class Splunk_BranchTopic extends AbstractAction
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
			'employee'	   => FALSE,
			'splunk_docteam' => TRUE,
			'storm_docteam'  => FALSE,
			'docteam'		=> FALSE
		);
	}
	
	protected function _allowed($user)
	{
		$this->select('docsManualSelect', 'label=Splunk Installation Manual');
		$this->waitForPageToLoad('10000');
		$this->assertEquals('Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs', $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent('link=Branch'), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->select('docsManualSelect', 'label=Splunk Installation Manual');
		$this->waitForPageToLoad('10000');
		$this->assertEquals('Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs', $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent('link=Branch'), $user);
	}
}
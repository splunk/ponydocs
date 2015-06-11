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
			'splunk_docteam' => FALSE,
			'storm_docteam'  => TRUE,
			'docteam'		=> FALSE
		);
	}
	
	protected function _allowed($user)
	{
		$this->select('docsProductSelect', 'label=Storm');
		$this->waitForPageToLoad('10000');
		$this->assertEquals('Documentation/Storm - PonyDocs', $this->getTitle(), $user);
		$this->select('docsManualSelect', 'label=Storm Installation Manual');
		$this->waitForPageToLoad('10000');
		$this->assertEquals('Documentation:Storm:Installation:WhatsintheStormInstallationManual:1.0 - PonyDocs', $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent('link=Branch'), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->select('docsProductSelect', 'label=Storm');
		$this->waitForPageToLoad('10000');
		$this->assertEquals('Documentation/Storm - PonyDocs', $this->getTitle(), $user);
		$this->select('docsManualSelect', 'label=Storm Installation Manual');
		$this->waitForPageToLoad('10000');
		$this->assertEquals('Documentation:Storm:Installation:WhatsintheStormInstallationManual:1.0 - PonyDocs', $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent('link=Branch'), $user);
	}
}
<?php

class Splunk_ViewManuals extends AbstractAction
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
		$this->open("/Documentation:Splunk:Manuals");
		$this->assertEquals("Documentation:Splunk:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent("link=Edit"), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->open("/Documentation:Splunk:Manuals");
		$this->assertNotEquals("Documentation:Splunk:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent("link=Edit"), $user);
	}
}
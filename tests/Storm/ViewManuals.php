<?php

class Storm_ViewManuals extends AbstractAction
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
		$this->open("/Documentation:Storm:Manuals");
		$this->assertEquals("Documentation:Storm:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent("link=Edit"), $user);
	}
	
	protected function _notAllowed($user)
	{
		$this->open("/Documentation:Storm:Manuals");
		$this->assertNotEquals("Documentation:Storm:Manuals - PonyDocs", $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent("link=Edit"), $user);
	}
}
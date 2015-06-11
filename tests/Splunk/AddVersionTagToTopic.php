<?php

class Splunk_AddVersionTagToTopic extends AbstractAction
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
		$this->select("docsManualSelect", "Splunk Installation Manual");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertTrue($this->isElementPresent("link=Edit"), $user);
		$this->click("link=Edit");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Editing Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user);
		$this->type("wpTextbox1", "= What's in the Splunk Installation Manual =\n\n'''1.0'''\n\nUse this guide to find [[Documentation:Installation:SystemRequirements|system requirements]], [[Documentation:Installation:installalicense|licensing information]], and [http://www.splunk.com/base/Documentation/latest/Installation/Aboutupgradingto4.1:READTHISFIRST procedures for installing or migrating Splunk].\n\n==Find what you need==\nYou can use the table of contents to the left of this panel, or simply search for what you want in the search box in the upper right. \n\nIf you're interested in more specific [http://www.splunk.com/wiki scenarios and best practices], you can visit the [http://www.splunk.com/wiki Splunk Community Wiki] to see how other users Splunk IT.\n\n====Make a PDF====\nIf you'd like a PDF of any version of this manual, click the '''pdf version''' link above the table of contents bar on the left side of this page. A PDF version of the manual is generated on the fly for you, and you can save it or print it out to read later. \n\n[[Category:V:Splunk:1.0]][[Category:V:Splunk:1.1]]");
		$this->click("wpSave");
		$this->waitForPageToLoad("10000");
		$this->assertTrue($this->isElementPresent("link=exact:V:Splunk:1.1"), $user);
		$this->assertEquals("Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user);
		
		// Delete
		$this->click("link=Edit");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Editing Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user);
		$this->type("wpTextbox1", "= What's in the Splunk Installation Manual =\n\n'''1.0'''\n\nUse this guide to find [[Documentation:Installation:SystemRequirements|system requirements]], [[Documentation:Installation:installalicense|licensing information]], and [http://www.splunk.com/base/Documentation/latest/Installation/Aboutupgradingto4.1:READTHISFIRST procedures for installing or migrating Splunk].\n\n==Find what you need==\nYou can use the table of contents to the left of this panel, or simply search for what you want in the search box in the upper right. \n\nIf you're interested in more specific [http://www.splunk.com/wiki scenarios and best practices], you can visit the [http://www.splunk.com/wiki Splunk Community Wiki] to see how other users Splunk IT.\n\n====Make a PDF====\nIf you'd like a PDF of any version of this manual, click the '''pdf version''' link above the table of contents bar on the left side of this page. A PDF version of the manual is generated on the fly for you, and you can save it or print it out to read later. \n\n[[Category:V:Splunk:1.0]]");
		$this->click("wpSave");
		$this->waitForPageToLoad("10000");
		$this->assertEquals("Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user);
	}
	
	protected function _notAllowed($user)
	{
		// Create
		$this->select("docsManualSelect", "Splunk Installation Manual", $user);
		$this->waitForPageToLoad("10000", $user);
		$this->assertEquals("Documentation:Splunk:Installation:WhatsinSplunkInstallationManual:1.0 - PonyDocs", $this->getTitle(), $user);
		$this->assertFalse($this->isElementPresent("link=Edit"), $user);
	}
}
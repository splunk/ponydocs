PonyDocs 1.0 Beta 2 - June 14th, 2012
=====================================

Open-source software documentation Extension and Skin for MediaWiki

For assistance:
* Full Documentation: http://docs.splunk.com/Documentation/Ponydocs
* Mailing list: https://groups.google.com/forum/#!forum/ponydocs
* Developers: ponydocs@splunk.com

Splunk > Open Source FTW!

Prerequisites & Assumptions
---------------------------

1. You're a sysadmin
2. You have a MediaWiki system at the ready.
   PonyDocs has only been tested with [MW 1.16.4](http://bit.ly/KqnCbw?mediawiki-installer), PHP 5.2.16 or 5.3.3 and MySQL 5.1.52.
3. You can update apache's conf files for the MediaWiki vhost.
4. You can run SQL commands on the MediaWiki DB.
5. You know this is a Beta-quality release ;)
6. You promise to read AND follow all these steps IN ORDER.
7. You've made a backup of your MediaWiki DB in case you didn't meet the previous requirement.

It is further assumed that your wiki has 4 classes of users:

* Anonymous users and guests who are logged in
	* Users who are in the (default) or "user" group, and/or one of the per-Product "preview" groups.
	* They can *only* read and cannot edit any pages.
    * Preview users for a Product can view content in preview Versions.
* Employees
	* Users who are in the "Employee" group can edit any single page but cannot use advanced PonyDocs functions like editing
      TOCs, Versions, and Manuals, and Branching or Inheriting.
* Docteam members
	* Users who can edit content and access all PonyDocs Special Pages.
	* They are in the per-Product "docteam" group.
      i.e if you have a Product called "Foo", a docteam user would need to be in the "Foo-docteam" group.
* Admins
	* Users who can edit User group membership.

Quick Install Instructions
--------------------------

Note: Please complete all install instructions before attempting to use your new PonyDocs installation.
Failure to do so will result in frustration and keyboard tossing.

### 1) Configure Apache.

1. Modify your Apache configuration for the use of friendly urls.  
2. Modify your host to enable rewrite rules.

   The following is an example of an Apache configuration that assumes MediaWiki was installed at the base of your html directory.
   If your MediaWiki instance resides in a sub-directory, modify the configuration accordingly.

	```
	################# START SAMPLE APACHE CONFIGURATION #################
	RewriteEngine On
	# Main passthrus
	RewriteRule ^/api.php$			/api.php [L,QSA]
	RewriteRule ^/images/(.*)$		/images/$1 [L,QSA]
	RewriteRule ^/config/(.*)$		/config/$1 [L,QSA]
	RewriteRule ^/skins/(.*)$		/skins/$1 [L,QSA]
	RewriteRule ^/extensions/(.*)$	/extensions/$1 [L,QSA]

	# Rewrite /Documentation/ to /Documentation
	RewriteRule ^/Documentation/$   /Documentation [L,R=301]

	# Rewrite rule to handle passing ugly doc urls to pretty urls
	RewriteRule ^/Documentation:(.*):(.*):(.*):(.*)	/Documentation/$1/$4/$2/$3 [L,QSA,R=301]
	RewriteRule ^/Documentation:(.*):(.*):(.*)		/Documentation/$1/latest/$2/$3 [L,QSA,R=301]

	# get home page requests to Documentation
	RewriteRule ^/$ /Documentation [R]

	# all other requests go to specific page
	RewriteRule ^/(\/*)(.*)$ /index.php?title=$3 [PT,QSA]
	################# END SAMPLE APACHE CONFIGURATION #################
	```
3. Restart Apache so Rewrite Rules will take affect.

### 2) Modify LocalSettings.php

1. Set `$wgLogo` to the PonyDocs logo if you like!
2. Modify your `$wgGroupPermissions` to add PonyDoc's additional permissions to your existing groups.
	* These permissions are named are branchTopic, branchmanual, inherit, viewall.
	* You can also create new groups for your permissions.
	* Review [Manual:User_rights](http://www.mediawiki.org/wiki/Manual:User_rights) for more information.  
3. Make sure to define $wgArticlePath (some MediaWiki instances do not have this property defined.)
	* Refer to [Manual:$wgArticlePath](http://www.mediawiki.org/wiki/Manual:$wgArticlePath) for more information.  
   For example: if MediaWiki was installed at the root of your html directory:
   `$wgArticlePath = '/$1';`
4. Update all the PONYDOCS_ contents to fit to your installation.
	* `PONYDOCS_PRODUCT_LOGO_URL`
	* `PONYDOCS_PDF_COPYRIGHT_MESSAGE`
	* `PONYDOCS_PDF_TITLE_IMAGE_PATH`
	* `PONYDOCS_DEFAULT_PRODUCT`
	* `PONYDOCS_ENABLE_BRANCHINHERIT_EMAIL`
5. Add a `$ponyDocsProductsList` array. More on this in section 5.
6. Finally, you'll need to include the extension itself:
   `include_once($IP . "/extensions/PonyDocs/PonyDocsExtension.php");`

* An example in your LocalSettings.php file with all the settings listed above, would be:

	```
	################# PONYDOCS START #################
	// first things first, set logo
	$wgLogo = "/extensions/PonyDocs/images/pony.png";

	// Implicit group for all visitors, remove access beyond reading
	$wgGroupPermissions['*']['createaccount'] = false;
	$wgGroupPermissions['*']['edit'] = false;
	$wgGroupPermissions['*']['createpage'] = false;
	$wgGroupPermissions['*']['upload'] = false;
	$wgGroupPermissions['*']['reupload'] = false;
	$wgGroupPermissions['*']['reupload-shared'] = false;
	$wgGroupPermissions['*']['writeapi'] = false;
	$wgGroupPermissions['*']['createtalk'] = false;
	$wgGroupPermissions['*']['read'] = true;

	// User is logged-in. Ensure that they still can't edit.
	$wgGroupPermissions['user']['read'] = true;
	$wgGroupPermissions['user']['createtalk'] = false;
	$wgGroupPermissions['user']['upload'] = false;
	$wgGroupPermissions['user']['reupload'] = false;
	$wgGroupPermissions['user']['reupload-shared'] = false;
	$wgGroupPermissions['user']['edit'] = false;
	$wgGroupPermissions['user']['move'] = false;
	$wgGroupPermissions['user']['minoredit'] = false;
	$wgGroupPermissions['user']['createpage'] = false;
	$wgGroupPermissions['user']['writeapi'] = false;
	$wgGroupPermissions['user']['move-subpages'] = false;
	$wgGroupPermissions['user']['move-rootuserpages'] = false;
	$wgGroupPermissions['user']['purge'] = false;
	$wgGroupPermissions['user']['sendemail'] = false;
	$wgGroupPermissions['user']['writeapi'] = false;

	// Admin group.
	$wgGroupPermissions['bureaucrat']['userrights'] = true;
	// Custom permission to branch ALL Topics for a Version.
	$wgGroupPermissions['bureaucrat']['branchall'] = true;

	// Implicit group for accounts that pass $wgAutoConfirmAge
	$wgGroupPermissions['autoconfirmed']['autoconfirmed'] = true;

	// Implicit group for accounts with confirmed email addresses
	// This has little use when email address confirmation is off
	$wgGroupPermissions['emailconfirmed']['emailconfirmed'] = true;

	// Users with bot privilege can have their edits hidden from various log pages by default
	$wgGroupPermissions['bot']['bot'] = true;
	$wgGroupPermissions['bot']['autoconfirmed']	= true;
	$wgGroupPermissions['bot']['nominornewtalk'] = true;
	$wgGroupPermissions['bot']['autopatrol'] = true;

	$wgArticlePath = '/$1';

	// Ponydocs environment configuration. Update to your specifications
	define('PONYDOCS_PRODUCT_LOGO_URL', 'http://' . $_SERVER['SERVER_NAME'] . '/extensions/PonyDocs/images/pony.png');
	define('PONYDOCS_PDF_COPYRIGHT_MESSAGE', 'Copyright Foo, Inc. All Rights Reserved');
	define('PONYDOCS_PDF_TITLE_IMAGE_PATH', '/extensions/PonyDocs/images/pony.png');
	define('PONYDOCS_DEFAULT_PRODUCT', 'Foo');
	define('PONYDOCS_ENABLE_BRANCHINHERIT_EMAIL', true);

	// NOTE: this *must* match what is in Documentation:Products.
	// This will be fixed in later versions
	$ponyDocsProductsList = array('Foo');

	include_once($IP . "/extensions/PonyDocs/PonyDocsExtension.php");
	#################  PONYDOCS END #################
	```

### 3) Install PonyDocs extension and Configure MediaWiki to load it.

1. Move the extensions/PonyDocs/ directory into your MediaWiki instance's extensions directory.
2. Update your MediaWiki database schema by running extensions/PonyDocs/sql/schema.sql.
	* Remove this sql file as it's no longer needed and is publicly reachable via your PonyDocs site.
3. Activate the PonyDocs skin
	* There is a sample PonyDocs skin that is provided in this archive.
	* In order to demo PonyDoc's features, you can use this skin by moving the contents of the `skin/` directory (two files and 
	  one directory) to `MEDIAWIKIBASE/skins/`.
	* This skin is just a starting point. Please customize this skin to suit your needs.
	* To activate the skin, update the `$wgDefaultSkin` value in LocalSettings.php:
	  `$wgDefaultSkin = 'ponydocs';`

### 4) Take a look at PonyDocsConfig.php

* Take a look at extensions/Ponydocs/PonyDocs.config.php.
* This defines a bunch of constants, most of which you shouldn't need to touch.
* As of this writing, changing these values has not been tested.

### 5) Configure your Products

1. Edit LocalSettings.php and add a $ponyDocsProductsList array
	* $ponyDocsProductList *must* be defined before the PonyDocs extension is included, as in the example in section 2.
	* $ponyDocsProductList is used to create docteam and preview permission groups.
	* There needs to be at least one Product in the array.
      Here is a minimal example for the Product Foo:
      `$ponyDocsProductsList = array('Foo');`

      And here are three:
      `$ponyDocsProductsList = array('Foo', 'Bar', 'Bas');`

2. Set your default Product to be one of the above in LocalSettings.php:
   `define('PONYDOCS_DEFAULT_PRODUCT', 'Foo');`

### 6) Add your administrator user to appropriate user groups.

1. Login as your admin user, visit your MediaWiki instance's Special:UserRights page
   and add your admin user to the docteam group for each Product you want to edit, e.g. Foo-docteam.
2. Be sure the admin is in the docteam group for the `PONYDOCS_DEFAULT_PRODUCT` defined in step 5.

### 7) Create your Products on the front-end.

1. Log in as your administrator user and visit the Documentation:Products page.
	* If MediaWiki was installed at the base of your documentation root, the URL is /Documentation:Products.
2. Click on the "Create" tab at the top of the page to edit the page and add new Products.

##### The Documentation:Products page contains a listing of all your Products.

* Each line defines a single Product.
  `{{#product:productShortName|displayName|description|parent}}`
	* productShortName can be any alphanumeric string (no spaces allowed).
	* displayName is the human readable name of the Product you are documenting.
	* description is a short description of the Product
	* parent is the short name of a parent Product.
	* Child Products will be displayed under their parents in Product listings.
* Here's an example of a page with the three Products above:
	```
	{{#product:Foo|Foo for fooing|Foo is the synergy of three popular domain-specific languages|}}
	{{#product:Bar|Bar for the bar|You've never seen a Bar like this before|}}
	{{#product:Bash|Bas is not Quux|Bas is a Quux-like framework for rapid prototyping|Bar}}
	```
* Only productShortName is required. displayName will default to shortName if left empty. 
* Please do include all the pipes if you are leaving some variables empty:
  `{{#product:Quux|||}}`
* Once the page is saved, you'll be able to move to the next step, defining your Versions.
* As you add more Products, add more lines to the Documentation:Products page.
* Don't forget to add corresponding elements to the `$ponyDocsProductsList` array in LocalSettings.php, as
  documented in Step 5 above.

### 8) Create your first Product Version.

1. Logged in as your administrator user, visit the Documentation:productShortName:Versions page.
	* productShortName is the shortName of one of the Products defined above
	* If MediaWiki was installed at the base of your documentation root, then simply go to
	  /Documentation:productShortName:Versions.
2. Click on the "Create" tab at the top of the page to edit the page and add new Versions.

##### The Documentation:productShortName:Versions page contains a listing of all Versions of a Product and their status

* The status can be "released", "unreleased" or "preview".
* For regular users, only "released" Versions can be seen.
* For employee and productShortName-docteam users, all Versions can be seen.
* There is also a productShortName-preview group which can see preview Versions for that Product.
* Each line in Documentation:productShortName:Versions must use the following syntax to define a Version:
  `{{#version:versionName|status}}`
* versionName can be any alphanumeric string (no spaces or underscores allowed).
	* versionName should match your software's Version. Status is either "released", "unreleased" or  "preview".
	* For example, to initialize Version 1.0 of your Product, have the following line in your 
	  Documentation:productShortName:Versions page:
	  `{{#version:1.0|unreleased}}`
* Once the Documentation:productShortName:Versions page is saved, you'll be able to move to the next step, defining your first
  Manual.
* As you add more Versions of your Product, add more lines to the Documentation:productShortName:Versions page.

### 9) Create your first Manual.

1. Now head to /Documentation:productShortName:Manuals.
2. Click on the "Create" tab at the top of the page to edit the page and add new Manuals.

##### The Documentation:productShortName:Manuals page defines the Manuals available for a Product Version.

* A Version can have all the Manuals, or a sub-set of the Manuals you define here.
* You'll create the links of the Manuals to your first Version in the next step.
* For now, you'll need to define the first Manual.
* Each line in Documentation:productShortName:Manuals must use the following syntax to define a Manual:
  `{{#manual:manualShortName|displayName}}`
* manualShortName can be any alphanumeric string (no spaces allowed).
	* For example, "Installation".
* displayName is the human readable name of the Manual.
	* displayName can have spaces and is the full name of the Manual.
	* For example, "Installation Manual".
* The following lines create two Manuals called Installation and FAQ:
	```
	{{#manual:Installation|Installation Manual}}
	{{#manual:FAQ|FAQ}}
	```
* Once saved, you will see the listing of your Manuals.
* Each Manual name will be a link to create the Table of Contents for your current Version (in this case, the first Version you
  created in Documentation:productShortName:Versions).
* By clicking on the Manual name, you'll proceed to the next step. 

### 10) Create your first Table of Contents (TOC) and auto-generate your first Topic.

1. Clicking on the "Installation Manual" link from the Documentation:productShortName:Manuals page will direct you to
   Documentation:productShortName:InstallationTOC1.0 (if your Manual name was Installation and your first Version is 1.0).
	* TOC pages contain the Table of Contents of the Manual for that Version.
	* The TOC page consists of Section Names and the Topics which reside under those sections.
2. Click on the "Create" tab at the top of the TOC page to edit the TOC page.
   Use the following syntax to create the TOC for this Manual:
	```
	Section Header
	* {{#topic:Topic Title}}
	```
   For example:
	```
	Getting Started
	* {{#topic:Before You Begin}}
	* {{#topic:Next Steps}}
	Common problems
	* {{#topic:Disk Permissions}}
	* {{#topic:Database Errors}}

	[[Category:V:productShortName:1.0]]
	```

* Note the Category tag inside the TOC, which was auto-populated when the page was created.
	* This will ensure the TOC is linked to Version 1.0.
* You must have this category tag present in order for the TOC to properly render for that Version.
* You must have at least one Section Header before the first Topic tag, and all Topic tags must be unordered list items.
* When you save the edit to your first TOC page, links to your new Topics will automatically be created.
* Clicking on the Topic in the TOC page will take you to the new Topic, which you'll be able to edit with your new content.
* Note that each new Topic page is also auto-populated with a category tag (or tags).

This should get you started! Have fun!

### Optional: Install HTMLDOC

In the Ponydocs skin, there is a link to "PDF Version".

* If you would like this to work, you'll need to install [HTMLDOC](http://www.htmldoc.org/)
* Additionally, you'll need to make sure your MEDIAWIKIBASE/images directory is writable by your web server user.


F.A.Q.
------

Q. Why do I get the error "Table 'ponydocs_doclinks' doesn't exist"?  
A. You didn't apply schema.sql to your database in step 3.

Q. Why can't I edit or create any content?
A. Review step 6 and make sure your user is in the in the correct productShortName-docteam group(s)

Q. I've created all the pages, but the drop down for "Product Version" or "select Manual" is empty. Why?
A. Make sure the ponydocs skin is installed correctly.
   If you can't find anything wrong, restore your backup and try again from the beginning.
   This system has been tested and should work if you follow the steps in order.

Q. When I click "View PDF" I get an error, "Failed to create PDF. Our team is looking into it."  
A. Follow step 11 and install HTMLDOC.
   Check that the web server can write to the images directory in your MediaWiki install.
   
Q. How come the vanilla ponydocs skin shipping with the extension doesn't look anything like docs.splunk.com?  
A. Splunk as extended their skin without porting all enhancements back to PonyDocs.
   Our long-term plan is to port all Splunk-only features back to the PonyDocs skin,
   and to remove as many features as possible from the skin and into the extension

HISTORY
-------
* PonyDocs 1.0 Alpha 3 - May 24, 2011
* PonyDocs 1.0 Beta 1 - September 6, 2011
* PonyDocs 1.0 Beta 2 - June 14, 2012
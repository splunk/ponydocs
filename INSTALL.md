PonyDocs 1.0 Beta 2 - June 14th, 2012
=====================================

Open Source documentation based on MediaWiki

For any assistance:

	Ashley Jones
	ashley@splunk.com

Splunk > Open Source FTW!

Prerequisites & Assumptions
---------------------------

1. You're a sysadmin
2. You have a MediaWiki system at the ready.
   Beta 2 only tested with [MW 1.16.4](http://bit.ly/KqnCbw?mediawiki-installer), PHP 5.2.16 and 5.3.3 and MySQL 5.1.52.
3. You can update apache's conf files for the MediaWiki vhost
4. You can run SQL commands on the MediaWiki DB
5. You know this is Beta ;)
6. You promise to read AND follow all these steps IN ORDER
7. You've made a backup of your MediaWiki DB in case you didn't meet the previous requirement

It is further assumed that have 4 classes of users of your wiki:

* Anonymous and guests who are logged in
	* These are folks who fall into the (default) or "user" group. 
	* They can *only* read and can not edit any pages.
* Employees
	* Folks who are in the "Employee" group and can edit any single page but not use any advanced PonyDocs functions like creating
      TOCs, Versions and Branching or Inheriting
* Editors
	* Folks who can do it all, short of editing user perms.
	* They are in the "PRODUCT-docteam" group.
	* There is a per product docteam group so if you had a product called "Foo", the editor would need  to be in the "Foo-docteam"
	  group
* Admins
	* Folks who can add, remove, and move Employees and Editors to the different product docteam groups

Quick Install Instructions
--------------------------

Notes: Please complete all install instructions before attempting to use your new PonyDocs installation.
Failure to do so will result in frustration and keyboard tossing.

### 1) Configure Apache.

1. Modify your Apache configuration for the use of friendly urls.  
2. Modify your host to enable rewrite rules.

   The following is an example of the Apache configuration that assumes MediaWiki was installed at the base of your html directory.
   If your MediaWiki instance resides in a sub-directory, modify the configuration accordingly.

	```
	################# START SAMPLE APACHE CONFIGURATION #################
	RewriteEngine On
	# Main passthrus
	RewriteRule ^/api.php$	  /api.php	[L,QSA]
	RewriteRule ^/images/(.*)$	  /images/$1  [L,QSA]
	RewriteRule ^/config/(.*)$	  /config/$1  [L,QSA]
	RewriteRule ^/skins/(.*)$	   /skins/$1   [L,QSA]
	RewriteRule ^/extensions/(.*)$  /extensions/$1  [L,QSA]

	# Rewrite /Documentation/ to /Documentation
	RewriteRule ^/Documentation/$   /Documentation  [L,R=301]

	# Rewrite rule to handle passing ugly doc urls to pretty urls
	RewriteRule ^/Documentation:(.*):(.*):(.*):(.*) /Documentation/$1/$4/$2/$3  [L,QSA,R=301]
	RewriteRule ^/Documentation:(.*):(.*):(.*)	  /Documentation/$1/latest/$2/$3  [L,QSA,R=301]

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
	* These permissions are named are branchtopic, branchmanual, inherit, viewall.
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

	// Our "in charge" group.
	$wgGroupPermissions['bureaucrat']['userrights'] = true;
	// Custom permission to branch ALL topics for a version.
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

	// Ponydocs environment configuration.  update to your
	// specific install
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

### 4) Have a looksee at PonyDocsConfig.php

* Take a look at extensions/Ponydocs/PonyDocs.config.php.
* It will define a bunch of constants, most of which you shouldn't need to touch.
* As of this writing, changing these values has not been tested.

### 5) Define your products in the code.

1. Edit your LocalSettings.php to include a list of products.
	* There is an empty array already in place called $ponyDocsProductsList.
	* This array *must* be defined before the PonyDocs extension is included, as in the example in section 2.
	* This list will be used to determine user groups.
	* There needs to be at least one product in the array. If you don't define one, PonyDocs will default to a "Splunk" product.
   Here is one product defined (Foo):
   `$ponyDocsProductsList = array('Foo');`

   And here are three:
   `$ponyDocsProductsList = array('Foo', 'Bar', 'Bash');`
2. Set your default product to be one of the above, also in LocalSettings.php:
   `define('PONYDOCS_DEFAULT_PRODUCT', 'Foo');`

### 6) Add your administrator user to appropriate user groups.

1. Logged-in as your administrator user, visit your MediaWiki instance's Special:UserRights page and add your admin user to the
   productShortName-docteam group for each product you want to edit, e.g. Foo-docteam.
2. Be sure the admin is in the productShortName-docteam for the `PONYDOCS_DEFAULT_PRODUCT` defined in step 5.

### 7) Create your products on the front-end.

1. Log in as your administrator user and visit the Documentation:Products page.
	* If MediaWiki was installed at the base of your documentation root, then simply go to /Documentation:Products.
2. Click on the "Create" tab at the top of the page to edit the page and add new products.

##### The Documentation:Products page contains a listing of all your products.

* Each line defines a single product.
  `{{#product:productShortName|displayName|description|parent}}`
	* productShortName can be any alphanumeric string (no spaces allowed).
	* displayName is the human readable name of the product you are documenting.
	* description is a short description of the product
	* parent is the short name of a parent product.
	* Child products will be displayed under their parents in product listings.
* Here's an example of a page with the three products above:

	```
	{{#product:Foo|Foo for fooing|Foo is the synergy of three popular domain-specific languages|}}
	{{#product:Bar|Bar for the bar|You've never seen a Bar like this before|}}
	{{#product:Bash|Bash is not Quux|Bash is a Quux-like framework for rapid prototyping|Bar}}
	```
* Only productShortName is required. displayName will default to shortName if left empty. 
* Please do include all the pipes if you are leaving some variables empty:
  `{{#product:Quux|||}}`
* Once the page is saved, you'll be able to move to the next step, defining your versions.
* As you add more products, add more lines to the Documentation:Products page.
* Don't forget to add corresponding elements to the `$ponyDocsProductsList` array in LocalSettings.php, as
  documented in Step 5 above.

### 8) Create your first product version.

1. Logged in as your administrator user, visit the Documentation:productShortName:Versions page.
	* productShortName is the shortName of one of the products defined above
	* If MediaWiki was installed at the base of your documentation root, then simply go to
	  /Documentation:productShortName:Versions.
2. Click on the "Create" tab at the top of the page to edit the page and add new versions.

##### The Documentation:productShortName:Versions page contains a listing of all versions of a product and their status

* The status can be "released", "unreleased" or "preview".
* For regular users, only "released" versions can be seen.
* For employee and productShortName-docteam users, all versions can be seen.
* There is also a productShortName-preview group which can see preview versions for that product.
* Each line in Documentation:productShortName:Versions must use the following syntax to define a version:
  `{{#version:versionName|status}}`
* versionName can be any alphanumeric string (no spaces or underscores allowed).
	* versionName should match your software's version. Status is either "released", "unreleased" or  "preview".
	* For example, to initialize version 1.0 of your product, have the following line in your 
	  Documentation:productShortName:Versions page:
	  `{{#version:1.0|unreleased}}`
* Once the Documentation:productShortName:Versions page is saved, you'll be able to move to the next step, defining your first
  manual.
* As you add more versions of your product, add more lines to the Documentation:productShortName:Versions page.

### 9) Create your first manual.

1. Now head to /Documentation:productShortName:Manuals.
2. Click on the "Create" tab at the top of the page to edit the page and add new manuals.

##### The Documentation:productShortName:Manuals page defines the Manuals available for a product version.

* A version can have all the manuals, or a sub-set of the manuals you define here.
* You'll create the links of the manuals to your first version in the next step.
* For now, you'll need to define the first manual.
* Each line in Documentation:productShortName:Manuals must use the following syntax to define a manual:
  `{{#manual:manualShortName|displayName}}`
* manualShortName can be any alphanumeric string (no spaces allowed).
	* For example, "Installation".
* displayName is the human readable name of the manual.
	* displayName can have spaces and is the full name of the Manual.
	* For example, "Installation Manual".
* The following lines create two manuals called Installation and FAQ:
	```
	{{#manual:Installation|Installation Manual}}
	{{#manual:FAQ|FAQ}}
	```
* Once saved, you will see the listing of your manuals.
* Each manual name will be a link to create the Table of Contents for your current version (in this case, the first version you
  created in Documentation:productShortName:Versions).
* By clicking on the Manual name, you'll proceed to the next step. 

### 10) Create your first Table of Contents (TOC) and auto-generate your first topic.

1. Clicking on the "Installation Manual" link from the Documentation:productShortName:Manuals page will direct you to
   Documentation:productShortName:InstallationTOC1.0 (if your manual name was Installation and your first version is 1.0).
	* TOC pages contain the Table of Contents of the manual for that version.
	* The TOC page consists of Section Names and the topics which reside under those sections.
2. Click on the "Create" tab at the top of the TOC page to edit the TOC page.
   Use the following syntax to create the TOC for this manual:
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

* Note the use of the Category tag inside the TOC, which should have been auto-populated when the page was created.
	* This will ensure the TOC is linked to version 1.0.
* You must have this category tag present in order for the TOC to properly render for that version.
* You must have at least one Section Header before the first topic tag, and all topic tags must be unordered list items.
* When you save the edit to your first TOC page, links to your new topics will automatically be created.
* Clicking on the topic in the TOC page will take you to the new topic, which you'll be able to edit with your new content.
* Note that each new topic page is also auto-populated with a category tag (or tags).

### 11) Install and configure wkhtmltopdf

In the Ponydocs skin, there is a link to "PDF Version".

* If you would like this to work, you'll need to install wkhtmltopdf. It can be downloaded here:
  https://code.google.com/p/wkhtmltopdf/downloads/list
* We recommend putting the binary in extensions/Ponydocs/bin/. Make sure it's executable by the web server user.
* PonyDocs has only been tested with wkhtmltopdf version 0.10.0 rc2
* Additionally, you'll need to make sure your MEDIAWIKIBASE/images directory is writable by your web server user.

This should get you started! Have fun!


F.A.Q.
------

Q. Why do I get an error "Table 'ponydocs_doclinks' doesn't exist"?  
A. You likely missed running the schema.sql file in step 3.

Q. Why can't I edit or create any docs pages?  
A. Go back to step 6 and make sure your user is in the in the correct productShortName-docteam group(s)

Q. I've created all the pages, but the drop down for "product version" or "select manual" is empty. Why?  
A. Reset to the backup you made before starting and try again.
   Though there are some corners you can paint yourself into, this system has been tested and should work if you follow the steps
   in order.

Q. When I click "View PDF" I get an error, "Failed to create PDF. Our team is looking into it."  
A. Follow step 11 to configure wkhtmltopdf.
   Check that the web user can write to the images directory in your MediaWiki install.
   
Q. How come the vanilla ponydocs skin shipping with the extension doesn't look anything like docs.splunk.com?  
A. The web development team has greatly extended the skin shipping with ponydocs.
   Long term, the plan is to port all delta of features between the two so that the entire docs.splunk.com feature set is
   available in the ponydocs skin.

HISTORY
-------
* PonyDocs 1.0 Alpha 3 - May 24, 2011
* PonyDocs 1.0 Beta 1 - September 6, 2011
* PonyDocs 1.0 Beta 2 - June 14, 2012
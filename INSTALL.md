Ponydocs 1.1 - June 2015
=======================

Prerequisites & Assumptions
---------------------------

1. Requirements
	* MediaWiki 1.24.x
	* PHP 5.2.x, 5.3.x or 5.4.x
	* Apache 2.x
1. You've installed MediaWiki and it's working
1. You've made a backup of your MW database in case anything goes wrong

Ponydocs assumes you have 4 classes of users:

* Anonymous and guests who are logged in
	* These are folks who fall into the (default) or "user" group. 
	* They can *only* read and can not edit any pages.
* Employees
	* Folks who are in the "Employee" group and can edit any single page but not use any advanced Ponydocs functions like creating
      TOCs, Versions and Branching or Inheriting
* Editors
	* Folks who can do it all, short of editing user perms.
	* They are in the "PRODUCT-docteam" group.
	* There is a per product docteam group so if you had a product called "Foo", the editor would need  to be in the "Foo-docteam"
	  group
* Admins
	* Users who can edit User group membership.

PonyDocs requires you to have [Short URLs](https://www.mediawiki.org/wiki/Manual:Short_URL) enabled.

This INSTALL document assumes that your wiki URLs match your MediaWiki docroot - which is not a MW best-practice.
However it should be easy to modify the example configurations here to support a more standard MW URL structure.


Quick Install Instructions
--------------------------

### 1) Patch MediaWiki

The way that Ponydocs maps many URLs to the same MW Page/Title requires a small patch. We don't fully understand the ramifications
of this change, and we are working on figuring out how to map URLs without patching core MW.

Apply MediaWiki.patch in this directory to your MediaWiki directory. Or read the patch and make the modification manually, it's
a one-line change.

### 2) Configure Apache.

The following is an example Apache configuration that assumes MediaWiki is installed in the docroot.
If MediaWiki is installed in a subdirectory of the docroot, modify the configuration accordingly.

	```
	################# START SAMPLE APACHE CONFIGURATION #################
	RewriteEngine On

	# Rewrite home page requests to Documentation
	RewriteRule ^/$ /Documentation [R]

	# Rewrite /Documentation/ to /Documentation
	RewriteRule ^/Documentation/$   /Documentation  [L,R=301]

	# Proxy /DocumentationStatic to Special:StaticDocServer
	RewriteRule ^/DocumentationStatic	- [L]
	ProxyPass /DocumentationStatic/	http://ponydocs.example.com/Special:StaticDocServer/

	# Rewrite ugly doc urls to pretty urls
	RewriteRule ^/Documentation:(.*):(.*):(.*):(.*)	/Documentation/$1/$4/$2/$3 [L,QSA,R=301]
	RewriteRule ^/Documentation:(.*):(.*):(.*)			/Documentation/$1/latest/$2/$3 [L,QSA,R=301]

	# Send all other requests to MediaWiki
	# NB: If you are not using vhosts, or are using apache < 2.2, remove %{DOCUMENT_ROOT}
	#     See discussion of REQUEST_FILENAME in http://httpd.apache.org/docs/current/mod/mod_rewrite.html#rewritecond
	RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME}	!-f
	RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME}	!-d
	RewriteCond %{REQUEST_URI}							!=/favicon.ico
	RewriteRule ^/.*$	/index.php [L]
	################# END SAMPLE APACHE CONFIGURATION #################
	```

Restart Apache

### 3) Install PonyDocs extension and PonyDocs skin

1. Move the extensions/PonyDocs/ directory to the MW extensions/ directory.
2. Move the contents of the skins/ directory (one directory and two files) to the MW skins/ directory
	* This skin is just a starting point. Please customize this skin to suit your needs.
3. Apply extensions/PonyDocs/sql/schema.sql to your MediaWiki database.
	* Remove this sql file as it's no longer needed and is publicly reachable via your PonyDocs site.

### 4) Modify LocalSettings.php

* $wgArticlePath is included here as it's necessary for Short URLs and is not always configured by default.
	* See [Manual:$wgArticlePath](http://www.mediawiki.org/wiki/Manual:$wgArticlePath)

The following is an example LocalSettings.php file with settings that make sense for ponydocs

```
################# PONYDOCS START #################

# The settings below should be updated

// Ponydocs environment configuration
define('PONYDOCS_PRODUCT_LOGO_URL', 'http://' . $_SERVER['SERVER_NAME'] . '/extensions/PonyDocs/images/pony.png');
define('PONYDOCS_PDF_COPYRIGHT_MESSAGE', 'Copyright Foo, Inc. All Rights Reserved');
define('PONYDOCS_PDF_TITLE_IMAGE_PATH', '/extensions/PonyDocs/images/pony.png');
define('PONYDOCS_DEFAULT_PRODUCT', 'Foo');
define('PONYDOCS_ENABLE_BRANCHINHERIT_EMAIL', true);

// NOTE: this *must* match the Product list in Documentation:Products.
$ponyDocsProductsList = array('Foo');

# The settings below may be updated

// Logo
$wgLogo = "$wgScriptPath/extensions/PonyDocs/images/pony.png";

// Debug logging
define( 'PONYDOCS_AUTOCREATE_DEBUG', FALSE );
define( 'PONYDOCS_CACHE_DEBUG', FALSE );
define( 'PONYDOCS_CASE_INSENSITIVE_DEBUG', FALSE );
define( 'PONYDOCS_DOCLINKS_DEBUG', FALSE );
define( 'PONYDOCS_REDIRECT_DEBUG', FALSE );
define( 'PONYDOCS_SESSION_DEBUG', FALSE );

// Temp directory
define( 'PONYDOCS_TEMP_DIR', '/tmp/');

// Category cache expiration in seconds
define( 'CATEGORY_CACHE_TTL', 300 );
define( 'NAVDATA_CACHE_TTL', 3600 );
define( 'TOC_CACHE_TTL', 3600 );

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

$wgDefaultSkin = 'ponydocs';

# The settings below should not be updated

// Enable cache
define( 'PONYDOCS_CACHE_ENABLED', TRUE );

// Namespace setup
define( 'PONYDOCS_DOCUMENTATION_NAMESPACE_NAME', 'Documentation' );
define( 'NS_PONYDOCS', 100 );
$wgExtraNamespaces[NS_PONYDOCS] = PONYDOCS_DOCUMENTATION_NAMESPACE_NAME;
// Include the PonyDocs namespace in article counts
$wgContentNamespaces[] = NS_PONYDOCS;

include_once($IP . "/extensions/PonyDocs/PonyDocsExtension.php");
#################  PONYDOCS END #################
```

### 5) Review PonyDocsConfig.php

* Take a look at extensions/Ponydocs/PonyDocs.config.php.
* It defines a bunch of constants, most of which you shouldn't need to touch.
* As of this writing, changing these values has not been tested.

### 6) Define your products in the code.

1. Edit LocalSettings.php to include a list of products.
	* If you copied the example LocalSettings.php configuration, there is an already an array named $ponyDocsProductsList.
	* This array *must* be defined before the PonyDocs extension is included.
	* This list is used to define user groups.
	* There needs to be at least one product in the array. If you don't define one, PonyDocs will default to a "Splunk" product.
   Here is one product defined (Foo):
   `$ponyDocsProductsList = array('Foo');`

   And here are three:
   `$ponyDocsProductsList = array('Foo', 'Bar', 'Bash');`
2. Set your default product to be one of the above, also in LocalSettings.php:
   `define('PONYDOCS_DEFAULT_PRODUCT', 'Foo');`

### 7) Add your administrator user to appropriate user groups.

1. Logged-in as your administrator user, visit your MediaWiki instance's Special:UserRights page and add your admin user to the
   productShortName-docteam group for each product you want to edit, e.g. Foo-docteam.
2. Be sure the admin is in the productShortName-docteam for the `PONYDOCS_DEFAULT_PRODUCT` defined in step 5.


### 8) Create your Products on the front-end.

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

### 9) Create your first Product Version.

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
	  Documentation:productShortName:Versions page: `{{#version:1.0|unreleased}}`
* Once the Documentation:productShortName:Versions page is saved, you'll be able to move to the next step, defining your first
  Manual.
* As you add more Versions of your Product, add more lines to the Documentation:productShortName:Versions page.

### 10) Create your first Manual.

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

### 11) Create your first Table of Contents (TOC) and auto-generate your first Topic.

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
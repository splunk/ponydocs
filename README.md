PonyDocs
========

A MediaWiki module for software documentation
---------------------------------------------

PonyDocs is the software that powers Splunk's documentation site, [docs.splunk.com](http://docs.splunk.com).

MediaWiki Features
------------------

* **Revisions** -
  As with all Pages in a MediaWiki instance, you can recall the history of all edits for every piece of content in your docs.
* **Non-html wiki markup** -
  Editors don't need to learn HTML! Instead they can use simple to use wiki markup.
* **Edits welcome** -
  The spirit of a wiki is that it's "easy to correct mistakes, rather than making it difficult to make them". 
  Encourage all your employees to fix the areas of your docs where they're experts.
* **LAMP Architecture** -
  http://docs.splunk.com handles tens of thousands of unique visitors a month and over half a million page views
  thanks to the the robust LAMP architecture.

Documentation-specific Features
-------------------------------

* **Products** -
  PonyDocs allows you create separate Products with the same system.
* **Versions** -
  Your customers can know they're looking at the right docs for the Version of the Product they're running.
* **Manuals** -
  You may just have an install Manual, or you may have 15 Manuals covering myriad Topics.
* **Table of Contents** -
  Within each manual, you can have as many Topics as you want, organized in a Table of Contents.
  You can re-order Topics dynamically within the TOC, and navigation is automatically created to reflect your changes.
* **All MediaWiki All the time** -
  All edits done with wiki markup, and no special HTML or SQL calls for your editors to learn.
* **Everything editor controlled** -
  All content in a PonyDocs instance (like http://docs.splunk.com) is fully editable. Nothing is hard-coded.
* **PDF output** -
  Sometimes there's nothing like a very well-formatted, fully-contained, single file for your documentation.
  We support one-click generation of any manual into a single PDF.
* **Per-product edit permissions** -
  Encouraging your community to edit is good, but sometimes you need to allow specific team members to edit specific Products.
  There's a "docteam" group for each Product, and a generic "employee" permission that can edit Topics in any Product.
* **Branching and Inheriting** -
  Often Topics will have the exact same content across multiple versions.
  You can tag Topics with multiple Versions (inherit) so you're only maintaining one copy.
  When a major release changes how something works, you can copy the content and divorce it from previous shared uses (branch).
  It's possible to branch or inherit entire Manuals or Products into new Versions.
* **PonyDocs-aware "what links here"** -
  PonyDocs extends MediaWiki in some unique ways, so we've recreated the "what links here" pages to be aware of our Topic links,
  including branched and inherited topics.
* **Semantic URLS** -
  URLs contain the Product, Manual, Version, and Topic name in a human-readable format that's good for search indexes.

Software Documentation-specific Features
----------------------------------------

* **Per-version view permissions** -
  With software, there's always a next release coming up.
  Versions can be unreleased (only visible by per-product docteam or global employee groups),
  preview (only viewable by per-product preview groups) or released (public).
* **Static html support** -
  PonyDocs allows you to import the output of Javadoc in your docs as a peer of other manuals for a product.
* **System-wide banner** -
  Often, as in the case of an emergency patch or the end of life of a major Version, you need to publish a site-wide banner.
  Banners can apply to entire Products, or a specific set of Versions within a Product.
* **"latest"** -
  The special "latest" Version always returns the most recently released Version of a Topic.
  If the Topic requested does not exist in the most recent Version, we'll find the last Version that it did exist in.
  Check out [the "latest" page for Splunk's manual](http://docs.splunk.com/Documentation/Splunk/latest) for an example.
  When viewing a previous Version of a Topic, PonyDocs will display a "not the latest version" banner.
  This helps visitors who have found your documentation via a search result know they're not viewing the latest docs.
* **Helpful redirects** -
  PonyDocs automaticall redirects you from a Manual URL to the first Topic in the Manual.

For assistance
--------------

* Full Documentation: http://docs.splunk.com/Documentation/Ponydocs
* Mailing list: https://groups.google.com/forum/#!forum/ponydocs
* Developers: ponydocs@splunk.com
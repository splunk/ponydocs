PonyDocs
========

A MediaWiki module for software documentation
---------------------------------------------

PonyDocs is the software that powers Splunk's documentation site, [docs.splunk.com](http://docs.splunk.com).

MediaWiki Features
------------------

* **Revisions** - As with all topics in a MediaWiki instance, all edits are available for audits and paper trail
  to see the history of every last piece of content in your docs.
* **Non-html wiki markup** - Editors don't need to learn HTML! Instead they can use simple to use wiki markup
* **Edits welcome** - The spirit of a wiki is that it's "easy to correct mistakes, rather than making it difficult to make them". 
  Encourage all your employees to fix the areas of your docs where they're experts.
* **LAMP Architecture** - The Splunk docs page easily handles tens of thousands of unique visitors a month 
  across over half a million page views thanks to the the robust LAMP architecture.

Documentation-specific Features
-------------------------------

* **Products** - Ponydocs allows you create separate products with the same system.
* **Versions** - Your customers can know they're looking at the right docs for the version of the product 
  they're running.
* **Manuals** - You may just have an install manual, or you may have 15 manuals covering myriad topics.
* **Topics** - Within each manual, you can have as many topics as you want. 
  You can re-order them dynamically within a manual, and your table of contents is automatically created to reflect your changes.
* **All MediaWiki All the time** - All edits done with wiki markup, and no special HTML or SQL calls for your editors to learn.
* **Everything editor controlled** - Any content you see in a Ponydocs instance (like docs.splunk.com) is fully editable
  and nothing is hard-coded.
* **PDF output** - Sometimes there's nothing like a very well-formatted, fully-contained, single file for your documentation.
  We support one-click generation of any manual into a single PDF.
* **Per-product permissions** - Encouraging your community to edit is good, but sometimes you need to allow specific team members
  to edit on specific products. This includes a generic "employee" permissions.
* **Branching and Inheriting** - Often topics like "Hardware Requirements" or "How to get support" will have the exact same content.
  You can repurpose topics across multiple versions (inherit) so you're only maintaining one copy.
  Additionally, when something like a major release totally changes how something works,
  you can easily copy the content and divorce it from previous shared uses (branch).
* **Ponydocs-aware "what links here"** - Given that Ponydocs extends MediaWiki in some unique ways,
  we've recreated the "what links here" pages to be aware of our topic links, including branched and inherited topics

Software Documentation-specific Features
----------------------------------------

* **Preview and Unreleased** - With software, there's always a next release coming up.
  With that in mind, Ponydocs supports the concept of a version being "Unreleased".
  This way folks in the employee group can view what's up next without the general public seeing it.
  Have an upcoming beta release you'd like to give certain customers the ability to see?
  Drop them in the per-product preview group and they can see any versions marked as "Preview"
* **Static html support** - Ponydocs allows you to easily include the output of Javadoc in your docs
  as a peer of other manuals for a product.
* **System-wide banner** - Often, as in the case of an emergency patch or the end of life of a major version,
  you need to publish a site-wide banner.
  Ponydocs has built in support for messaging within ranges of versions for a given product.
* **"latest"** - The concept of latest always works for the the most recent version of a product.
  Check out [the "latest" page for Splunk's manual](http://docs.splunk.com/Documentation/Splunk/latest) to see an example.
  As well, any topic that isn't the latest version will display a "not the latest version" banner.
  This helps visitors who have found your documentation via a search result know they're not viewing the latest docs.
* **Helpful redirects** - Given how dynamic the MediaWiki system is, Ponydocs goes out of its way to redirect you
  from a generic manual URL to the first topic on the manual.
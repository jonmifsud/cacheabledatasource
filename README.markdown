# DB Datasource Cache

* Version: 0.7
* Authors: [Nick Dunn](http://nick-dunn.co.uk/), [Jonathan Mifsud](http://jonmifsud.com), [Giel Berkers](http://www.gielberkers.com)
* Build Date: 2011-12-07
* Requirements: Symphony 2.2

Explorations from a forum discussion [Datasource Caching](http://symphony-cms.com/discuss/thread/32535/).

## DISCLAIMER
While still relatively untested this extension has been in production on a couple of sites and has greatly improved performance! Currently monitoring where cache is expected to grow quite big as in this territory its still relatively untested.

## Rationale

Some datasources simply execute a lot of database queries, and if you run a busy website then certain DSs may be a performance hit. Presently you have several options:

* reduce the number of fields and entries your DS is querying
* use the [Cachelite extension](http://symphony-cms.com/download/extensions/view/20455/) to cache the entire rendered HTML output of pages (useful to survive the Digg-effect)

However sometimes neither of these are viable. Perhaps you really *need* all of that data in your XML, or perhaps you have a "Logged in as {user}" notice in the header that means you can't cache the HTML output for all users.

This extension bundles a `dbdatasourcecache` class from which your data sources can extend.

## How do I use it?
Install this extension. Actual Installation/Update is required as cache does not activate unless latest version is installed.

Once installed, an option appears when editing datasources, allowing you to cache them by simple ticking a checkbox and setting a time limit.

## Refresh your frontend page
View the `?debug` XML of your frontend page and you should see the cached XML and the age of the cache in seconds. The cached XML might jump to the top of the order in the XML source. This is normal, and is a by-product of how Symphony works out ordering on the fly.

## Are Output Parameters supported?
Yes! If a DS outputs parameters into the param pool then these are cached along with the XML.

## Can I force an update at a particular time?
Yes! This is possible by setting a new variable with the expiry time
		
		public $dsParamLASTUPDATE = 0; //on top of file where 0 is the expiry time (seconds since epoch) 0 will mean that cache will not expire obviously
		
		$this->dsParamLASTUPDATE = strtotime('00:00');//in your construct
		
## Does cache purge when I update?
The cache is meant to purge automatically for every entry that you update; however all datasources in that section are purged resetting your cache. To purge your cache on a per entry/datasource what you have to do is add the following into your datasource.
		
		public $dsParamFLUSH = array(
				'title' => 'handle',//where title is your field name and handle is the url parameter
		);

This allows mapping of url parameters to field names; and when these match on field update the entry is cleared. If you do not want to purge the cache you can insert an invalid value eg -1 instead of handle and this will never match
		
## How do I purge the cache?
Simply go into System menu and find Cacheable DB Datasource; from there you can select which datasource you want to purge the cache for.

## Why are so many cache entries created?
Cache entries are never deleted, only overwritten when they have expired. It is normal to have many entries generated for each data source since the filename is a hashed signature of all of its configuration properties and filters. This means that if you have pagination enabled, a cache entry is generated for each page of results since each page creates a unique combination of filters.

It works this way to allow for very wide, rather than narrow, hierarchies. Say you have a site showcasing bands, 10,000 in total. Your Band page accepts an artist entry ID and filters the Bands section to show that single entry. For this wide sitemap, you would require each instance of the Band Profile datasource to be cached individually. Which it is :-)

## Changelog

* 0.7, 2011-12-07
    * Adds functionality to set the caching by using the backend instead of editing your datasources manually.

* 0.6, 2011-11-29
	* First public release of the extension that superseeds cacheabledatasource provided by Nick Dunn. Stores into database and adds some additional features.

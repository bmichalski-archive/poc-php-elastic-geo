# php-elastic-geo-poc
An Elastic / PHP GeoSpatial search POC

Simple test:
<pre>
    php geo-indexing-test.php HOST TREE [options]
</pre>
    
Available TREEs are:
* quadtree
* geohash

Options:
* --port PORT, an integer, the port to use
* --index INDEX, a string, the ES index to use
* --type TYPE, a string, the ES type to use
* either (not or) :
** --treeLevels TREE_LEVELS, integer
** --precision PRECISION, in meters and an integer
* --tab, whether or not to output tab separated data
* --bulk-size BULK_SIZE, an integer, the size of the bulk

See usage in multi-geo-indexing-tests.php.

Multiple tests:
<pre>
php multi-geo-indexing-tests.php HOST [options]
</pre>

Options:
* --port PORT, an integer, the port to use
* --index INDEX, a string, the ES index to use
* --type TYPE, a string, the ES type to use

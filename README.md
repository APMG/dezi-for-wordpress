dezi-for-wordpress
==================

Dezi search plugin for Wordpress.

Copyright 2012 American Public Media Group.

Licensed under the MIT license.

Based on Solr for WordPress plugin.

## Description ##

A WordPress plugin that replaces the default WordPress search with Solr.  Features include:

 * Index pages and posts
 * Enable faceting on fields such as tags, categories, author, and page type.
 * Indexing and faceting on custom fields
 * Multisite support
 * Treat the category facet as a taxonomy
 * Add special template tags so you can create your own custom result pages to match your theme.
 * Completely replaces default WordPress search, just install and configure.
 * Completely integrated into default WordPress theme and search widget.
 * Configuration options allow you to select pages to ignore, features to enable/disable, and what type of result information you want output.
 * i18n Support
 * Multi server/core support

Note that this plugin requires you to have an instance of Dezi 
using a schema with the following fields: 
id, permalink, title, content, numcomments, categories, categoriessrch, tags, tagssrch, author, type, and text.
The plugin is distributed with a Dezi config file you can use at *dezi-config.pl*.


== Installation ==

1. Upload the *dezi-for-wordpress* folder to the */wp-content/plugins/* directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin with the hostname, port, and URI path to your Dezi installation.
4. Load all your posts and/or pages via the "Load All Posts" button in the settings page.

= Custom Theme Integration =
1. Create a new theme file called "dezi4w_search.php".
2. Insert your markup, use template methods dezi4w_search_form() and dezi4w_search_results() to insert the search box and results respectively.
3. Add result styling to your theme css file, see *dezi-for-wordpress/template/search.css* for an example.
4. You can use the search widget in your sidebar for search, or use a custom search box that submits the query in the parameter "s".




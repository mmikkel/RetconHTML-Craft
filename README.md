# Retcon HTML plugin for Craft CMS

## Got WYSIWYG?

_Those clients, eh? Yeah, sure – put a 4 MB png file in your Redactor field, and don't bother applying a transform or anything. See if I care!_

Retcon HTML adds a really nifty Twig filter to Craft, enabling you to easily do some pretty cool rewrites to any HTML block. Bulk transform images, add or replace attributes, change tag types, correct header hierarchies – and more.

Note that Retcon does not contain any methods for outright _cleaning_ your HTML – e.g. removing empty paragraphs – as Craft does a pretty good job with that stuff out of the box.

### Features

* Add or replace HTML attributes
* Bulk image transforms
* Rewrite image tags for lazy loading
* Wrap stuff in other stuff
* Remove containers by selector
* Extract containers by selector
* Change tag types
* Automatically correct header hierarchy

### Usage

Retcon HTML works with a single Twig filter – _retcon_.

* Bulk transforming images

Method name: transform
Parameters:

@transform (either a named transform, or an object)

{{ yourHtml | retcon( 'transform', 'yourNamedTransform' ) }}
{{ yourHtml | retcon( 'transform', { width : 1024, height : 680, mode : 'fit' } ) }}

* Rewrite image tags for lazy loading

Method name: lazy
Parameters

@className (optional, name of class to add to rewritten images, defaults to ".lazy")
@attributeName (optional, name of the data attribute where the original src will be stored, defaults to "original")

{{ yourHtml | retcon( 'lazy' ) }}
{{ yourHtml | retcon( 'lazy', 'myLazyImage' ) }}
{{ yourHtml | retcon( 'lazy', 'myLazyImage', 'orgsrc' ) }}

* Add or replace HTML attributes

Method name: attr
Parameters:

@attr



// Chaining

{{ yourHtml | retcon( [
	{ 'transform', 'yourNamedTransform' }, // Transform all images using the named transform "myNamedTransform"
	{ 'lazy', 'myLazyImageClass' }, // Rewrite all images for lazy loading, using the class .myLazyImageClass
	{ 'attr', 'img', 'class', 'myImageClass' }, // Add the class .myImageClass to all image tags
] ) }}

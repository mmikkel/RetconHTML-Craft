<?php
namespace Craft;

use Twig_Extension;
use Twig_Filter_Method;

class RetconHtmlTwigExtension extends \Twig_Extension
{

	public function getName()
    {
        return 'Retcon HTML';
    }

    public function getFilters()
	{
		return array(
			// Apply an image transform to all <img> tags
			'retconTransform' => new Twig_Filter_Method( $this, 'transform' ),
			// Rewrite <img> tags for lazy loading
			'retconLazy' => new Twig_Filter_Method( $this, 'lazy' ),
			// Add automatic alt tags to images
			'retconAutoAlt' => new Twig_Filter_Method( $this, 'autoAlt' ),
			// Add (or overwrite) attributes for selector
			'retconAttr' => new Twig_Filter_Method( $this, 'attr' ),
			// Wrap stuff in other stuff!
			'retconWrap' => new Twig_Filter_Method( $this, 'wrap' ),
			// Unwrap stuff
			'retconUnwrap' => new Twig_Filter_Method( $this, 'unwrap' ),
			// Remove the matching selector(s)!
			'retconRemove' => new Twig_Filter_Method( $this, 'remove' ),
			// Remove *everything but* the matching selector(s)!
			'retconOnly' => new Twig_Filter_Method( $this, 'only' ),
			// Change tag type
			'retconChangeTag' => new Twig_Filter_Method( $this, 'change' ),
		);
	}

	public function transform( $input, $transform )
	{
		return $transform ? craft()->retconHtml->transform( $input, $transform ) : $input;
	}

	public function lazy( $input, $className = null, $attributeName = null )
	{
		return craft()->retconHtml->lazy( $input, $className, $attributeName );
	}

	public function autoAlt( $input, $overwrite = false )
	{
		return craft()->retconHtml->autoAlt( $input, $overwrite );
	}

	public function attr( $input, $selectors, $attributes = array(), $overwrite = true )
	{
		return craft()->retconHtml->attr( $input, $selectors, $attributes, $overwrite );
	}

	public function wrap( $input, $selectors, $wrapper )
	{
		return craft()->retconHtml->wrap( $input, $selectors, $wrapper );
	}

	public function unwrap( $input, $selectors )
	{
		return craft()->retconHtml->unwrap( $input, $selectors );
	}

	public function remove( $input, $selectors )
	{
		return craft()->retconHtml->remove( $input, $selectors );
	}

	public function only( $input, $selectors )
	{
		return craft()->retconHtml->only( $input, $selectors );
	}

	public function change( $input, $selectors, $toTag )
	{
		return craft()->retconHtml->change( $input, $selectors, $toTag );
	}

}
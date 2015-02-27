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
			// Wrap selectors in other selectors!
			'retconWrap' => new Twig_Filter_Method( $this, 'wrap' ),
			// Unwrap seleectors by negative depth
			'retconUnwrap' => new Twig_Filter_Method( $this, 'unwrap' ),
			// Remove the matching selector(s)!
			'retconRemove' => new Twig_Filter_Method( $this, 'remove' ),
			// Remove *everything but* the matching selector(s)!
			'retconOnly' => new Twig_Filter_Method( $this, 'only' ),
			// Change tag type. Oh man
			'retconChangeTag' => new Twig_Filter_Method( $this, 'change' ),
		);
	}

	public function transform( $input, $transform = false )
	{
		return $transform ? craft()->retconHtml->transform( $input, $transform ) : $input;
	}

	public function lazy( $input, $class = null, $attribute = null )
	{
		return craft()->retconHtml->lazy( $input, $attribute );
	}

	public function autoAlt( $input, $overwrite = false )
	{
		return craft()->retconHtml->autoAlt( $input, $overwrite );
	}

	public function attr( $input, $selectors, $attributes = array(), $overwrite = true )
	{
		return ( is_array( $attributes ) && count( $attributes ) > 0 ) ? craft()->retconHtml->attr( $input, $selectors, $attributes, $overwrite ) : $input;
	}

	// 'img', '.imageWrapper'
	// '.something', div
	public function wrap( $input, $selectorsToWrap, $wrapper )
	{
		// Wrap <a> in <div class="foo" />
		return $input;
	}

	public function unwrap( $input, $selectors, $depth = 1 )
	{
		return $input;
	}

	/*
	* Removes all matching selectors
	*
	*/
	public function remove( $input, $selectors )
	{
		return craft()->retconHtml->remove( $input, $selectors );
	}

	public function only( $input, $selectors )
	{
		return $input;
	}

	// <p>Something</p> => <span>Something</span>
	public function change( $input, $fromTag, $toTag )
	{
		return $input;
	}

}
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
			// Add (or overwrite) attributes for selector
			'retconAttr' => new Twig_Filter_Method( $this, 'attr' ),
			// Rewrite <img> tags for lazy loading
			'retconLazy' => new Twig_Filter_Method( $this, 'lazy' ),
			// Wrap selectors in other selectors!
			'retconWrap' => new Twig_Filter_Method( $this, 'wrap' ),
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

	public function attr( $input, $selectors, $attributes = array(), $overwrite = true )
	{
		return craft()->retconHtml->attr( $input, $attributes, $selectors, $overwrite );
	}

	public function lazy( $input, $class = null, $attribute = null )
	{
		return craft()->retconHtml->lazy( $input, $attribute );
	}

	// 'img', '.imageWrapper'
	// '.something', div
	public function wrap( $input, $selectorsToWrap, $wrapper )
	{
		// Wrap <a> in <div class="foo" />
		return $input;
	}

	public function remove( $input, $selectors )
	{
		return $input;
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
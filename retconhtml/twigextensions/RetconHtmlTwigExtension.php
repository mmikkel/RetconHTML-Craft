<?php namespace Craft;

/**
 * Got WYSIWYG? Retcon offers convenient Twig filters for easy HTML rewriting.
 *
 * @author      Mats Mikkel Rummelhoff <http://mmikkel.no>
 * @package     Retcon HTML
 * @since       Craft 2.3
 * @copyright   Copyright (c) 2016, Mats Mikkel Rummelhoff
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 * @link        https://github.com/mmikkel/RetconHTML-Craft
 */

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

			// Catch-all filter
			'retcon' => new Twig_Filter_Method($this, 'retcon'),

			// Apply an image transform to all <img> tags
			'retconTransform' => new Twig_Filter_Method($this, 'transform'),

			// Rewrite <img> tags for lazy loading
			'retconLazy' => new Twig_Filter_Method($this, 'lazy'),

			// Add automatic alt tags to images
			'retconAutoAlt' => new Twig_Filter_Method($this, 'autoAlt'),

			// Add (or overwrite) attributes for selector
			'retconAttr' => new Twig_Filter_Method($this, 'attr'),

			// Wrap stuff in other stuff!
			'retconWrap' => new Twig_Filter_Method($this, 'wrap'),

			// Unwrap stuff
			'retconUnwrap' => new Twig_Filter_Method($this, 'unwrap'),

			// Remove the matching selector(s)!
			'retconRemove' => new Twig_Filter_Method($this, 'remove'),

			// Remove *everything but* the matching selector(s)!
			'retconOnly' => new Twig_Filter_Method($this, 'only'),

			// Change tag type
			'retconChange' => new Twig_Filter_Method($this, 'change'),

			// Inject stuff inside one or several containers
			'retconInject' => new Twig_Filter_Method($this, 'inject'),

			// Correct header hierarchy
			'retconHTagCorrect' => new Twig_Filter_Method($this, 'hTagCorrect'),

			// Replace with regex
			'retconReplace' => new Twig_Filter_Method($this, 'replace'),

		);
	}

	public function retcon()
	{
		$args = func_get_args();
		$html = array_shift($args);
		return craft()->retconHtml->retcon($html, $args);
	}

	public function transform($html, $transform)
	{
		return craft()->retconHtml->transform($html, $transform);
	}

	public function lazy($html, $className = null, $attributeName = null)
	{
		return craft()->retconHtml->lazy($html, $className, $attributeName);
	}

	public function autoAlt($html, $overwrite = false)
	{
		return craft()->retconHtml->autoAlt($html, $overwrite);
	}

	public function attr($html, $selectors, $attributes, $overwrite = true)
	{
		return craft()->retconHtml->attr($html, $selectors, $attributes, $overwrite);
	}

	public function wrap($html, $selectors, $wrapper)
	{
		return craft()->retconHtml->wrap($html, $selectors, $wrapper);
	}

	public function unwrap($html, $selectors)
	{
		return craft()->retconHtml->unwrap($html, $selectors);
	}

	public function remove($html, $selectors)
	{
		return craft()->retconHtml->remove($html, $selectors);
	}

	public function only($html, $selectors)
	{
		return craft()->retconHtml->only($html, $selectors);
	}

	public function change($html, $selectors, $toTag)
	{
		return craft()->retconHtml->change($html, $selectors, $toTag);
	}

	public function inject($html, $selectors, $toInject, $overwrite = false)
	{
		return craft()->retconHtml->inject($html, $selectors, $toInject, $overwrite);
	}

	public function hTagCorrect($html, $startAt = 'h1')
	{
		return craft()->retconHtml->hTagCorrect($html, $startAt);
	}

	public function replace($html, $pattern, $replace = '')
	{
		return craft()->retconHtml->replace($html, $pattern, $replace);
	}

}

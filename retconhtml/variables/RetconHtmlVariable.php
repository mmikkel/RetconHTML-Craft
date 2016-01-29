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

class RetconHtmlVariable
{

	protected $_plugin = null;

	public function getPlugin()
	{
		if ($this->_plugin === null) {
			$this->_plugin = craft()->plugins->getPlugin('retconHtml');
		}
		return $this->_plugin;
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

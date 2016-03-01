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

class RetconHtmlDocument extends \DOMDocument
{

	private $_outputEncoding,
			$_xpath = null;

	public function __construct($html = false)
	{

		parent::__construct();

		libxml_use_internal_errors(true); // Might make this a setting in the future

		$this->_outputEncoding = craft()->retconHtml_helper->getEncoding();

		if ($html) {
			$this->loadHtml($html);
		}

		$this->preserveWhiteSpace = false;

	}

	public function getElementsBySelector($selectorStr){

		$selector = craft()->retconHtml_helper->getSelectorObject($selectorStr);

		// ID or class
		if ($selector->attribute) {

			$xpath = $this->getXPath();

			$query = '//' . $selector->tag . '[contains(concat(" ",@' . $selector->attribute . '," "), " ' . $selector->attributeValue . ' ")]';

			$elements = $xpath->query($query);

		} else {

			$elements = $this->getElementsByTagName($selector->tag);

		}

		return $elements && $elements->length > 0 ? $elements : false;

	}

	public function loadHtml($html, $options = null)
	{
		parent::loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', $this->_outputEncoding));
		$this->normalize();
	}

	public function getHtml()
	{
		return TemplateHelper::getRaw(preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', parent::saveHTML())) ?: false;
	}

	private function getXPath()
	{
		if ($this->_xpath === null) {
			$this->_xpath = new \DomXPath($this);
		}
		return $this->_xpath;
	}

}
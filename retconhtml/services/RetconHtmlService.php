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

class RetconHtmlService extends BaseApplicationComponent
{

	protected 	$_allowedTransformExtensions = array('jpg', 'png', 'gif'),
				$_transforms = null;

	/*
	* retcon
	*
	* Catch-all wrapping all other methods
	*
	* @html String
	*
	* @args Mixed
	*
	*/
	public function retcon($html, $args)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		if (empty($args)) {
			throw new Exception(Craft::t("No filter method or callbacks defined"));
			return $html;
		}

		$calls = is_array($args[0]) ? $args[0] : array($args);

		foreach ($calls as $call) {

			$args = is_array($call) ? $call : array($call);

			$filter = array_shift($args);

			if (!method_exists($this, $filter)) {
				throw new Exception(Craft::t("Undefined filter method {$filter}"));
				return $html;
			}

			$html = call_user_func_array(array($this, $filter), array_merge(array($html), $args));

		}

		return $html;

	}

	/*
	* transform
	*
	* Apply an image transform to all images.
	*
	* @html String
	*
	* @transform Mixed
	* Named (String) or inline transform (Array)
	*
	*/
	public function transform($html, $transform, $transformDefaults = null, $configOverrides = null)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		// Get images from the DOM
		$doc = new RetconHtmlDocument($html);

		if (!$docImages = $doc->getElementsByTagName('img')) {
			return $html;
		}

		// Get transform
		if (is_string($transform)) {

			// Named transform
			$transformHandle = $transform;

			if (!isset($this->_transforms[$transformHandle])) {
				$transform = craft()->assetTransforms->getTransformByHandle($transformHandle);
				$this->_transforms[$transformHandle] = $transform;
			}

			$transform = $this->_transforms[$transformHandle] ?: false;

		} else if (is_array($transform)) {

			// Template transform
			$transform = craft()->assetTransforms->normalizeTransform($transform);

		} else {

			// Nah.
			return $html;

		}

		if (!$transform) {
			return $html;
		}

		// I can haz Imager?
		$imagerPlugin = craft()->plugins->getPlugin('imager');
		if ($imagerPlugin) {

			// Imager doesn't want to deal with AssetTransformModels. Wtf, André.
			$transform = $transform->getAttributes();

			foreach ($docImages as $docImage) {

				$imageUrl = $docImage->getAttribute('src');
				$imageUrlInfo = parse_url($imageUrl);

				$transformedImage = craft()->imager->transformImage($imageUrl, $transform, $transformDefaults, $configOverrides);

				if ($transformedImage) {
					$docImage->setAttribute('src', $transformedImage->url);
					if ($docImage->getAttribute('width')) {
						$docImage->setAttribute('width', $transformedImage->width);
					}
					if ($docImage->getAttribute('height')) {
						$docImage->setAttribute('height', $transformedImage->height);
					}
				}

			}

		} else {

			// Oh well, let's do our best anyway
			$transformWidth = $transform->width ?: 'AUTO';
			$transformHeight = $transform->height ?: 'AUTO';
			$transformMode = $transform->mode ?: 'crop';
			$transformPosition = $transform->position ?: 'center-center';
			$transformQuality = $transform->quality ?: craft()->config->get('defaultImageQuality');
			$transformFormat = $transform->format ?: null;

			// Set format to jpg if we dont have Imagick installed
			if ($transformFormat !== 'jpg' && !craft()->images->isImagick()) {
				$transformFormat = 'jpg';
			}

			// Create transform handle if missing
			if (!isset($transformHandle)) {

				$transformFilenameAttributes = array(
					$transformWidth . 'x' . $transformHeight,
					$transformMode,
					$transformPosition,
					$transformQuality
				);

				$transformHandle = implode('_', $transformFilenameAttributes);

			}

			// Get basepaths and URLs
			$basePath = craft()->retconHtml_helper->getSetting('baseTransformPath');
			$baseUrl = craft()->retconHtml_helper->getSetting('baseTransformUrl');

			$siteUrl = rtrim(UrlHelper::getSiteUrl(), '/');
			$host = parse_url($baseUrl, PHP_URL_HOST);

			// Transform images and rewrite sources
			foreach ($docImages as $docImage) {

				$imageUrl = $docImage->getAttribute('src');
				$imageUrlInfo = parse_url($imageUrl);
				$imagePathInfo = pathinfo($imageUrlInfo['path']);

				// Check extension
				if (!in_array(strtolower($imagePathInfo['extension']), $this->_allowedTransformExtensions)) {
					continue;
				}

				// Is image local?
				$imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

				if (!$imageIsLocal) {
					// Non-local images not supported yet
					continue;
				}

				$useAbsoluteUrl = $baseUrl !== $siteUrl || strpos($imageUrl, 'http') > -1 ? true : false;

				// Build filename/path
				$imageTransformedFilename = $imagePathInfo['filename'] . '.' . ($transformFormat ?: $imagePathInfo['extension']);
				$imageTransformedFolder = $basePath . $imagePathInfo['dirname'] . '/_' . $transformHandle;
				$imageTransformedPath = $imageTransformedFolder . '/' . $imageTransformedFilename;

				// Exit if local file doesn't even exist. Sheesh
				if (!file_exists($basePath . $imageUrlInfo['path'])) {
					continue;
				}

				// Create folder if need be
				if (!file_exists($imageTransformedFolder) || !is_dir($imageTransformedFolder)) {
					$chmod = $imageIsLocal ? fileperms($basePath . $imagePathInfo['dirname']) : 0777;
					if (!@mkdir($imageTransformedFolder, $chmod, true)) {
						continue;
					}
				}

				// Transform image
				if (!file_exists($imageTransformedPath)) {

					$docImagesource = $basePath . $imageUrlInfo['path'];

					if (!$image = @craft()->images->loadImage($docImagesource)) {
						continue;
					}

					@$image->setQuality($transformQuality);

					switch ($transformMode) {

						case 'crop' :

							@$image->scaleAndCrop($transform->width, $transform->height, true, $transform->position);

							break;

						case 'fit' :

							@$image->scaleToFit($transform->width, $transform->height, true);

							break;

						default :

							@$image->resize($transform->width, $transform->height);

					}

					if (!@$image->saveAs($imageTransformedPath)) {
						continue;
					}

				}

				// Phew!Now where's that src attribute...
				$imageTransformedUrl = str_replace($basePath, ($useAbsoluteUrl ? $baseUrl : ''), $imageTransformedPath);

				$docImage->setAttribute('src', $imageTransformedUrl);

				if ($docImage->getAttribute('width')) {
					$docImage->setAttribute('width', $transformWidth);
				}

				if ($docImage->getAttribute('height')) {
					$docImage->setAttribute('height', $transformHeight);
				}

			}

		}

		return $doc->getHtml();

	}

	/*
	* lazy
	*
	* Replaces the src attribute with a base64 encoded, transparent gif
	* The original source will be retained in a data attribute
	*
	* @className String
	* Class for lazy images (optional, default "lazy")
	*
	* @attributeName String
	* Name of data attribute for original source (optional, default "original")
	*
	*/
	public function lazy($html, $className = null, $attributeName = null)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$doc = new RetconHtmlDocument($html);

		if (!$docImages = $doc->getElementsByTagName('img')) {
			return $html;
		}

		$attributeName = 'data-' . ($attributeName ?: 'original');
		$className = $className ?: 'lazy';

		foreach ($docImages as $docImage) {
			$imageClasses = explode(' ', $docImage->getAttribute('class'));
			$imageClasses[] = $className;
			$docImage->setAttribute('class', trim(implode(' ', $imageClasses)));
			$docImage->setAttribute($attributeName, $docImage->getAttribute('src'));
			$docImage->setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
		}

		return $doc->getHtml();

	}

	/*
	* autoAlt
	*
	* Adds filename as alt attribute for images missing alternative text. Optionally overwrite alt attribute for all images
	*
	* @overwrite Boolean
	* Overwrite existing alt attributes (optional, default false)
	*
	*/
	public function autoAlt($html, $overwrite = false)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$doc = new RetconHtmlDocument($html);

		if (!$docImages = $doc->getElementsByTagName('img')) {
			return $html;
		}

		foreach ($docImages as $docImage) {

			$alt = $docImage->getAttribute('alt');

			if (!$alt || strlen($alt) === 0) {
				$imageSource = $docImage->getAttribute('src');
				$imageSourcePathinfo = pathinfo($imageSource);
				$docImage->setAttribute('alt', $imageSourcePathinfo['filename']);
			}

		}

		return $doc->getHtml();

	}

	/*
	* attr
	*
	* Adds or replaces one or many attributes for one or many selectors
	*
	* @selectors Mixed
	* String or Array of strings
	*
	* @attributes Array
	* Associative array of attribute names and values
	*
	* @overwrite Boolean
	* Overwrites existing attribute values (optional, true)
	*
	*/
	public function attr($html, $selectors, $attributes, $overwrite = true)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);

		foreach ($selectors as $selector) {

			// Get all matching selectors, and add/replace attributes
			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			foreach ($elements as $element) {

				foreach ($attributes as $key => $value) {

					// Add or remove?
					if (!$value) {

						$element->removeAttribute($key);

					} else if ($value === true) {

						$element->setAttribute($key, '');

					} else {

						if (!$overwrite && $key !== 'id') {
							$attributeValues = explode(' ', $element->getAttribute($key));
							if (!in_array($value, $attributeValues)) {
								$attributeValues[] = $value;
							}
						} else {
							$attributeValues = array($value);
						}

						$element->setAttribute($key, trim(implode(' ', $attributeValues)));
					}

				}

			}

		}

		return $doc->getHtml();

	}

	/*
	* remove
	*
	* Remove all elements matching given selector(s)
	*
	* @selectors Mixed
	* String or Array of strings
	*
	*/
	public function remove($html, $selectors)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);

		foreach ($selectors as $selector) {

			// Get all matching selectors, and remove them
			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			$numElements = $elements->length;

			for ($i = $numElements - 1; $i >= 0; --$i) {
				$element = $elements->item($i);
				$element->parentNode->removeChild($element);
			}

		}

		return $doc->getHtml();

	}

	/*
	* only
	*
	* Remove everything except elements matching given selector(s)
	*
	* @selectors Mixed
	* String or Array of strings
	*
	*/
	public function only($html, $selectors)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);
		$fragment = $doc->createDocumentFragment();

		foreach ($selectors as $selector) {

			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			$numElements = $elements->length;

			for ($i = $numElements - 1; $i >= 0; --$i) {

				$fragment->appendChild($elements->item($i));

			}

		}

		$body = $doc->getElementsByTagName('body')->item(0);
		$body->parentNode->replaceChild($fragment, $body);

		return $doc->getHtml();

	}

	/*
	* change
	*
	* Changes tag type/name for given selector(s)
	*
	* @selectors Mixed
	* String or Array of strings
	*
	* @toTag String
	* Tag type matching elements will be converted to, e.g. "span"
	*/
	public function change($html, $selectors, $toTag)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);

		foreach ($selectors as $selector) {

			// Get all matching selectors, and add/replace attributes
			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			$numElements = $elements->length;

			for ($i = $numElements - 1; $i >= 0; --$i) {

				$element = $elements->item($i);

				// Perform a deep copy of the element, changing its tag name
				$children = array();

				foreach ($element->childNodes as $child) {
					$children[] = $child;
				}

				$newElement = $element->ownerDocument->createElement($toTag);

				foreach ($children as $child) {
					$newElement->appendChild($element->ownerDocument->importNode($child, true));
				}

				foreach ($element->attributes as $attribute) {
					$newElement->setAttribute($attribute->nodeName, $attribute->nodeValue);
				}

			    $element->parentNode->replaceChild($newElement, $element);

			}

		}

		return $doc->getHtml();

	}

	/*
	* wrap
	*
	* Wraps one or many selectors
	*
	* @selectors Mixed
	* String or Array of strings
	*
	* @wrapper String
	* Element to create as wrapper, e.g. "div.wrapper"
	*
	*/
	public function wrap($html, $selectors, $wrapper)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);

		// Get wrapper
		$wrapper = craft()->retconHtml_helper->getSelectorObject($wrapper);
		$wrapper->tag = $wrapper->tag === '*' ? 'div' : $wrapper->tag;
		$wrapperNode = $doc->createElement($wrapper->tag);

		if ($wrapper->attribute) {
			$wrapperNode->setAttribute($wrapper->attribute, $wrapper->attributeValue);
		}

		foreach ($selectors as $selector) {

			// Get all matching selectors, and add/replace attributes
			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			$numElements = $elements->length;

			for ($i = $numElements - 1; $i >= 0; --$i) {

				$element = $elements->item($i);
				$wrapperClone = $wrapperNode->cloneNode(true);
				$element->parentNode->replaceChild($wrapperClone, $element);
				$wrapperClone->appendChild($element);

			}

		}

		return $doc->getHtml();

	}

	/*
	* unwrap
	*
	* Removes the parent of given selector(s), retaining all child nodes
	*
	* @selectors Mixed
	* String or Array of strings
	*
	*/
	public function unwrap($html, $selectors)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);

		foreach ($selectors as $selector) {

			// Get all matching selectors, and add/replace attributes
			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			$numElements = $elements->length;

			for ($i = $numElements - 1; $i >= 0; --$i) {

				$element = $elements->item($i);
				$parentNode = $element->parentNode;
				$fragment = $doc->createDocumentFragment();

				while ($parentNode->childNodes->length > 0) {
					$fragment->appendChild($parentNode->childNodes->item(0));
				}

				$parentNode->parentNode->replaceChild($fragment, $parentNode);

			}

		}

		return $doc->getHtml();

	}

	/*
	* inject
	*
	* Injects string value into all elements matching given selector(s)
	*
	* @selectors Mixed
	* String or Array of strings
	*
	* @toInject String
	* Content to inject
	*
	*/
	public function inject($html, $selectors, $toInject, $overwrite = false)
	{

		if (!$html || strlen($html) === 0) {
			return $html;
		}

		$selectors = is_array($selectors) ? $selectors : array($selectors);

		$doc = new RetconHtmlDocument($html);

		// What are we trying to inject, exactly?
		if (preg_match("/<[^<]+>/", $toInject, $matches) != 0) {
			// Injected content is HTML
			$fragmentDoc = new RetconHtmlDocument('<div id="injectWrapper">' . $toInject . '</div>');
			$injectNode = $fragmentDoc->getElementById('injectWrapper')->childNodes->item(0);
		} else {
			$textNode = $doc->createTextNode("{$toInject}");
		}

		foreach ($selectors as $selector) {

			// Get all matching selectors, and add/replace attributes
			if (!$elements = $doc->getElementsBySelector($selector)) {
				continue;
			}

			$numElements = $elements->length;

			for ($i = $numElements - 1; $i >= 0; --$i) {

				$element = $elements->item($i);

				if (!$overwrite) {

					if (isset($injectNode)) {
						$element->appendChild($doc->importNode($injectNode->cloneNode(true), true));
					} else {
						$element->appendChild($textNode->cloneNode());
					}

				} else {

					if (isset($injectNode)) {
						$element->nodeValue = "";
						$element->appendChild($doc->importNode($injectNode->cloneNode(true), true));
					} else {
						$element->nodeValue = $toInject;
					}

				}

			}

		}

		return $doc->getHtml();

	}

	/*
	* hTagCorrect
	*
	*
	*/
	public function hTagCorrect($html, $startAt = 'h1')
	{
		// TODO
		return $html;
	}

	/*
	*	regex
	*
	*/
	public function replace($html, $pattern, $replace = '')
	{
		if (!$html || strlen($html) === 0) {
			return $html;
		}
		return preg_replace($pattern, $replace, $html);
	}

}

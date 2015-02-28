<?php namespace Craft;

/**
 * Got WYSIWYG? Retcon offers convenient Twig filters for easy HTML rewriting.
 *
 * @author      Mats Mikkel Rummelhoff <http://mmikkel.no>
 * @package     Retcon HTML
 * @since       Craft 2.3
 * @copyright   Copyright (c) 2015, Mats Mikkel Rummelhoff
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 * @link        https://github.com/mmikkel/RetconHtml-Craft
 */

class RetconHtmlService extends BaseApplicationComponent
{

	protected 	$_allowedTransformExtensions = array( 'jpg', 'png', 'gif' ),
				$_transforms = null;

	/*
	* Apply transform to all images in an HTML block
	*
	*/
	public function transform( $input, $transform )
	{

		// Get images from the DOM
		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );
		if ( ! $dom || ! @$domImages = $dom->getElementsByTagName( 'img' ) ) {
			return $input;
		}

		// Keep track of number of sources rewritten
		$numSourcesRewritten = 0;

		// Get transform
		if ( is_string( $transform ) ) {

			// Named transform
			$transformHandle = $transform;

			if ( ! isset( $this->_transforms[ $transformHandle ] ) ) {
				$transform = craft()->assetTransforms->getTransformByHandle( $transformHandle );
				$this->_transforms[ $transformHandle ] = $transform;
			}

			$transform = $this->_transforms[ $transformHandle ] ?: false;

		} else if ( is_array( $transform ) ) {

			// Template transform
			$transform = craft()->assetTransforms->normalizeTransform( $transform );

		} else {

			// Nah.
			return $input;

		}

		// Get transform attributes
		$transformWidth = $transform->width ?: 'AUTO';
		$transformHeight = $transform->width ?: 'AUTO';
		$transformMode = $transform->mode ?: 'crop';
		$transformPosition = $transform->position ?: 'center-center';
		$transformQuality = $transform->quality ?: craft()->config->get( 'defaultImageQuality' );
		$transformFormat = $transform->format ?: null;

		// Set format to jpg if we dont have Imagick installed
		if ( $transformFormat !== 'jpg' && ! craft()->images->isImagick() ) {
			$transformFormat = 'jpg';
		}

		// Create transform handle if missing
		if ( ! isset( $transformHandle ) ) {

			$transformFilenameAttributes = array(
				$transformWidth . 'x' . $transformHeight,
				$transformMode,
				$transformPosition,
				$transformQuality
			);

			$transformHandle = implode( '_', $transformFilenameAttributes );

		}

		// Get basepaths and URLs
		$basePath = craft()->retconHtml_helper->getSetting( 'baseTransformPath' );
		$baseUrl = craft()->retconHtml_helper->getSetting( 'baseTransformUrl' );
		$host = pathinfo( $baseUrl, PHP_URL_HOST );

		// Transform images and rewrite sources
		foreach ( $domImages as $domImage ) {

			$imageUrl = $domImage->getAttribute( 'src' );
			$imageUrlInfo = parse_url( $imageUrl );
			$imagePathInfo = pathinfo( $imageUrlInfo[ 'path' ] );

			// Check extension
			if ( ! in_array( $imagePathInfo[ 'extension' ], $this->_allowedTransformExtensions ) ) {
				continue;
			}

			// Is image local?
			$imageIsLocal = ! ( isset( $imageUrlInfo[ 'host' ] ) && $imageUrlInfo[ 'host' ] !== $host );

			if ( ! $imageIsLocal ) {
				// Non-local images not supported yet
				continue;
			}

			// Build filename/path
			$imageTransformedFilename = $imagePathInfo[ 'filename' ] . '.' . ( $transformFormat ?: $imagePathInfo[ 'extension' ] );
			$imageTransformedFolder = $basePath . $imagePathInfo[ 'dirname' ] . '/_' . $transformHandle;
			$imageTransformedPath = $imageTransformedFolder . '/' . $imageTransformedFilename;

			// Exit if local file doesn't even exist. Sheesh
			if ( ! file_exists( $basePath . $imageUrlInfo[ 'path' ] ) ) {
				continue;
			}

			// Create folder if need be
			if ( ! file_exists( $imageTransformedFolder ) || ! is_dir( $imageTransformedFolder ) ) {
				$chmod = $imageIsLocal ? fileperms( $basePath . $imagePathInfo[ 'dirname' ] ) : 0777;
				if ( ! @mkdir( $imageTransformedFolder, $chmod, true ) ) {
					continue;
				}
			}

			// Transform image
			if ( ! file_exists( $imageTransformedPath ) ) {

				$domImagesource = $basePath . $imageUrlInfo[ 'path' ];
				$image = @craft()->images->loadImage( $domImagesource );

				if ( ! $image ) {
					continue;
				}

				@$image->setQuality( $transformQuality );

				switch ( $transformMode ) {

					case 'crop' :

						@$image->scaleAndCrop( $transform->width, $transform->height, true, $transform->position );

						break;

					case 'fit' :

						@$image->scaleToFit( $transform->width, $transform->height, true );

						break;

					default :

						@$image->resize( $transform->width, $transform->height );

				}

				if ( ! @$image->saveAs( $imageTransformedPath ) ) {
					continue;
				}

			}

			// Phew! Now where's that src attribute...
			$imageTransformedUrl = str_replace( $basePath, $baseUrl, $imageTransformedPath );

			$domImage->setAttribute( 'src', $imageTransformedUrl );

			if ( $domImage->getAttribute( 'width' ) ) {
				$domImage->setAttribute( 'width', $transformWidth );
			}

			if ( $domImage->getAttribute( 'height' ) ) {
				$domImage->setAttribute( 'height', $transformWidth );
			}

			$numSourcesRewritten++;

		}

		// Only bother parsing the HTML if we actually rewrote any sources
		if ( $numSourcesRewritten > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

	/*
	* Rewrites img tags for lazy loading
	*
	*/
	public function lazy( $input, $className = false, $attributeName = false )
	{

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );

		if ( ! $dom || ! @$domImages = $dom->getElementsByTagName( 'img' ) ) {
			return $input;
		}

		$attributeName = 'data-' . ( $attributeName ?: 'original' );
		$className = $className ?: 'lazy';
		$numSourcesRewritten = 0;

		foreach ( $domImages as $domImage ) {
			$imageClasses = explode( ' ', $domImage->getAttribute( 'class' ) );
			$imageClasses[] = $className;
			$domImage->setAttribute( 'class', trim( implode( ' ', $imageClasses ) ) );
			$domImage->setAttribute( $attributeName, $domImage->getAttribute( 'src' ) );
			$domImage->setAttribute( 'src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
			$numSourcesRewritten++;
		}

		if ( $numSourcesRewritten > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

	/*
	* Adds filename as alt tag to images missing the latter
	*
	*/
	public function autoAlt( $input, $overwrite = false )
	{

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );

		if ( ! $dom || ! @$domImages = $dom->getElementsByTagName( 'img' ) ) {
			return $input;
		}

		$numSourcesRewritten = 0;

		foreach ( $domImages as $domImage ) {

			$alt = $domImage->getAttribute( 'alt' );

			if ( ! $alt || strlen( $alt ) === 0 ) {
				$imageSource = $domImage->getAttribute( 'src' );
				$imageSourcePathinfo = pathinfo( $imageSource );
				$domImage->setAttribute( 'alt', $imageSourcePathinfo[ 'filename' ] );
				$numSourcesRewritten++;
			}

		}

		// Only bother parsing the HTML if we actually rewrote any sources
		if ( $numSourcesRewritten > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

	/*
	* Adds or replaces attributes
	*
	*/
	public function attr( $input, $selectors, $attributes, $overwrite = true )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );

		if ( ! $dom ) {
			return $input;
		}

		@$dom->preserveWhiteSpace = false;
		$xpath = null;

		$numElementsRewritten = 0;

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			$selector = craft()->retconHtml_helper->getSelector( $selector );

			// ID or class
			if ( $selector->attribute ) {

				if ( $xpath === null ) {
					@$xpath = new \DomXPath( $dom );
				}

				$query = '//' . $selector->tag . '[contains(concat(" ",@' . $selector->attribute . '," "), "' . $selector->attributeValue . '")]';
				$elements = @$xpath->query( $query );


			} else {

				$elements = @$dom->getElementsByTagName( $selector->tag );

			}

			if ( ! isset( $elements ) || $elements->length === 0 ) {
				continue;
			}

			foreach ( $elements as $element ) {

				foreach ( $attributes as $key => $value ) {

					// Add or remove?
					if ( ! $value ) {

						$element->removeAttribute( $key );

					} else {

						if ( ! $overwrite && $key !== 'id' ) {
							$attributeValues = explode( ' ', $element->getAttribute( $key ) );
							if ( ! in_array( $value, $attributeValues ) ) {
								$attributeValues[] = $value;
							}
						} else {
							$attributeValues = array( $value );
						}

						$element->setAttribute( $key, trim( implode( ' ', $attributeValues ) ) );
					}

					$numElementsRewritten++;

				}

			}

		}

		if ( $numElementsRewritten > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;
	}

	/*
	* Removes all matching selectors
	*
	*/
	public function remove( $input, $selectors )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );

		if ( ! $dom ) {
			return $input;
		}

		@$dom->preserveWhiteSpace = false;
		$xpath = null;

		$numElementsRemoved = 0;

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and remove them
			$selector = craft()->retconHtml_helper->getSelector( $selector );

			if ( $selector->attribute ) {

				if ( $xpath === null ) {
					@$xpath = new \DomXPath( $dom );
				}

				$query = '//' . $selector->tag . '[contains(concat(" ",@' . $selector->attribute . '," "), "' . $selector->attributeValue . '")]';

				$elements = @$xpath->query( $query );

				if ( $elements && $elements->length > 0 ) {

					// Remove nodes
					foreach ( $elements as $element ) {

						$element->parentNode->removeChild( $element );
						$numElementsRemoved++;

					}

				}

			} else {

				$elements = @$dom->getElementsByTagName( $selector->tag );

				// Remove nodes
				while ( $elements->length > 0 ) {
					$element = $elements->item( 0 );
					$element->parentNode->removeChild( $element );
					$numElementsRemoved++;
				}

			}

		}

		if ( $numElementsRemoved > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

	/*
	* Change tag types
	*
	*/
	public function change( $input, $selectors, $toTag )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );
		@$dom->normalize();

		if ( ! $dom ) {
			return $input;
		}

		@$dom->preserveWhiteSpace = false;
		$xpath = null;

		$numElementsRewritten = 0;

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			$selector = craft()->retconHtml_helper->getSelector( $selector );

			// ID or class
			if ( $selector->attribute ) {

				if ( $xpath === null ) {
					@$xpath = new \DomXPath( $dom );
				}

				$query = '//' . $selector->tag . '[contains(concat(" ",@' . $selector->attribute . '," "), "' . $selector->attributeValue . '")]';
				$elements = @$xpath->query( $query );


			} else {

				$elements = @$dom->getElementsByTagName( $selector->tag );

			}

			if ( ! isset( $elements ) || $elements->length === 0 ) {
				continue;
			}

			foreach ( $elements as $element ) {

				// Perform a deep copy of the element, changing its tag name
				$children = array();

				foreach ( $element->childNodes as $child ) {
					$children[] = $child;
				}

				$newElement = $element->ownerDocument->createElement( $toTag );

				foreach ( $children as $child ) {
					$newElement->appendChild( $element->ownerDocument->importNode( $child, true ) );
				}

				foreach ( $element->attributes as $attribute ) {
					$newElement->setAttribute( $attribute->nodeName, $attribute->nodeValue );
				}

			    $element->parentNode->replaceChild( $newElement, $element );

    			$numElementsRewritten++;

			}

		}

		if ( $numElementsRewritten > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

	/*
	* Wrap stuff in other stuff
	*
	*/
	public function wrap( $input, $selectors, $wrapper )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', craft()->retconHtml_helper->getEncoding() ) );
		@$dom->normalize();

		if ( ! $dom ) {
			return $input;
		}

		@$dom->preserveWhiteSpace = false;
		$xpath = null;

		$numElementsRewritten = 0;

		// Get wrapper
		$wrapper = craft()->retconHtml_helper->getSelector( $wrapper );
		$wrapperNode = @$dom->createElement( $wrapper->tag );
		if ( $wrapper->attribute ) {
			$wrapperNode->setAttribute( $wrapper->attribute, $wrapper->attributeValue );
		}

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			$selector = craft()->retconHtml_helper->getSelector( $selector );

			// ID or class
			if ( $selector->attribute ) {

				if ( $xpath === null ) {
					@$xpath = new \DomXPath( $dom );
				}

				$query = '//' . $selector->tag . '[contains(concat(" ",@' . $selector->attribute . '," "), "' . $selector->attributeValue . '")]';
				$elements = @$xpath->query( $query );


			} else {

				$elements = @$dom->getElementsByTagName( $selector->tag );

			}

			if ( ! isset( $elements ) || $elements->length === 0 ) {
				continue;
			}

			foreach ( $elements as $element ) {

				$wrapperClone = $wrapperNode->cloneNode();
				$element->parentNode->replaceChild( $wrapperClone, $element );
				$wrapperClone->appendChild( $element );

				$numElementsRewritten++;

			}

		}

		if ( $numElementsRewritten > 0 ) {
			return craft()->retconHtml_helper->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

}
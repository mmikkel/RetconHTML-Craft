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
	public function transform( $html, $transform )
	{

		// Get images from the DOM
		$doc = new RetconHtmlDocument( $html );
		
		if ( ! $docImages = $doc->getElementsByTagName( 'img' ) ) {
			return $html;
		}

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
			return $html;

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
		foreach ( $docImages as $docImage ) {

			$imageUrl = $docImage->getAttribute( 'src' );
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

				$docImagesource = $basePath . $imageUrlInfo[ 'path' ];
				
				if ( ! $image = @craft()->images->loadImage( $docImagesource ) ) {
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

			$docImage->setAttribute( 'src', $imageTransformedUrl );

			if ( $docImage->getAttribute( 'width' ) ) {
				$docImage->setAttribute( 'width', $transformWidth );
			}

			if ( $docImage->getAttribute( 'height' ) ) {
				$docImage->setAttribute( 'height', $transformWidth );
			}

		}

		return $doc->getHtml();

	}

	/*
	* Rewrites img tags for lazy loading
	*
	*/
	public function lazy( $html, $className = false, $attributeName = false )
	{

		$doc = new RetconHtmlDocument( $html );

		if ( ! $docImages = $doc->getElementsByTagName( 'img' ) ) {
			return $html;
		}

		$attributeName = 'data-' . ( $attributeName ?: 'original' );
		$className = $className ?: 'lazy';

		foreach ( $docImages as $docImage ) {
			$imageClasses = explode( ' ', $docImage->getAttribute( 'class' ) );
			$imageClasses[] = $className;
			$docImage->setAttribute( 'class', trim( implode( ' ', $imageClasses ) ) );
			$docImage->setAttribute( $attributeName, $docImage->getAttribute( 'src' ) );
			$docImage->setAttribute( 'src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		}

		return $doc->getHtml();

	}

	/*
	* Adds filename as alt tag to images missing the latter
	*
	*/
	public function autoAlt( $html, $overwrite = false )
	{

		$doc = new RetconHtmlDocument( $html );

		if ( ! $docImages = $doc->getElementsByTagName( 'img' ) ) {
			return $html;
		}

		$numSourcesRewritten = 0;

		foreach ( $docImages as $docImage ) {

			$alt = $docImage->getAttribute( 'alt' );

			if ( ! $alt || strlen( $alt ) === 0 ) {
				$imageSource = $docImage->getAttribute( 'src' );
				$imageSourcePathinfo = pathinfo( $imageSource );
				$docImage->setAttribute( 'alt', $imageSourcePathinfo[ 'filename' ] );
				$numSourcesRewritten++;
			}

		}

		return $doc->getHtml();

	}

	/*
	* Adds or replaces attributes
	*
	*/
	public function attr( $html, $selectors, $attributes, $overwrite = true )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		$doc = new RetconHtmlDocument( $html );

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			if ( ! $elements = $doc->getElementsBySelector( $selector ) ) {
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

				}

			}

		}

		return $doc->getHtml();

	}

	/*
	* Removes all matching selectors
	*
	*/
	public function remove( $html, $selectors )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		$doc = new RetconHtmlDocument( $html );
		
		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and remove them
			if ( ! $elements = $doc->getElementsBySelector( $selector ) ) {
				continue;
			}

			foreach ( $elements as $element ) {
				$element->parentNode->removeChild( $element );
			}

		}

		return $doc->getHtml();

	}

	/*
	* Extracts all matching selectors
	*
	*/
	public function only( $html, $selectors )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		$doc = new RetconHtmlDocument( $html );
		$fragment = $doc->createDocumentFragment();
		
		foreach ( $selectors as $selector ) {

			if ( ! $elements = $doc->getElementsBySelector( $selector ) ) {
				continue;
			}

			foreach ( $elements as $element ) {

				$fragment->appendChild( $element );

			}

		}

		$body = $doc->getElementsByTagName( 'body')->item( 0 );
		$body->parentNode->replaceChild( $fragment, $body );

		return $doc->getHtml();

	}

	/*
	* Change tag types
	*
	*/
	public function change( $html, $selectors, $toTag )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		$doc = new RetconHtmlDocument( $html );
		
		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			if ( ! $elements = $doc->getElementsBySelector( $selector ) ) {
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

			}

		}

		return $doc->getHtml();

	}

	/*
	* Wrap stuff in other stuff
	*
	*/
	public function wrap( $html, $selectors, $wrapper )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		$doc = new RetconHtmlDocument();
		
		// Get wrapper
		$wrapper = craft()->retconHtml_helper->getSelectorObject( $wrapper );
		$wrapperNode = $doc->createElement( $wrapper->tag );
		
		if ( $wrapper->attribute ) {
			$wrapperNode->setAttribute( $wrapper->attribute, $wrapper->attributeValue );
		}

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			if ( ! $elements = $doc->getElementsBySelector( $selector ) ) {
				continue;
			}

			foreach ( $elements as $element ) {

				$wrapperClone = $wrapperNode->cloneNode();
				$element->parentNode->replaceChild( $wrapperClone, $element );
				$wrapperClone->appendChild( $element );

			}

		}

		return $doc->getHtml();

	}

	/*
	* Remove parent nodes, optional depth
	*
	*/
	public function unwrap( $html, $selectors )
	{

		if ( ! is_array( $selectors ) ) {
			$selectors = array( $selectors );
		}

		$doc = new RetconHtmlDocument( $html );
		
		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			if ( ! $elements = $doc->getElementsBySelector( $selector ) ) {
				continue;
			}

			foreach ( $elements as $element ) {

				$parentNode = $element->parentNode;

				$fragment = $doc->createDocumentFragment();

				while ( $parentNode->childNodes->length > 0 ) {
					$fragment->appendChild( $parentNode->childNodes->item( 0 ) );
				}

				$parentNode->parentNode->replaceChild( $fragment, $parentNode );

			}

		}

		return $doc->getHtml();

	}

}
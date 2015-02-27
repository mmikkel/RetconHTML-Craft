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
				$_encoding = 'UTF-8',
				$_transforms = null,
				$_environmentVariables = null,
				$_settings = null;

	public function getSetting( $setting )
	{
		if ( $this->_settings === null ) {
			$plugin = craft()->plugins->getPlugin( 'retconHtml' );
			$this->_settings = $plugin->getSettings();
		}
		return $this->_settings[ $setting ] ?: false;
	}

	/*
	* Apply transform to all images in an HTML block
	*
	*/
	public function transform( $input, $transform )
	{

		// Get images from the DOM
		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', $this->_encoding ) );
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
		$basePath = rtrim( $_SERVER[ 'DOCUMENT_ROOT' ], '/' ); // Todo: Get from plugin settings!!
		$siteUrl = rtrim( CRAFT_SITE_URL, '/' );
		$host = pathinfo( $siteUrl, PHP_URL_HOST );

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
			$imageTransformedUrl = str_replace( $basePath, $siteUrl, $imageTransformedPath );

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
			return $this->getHtml( @$dom->saveHTML() ) ?: $input;
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
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', $this->_encoding ) );

		if ( ! $dom || ! @$domImages = $dom->getElementsByTagName( 'img' ) ) {
			return $input;
		}

		$attributeName = 'data-' . ( $attributeName ?: 'original' );
		$className = $className ?: 'lazy';

		foreach ( $domImages as $domImage ) {
			$imageClasses = explode( ' ', $domImage->getAttribute( 'class' ) );
			$imageClasses[] = $className;
			$domImage->setAttribute( 'class', trim( implode( ' ', $imageClasses ) ) );
			$domImage->setAttribute( $attributeName, $domImage->getAttribute( 'src' ) );
			$domImage->setAttribute( 'src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		}

		return $this->getHtml( @$dom->saveHTML() ) ?: $input;

	}

	/*
	* Adds filename as alt tag to images missing the latter
	*
	*/
	public function autoAlt( $input, $overwrite = false )
	{

		@$dom = new \DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', $this->_encoding ) );

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
			return $this->getHtml( @$dom->saveHTML() ) ?: $input;
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
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', $this->_encoding ) );

		if ( ! $dom ) {
			return $input;
		}

		@$dom->preserveWhiteSpace = false;
		$xpath = null;

		$numElementsRewritten = 0;

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and add/replace attributes
			$selector = $this->getSelector( $selector );

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
			return $this->getHtml( @$dom->saveHTML() ) ?: $input;
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
		@$dom->loadHTML( mb_convert_encoding( $input, 'HTML-ENTITIES', $this->_encoding ) );

		if ( ! $dom ) {
			return $input;
		}

		@$dom->preserveWhiteSpace = false;
		$xpath = null;

		$numElementsRemoved = 0;

		foreach ( $selectors as $selector ) {

			// Get all matching selectors, and remove them
			$selector = $this->getSelector( $selector );

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
			return $this->getHtml( @$dom->saveHTML() ) ?: $input;
		}

		return $input;

	}

	/*
	* Changes tag types
	*
	*/
	public function change( $input, $selectors, $toTag )
	{

		// TODO
		return $input;

	}

	protected function getSelector( $selector )
	{

		$delimiters = array( 'id' => '#', 'class' => '.' );

		$selectorString = preg_replace( '/\s+/', '', $selector );

		$selector = array(
			'tag' => $selector,
			'attribute' => false,
			'attributeValue' => false,
		);

		// Check for class or ID
		foreach ( $delimiters as $attribute => $indicator ) {

			if ( strpos( $selectorString, $indicator ) > -1 ) {

				$temp = explode( $indicator, $selectorString );

				$selector[ 'tag' ] = $temp[ 0 ] !== '' ? $temp[ 0 ] : '*';

				if ( ( $attributeValue = $temp[ count( $temp ) - 1 ] ) !== '' ) {
					$selector[ 'attribute' ] = $attribute;
					$selector[ 'attributeValue' ] = $attributeValue;
				}

				break;

			}

		}

		return (object) $selector;

	}

	protected function isTagOrClassOrId( $selector )
	{

		$firstChar = $selector[ 0 ];

		switch ( $firstChar ) {

			case '#' :
				return 'id';

			case '.' :
				return 'class';

			default :
				return 'tag';

		}

	}

	protected function getHtml( $html )
	{
		return TemplateHelper::getRaw( preg_replace( '~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', $html ) ) ?: false;
	}

}
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
				$_transforms = null,
				$_environmentVariables = null;

	protected function prepareHtml( $html ) {
		return TemplateHelper::getRaw( preg_replace( '~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', $html ) ) ?: false;
	}

	/*
	*
	*
	*/
	public function attr( $input, $tags, $attributes )
	{
		return $input;
	}

	/*
	* Apply transform to all images in an HTML block
	*
	*/
	public function transform( $input, $transform )
	{

		// Get images from the DOM
		@$dom = new \DOMDocument();
		@$dom->loadHTML( $input );
		if ( ! @$domImages = $dom->getElementsByTagName( 'img' ) ) {
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

			// Build filename/path
			$imageTransformedFilename = $imagePathInfo[ 'filename' ] . '.' . ( $transformFormat ?: $imagePathInfo[ 'extension' ] );
			$imageTransformedFolder = $basePath . $imagePathInfo[ 'dirname' ] . '/_' . $transformHandle;
			$imageTransformedPath = $imageTransformedFolder . '/' . $imageTransformedFilename;

			// Exit if local file doesn't even exist. Sheesh
			if ( $imageIsLocal && ! file_exists( $basePath . $imageUrlInfo[ 'path' ] ) ) {
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

				if ( ! $imageIsLocal ) {
					// TODO: Store external images in storage
					continue;
				}
				
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

			// $image->setAttribute( 'width', $asset->getWidth( $transform ) ); // TODO: Only set if already set I guess
			// $image->setAttribute( 'height', $asset->getHeight( $transform ) );

			$numSourcesRewritten++;
			
		}

		// Only bother parsing the HTML if we actually rewrote any sources
		if ( $numSourcesRewritten > 0 ) {
			return $this->prepareHtml( @$dom->saveHTML() ) ?: $input;
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
		@$dom->loadHTML( $input );

		if ( ! @$domImages = $dom->getElementsByTagName( 'img' ) ) {
			return $input;
		}

		$attributeName = 'data-' . ( $attributeName ?: 'original' );
		$className = $className ?: 'lazy';

		foreach ( $domImages as $domImage ) {
			$imageClasses = explode( ' ', $domImage->getAttribute( 'class' ) );
			$imageClasses[] = $className;
			$domImage->setAttribute( 'class', trim( implode( ' ', $imageClasses ) ) );
			$domImage->setAttribute( $attributeName, $domImage->getAttribute( 'src' ) );
			$domImage->setAttribute( 'src', 'image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAIC‌​RAEAOw==' );
		}

		return $this->prepareHtml( @$dom->saveHTML() ) ?: $input;

	}

}
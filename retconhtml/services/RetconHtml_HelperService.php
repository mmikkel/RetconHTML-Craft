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

class RetconHtml_HelperService extends BaseApplicationComponent
{

    /**
     * @var null
     */
    protected $_environmentVariables = null;
    /**
     * @var null
     */
    protected $_settings = null;
    /**
     * @var array
     */
    protected $_allowedTransformExtensions = array('jpg', 'png', 'gif');
    /**
     * @var null
     */
    protected $_transforms = null;

    /**
     * @param $setting
     * @return bool
     */
    public function getSetting($setting)
    {

        // Get settings
        if ($this->_settings === null) {

            $plugin = craft()->plugins->getPlugin('retconHtml');
            $pluginSettings = $plugin->getSettings();
            $settings = array();

            $settings['baseTransformPath'] = trim(rtrim($pluginSettings->baseTransformPath, '/') ?: rtrim($_SERVER['DOCUMENT_ROOT'], '/'));
            $settings['baseTransformUrl'] = trim(rtrim($pluginSettings->baseTransformUrl, '/') ?: rtrim(UrlHelper::getSiteUrl(), '/'));
            $settings['encoding'] = trim($pluginSettings->encoding) ?: 'UTF-8';

            if (strpos($settings['baseTransformPath'], '{') > -1 || strpos($settings['baseTransformUrl'], '{') > -1) {

                // Get environment variables
                if ($this->_environmentVariables === null) {
                    $this->_environmentVariables = craft()->config->get('environmentVariables');
                }

                // Replace environment variables
                if (is_array($this->_environmentVariables) && !empty($this->_environmentVariables)) {
                    foreach ($this->_environmentVariables as $key => $value) {
                        $settings['baseTransformPath'] = preg_replace('#/+#', '/', str_replace('{' . $key . '}', $value, $settings['baseTransformPath']));
                        $settings['baseTransformUrl'] = preg_replace('#/+#', '/', str_replace('{' . $key . '}', $value, $settings['baseTransformUrl']));
                        $settings['baseTransformUrl'] = str_replace(':/', '://', $settings['baseTransformUrl']);
                    }
                }

            }

            $settings['useImager'] = $pluginSettings->useImager === '1' || $pluginSettings->useImager === true || !isset($pluginSettings->useImager);

            $this->_settings = $settings;

        }

        return $this->_settings[$setting] ?: false;

    }

    /**
     * @param $selector
     * @return object
     */
    public function getSelectorObject($selector)
    {

        $delimiters = array('id' => '#', 'class' => '.');

        $selectorStr = preg_replace('/\s+/', '', $selector);

        $selector = array(
            'tag' => $selector,
            'attribute' => false,
            'attributeValue' => false,
        );

        // Check for class or ID
        foreach ($delimiters as $attribute => $indicator) {

            if (strpos($selectorStr, $indicator) > -1) {

                $temp = explode($indicator, $selectorStr);

                $selector['tag'] = $temp[0] !== '' ? $temp[0] : '*';

                if (($attributeValue = $temp[count($temp) - 1]) !== '') {
                    $selector['attribute'] = $attribute;
                    $selector['attributeValue'] = $attributeValue;
                }

                break;

            }

        }

        return (object)$selector;

    }

    /**
     * @return bool
     */
    public function getEncoding()
    {
        $encoding = $this->getSetting('encoding');
        return $encoding ? trim($encoding) : false;
    }

    /**
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getBase64Pixel($width = 1, $height = 1)
    {
        return "data:image/svg+xml;charset=utf-8," . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $width $height'/>");
    }

    /**
     * @param $transform
     * @return bool
     */
    public function getImageTransform($transform)
    {
        if (is_string($transform)) {

            // Named transform
            if (!isset($this->_transforms[$transform])) {
                $this->_transforms[$transform] = craft()->assetTransforms->getTransformByHandle($transform);
            }

            return $this->_transforms[$transform];

        } else if (is_array($transform)) {

            // Template transform
            return craft()->assetTransforms->normalizeTransform($transform);

        }
        return false;
    }

    /**
     * @param $imageUrl
     * @param AssetTransformModel $transform
     * @param null $transformDefaults
     * @param null $configOverrides
     * @return bool|object
     */
    public function getTransformedImage($imageUrl, AssetTransformModel $transform, $transformDefaults = null, $configOverrides = null)
    {

        $useImager = craft()->retconHtml_helper->getSetting('useImager') !== false;
        $imagerPlugin = $this->getImagerPlugin();

        if ($useImager && $imagerPlugin) {
            return craft()->imager->transformImage($imageUrl, $transform->getAttributes(), $transformDefaults, $configOverrides);
        }

        // TODO Check $imageUrl for reference tags; if found try to get Assets and transform image natively
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
        $transformHandle = isset($transform->handle) && $transform->handle ? $transform->handle : null;
        if (!$transformHandle) {
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

        $imageUrlInfo = parse_url($imageUrl);
        $imagePathInfo = pathinfo($imageUrlInfo['path']);

        // Check extension
        if (!in_array(strtolower($imagePathInfo['extension']), $this->_allowedTransformExtensions)) {
            return false;
        }

        // Is image local?
        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

        if (!$imageIsLocal) {
            // Non-local images not supported yet
            return false;
        }

        $useAbsoluteUrl = $baseUrl !== $siteUrl || strpos($imageUrl, 'http') > -1 ? true : false;

        // Build filename/path
        $imageTransformedFilename = $this->fixSlashes($imagePathInfo['filename'] . '.' . ($transformFormat ?: $imagePathInfo['extension']));
        $imageTransformedFolder = $this->fixSlashes($basePath . $imagePathInfo['dirname'] . '/_' . $transformHandle);
        $imageTransformedPath = $this->fixSlashes($imageTransformedFolder . '/' . $imageTransformedFilename);

        // Exit if local file doesn't exist
        if (!file_exists($basePath . $imageUrlInfo['path'])) {
            return false;
        }

        // We can haz folder?
        IOHelper::ensureFolderExists($imageTransformedFolder);

        // Transform image
        if (!file_exists($imageTransformedPath)) {
            $docImagePath = $this->fixSlashes($basePath . $imageUrlInfo['path']);
            if (!$image = @craft()->images->loadImage($docImagePath)) {
                return false;
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
                return false;
            }
        }

        // Phew! Now where's that src attribute...
        $imageTransformedUrl = $this->fixSlashes(str_replace($basePath, ($useAbsoluteUrl ? $baseUrl : ''), $imageTransformedPath));

        return (object)array(
            'url' => $imageTransformedUrl,
            'width' => $transformWidth,
            'height' => $transformHeight,
        );

    }

    /**
     * @param $images
     * @param string $descriptor
     * @return mixed
     */
    public function getSrcsetAttribute($images, $descriptor = 'w')
    {
        $sizes = [];
        foreach ($images as $image) {
            $sizes[] = $image->url . ' ' . $image->width . $descriptor;
        }
        return implode(', ', $sizes);
    }

    /**
     * @param $domImageNode
     * @return array|bool
     */
    public function getImageDimensions($domImageNode)
    {

        $width = $domImageNode->getAttribute('width') ?: null;
        $height = $domImageNode->getAttribute('height') ?: null;

        if ($width && $height) {
            return array(
                'width' => $width,
                'height' => $height,
            );
        }

        $imageUrl = $domImageNode->getAttribute('src');

        if (!$imageUrl) {
            return false;
        }

        $basePath = craft()->retconHtml_helper->getSetting('baseTransformPath');
        $baseUrl = craft()->retconHtml_helper->getSetting('baseTransformUrl');
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $imageUrlInfo = parse_url($imageUrl);
        $imagePathInfo = pathinfo($imageUrlInfo['path']);
        $imagePath = $this->fixSlashes($basePath . $imageUrlInfo['path']);
        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

        if (!$imageIsLocal || !file_exists($imagePath)) {
            return false;
        }

        try {
            list($width, $height) = getimagesize($imagePath);
            return array(
                'width' => $width,
                'height' => $height,
            );
        } catch (\Exception $e) {
        }

        return false;

    }

    /**
     * @param $str
     * @param bool $removeInitial
     * @param bool $removeTrailing
     * @return mixed
     */
    public function fixSlashes($str)
    {
        return preg_replace('~(^|[^:])//+~', '\\1/', $str);
    }

    /**
     * @return mixed
     */
    public function getImagerPlugin()
    {
        return craft()->plugins->getPlugin('imager');
    }

}

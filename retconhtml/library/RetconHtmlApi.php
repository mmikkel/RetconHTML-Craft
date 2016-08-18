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

class RetconHtmlApi
{

    /**
     * @return mixed
     */
    public static function retcon()
    {
        $args = func_get_args();
        $html = array_shift($args);
        return craft()->retconHtml->retcon($html, $args);
    }

    /**
     * @param $html
     * @param $transform
     * @param null $transformDefaults
     * @param null $configOverrides
     * @return mixed
     */
    public static function transform($html, $transform, $transformDefaults = null, $configOverrides = null)
    {
        return craft()->retconHtml->transform($html, $transform);
    }

    /**
     * @param $html
     * @param $transforms
     * @param null $sizes
     * @param bool $base64src
     * @param null $transformDefaults
     * @param null $configOverrides
     * @return mixed
     */
    public static function srcset($html, $transforms, $sizes = null, $base64src = true, $transformDefaults = null, $configOverrides = null)
    {
        return craft()->retconHtml->srcset($html, $transforms, $sizes, $base64src, $transformDefaults, $configOverrides);
    }

    /**
     * @param $html
     * @param null $className
     * @param null $attributeName
     * @return mixed
     */
    public static function lazy($html, $className = null, $attributeName = null)
    {
        return craft()->retconHtml->lazy($html, $className, $attributeName);
    }

    /**
     * @param $html
     * @param bool $overwrite
     * @return mixed
     */
    public static function autoAlt($html, $overwrite = false)
    {
        return craft()->retconHtml->autoAlt($html, $overwrite);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $attributes
     * @param bool $overwrite
     * @return mixed
     */
    public static function attr($html, $selectors, $attributes, $overwrite = true)
    {
        return craft()->retconHtml->attr($html, $selectors, $attributes, $overwrite);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $attributes
     * @return mixed
     */
    public static function renameAttr($html, $selectors, $attributes)
    {
        return craft()->retconHtml->renameAttr($html, $selectors, $attributes);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $wrapper
     * @return mixed
     */
    public static function wrap($html, $selectors, $wrapper)
    {
        return craft()->retconHtml->wrap($html, $selectors, $wrapper);
    }

    /**
     * @param $html
     * @param $selectors
     * @return mixed
     */
    public static function unwrap($html, $selectors)
    {
        return craft()->retconHtml->unwrap($html, $selectors);
    }

    /**
     * @param $html
     * @param $selectors
     * @return mixed
     */
    public static function remove($html, $selectors)
    {
        return craft()->retconHtml->remove($html, $selectors);
    }

    /**
     * @param $html
     * @param $selectors
     * @return mixed
     */
    public static function only($html, $selectors)
    {
        return craft()->retconHtml->only($html, $selectors);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $toTag
     * @return mixed
     */
    public static function change($html, $selectors, $toTag)
    {
        return craft()->retconHtml->change($html, $selectors, $toTag);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $toInject
     * @param bool $overwrite
     * @return mixed
     */
    public static function inject($html, $selectors, $toInject, $overwrite = false)
    {
        return craft()->retconHtml->inject($html, $selectors, $toInject, $overwrite);
    }

    /**
     * @param $html
     * @param $pattern
     * @param string $replace
     * @return mixed
     */
    public static function replace($html, $pattern, $replace = '')
    {
        return craft()->retconHtml->replace($html, $pattern, $replace);
    }

}

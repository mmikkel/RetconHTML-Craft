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
use Twig_SimpleFilter;

class RetconHtmlTwigExtension extends \Twig_Extension
{

    /**
     * @return string
     */
    public function getName()
    {
        return 'Retcon';
    }

    /**
     * @return mixed
     */
    public function getFilters()
    {
        return array_map(function ($method) {
            return new Twig_SimpleFilter('retcon' . ($method != 'retcon' ? ucfirst($method) : ''), array('Craft\RetconHtmlApi', $method));
        }, get_class_methods('Craft\RetconHtmlApi'));
    }

}

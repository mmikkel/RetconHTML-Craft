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

class RetconHtmlVariable extends RetconHtmlApi
{

    protected $_plugin = null;

    /**
     * @return null
     */
    public function getPlugin()
    {
        if ($this->_plugin === null) {
            $this->_plugin = craft()->plugins->getPlugin('retconHtml');
        }
        return $this->_plugin;
    }

    /**
     * @return mixed
     */
    public function settings()
    {
        return $this->getPlugin()->getSettings();
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getSetting($key)
    {
        return craft()->retconHtml_helper->getSetting($key);
    }

    /**
     * @return mixed
     */
    public function getImagerPlugin()
    {
        return craft()->retconHtml_helper->getImagerPlugin();
    }

}

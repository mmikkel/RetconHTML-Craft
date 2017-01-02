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

class RetconHtmlPlugin extends BasePlugin
{

    /**
     * @var string
     */
    protected $_version = '1.3.3';
    /**
     * @var string
     */
    protected $_schemaVersion = '1.0';
    /**
     * @var string
     */
    protected $_developer = 'Mats Mikkel Rummelhoff';
    /**
     * @var string
     */
    protected $_developerUrl = 'http://mmikkel.no';
    /**
     * @var string
     */
    protected $_pluginUrl = 'https://github.com/mmikkel/RetconHTML-Craft';
    /**
     * @var string
     */
    protected $_releaseFeedUrl = 'https://raw.githubusercontent.com/mmikkel/RetconHTML-Craft/master/releases.json';
    /**
     * @var string
     */
    protected $_documentationUrl = 'https://github.com/mmikkel/RetconHTML-Craft/blob/master/README.md';
    /**
     * @var string
     */
    protected $_description = 'Adds Twig filters for easy HTML rewriting.';

    /**
     * @return string
     */
    public function getName()
    {
        return 'Retcon';
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * @return string
     */
    public function getSchemaVersion()
    {
        return $this->_schemaVersion;
    }

    /**
     * @return string
     */
    public function getDeveloper()
    {
        return $this->_developer;
    }

    /**
     * @return string
     */
    public function getDeveloperUrl()
    {
        return $this->_developerUrl;
    }

    /**
     * @return string
     */
    public function getPluginUrl()
    {
        return $this->_pluginUrl;
    }

    /**
     * @return string
     */
    public function getReleaseFeedUrl()
    {
        return $this->_releaseFeedUrl;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @return string
     */
    public function getDocumentationUrl()
    {
        return $this->_documentationUrl;
    }

    /**
     * @return bool
     */
    public function hasCpSection()
    {
        return false;
    }

    /**
     * @return array
     */
    public function defineSettings()
    {
        return array(
            'baseTransformPath' => AttributeType::String,
            'baseTransformUrl' => AttributeType::String,
            'encoding' => AttributeType::String,
            'useImager' => array(AttributeType::Bool, 'default' => true),
        );
    }

    /**
     * @return mixed
     */
    public function getSettingsHtml()
    {
        return craft()->templates->render('retconHtml/settings', array(
            'settings' => $this->getSettings()
        ));
    }

    /**
     * @return RetconHtmlTwigExtension
     */
    public function addTwigExtension()
    {
        Craft::import('plugins.retconhtml.twigextensions.RetconHtmlTwigExtension');
        return new RetconHtmlTwigExtension();
    }

    /**
     *
     */
    public function init()
    {
        parent::init();
        Craft::import('plugins.retconhtml.library.*');
    }

}

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

class RetconHtmlPlugin extends BasePlugin
{

	protected   $_version = '0.1',
				$_developer = 'Mats Mikkel Rummelhoff',
				$_developerUrl = 'http://mmikkel.no',
				$_pluginUrl = 'https://github.com/mmikkel/RetconHTML-Craft';

	public function getName()
	{
		 return Craft::t( 'Retcon HTML' );
	}

	public function getVersion()
	{
		return $this->_version;
	}

	public function getDeveloper()
	{
		return $this->_developer;
	}

	public function getDeveloperUrl()
	{
		return $this->_developerUrl;
	}

	public function getPluginUrl()
	{
		return $this->_pluginUrl;
	}

	public function hasCpSection()
	{
		return false;
	}

	protected function defineSettings()
    {
        return array(
            'baseTransformPath' => AttributeType::String,
            'baseTransformUrl' => AttributeType::String,
            'encoding' => AttributeType::String,
        );
    }

    public function getSettingsHtml()
    {
		return craft()->templates->render( 'retconHtml/settings', array(
            'settings' => $this->getSettings()
        ) );
    }

	public function addTwigExtension()
	{
		Craft::import( 'plugins.retconhtml.twigextensions.RetconHtmlTwigExtension' );
		return new RetconHtmlTwigExtension();
	}

	public function init ()
    {
		parent::init();
		Craft::import( 'plugins.retconHtml.library.*' );
	}

}

<?php

namespace BookStack\Util;

use BookStack\App\AppVersion;
use HTMLPurifier;
use HTMLPurifier_Config;
use HTMLPurifier_DefinitionCache_Serializer;
use HTMLPurifier_HTML5Config;
use HTMLPurifier_HTMLDefinition;

/**
 * Provides a configured HTML Purifier instance.
 * https://github.com/ezyang/htmlpurifier
 * Also uses this to extend support to HTML5 elements:
 * https://github.com/xemlock/htmlpurifier-html5
 */
class ConfiguredHtmlPurifier
{
    protected HTMLPurifier $purifier;
    protected static bool $cachedChecked = false;

    public function __construct()
    {
        $config = HTMLPurifier_HTML5Config::createDefault();
        $this->setConfig($config);
        $this->resetCacheIfNeeded($config);

        $htmlDef = $config->getDefinition('HTML', true, true);
        if ($htmlDef instanceof HTMLPurifier_HTMLDefinition) {
            $this->configureDefinition($htmlDef);
        }

        $this->purifier = new HTMLPurifier($config);
    }

    protected function resetCacheIfNeeded(HTMLPurifier_Config $config): void
    {
        if (self::$cachedChecked) {
            return;
        }

        $cachedForVersion = cache('htmlpurifier::cache-version');
        $appVersion = AppVersion::get();
        if ($cachedForVersion !== $appVersion) {
            foreach (['HTML', 'CSS', 'URI'] as $name) {
                $cache = new HTMLPurifier_DefinitionCache_Serializer($name);
                $cache->flush($config);
            }
            cache()->set('htmlpurifier::cache-version', $appVersion);
        }

        self::$cachedChecked = true;
    }

    protected function setConfig(HTMLPurifier_Config $config): void
    {
        $config->set('Cache.SerializerPath', storage_path('framework/purifier'));
        $config->set('Core.AllowHostnameUnderscore', true);
        $config->set('CSS.AllowTricky', true);
        $config->set('HTML.SafeIframe', true);
        $config->set('Attr.EnableID', true);
        $config->set('Attr.ID.HTML5', true);
        $config->set('Output.FixInnerHTML', false);
        $config->set('URI.SafeIframeRegexp', '%^(http://|https://|//)%');
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'ftp' => true,
            'nntp' => true,
            'news' => true,
            'tel' => true,
            'file' => true,
        ]);

         // $config->set('Cache.DefinitionImpl', null); // Disable cache during testing
    }

    public function configureDefinition(HTMLPurifier_HTMLDefinition $definition): void
    {
        // Allow the object element
        $definition->addElement(
            'object',
            'Inline',
            'Flow',
            'Common',
            [
                'data'   => 'URI',
                'type'   => 'Text',
                'width'  => 'Length',
                'height' => 'Length',
            ]
        );

        // Allow the embed element
        $definition->addElement(
            'embed',
            'Inline',
            'Empty',
            'Common',
            [
                'src'   => 'URI',
                'type'   => 'Text',
                'width'  => 'Length',
                'height' => 'Length',
            ]
        );

        // Allow checkbox inputs
        $definition->addElement(
            'input',
            'Formctrl',
            'Empty',
            'Common',
            [
                'checked' => 'Bool#checked',
                'disabled' => 'Bool#disabled',
                'name' => 'Text',
                'readonly' => 'Bool#readonly',
                'type' => 'Enum#checkbox',
                'value' => 'Text',
            ]
        );
    }

    public function purify(string $html): string
    {
        return $this->purifier->purify($html);
    }
}

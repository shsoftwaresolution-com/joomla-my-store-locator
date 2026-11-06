<?php

/**
 * @package     mod_mystorelocator
 * @copyright   (C) 2026 Internet am Dornbusch, Sajjad Hanifa
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Shss\Module\MyStoreLocator\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Shss\Module\MyStoreLocator\Site\Helper\MyStoreLocatorHelper;

/**
 * Dispatcher of mod_mystorelocator.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /** Counts maps on the page so every container gets a unique id. */
    private static int $counter = 0;

    /**
     * Layout data: map configuration, unique container id and the consent texts.
     * Consent on → only the local consent script is added (nothing external before the click);
     * consent off → the external widget script (once per page).
     *
     * @return  array
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        $app  = $this->getApplication();

        /** @var MyStoreLocatorHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('MyStoreLocatorHelper');
        $map    = $helper->getMap($data['params'], $app->getLanguage()->getTag());

        $data['mslMap'] = $map;
        $data['mslId']  = 'msl-map-' . (int) $data['module']->id . '-' . (++self::$counter);

        if ($map === []) {
            return $data;
        }

        if ($map['consent']) {
            $data['mslConsentText'] = $this->consentTexts($map['lang']);
        }

        $doc = $app->getDocument();

        if ($doc && method_exists($doc, 'getWebAssetManager')) {
            $wa = $doc->getWebAssetManager();

            if ($map['consent']) {
                if (!$wa->assetExists('script', 'mod_mystorelocator.consent')) {
                    $wa->registerScript('mod_mystorelocator.consent', 'mod_mystorelocator/msl-consent.js', [], ['defer' => true]);
                }

                $wa->useScript('mod_mystorelocator.consent');
            } else {
                if (!$wa->assetExists('script', 'mod_mystorelocator.widget')) {
                    $wa->registerScript(
                        'mod_mystorelocator.widget',
                        MyStoreLocatorHelper::WIDGET_URL,
                        ['version' => ''],
                        ['async' => true, 'id' => 'msl-widget-js']
                    );
                }

                $wa->useScript('mod_mystorelocator.widget');
            }
        }

        return $data;
    }

    /**
     * Placeholder text and button label in the map language (module language files, built-in fallback).
     *
     * @param   string  $lang  de|en
     *
     * @return  array{text: string, button: string}
     */
    private function consentTexts(string $lang): array
    {
        $fallback = MyStoreLocatorHelper::CONSENT_FALLBACK[$lang] ?? MyStoreLocatorHelper::CONSENT_FALLBACK['en'];
        $language = $this->getApplication()->getLanguage();

        if (stripos($language->getTag(), $lang) !== 0) {
            // map language differs from the page language: load the module strings of the map language
            $tag = $lang === 'de' ? 'de-DE' : 'en-GB';

            try {
                $language = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag);
                $language->load('mod_mystorelocator', JPATH_SITE . '/modules/mod_mystorelocator', $tag, true, false);
            } catch (\Throwable $e) {
                return $fallback;
            }
        }

        $text   = $language->_('MOD_MYSTORELOCATOR_CONSENT_TEXT');
        $button = $language->_('MOD_MYSTORELOCATOR_CONSENT_BUTTON');

        return [
            'text'   => $text !== 'MOD_MYSTORELOCATOR_CONSENT_TEXT' ? $text : $fallback['text'],
            'button' => $button !== 'MOD_MYSTORELOCATOR_CONSENT_BUTTON' ? $button : $fallback['button'],
        ];
    }
}

<?php

/**
 * @package     mod_mystorelocator
 * @copyright   (C) 2026 Internet am Dornbusch, Sajjad Hanifa
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Shss\Module\MyStoreLocator\Site\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Shss\Module\MyStoreLocator\Site\Helper\MyStoreLocatorHelper;

/**
 * Map token field: text input (token, embed code or map URL) plus the optional "Connect with My Store Locator" button.
 * The button opens the app's connect page in a popup; the app sends the browser back to a static page of this module
 * (media/mod_mystorelocator/connect-return.html), which posts the token back to this window (same origin).
 */
class MsltokenField extends TextField
{
    /** @var string */
    protected $type = 'Msltoken';

    /**
     * @return  string
     */
    protected function getInput()
    {
        $this->hint = $this->hint ?: Text::_('MOD_MYSTORELOCATOR_FIELD_TOKEN_HINT');
        $input      = parent::getInput();
        $esc        = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $returnUrl = rtrim(Uri::root(), '/') . '/media/mod_mystorelocator/connect-return.html';
        $isHttps   = stripos($returnUrl, 'https://') === 0;
        $connect   = MyStoreLocatorHelper::APP_URL . '/connect?platform=joomla&return_url=' . rawurlencode($returnUrl);
        $register  = MyStoreLocatorHelper::APP_URL . '/#/register?utm_source=joomla';

        $doc = Factory::getApplication()->getDocument();

        if ($doc && method_exists($doc, 'getWebAssetManager')) {
            $doc->getWebAssetManager()->registerAndUseScript(
                'mod_mystorelocator.connect',
                'mod_mystorelocator/connect.js',
                [],
                ['defer' => true]
            );
        }

        $html   = [];
        $html[] = '<div class="msl-token-field" data-msl-token-field'
            . ' data-connect-url="' . $esc($connect) . '"'
            . ' data-input-id="' . $esc($this->id) . '"'
            . ' data-text-ok="' . $esc(Text::_('MOD_MYSTORELOCATOR_FIELD_TOKEN_OK')) . '"'
            . ' data-text-bad="' . $esc(Text::_('MOD_MYSTORELOCATOR_FIELD_TOKEN_INVALID')) . '"'
            . ' data-text-connected="' . $esc(Text::_('MOD_MYSTORELOCATOR_CONNECT_DONE')) . '"'
            . ' data-text-blocked="' . $esc(Text::_('MOD_MYSTORELOCATOR_CONNECT_BLOCKED')) . '">';
        $html[] = $input;
        $html[] = '<div class="msl-token-status form-text" aria-live="polite"></div>';
        $html[] = '<div class="msl-token-actions mt-2 d-flex flex-wrap align-items-center gap-2">';
        $html[] = '<button type="button" class="btn btn-primary msl-connect"' . ($isHttps ? '' : ' disabled') . '>'
            . '<span class="icon-link" aria-hidden="true"></span> '
            . $esc(Text::_('MOD_MYSTORELOCATOR_CONNECT_BUTTON')) . '</button>';
        $html[] = '<a class="btn btn-outline-secondary" href="' . $esc($register) . '" target="_blank" rel="noopener">'
            . $esc(Text::_('MOD_MYSTORELOCATOR_REGISTER_LINK')) . '</a>';
        $html[] = '</div>';

        if (!$isHttps) {
            $html[] = '<div class="form-text text-muted">' . $esc(Text::_('MOD_MYSTORELOCATOR_CONNECT_NEEDS_HTTPS')) . '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }
}

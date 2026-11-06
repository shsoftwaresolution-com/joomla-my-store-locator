<?php

/**
 * @package     mod_mystorelocator
 * @copyright   (C) 2026 Internet am Dornbusch, Sajjad Hanifa
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Shss\Module\MyStoreLocator\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\Registry\Registry;

/**
 * Builds the embed attributes of a My Store Locator map. No remote calls, no state.
 */
class MyStoreLocatorHelper
{
    /** Base URL of the app (map widget, connect flow, sign-up). */
    public const APP_URL = 'https://app.my-store-locator.com';

    /** Public widget script, loaded once per page. */
    public const WIDGET_URL = self::APP_URL . '/widget.js';

    /** Valid map token. */
    public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{6,64}$/';

    /** UTM parameters for links to our site. */
    public const UTM = 'utm_source=joomla&utm_medium=integration&utm_campaign=joomla-plugin';

    /**
     * Extract a map token from a raw token, an embed code (iframe or widget) or a public map URL (…/#/m/<token>).
     *
     * @param   mixed  $input  Raw input.
     *
     * @return  string  Token, or '' when nothing valid was found.
     */
    public static function extractToken($input): string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return '';
        }

        $candidate = $input;

        if (preg_match('/data-token\s*=\s*["\']?([A-Za-z0-9_-]+)/i', $input, $m)) {
            $candidate = $m[1];
        } elseif (preg_match('~#/m/([A-Za-z0-9_-]+)~', $input, $m)) {
            $candidate = $m[1];
        } elseif (preg_match('~/m/([A-Za-z0-9_-]+)~', $input, $m)) {
            $candidate = $m[1];
        }

        return preg_match(self::TOKEN_PATTERN, $candidate) ? $candidate : '';
    }

    /**
     * Form filter for the token field: store the extracted token; keep the raw input when nothing was found,
     * so the validation rule can report it and the user sees what was typed.
     *
     * @param   mixed  $value  Submitted value.
     *
     * @return  string
     */
    public static function filterToken($value): string
    {
        $raw   = trim((string) $value);
        $token = self::extractToken($raw);

        return $token !== '' ? $token : mb_substr(strip_tags($raw), 0, 500);
    }

    /**
     * Normalise a height: 'auto', a number (px) or a number with px|vh|rem|em. Anything else becomes 'auto'.
     *
     * @param   mixed  $value  Raw height.
     *
     * @return  string
     */
    public static function filterHeight($value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || $value === 'auto') {
            return 'auto';
        }

        if (preg_match('/^\d{2,5}$/', $value)) {
            return $value . 'px';
        }

        if (preg_match('/^\d{1,5}(\.\d{1,2})?(px|vh|rem|em)$/', $value)) {
            return $value;
        }

        return 'auto';
    }

    /**
     * Map language: 'de' or 'en'; 'auto' follows the current Joomla language (de* → de, everything else → en).
     *
     * @param   string  $setting  Module setting (auto|de|en).
     * @param   string  $langTag  Current language tag, e.g. de-DE.
     *
     * @return  string
     */
    public static function resolveLanguage(string $setting, string $langTag): string
    {
        if ($setting === 'de' || $setting === 'en') {
            return $setting;
        }

        return stripos($langTag, 'de') === 0 ? 'de' : 'en';
    }

    /** Built-in placeholder texts, used when the map language's file cannot be loaded. */
    public const CONSENT_FALLBACK = [
        'de' => [
            'text'   => 'Diese Karte wird von my-store-locator.com geladen (eigener Kartenserver in Deutschland). Dabei wird Ihre IP-Adresse an diesen Server übertragen.',
            'button' => 'Karte laden',
        ],
        'en' => [
            'text'   => 'This map is loaded from my-store-locator.com (own map server in Germany). Your IP address is transferred to this server.',
            'button' => 'Load map',
        ],
    ];

    /**
     * Map configuration for the layout, or an empty array when no valid token is configured.
     *
     * Consent on (strict two-click): the container gets no widget mount markers (no class "msl-map", no data-mystorelocator,
     * no data-consent) but data-msl-gate; the local msl-consent.js releases it and loads widget.js after the click.
     * Consent off: normal widget container (class "msl-map" is added in the layout, data-mystorelocator here).
     *
     * @param   Registry  $params   Module parameters.
     * @param   string    $langTag  Current language tag.
     *
     * @return  array{consent: bool, lang: string, placeholderHeight: string, attrs: array<string, string>}|array{}
     */
    public function getMap(Registry $params, string $langTag): array
    {
        $token = self::extractToken($params->get('token', ''));

        if ($token === '') {
            return [];
        }

        $consent = (int) $params->get('consent', 1) === 1;
        $height  = self::filterHeight($params->get('height', 'auto'));
        $lang    = self::resolveLanguage((string) $params->get('language', 'auto'), $langTag);
        $attrs   = $consent ? ['data-msl-gate' => ''] : ['data-mystorelocator' => ''];

        $attrs['data-token']  = $token;
        $attrs['data-height'] = $height;
        $attrs['data-lang']   = $lang;

        if ((int) $params->get('lazy', 1) === 1) {
            $attrs['data-lazy'] = 'true';
        }

        $offset = max(0, min(1000, (int) $params->get('offset', 0)));

        if ($height === 'auto' && $offset > 0) {
            $attrs['data-offset'] = (string) $offset;
        }

        if ($height === 'auto' && $params->get('layout_mode', 'default') === 'split') {
            $attrs['data-layout'] = 'split';
        }

        return [
            'consent'           => $consent,
            'lang'              => $lang,
            'placeholderHeight' => $height === 'auto' ? '760px' : $height,
            'attrs'             => $attrs,
        ];
    }

    /**
     * Render data attributes as an escaped HTML attribute string.
     *
     * @param   array<string, string>  $attrs  Attributes.
     *
     * @return  string
     */
    public static function renderAttributes(array $attrs): string
    {
        $out = '';

        foreach ($attrs as $name => $value) {
            $name = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $name));
            $out .= ' ' . $name . ($value === '' ? '' : '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"');
        }

        return $out;
    }
}

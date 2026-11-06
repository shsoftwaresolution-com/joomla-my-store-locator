<?php

/**
 * @package     mod_mystorelocator
 * @copyright   (C) 2026 Internet am Dornbusch, Sajjad Hanifa
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Shss\Module\MyStoreLocator\Site\Helper\MyStoreLocatorHelper;

/** @var array  $mslMap */
/** @var string $mslId */
/** @var array  $mslConsentText */

if (empty($mslMap)) {
    echo "<!-- My Store Locator: no valid map token configured -->\n";

    return;
}

$esc   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$attrs = MyStoreLocatorHelper::renderAttributes($mslMap['attrs']);

if (!$mslMap['consent']) : ?>
<div class="msl-map" id="<?php echo $esc($mslId); ?>"<?php echo $attrs; ?>></div>
    <?php return;
endif; ?>
<div class="msl-map-consent" id="<?php echo $esc($mslId); ?>"<?php echo $attrs; ?>>
    <div class="msl-consent" style="height:<?php echo $esc($mslMap['placeholderHeight']); ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:#f6f7f9;border-radius:12px;font:14px system-ui;text-align:center;padding:24px;box-sizing:border-box">
        <p style="max-width:420px;margin:0;color:#475467"><?php echo $esc($mslConsentText['text']); ?></p>
        <button type="button" class="msl-consent__load" style="background:#5b55d9;color:#fff;border:0;border-radius:10px;padding:10px 18px;font-weight:600;cursor:pointer"><?php echo $esc($mslConsentText['button']); ?></button>
    </div>
</div>

<?php

/**
 * @package     mod_mystorelocator
 * @copyright   (C) 2026 Internet am Dornbusch, Sajjad Hanifa
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Shss\Module\MyStoreLocator\Site\Rule;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Shss\Module\MyStoreLocator\Site\Helper\MyStoreLocatorHelper;

/**
 * Validation rule: the value must contain a valid map token (raw token, embed code or public map URL).
 */
class MsltokenRule extends FormRule
{
    /**
     * @param   \SimpleXMLElement  $element  Field element.
     * @param   mixed              $value    Field value.
     * @param   string             $group    Field group.
     * @param   Registry           $input    Whole form data.
     * @param   Form               $form     Form object.
     *
     * @return  boolean
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return ((string) $element['required']) !== 'true';
        }

        return MyStoreLocatorHelper::extractToken($value) !== '';
    }
}

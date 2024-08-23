<?php
/**
 * Copyright (C) 2024  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace JvH\JvHPuzzelDbBundle\DCA;

use Contao\Backend;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;

class PuzzelProduct extends Backend {

  /**
   * Return the "toggle visibility" button
   *
   * @param array  $row
   * @param string $href
   * @param string $label
   * @param string $title
   * @param string $icon
   * @param string $attributes
   *
   * @return string
   */
  public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
  {
    $security = System::getContainer()->get('security.helper');

    if (!$security->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, 'tl_jvh_db_puzzel_product::visible'))
    {
      return '';
    }
    $href .= '&amp;id=' . $row['id'];

    if (empty($row['visible']))
    {
      $icon = 'invisible.svg';
    }

    return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '" onclick="Backend.getScrollOffset();return AjaxRequest.toggleField(this,true)">' . Image::getHtml($icon, $label, 'data-icon="' . Image::getPath('visible.svg') . '" data-icon-disabled="' . Image::getPath('invisible.svg') . '" data-state="' . ($row['visible'] ? 1 : 0) . '"') . '</a> ';
  }

  public function generateAliasNL($varValue, DataContainer $dc)
  {
    return $this->generateAlias($dc->activeRecord->naam_nl, 'alias_nl', $varValue, $dc);
  }

  public function generateAliasEN($varValue, DataContainer $dc)
  {
    return $this->generateAlias($dc->activeRecord->naam_en, 'alias_en', $varValue, $dc);
  }

  /**
   * Auto-generate an article alias if it has not been set yet
   *
   * @param string        $name
   * @param string        $field
   * @param mixed         $varValue
   * @param DataContainer $dc
   *
   * @return string
   *
   * @throws Exception
   */
  public function generateAlias($name, $field, $varValue, DataContainer $dc)
  {
    $aliasExists = function (string $alias) use ($dc, $field): bool
    {
      if (in_array($alias, array('top', 'wrapper', 'header', 'container', 'main', 'left', 'right', 'footer'), true))
      {
        return true;
      }

      return $this->Database->prepare("SELECT id FROM tl_jvh_db_puzzel_product WHERE ".$field."=? AND id!=?")->execute($alias, $dc->id)->numRows > 0;
    };

    // Generate an alias if there is none
    if (!$varValue)
    {
      $varValue = System::getContainer()->get('contao.slug')->generate($name, [], $aliasExists);
    }
    elseif (preg_match('/^[1-9]\d*$/', $varValue))
    {
      throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'], $varValue));
    }
    elseif ($aliasExists($varValue))
    {
      throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
    }

    return $varValue;
  }

  public function loadStukjes($value, DataContainer $dc) {
    $objProduct = PuzzelProductModel::findByPk($dc->id);
    if ($objProduct) {
      return PuzzelProductModel::getStukjes($objProduct->puzzel_formaat);
    }
    return '';
  }

  public function labelCallback(array $row, string $label, DataContainer $dc, array $labels) {
    $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
    $stukjesKey = array_search('stukjes', $fields, true);
    if (!empty($row['puzzel_formaat'])) {
      $labels[$stukjesKey] = PuzzelProductModel::getStukjes($row['puzzel_formaat']);
    }
    return $labels;
  }

}
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

namespace JvH\JvHPuzzelDbBundle\Model;

use Contao\Model;

class StukjesModel extends Model {

  protected static $strTable = 'tl_jvh_db_stukjes';

  public static function getLabel(int $id = null): string {
    if (empty($id)) {
      return '';
    }
    $objModel = static::getModelFromCache($id);
    if (empty($objModel)) {
      return '';
    }
    return $objModel->label;
  }

  private static function getModelFromCache(int $id) {
    static $cache = null;
    if ($cache == null) {
      $cache = [];
      $objModels = static::findAll();
      foreach ($objModels as $objModel) {
        $cache[$objModel->id] = $objModel;
      }
    }
    if (isset($cache[$id])) {
      return $cache[$id];
    }
    return null;
  }

}
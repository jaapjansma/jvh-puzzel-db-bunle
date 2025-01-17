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

use Contao\Database;
use Contao\Model;

class CollectionModel extends Model {

  const COLLECTION = 1;
  const WISHLIST = 2;

  protected static $strTable = 'tl_jvh_db_collection';

  public static function existsInCollection(int $product_id, int $member_id, int $collection): bool {
    return (bool) self::countInCollection($product_id, $member_id, $collection) > 0;
    //if (CollectionModel::findBy(['puzzel_product=?', 'member=?', 'collection=?'], [$product_id, $member_id, $collection])) {
    //  return true;
    //}
    //return false;
  }

  public static function removeFromWishlist(int $product_id, int $member_id) {
    /** @var Model\Collection $items */
    $items = CollectionModel::findBy(['puzzel_product=?', 'member=?', 'collection=?'], [$product_id, $member_id, CollectionModel::WISHLIST]);
    foreach($items as $item) {
      /** @var $item CollectionModel */
      $item->delete();
    }
  }

  public static function countInCollection(int $product_id, int $member_id, int $collection): int {
    self::userCollectionProductCount($member_id);
    if (isset(self::$collectionProductCount[$member_id][$collection][$product_id])) {
      return self::$collectionProductCount[$member_id][$collection][$product_id];
    }
    return 0;
    //$result = Database::getInstance()->prepare("SELECT COUNT(*) as `count` FROM `tl_jvh_db_collection` WHERE `puzzel_product` = ? AND `member` = ?  AND `collection` = ?")->execute($product_id, $member_id, $collection)->fetchAssoc();
    //return (int) $result['count'];
  }

  static $collectionProductCount;
  public static function userCollectionProductCount(int $member_id) {
    if (!isset(self::$collectionProductCount[$member_id])) {
      $result = Database::getInstance()->prepare("SELECT COUNT(*) as `count`, `collection`, `puzzel_product` FROM `tl_jvh_db_collection` WHERE `member` = ? GROUP BY `collection`, `puzzel_product`")->execute($member_id)->fetchAllAssoc();
      self::$collectionProductCount[$member_id] = [];
      foreach ($result as $item) {
        self::$collectionProductCount[$member_id][$item['collection']][$item['puzzel_product']] = $item['count'];
      }
    }
  }

  /**
   * Delete the current record and return the number of affected rows
   *
   * @return integer The number of affected rows
   */
  public function delete()
  {
    // Track primary key changes
    $intPk = $this->arrModified[static::$strPk] ?? $this->{static::$strPk};
    Database::getInstance()->prepare("DELETE FROM `tl_jvh_db_collection_status_log` WHERE `pid` = ?")->execute($intPk);
    return parent::delete();
  }


}
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

namespace JvH\JvHPuzzelDbBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MigratePuzzelPlaatFormaat extends AbstractMigration {

  public function __construct(private readonly Connection $connection)
  {
  }

  public function shouldRun(): bool
  {
    return true;
    $schemaManager = $this->connection->createSchemaManager();

    // If the database table itself does not exist we should do nothing
    try {
      if ($schemaManager->tablesExist(['tl_jvh_db_puzzel_formaat'])) {
        return false;
      }
    } catch (Exception $e) {
    }
    return true;
  }

  public function run(): MigrationResult
  {
    $this->connection->executeQuery("
        CREATE TABLE `tl_jvh_db_puzzel_formaat` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `tstamp` int(10) UNSIGNED NOT NULL DEFAULT 0,
            `puzzel_plaat` int(10) UNSIGNED NOT NULL DEFAULT 0,
            `stukjes` int(10) UNSIGNED NOT NULL DEFAULT 0,
            `visible` char(1) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB;
    ");
    $this->connection->executeQuery("ALTER TABLE `tl_jvh_db_puzzel_product` ADD COLUMN `puzzel_formaat` blob DEFAULT NULL");

    $alreadyInsertedCombination = [];
    $producten = $this->connection->fetchAllAssociative("SELECT `p`.`id`, `p`.`puzzel_plaat`, `p`.`stukjes`, `p`.`visible` FROM `tl_jvh_db_puzzel_product` `p` ORDER BY `id`");
    $id = 1;
    $insertCount = 0;
    $updateCount = 0;
    foreach($producten as $row) {
      $puzzel_plaat_id = StringUtil::deserialize($row['puzzel_plaat']);
      if (is_array($puzzel_plaat_id)) {
        $puzzel_plaat_id = reset($puzzel_plaat_id);
      }
      if (empty($puzzel_plaat_id)) {
        continue;
      }
      if (!isset($alreadyInsertedCombination[$row['stukjes']][$puzzel_plaat_id])) {
        try {
          $insertStatement = $this->connection->prepare("INSERT INTO `tl_jvh_db_puzzel_formaat` (`id`, `tstamp`, `puzzel_plaat`, `stukjes`, `visible`) VALUES (?, ?, ?, ?, ?)");
          $insertStatement->executeQuery([$id, time(), $puzzel_plaat_id, $row['stukjes'], $row['visible']]);
          $alreadyInsertedCombination[$row['stukjes']][$puzzel_plaat_id] = $id;
          $insertCount ++;
        } catch (Exception $e) {
          return $this->createResult(false, $e->getMessage());
        }
      }
      try {
        $formaat[0] = $alreadyInsertedCombination[$row['stukjes']][$puzzel_plaat_id];
        $updateStatement = $this->connection->prepare("UPDATE `tl_jvh_db_puzzel_product` SET `puzzel_formaat` = ? WHERE `id` = ?");
        $updateStatement->executeQuery([serialize($formaat), $row['id']]);
        $updateCount ++;
      } catch (Exception $e) {
        return $this->createResult(false, $e->getMessage());
      }
      $id ++;
    }

    return $this->createResult(true, 'Migrated puzzel producten naar puzzel formaat. Inserted: ' . $insertCount . '. Updated: '.$updateCount);
  }


}
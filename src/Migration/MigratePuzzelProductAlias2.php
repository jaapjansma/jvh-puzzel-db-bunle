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
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MigratePuzzelProductAlias2 extends AbstractMigration
{

  public function __construct(private readonly Connection $connection)
  {
  }

  public function shouldRun(): bool
  {


    // If the database table itself does not exist we should do nothing
    try {
      $countNl = 0;
      $result =  $this->connection->executeQuery("SELECT COUNT(*) as `count` FROM `tl_jvh_db_puzzel_product` GROUP BY alias_nl HAVING COUNT(*) > 1; ")->fetchAllAssociative();
      foreach ($result as $row) {
        $countNl += $row['count'];
      }
      $countEn = 0;
      $result =  $this->connection->executeQuery("SELECT COUNT(*) as `count` FROM `tl_jvh_db_puzzel_product` GROUP BY alias_en HAVING COUNT(*) > 1; ")->fetchAllAssociative();
      foreach ($result as $row) {
        $countEn += $row['count'];
      }
      if ($countNl > 0 || $countEn > 0) {
        return true;
      }
    } catch (Exception $e) {
    }
    return false;
  }

  public function run(): MigrationResult
  {
    $result =  $this->connection->executeQuery("SELECT COUNT(*) as `count`, `alias_nl` FROM `tl_jvh_db_puzzel_product` GROUP BY alias_nl HAVING COUNT(*) > 1; ")->fetchAllAssociative();
    foreach ($result as $row) {
      $this->updateAlias($row['alias_nl'], 'alias_nl');
    }
    $result =  $this->connection->executeQuery("SELECT COUNT(*) as `count`, `alias_en` FROM `tl_jvh_db_puzzel_product` GROUP BY alias_en HAVING COUNT(*) > 1; ")->fetchAllAssociative();
    foreach ($result as $row) {
      $this->updateAlias($row['alias_en'], 'alias_en');
    }

    return $this->createResult(true, 'Migrated puzzel producten aliases.');
  }

  public function updateAlias($alias, $field)
  {
    $result = $this->connection->executeQuery("SELECT * FROM `tl_jvh_db_puzzel_product` WHERE `$field` = '$alias'")->fetchAllAssociative();
    $i = 0;
    foreach ($result as $row) {
      if ($i > 0) {
        $newAlias = $alias . '-'.$i;
        if ($this->aliasExists($field, $newAlias, $row['id'])) {
          $newAlias = $alias . '-'. $row['id'];
        }
        $this->connection->prepare("UPDATE tl_jvh_db_puzzel_product SET ". $field ." =? WHERE id =? ")->executeQuery([$newAlias, $row['id']]);
      }
      $i++;
    }
  }

  public function aliasExists($field, $alias, $id) {
    return $this->connection->prepare("SELECT id FROM tl_jvh_db_puzzel_product WHERE ".$field."=? AND id!=?")->executeQuery([$alias, $id])->numRows > 0;
  }
}
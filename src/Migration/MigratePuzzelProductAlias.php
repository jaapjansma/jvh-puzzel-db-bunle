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

class MigratePuzzelProductAlias extends AbstractMigration
{

  public function __construct(private readonly Connection $connection)
  {
  }

  public function shouldRun(): bool
  {
    $schemaManager = $this->connection->createSchemaManager();

    // If the database table itself does not exist we should do nothing
    try {
      $columns = $schemaManager->listTableColumns('tl_jvh_db_puzzel_product');
      if (isset($columns['alias_nl']) && isset($columns['alias_en'])) {
        return false;
      }
    } catch (Exception $e) {
    }
    return true;
  }

  public function run(): MigrationResult
  {
    $schemaManager = $this->connection->createSchemaManager();
    $columns = $schemaManager->listTableColumns('tl_jvh_db_puzzel_product');
    if (!isset($columns['alias_nl'])) {
      $this->connection->executeQuery("ALTER TABLE `tl_jvh_db_puzzel_product` ADD COLUMN `alias_nl` varchar(255) NOT NULL default '' AFTER `tstamp`");
    }
    if (!isset($columns['alias_en'])) {
      $this->connection->executeQuery("ALTER TABLE `tl_jvh_db_puzzel_product` ADD COLUMN `alias_en` varchar(255) NOT NULL default '' AFTER `alias_nl`");
    }
    $producten = $this->connection->fetchAllAssociative("SELECT `p`.`id`, `p`.`alias_nl`, `p`.`alias_en`, `p`.`naam_nl`, `p`.`naam_en` FROM `tl_jvh_db_puzzel_product` `p` WHERE `alias_nl` = '' OR `alias_en` = '' ORDER BY `id`");
    foreach($producten as $row) {
      if (empty($row['alias_nl'])) {
        $this->generateAlias($row['naam_nl'], 'alias_nl', $row['id']);
      }
      if (empty($row['alias_en'])) {
        $this->generateAlias($row['naam_en'], 'alias_en', $row['id']);
      }
    }

    return $this->createResult(true, 'Migrated puzzel producten aliases.');
  }

  public function generateAlias($name, $field, int $id)
  {
    $aliasExists = function (string $alias) use ($id, $field): bool
    {
      if (in_array($alias, array('top', 'wrapper', 'header', 'container', 'main', 'left', 'right', 'footer'), true))
      {
        return true;
      }

      return $this->connection->prepare("SELECT id FROM tl_jvh_db_puzzel_product WHERE ".$field."=? AND id!=?")->executeQuery([$alias, $id])->numRows > 0;
    };

    if (empty($name)) {
      $name = 'alias_'.$id;
    }
    $varValue = System::getContainer()->get('contao.slug')->generate($name, [], $aliasExists);
    $this->connection->prepare("UPDATE tl_jvh_db_puzzel_product SET ". $field ." =? WHERE id =? ")->executeQuery([$varValue, $id]);
  }
}
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

use Contao\ArrayUtil;
use Contao\Database;
use Contao\File;
use Contao\FilesModel;
use Contao\Model;
use Contao\StringUtil;
use Contao\System;
use Model\Collection;
use Symfony\Component\Filesystem\Path;

class PuzzelProductModel extends Model {

  protected static $strTable = 'tl_jvh_db_puzzel_product';

  public static function findByFormaatId(int $formaatId):? Collection {
    $strQuery = "SELECT `id` FROM `tl_jvh_db_puzzel_product` WHERE 1 ";
    $arrAllKeywords[] = "`puzzel_formaat` LIKE ?";
    $arrValues[] = '%;i:' . $formaatId . ';}%';
    $arrAllKeywords[] = "`puzzel_formaat` LIKE ?";
    $arrValues[] = '%;i:' . $formaatId . ';i%';
    if (count($arrAllKeywords)) {
      $strQuery .= "AND (" . implode(" OR ", $arrAllKeywords) . ")";
    }
    $objResultStmt = Database::getInstance()->prepare($strQuery);
    $objResult = $objResultStmt->execute(...$arrValues);
    $ids =[];
    while ($objResult->next()) {
      $ids[] = $objResult->id;
    }
    if (count($ids)) {
      return static::findMultipleByIds($ids);
    }
    return null;
  }

  public static function getStukjes(string $puzzel_formaat): string {
    $arrPuzzelFormaatIds = StringUtil::deserialize($puzzel_formaat, true);
    $arrPuzzelFormaatIds = array_filter($arrPuzzelFormaatIds, function($v) {
      return !empty($v);
    });
    $return = [];
    $arrStukjes = [];
    if (count($arrPuzzelFormaatIds)) {
      $objPuzzelFormaten = PuzzelFormaatModel::findAll([
        'column' => 'id',
        'value'  => $arrPuzzelFormaatIds,
        'return' => 'Collection'
      ]);
      while ($objPuzzelFormaten->next()) {
        if (!in_array($objPuzzelFormaten->stukjes, $arrStukjes)) {
          $arrStukjes[] = $objPuzzelFormaten->stukjes;
          $return[] = StukjesModel::getLabel($objPuzzelFormaten->stukjes);
        }
      }
    }
    return implode(", ", $return);
  }

  public static function generateFigureElements(string $strMultiSrc, string $strOrderSrc, int $id, string $imgSize, bool $enableLightBox): array {
    $multiSrc = array_map('\Contao\StringUtil::binToUuid', StringUtil::deserialize($strMultiSrc, true));
    $orderSrc = array_map('\Contao\StringUtil::binToUuid', StringUtil::deserialize($strOrderSrc, true));
    $projectDir = System::getContainer()->getParameter('kernel.project_dir');
    $figures = [];
    $images = array();
    $objFiles = FilesModel::findMultipleByUuids($multiSrc);
    // Get all images
    if ($objFiles) {
      while ($objFiles->next()) {
        // Continue if the files has been processed or does not exist
        if (isset($images[$objFiles->path]) || !file_exists(Path::join($projectDir, $objFiles->path))) {
          continue;
        }

        // Single files
        if ($objFiles->type == 'file') {
          $objFile = new File($objFiles->path);

          if (!$objFile->isImage) {
            continue;
          }

          $row = $objFiles->row();
          $row['mtime'] = $objFile->mtime;

          // Add the image
          $images[$objFiles->path] = $row;
        } // Folders
        else {
          $objSubfiles = FilesModel::findByPid($objFiles->uuid, array('order' => 'name'));

          if ($objSubfiles === null) {
            continue;
          }

          while ($objSubfiles->next()) {
            // Skip subfolders and files that do not exist
            if ($objSubfiles->type == 'folder' || !file_exists(Path::join($projectDir, $objSubfiles->path))) {
              continue;
            }

            $objFile = new File($objSubfiles->path);

            if (!$objFile->isImage) {
              continue;
            }

            $row = $objSubfiles->row();
            $row['mtime'] = $objFile->mtime;

            // Add the image
            $images[$objSubfiles->path] = $row;
          }
        }
      }
      $images = ArrayUtil::sortByOrderField($images, $orderSrc);
      $images = array_values($images);
      $figureBuilder = System::getContainer()
        ->get('contao.image.studio')
        ->createFigureBuilder()
        ->setSize($imgSize)
        ->enableLightbox($enableLightBox)
        ->setLightboxGroupIdentifier('lb' . $id);

      // Rows
      for ($i = 0; $i < count($images); $i++) {
        $figure = $figureBuilder
          ->fromId($images[$i]['id'])
          ->build();
        $cellData = $figure->getLegacyTemplateData();
        $cellData['figure'] = $figure;
        $figures[$i] = $cellData;
      }
    }
    return $figures;
  }

}
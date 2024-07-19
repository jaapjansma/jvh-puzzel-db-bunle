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

namespace JvH\JvHPuzzelDbBundle\Frontend;

use Contao\ArrayUtil;
use Contao\BackendTemplate;
use Contao\Database;
use Contao\Date;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\Search;
use Contao\StringUtil;
use Contao\System;
use Haste\Model\Relations;
use Isotope\Frontend;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\Product;
use Isotope\Model\Product\AbstractProduct;
use JvH\JvHPuzzelDbBundle\Model\PuzzelFormaatModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelPlaatModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;
use JvH\JvHPuzzelDbBundle\Model\SerieModel;
use JvH\JvHPuzzelDbBundle\Model\StukjesModel;
use JvH\JvHPuzzelDbBundle\Model\TekenaarModel;
use JvH\JvHPuzzelDbBundle\Model\UitgeverModel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PuzzelPlaatReader extends Module {

  protected $strTemplate = 'mod_jvh_db_puzzel_plaat_reader';

  public function generate()
  {
    $request = System::getContainer()->get('request_stack')->getCurrentRequest();
    if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
    {
      $objTemplate = new BackendTemplate('be_wildcard');
      $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['jvh_db_puzzel_plaat_lijst'][0] . ' ###';
      $objTemplate->title = $this->headline;
      $objTemplate->id = $this->id;
      $objTemplate->link = $this->name;
      $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

      return $objTemplate->parse();
    }

    // Return an empty string if "items" is not set (to combine list and reader on same page)
    if (!Input::get('auto_item'))
    {
      return '';
    }

    return parent::generate();
  }

  /**
   * Compile the current element
   */
  protected function compile()
  {
    global $objPage;
    System::loadLanguageFile('tl_jvh_db_puzzel_plaat');
    $puzzelPlaat = PuzzelPlaatModel::findByAlias(Input::get('auto_item'));
    $data = $puzzelPlaat->row();
    if ($puzzelPlaat->tekenaar) {
      $tekenaar = TekenaarModel::findByPk($puzzelPlaat->tekenaar);
      $data['tekenaar'] = $tekenaar->row();
    }
    $templateData = [];
    if (!empty($data['singleSRC'])) {
      $fileModel = FilesModel::findById($data['singleSRC']);
      if (file_exists(System::getContainer()->getParameter('kernel.project_dir') . '/' . $fileModel->path)) {
        $objFile = new File($fileModel->path);
        if ($objFile->isImage) {
          $figure = System::getContainer()
            ->get('contao.image.studio')
            ->createFigureBuilder()
            ->fromFilesModel($fileModel)
            ->setSize($this->imgSize)
            ->enableLightbox((bool) $this->fullsize)
            ->build();

          $templateData = $figure->getLegacyTemplateData();
          $templateData['figure'] = $figure;
        }
      }
    }
    $templateData['item'] = $data;
    $this->Template->setData($templateData);
    $this->Template->naam_field = 'naam_nl';
    $this->Template->naam_other_field = 'naam_en';
    $this->Template->opmerkingen_field = 'opmerkingen_nl';
    if ($GLOBALS['TL_LANGUAGE'] == 'en') {
      $this->Template->naam_field = 'naam_en';
      $this->Template->naam_other_field = 'naam_nl';
      $this->Template->opmerkingen_field = 'opmerkingen_en';
    }
    $this->Template->producten = $this->getProducten($puzzelPlaat->id);

    $objPage->pageTitle = $data[$this->Template->naam_field];
  }

  protected function getProducten(int $puzzelPlaatId): string {
    global $objPage;
    System::loadLanguageFile('tl_jvh_db_puzzel_product');
    System::loadLanguageFile('tl_jvh_db_puzzel_formaat');
    $projectDir = System::getContainer()->getParameter('kernel.project_dir');
    $objProducts = PuzzelProductModel::findAll();
    $strProducten = '';
    if ($objProducts) {
      while ($objProducts->next()) {
        if (empty($objProducts->visible)) {
          continue;
        }
        $productData = $objProducts->row();
        $arrPuzzelFormaatIds = StringUtil::deserialize($objProducts->puzzel_formaat);
        $objPuzzelFormaten = PuzzelFormaatModel::findBy(['id', 'puzzel_plaat'], [$arrPuzzelFormaatIds, $puzzelPlaatId]);
        while ($objPuzzelFormaten->next()) {
          $productData['release_date'] = Date::parse($objPage->dateFormat, $productData['release_date']);
          $productData['serie'] = SerieModel::getLabel($productData['serie']);
          $productData['stukjes'] = StukjesModel::getLabel($objPuzzelFormaten->stukjes);
          $productData['uitgever'] = UitgeverModel::getNaam($productData['uitgever']);

          $multiSrc = array_map('\Contao\StringUtil::binToUuid', StringUtil::deserialize($objProducts->multiSRC, true));
          $orderSrc = array_map('\Contao\StringUtil::binToUuid', StringUtil::deserialize($objProducts->orderSRC, true));
          $figures = [];
          if (is_array($multiSrc) && count($multiSrc)) {
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
                ->setSize($this->imgSize)
                ->enableLightbox((bool)$this->fullsize)
                ->setLightboxGroupIdentifier('lb' . $objProducts->id);

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
          }

          $objTemplate = new FrontendTemplate($this->galleryTpl ?: 'puzzel_producten_default');
          $objTemplate->item = $productData;
          $objTemplate->figures = $figures;
          $objTemplate->naam_field = 'naam_nl';
          $objTemplate->opmerkingen_field = 'opmerkingen_nl';
          if ($GLOBALS['TL_LANGUAGE'] == 'en') {
            $objTemplate->naam_field = 'naam_en';
            $objTemplate->opmerkingen_field = 'opmerkingen_en';
          }
          if (!empty($productData['product_id'])) {
            $objProducts = Product::findAvailableByIds([$productData['product_id']]);
            if ($objProducts) {
              $productModels = $objProducts->getModels();
              if ($productModels) {
                $objProduct = reset($productModels);
                if ($objProduct) {
                  $productJumpTo = $this->findJumpToPage($objProduct);
                  $objTemplate->webshop_product_url = $objProduct->generateUrl($productJumpTo, true);
                }
              }
            }
          }
          $strProducten .= $objTemplate->parse();
        }
      }
    }
    return $strProducten;
  }

  protected function findJumpToPage(IsotopeProduct $objProduct)
  {
    global $objPage;
    global $objIsotopeListPage;

    $arrCategories = $objProduct instanceof AbstractProduct ? $objProduct->getCategories(true) : [];
    $arrCategories = Frontend::getPagesInCurrentRoot($arrCategories, FrontendUser::getInstance());
    if (!empty($arrCategories)
      && ($objCategories = PageModel::findMultipleByIds($arrCategories)) !== null
    ) {
      $blnMoreThanOne = $objCategories->count() > 1;
      foreach ($objCategories as $objCategory) {

        if ('index' === $objCategory->alias && $blnMoreThanOne) {
          continue;
        }

        return $objCategory;
      }
    }

    return $objIsotopeListPage ? : $objPage;
  }


}
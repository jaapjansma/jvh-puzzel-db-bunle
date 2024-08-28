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

use Contao\BackendTemplate;
use Contao\Date;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Isotope\Frontend;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\Product;
use Isotope\Model\Product\AbstractProduct;
use JvH\JvHPuzzelDbBundle\Model\DoosModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelFormaatModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelPlaatModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;
use JvH\JvHPuzzelDbBundle\Model\SerieModel;
use JvH\JvHPuzzelDbBundle\Model\StukjesModel;
use JvH\JvHPuzzelDbBundle\Model\TekenaarModel;
use JvH\JvHPuzzelDbBundle\Model\UitgeverModel;

class PuzzelProductReader extends Module
{

  protected $strTemplate = 'mod_jvh_db_puzzel_product_reader';

  public function generate()
  {
    $request = System::getContainer()->get('request_stack')->getCurrentRequest();
    if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
      $objTemplate = new BackendTemplate('be_wildcard');
      $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['jvh_db_puzzel_product_reader'][0] . ' ###';
      $objTemplate->title = $this->headline;
      $objTemplate->id = $this->id;
      $objTemplate->link = $this->name;
      $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id)));

      return $objTemplate->parse();
    }

    // Return an empty string if "items" is not set (to combine list and reader on same page)
    if (!Input::get('auto_item')) {
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
    System::loadLanguageFile('tl_jvh_db_puzzel_product');
    System::loadLanguageFile('tl_jvh_db_puzzel_plaat');
    System::loadLanguageFile('tl_jvh_db_puzzel_formaat');
    $objProduct = PuzzelProductModel::findByAlias(Input::get('auto_item'));
    $data = $objProduct->row();
    $templateData['item'] = $data;
    $this->Template->setData($templateData);
    $this->Template->naam_field = 'naam_nl';
    if ($GLOBALS['TL_LANGUAGE'] == 'en') {
      $this->Template->naam_field = 'naam_en';
    }
    $this->loadProductIntoTemplate($objProduct);
    $this->Template->platen = $this->getPuzzelPlaten($objProduct->puzzel_formaat);
    $objPage->pageTitle = $data[$this->Template->naam_field];
  }

  protected function loadProductIntoTemplate($objProduct) {
    global $objPage;
    System::loadLanguageFile('tl_jvh_db_puzzel_product');
    System::loadLanguageFile('tl_jvh_db_puzzel_formaat');
    if ($objProduct && !empty($objProduct->visible)) {
      $productData = $objProduct->row();
      $productData['release_date'] = Date::parse($objPage->dateFormat, $productData['release_date']);
      $productData['serie'] = SerieModel::getLabel($productData['serie']);
      $productData['uitgever'] = UitgeverModel::getNaam($productData['uitgever']);
      $productData['stukjes'] = PuzzelProductModel::getStukjes($productData['puzzel_formaat']);
      $productData['doos'] = DoosModel::getLabel($productData['doos']);
      if ($objProduct->multiSRC !== null && $objProduct->orderSRC !== null) {
        $figures = PuzzelProductModel::generateFigureElements($objProduct->multiSRC, $objProduct->orderSRC, $objProduct->id, $this->imgSize, (bool)true, 'puzzel_product_reader');
      }

      $this->Template->item = $productData;
      $this->Template->figures = $figures;
      $this->Template->naam_field = 'naam_nl';
      $this->Template->opmerkingen_field = 'opmerkingen_nl';
      if ($GLOBALS['TL_LANGUAGE'] == 'en') {
        $this->Template->naam_field = 'naam_en';
        $this->Template->opmerkingen_field = 'opmerkingen_en';
      }
      if (!empty($productData['product_id'])) {
        $objIsoProducts = Product::findAvailableByIds([$productData['product_id']]);
        if ($objIsoProducts) {
          $productIsoModels = $objIsoProducts->getModels();
          if ($productIsoModels) {
            $objIsoProduct = reset($productIsoModels);
            if ($objIsoProduct) {
              $productJumpTo = $this->findJumpToPage($objIsoProduct);
              $this->Template->webshop_product_url = $objIsoProduct->generateUrl($productJumpTo, true);
            }
          }
        }
      }
    }
  }

  protected function getPuzzelPlaten(string $puzzel_formaat): string {
    $strPlaten = '';
    $arrPuzzelFormaatIds = StringUtil::deserialize($puzzel_formaat, true);
    $arrPuzzelFormaatIds = array_filter($arrPuzzelFormaatIds, function($v) {
      return !empty($v);
    });
    foreach($arrPuzzelFormaatIds as $puzzelFormaatId) {
      $objPuzzelFormaten = PuzzelFormaatModel::findByPk($puzzelFormaatId);
      if ($objPuzzelFormaten && $objPuzzelFormaten->visible) {
        $objTemplate = new FrontendTemplate($this->galleryTpl ?: 'puzzel_plaat_default');
        $puzzelPlaat = PuzzelPlaatModel::findByPk($objPuzzelFormaten->puzzel_plaat);
        $data = $puzzelPlaat->row();
        $data['stukjes'] = StukjesModel::getLabel($objPuzzelFormaten->stukjes);
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
                ->enableLightbox((bool) true)
                ->setLightboxGroupIdentifier('puzzel_product_reader')
                ->build();

              $templateData = $figure->getLegacyTemplateData();
              $templateData['figure'] = $figure;
            }
          }
        }
        $templateData['item'] = $data;
        $objTemplate->setData($templateData);
        $objTemplate->naam_field = 'naam_nl';
        $objTemplate->naam_other_field = 'naam_en';
        $objTemplate->opmerkingen_field = 'opmerkingen_nl';
        if ($GLOBALS['TL_LANGUAGE'] == 'en') {
          $objTemplate->naam_field = 'naam_en';
          $objTemplate->naam_other_field = 'naam_nl';
          $objTemplate->opmerkingen_field = 'opmerkingen_en';
        }
        $strPlaten .= $objTemplate->parse();
      }
    }
    return $strPlaten;
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
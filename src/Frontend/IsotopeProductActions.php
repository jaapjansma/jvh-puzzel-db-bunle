<?php
/**
 * Copyright (C) 2025  Jaap Jansma (jaap.jansma@civicoop.org)
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
use Contao\StringUtil;
use Contao\System;
use Haste\Input\Input;
use Isotope\Model\Product;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;

class IsotopeProductActions extends AbstractModule {

  protected $strTemplate = 'mod_jvh_db_isotope_product_actions';

  public function generate()
  {
    $request = System::getContainer()->get('request_stack')->getCurrentRequest();
    if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
      $objTemplate = new BackendTemplate('be_wildcard');
      $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['jvh_db_isotope_product_actions'][0] . ' ###';
      $objTemplate->title = $this->headline;
      $objTemplate->id = $this->id;
      $objTemplate->link = $this->name;
      $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id)));

      return $objTemplate->parse();
    }
    return parent::generate();
  }

  /**
   * Compile the current element
   */
  protected function compile()
  {
    static $currentUrl;
    global $objPage;

    if ($currentUrl == null) {
      $auto_item = \Contao\Input::get('auto_item');
      if (is_string($auto_item) && strlen($auto_item)) {
        $auto_item = '/' . $auto_item;
      } else {
        $auto_item = null;
      }
      $currentUrl = $objPage->getFrontendUrl($auto_item);
    }

    $objIsoProduct = Product::findAvailableByIdOrAlias(Input::getAutoItem('product'));
    $puzzelProduct = PuzzelProductModel::findBy('product_id', $objIsoProduct->id);
    $this->Template->collection_links = '';
    if ($puzzelProduct && $this->User->id) {
      $product_id = $puzzelProduct->id;
      $this->Template->collection_url = $currentUrl . '?collection='.$product_id;
      $this->Template->wishlist_url = $currentUrl . '?wishlist='.$product_id;
      $this->Template->cart_url = $currentUrl . '?cart='.$product_id;
      $this->Template->collection_exists = CollectionModel::countInCollection($product_id, $this->User->id, CollectionModel::COLLECTION);
      $this->Template->wishlist_exists = CollectionModel::countInCollection($product_id, $this->User->id, CollectionModel::WISHLIST);
    }
  }

}
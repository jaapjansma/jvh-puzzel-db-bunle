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

use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\PageModel;
use Contao\System;
use Isotope\Frontend;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Isotope;
use Isotope\Model\Product;
use Isotope\Model\Product\AbstractProduct;
use JvH\JvHPuzzelDbBundle\Event\CollectionUpdatedEvent;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use JvH\JvHPuzzelDbBundle\Model\CollectionStatusLogModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;

abstract class AbstractModule extends \Contao\Module {

  public function generate()
  {
    global $objPage;
    $this->User = FrontendUser::getInstance();
    if ($this->User->id && $id = \Contao\Input::get('collection')) {
      $this->saveProductInCollection($id, CollectionModel::COLLECTION);
    }
    if ($this->User->id && $id = \Contao\Input::get('wishlist')) {
      $this->saveProductInCollection($id, CollectionModel::WISHLIST);
    }
    if ($this->User->id && $id = \Contao\Input::get('cart')) {
      $this->addProductToCard($id);
    }
    if ($this->User->id && $id = \Contao\Input::get('delete_status_log')) {
      $this->deleteStatusLog($id);
    }
    return parent::generate();
  }

  protected function saveProductInCollection(int $product_id, int $collection, bool $redirect=true) {
    global $objPage;
    if ($collection == CollectionModel::COLLECTION || !CollectionModel::existsInCollection($product_id, $this->User->id, $collection)) {
      $collectionModel = new CollectionModel();
      $collectionModel->puzzel_product = $product_id;
      $collectionModel->collection = $collection;
      $collectionModel->member = $this->User->id;
      $collectionModel->tstamp = time();
      $collectionModel->save();
      $statusLog = new CollectionStatusLogModel();
      $statusLog->pid = $collectionModel->id;
      $statusLog->status = 1;
      $statusLog->tstamp = time();
      $statusLog->save();

      $event = new CollectionUpdatedEvent($collectionModel, 'added');
      System::getContainer()->get('event_dispatcher')->dispatch($event, CollectionUpdatedEvent::EVENT);
    }
    if ($redirect) {
      $url = $this->generateCurrentUrl();
      $url .= '#product-'.$product_id;
      $this->redirect($url);
    }
  }

  protected function addProductToCard(int $product_id, bool $redirect=true) {
    $objProduct = PuzzelProductModel::findByPk($product_id);
    $isoTopeProducts = Product::findAvailableByIds([$objProduct->product_id]);
    if ($isoTopeProducts) {
      /** @var IsotopeProduct $isoTopeProduct */
      foreach ($isoTopeProducts->getModels() as $isoTopeProduct) {
        $arrConfig['jumpTo'] = $this->findJumpToPage($isoTopeProduct);
        Isotope::getCart()->addProduct($isoTopeProduct, 1, $arrConfig);
      }
    }
    if ($redirect) {
      $url = $this->generateCurrentUrl();
      $url .= '#product-'.$product_id;
      $this->redirect($url);
    }
  }

  protected function deleteStatusLog(int $log_id, bool $redirect=true) {
    $objStatus = CollectionStatusLogModel::findByPk($log_id);
    $product_id = $objStatus->pid;
    $objStatus->delete();
    if ($redirect) {
      $url = $this->generateCurrentUrl();
      $this->redirect($url);
    }
  }

  protected function generateCurrentUrl(): string {
    global $objPage;
    $auto_item = \Contao\Input::get('auto_item');
    if (is_string($auto_item) && strlen($auto_item)) {
      $auto_item = '/' . $auto_item;
    } else {
      $auto_item = null;
    }
    $url = $objPage->getFrontendUrl($auto_item);
    $queryParams = [];
    foreach ($_GET as $key => $value) {
      if (in_array($key, ['collection', 'wishlist', 'cart', 'delete_status_log', 'auto_item'])) {
        continue;
      }
      $queryParams[$key] = $value;
    }
    $query = http_build_query($queryParams);
    if (strlen($query)) {
      $url .= '?' . $query;
    }
    return $url;
  }

  protected function generateCollectionLinks(int $product_id): string {
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

    if ($this->User->id) {
      $objTemplate = new FrontendTemplate('collection_links');
      $objTemplate->collection_url = $currentUrl . '?collection='.$product_id;
      $objTemplate->wishlist_url = $currentUrl . '?wishlist='.$product_id;
      $objTemplate->cart_url = $currentUrl . '?cart='.$product_id;
      $objTemplate->collection_exists = CollectionModel::countInCollection($product_id, $this->User->id, CollectionModel::COLLECTION);
      $objTemplate->wishlist_exists = CollectionModel::countInCollection($product_id, $this->User->id, CollectionModel::WISHLIST);
      return $objTemplate->parse();
    }
    return '';
  }

  protected function generateCartUrl(int $product_id): string {
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
    return $currentUrl . '?cart='.$product_id;
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
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
use Contao\Input;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use JvH\JvHPuzzelDbBundle\Model\CollectionStatusLogModel;

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
    }
    if ($redirect) {
      $auto_item = \Contao\Input::get('auto_item');
      if (is_string($auto_item) && strlen($auto_item)) {
        $auto_item = '/' . $auto_item;
      } else {
        $auto_item = null;
      }
      $url = $objPage->getFrontendUrl($auto_item);
      $queryParams = [];
      foreach ($_GET as $key => $value) {
        if (in_array($key, ['collection', 'wishlist', 'auto_item'])) {
          continue;
        }
        $queryParams[$key] = $value;
      }
      $query = http_build_query($queryParams);
      if (strlen($query)) {
        $url .= '?' . $query;
      }
      $this->redirect($url);
    }
  }

  protected function generateCollectionLinks(int $product_id): string {
    global $objPage;
    if ($this->User->id) {
      $auto_item = \Contao\Input::get('auto_item');
      if (is_string($auto_item) && strlen($auto_item)) {
        $auto_item = '/' . $auto_item;
      } else {
        $auto_item = null;
      }
      $objTemplate = new FrontendTemplate('collection_links');
      $objTemplate->collection_url = $objPage->getFrontendUrl($auto_item) . '?collection='.$product_id;
      $objTemplate->wishlist_url = $objPage->getFrontendUrl($auto_item) . '?wishlist='.$product_id;
      $objTemplate->collection_exists = CollectionModel::countInCollection($product_id, $this->User->id, CollectionModel::COLLECTION);
      $objTemplate->wishlist_exists = CollectionModel::countInCollection($product_id, $this->User->id, CollectionModel::WISHLIST);
      return $objTemplate->parse();
    }
    return '';
  }

}
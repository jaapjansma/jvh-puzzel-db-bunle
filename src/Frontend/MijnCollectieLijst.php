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
use Contao\CoreBundle\Exception\RouteParametersException;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\Search;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use JvH\JvHPuzzelDbBundle\Model\CollectionModel;
use JvH\JvHPuzzelDbBundle\Model\DoosModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;
use JvH\JvHPuzzelDbBundle\Model\SerieModel;
use JvH\JvHPuzzelDbBundle\Model\UitgeverModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MijnCollectieLijst extends AbstractModule
{

  protected $strTemplate = 'mod_jvh_db_mijn_collectie_lijst';

  public function generate()
  {
    global $objPage;
    $request = System::getContainer()->get('request_stack')->getCurrentRequest();
    if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
      $objTemplate = new BackendTemplate('be_wildcard');
      $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['jvh_db_puzzel_mijn_collectie_lijst'][0] . ' ###';
      $objTemplate->title = $this->headline;
      $objTemplate->id = $this->id;
      $objTemplate->link = $this->name;
      $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id)));

      return $objTemplate->parse();
    }
    if ($id = \Contao\Input::get('delete')) {
      $collectionModel = CollectionModel::findByPk($id);
      $collectionModel->delete();
      $url = $objPage->getFrontendUrl();
      $this->redirect($url);
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
    System::loadLanguageFile('tl_jvh_db_puzzel_formaat');
    System::loadLanguageFile('tl_jvh_db_puzzel_plaat');
    System::loadLanguageFile('tl_jvh_db_collection');
    System::loadLanguageFile('tl_jvh_db_collection_status_log');
    if (\is_array(Input::get('keywords')))
    {
      throw new BadRequestHttpException('Expected string, got array');
    }
    $this->Template->uniqueId = $this->id;
    $objTarget = $this->objModel->getRelated('jumpTo');
    if (empty($objTarget)) {
      $objTarget = $objPage;
    }

    if (Input::post('FORM_SUBMIT') == $this->id) {
      $ids = Input::post('collection_item');
      if (is_array($ids) && count($ids)) {
        $action_type = Input::post('action_type');
        if ($action_type == 'delete') {
          $strQuery = "DELETE FROM `tl_jvh_db_collection_status_log` WHERE `pid` IN (" . implode(",", $ids) . ")";
          Database::getInstance()->prepare($strQuery)->execute();
          $strQuery = "DELETE FROM `tl_jvh_db_collection` WHERE `id` IN (" . implode(",", $ids) . ")";
          Database::getInstance()->prepare($strQuery)->execute();
        } elseif ($action_type == 'addToCart') {
          foreach ($ids as $id) {
            $collectionItem = CollectionModel::findByPk($id);
            $this->addProductToCard($collectionItem->puzzel_product, false);
          }
        }
      }

      $url = $objPage->getFrontendUrl();
      $queryParams = [];
      foreach($_GET as $key => $value) {
        if (in_array($key, ['collection_item', 'auto_item'])) {
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

    $arrResult = $this->getProducts();
    $ids = [];
    foreach($arrResult as $index => $item) {
      $ids[] = $item['id'];
      try {
        $arrResult[$index]['link'] = $objTarget->getFrontendUrl('/' . $item['id']);
      } catch (RouteParametersException $e) {
        $arrResult[$index]['link'] = '';
      }
      $arrResult[$index]['class'] = [];
      switch ($item['collection']) {
        case CollectionModel::COLLECTION:
          $arrResult[$index]['class'][] = 'collection';
          break;
        case CollectionModel::WISHLIST:
          $arrResult[$index]['class'][] = 'wishlist';
          break;
      }
      $arrResult[$index]['collection'] = '';
      if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection']['collection_options'][$item['collection']])) {
        $arrResult[$index]['collection'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection']['collection_options'][$item['collection']];
      }
      $arrResult[$index]['titel'] = $item['naam_' . $GLOBALS['TL_LANGUAGE']];
      $arrResult[$index]['tstamp'] = date('d-m-Y', $item['tstamp']);
      $arrResult[$index]['serie'] = SerieModel::getLabel($arrResult[$index]['serie']);
      $arrResult[$index]['doos'] = DoosModel::getLabel($arrResult[$index]['doos']);
      $arrResult[$index]['uitgever'] = UitgeverModel::getNaam($arrResult[$index]['uitgever']);
      $arrResult[$index]['release_date'] = Date::parse($objPage->dateFormat, $arrResult[$index]['release_date']);
      $arrResult[$index]['stukjes'] = PuzzelProductModel::getStukjes($arrResult[$index]['puzzel_formaat']);
      $arrResult[$index]['tekenaar'] = PuzzelProductModel::getTekenaars($arrResult[$index]['puzzel_formaat']);
      $arrResult[$index]['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][0];
      if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$item['status']])) {
        $arrResult[$index]['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$item['status']];
      }
      $arrResult[$index]['collection_links'] = '';
      $arrResult[$index]['delete_link'] = $objPage->getFrontendUrl() . '?delete=' . $item['id'];
      $arrResult[$index]['edit_link'] = $objTarget->getFrontendUrl() . '?id=' . $item['id'];
      $arrResult[$index]['webshop_cart_url'] = $this->generateCartUrl($item['puzzel_product']);

      $arrResult[$index]['figures'] = [];
      $orderSrc = '';
      if (isset($item['orderSRC'])) {
        $orderSrc = $item['orderSRC'];
      }
      if (isset($item['collection_orderSRC'])) {
        $orderSrc = $item['collection_orderSRC'];
      }
      if (isset($item['multiSRC'])) {
        $arrResult[$index]['figures'] = PuzzelProductModel::generateFigureElements($item['multiSRC'], $orderSrc, $item['id'], $this->imgSize, (bool)$this->fullsize);
      }
    }
    $this->Template->statusLogsPerPid = $this->getStatusLog($ids);
    $count = count($arrResult);
    $this->Template->count = $count;
    $this->Template->results = $arrResult;
    $this->Template->formId = $this->id;
    $this->Template->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
  }

  protected function getProducts(): array
  {
    if (!$this->User->id) {
      return [];
    }

    $strQuery = "SELECT `tl_jvh_db_puzzel_product`.`naam_nl`, `tl_jvh_db_puzzel_product`.`naam_en`, `tl_jvh_db_puzzel_product`.`product_number`, `tl_jvh_db_puzzel_product`.`product_id`, `tl_jvh_db_puzzel_product`.`multiSRC`, `tl_jvh_db_puzzel_product`.`orderSRC`, `tl_jvh_db_puzzel_product`.`release_date`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`serie`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`doos`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`uitgever`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`puzzel_formaat`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`id` as `puzzel_product`,";
    $strQuery .= "`tl_jvh_db_collection`.`collection`, `tl_jvh_db_collection`.`id`, `tl_jvh_db_collection`.`tstamp`, `tl_jvh_db_collection`.`comment`, `tl_jvh_db_collection`.`orderSRC` AS `collection_orderSRC`, ";
    $strQuery .= "`tl_jvh_db_collection_status_log`.`status`";
    $strQuery .= " FROM `tl_jvh_db_collection`";
    $strQuery .= " LEFT JOIN (SELECT    MAX(`id`) `max_id`, `pid` FROM `tl_jvh_db_collection_status_log` GROUP BY  `pid`) `recent_status` ON (`recent_status`.`pid` = `tl_jvh_db_collection`.`id`)";
    $strQuery .= " LEFT JOIN `tl_jvh_db_collection_status_log` ON (`tl_jvh_db_collection_status_log`.`id` = `recent_status`.`max_id`)";
    $strQuery .= " INNER JOIN `tl_jvh_db_puzzel_product` ON `tl_jvh_db_collection`.`puzzel_product` = `tl_jvh_db_puzzel_product`.`id`";
    $strQuery .= " LEFT JOIN `tl_jvh_db_series` ON `tl_jvh_db_series`.`id` = `tl_jvh_db_puzzel_product`.`serie`";
    $strQuery .= " LEFT JOIN `tl_jvh_db_uitgever` ON `tl_jvh_db_uitgever`.`id` = `tl_jvh_db_puzzel_product`.`uitgever`";
    $strQuery .= " LEFT JOIN `tl_jvh_db_doos` ON `tl_jvh_db_doos`.`id` = `tl_jvh_db_puzzel_product`.`doos`";
    $strQuery .= " WHERE `tl_jvh_db_puzzel_product`.`visible` = '1'";
    $strQuery .= " AND `tl_jvh_db_collection`.`member` = ?";
    $arrValues[] = $this->User->id;

    $objResultStmt = Database::getInstance()->prepare($strQuery);
    $objResult = $objResultStmt->execute(...$arrValues);
    return $objResult->fetchAllAssoc();
  }

  protected function getStatusLog(array $ids) {
    if (count($ids) === 0) {
      return [];
    }

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

    /** @var Connection $connections */
    $connection = System::getContainer()->get('database_connection');
    $objResult = $connection->executeQuery("SELECT * FROM `tl_jvh_db_collection_status_log` WHERE `pid` IN (?) ORDER BY `pid`, `tstamp` DESC", [$ids], [ArrayParameterType::INTEGER]);
    $return = [];
    foreach ($objResult->fetchAllAssociative() as $row) {
      if (!isset($return[$row['pid']])) {
        $return[$row['pid']] = [];
      }
      if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$row['status']])) {
        $row['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$row['status']];
      } else {
        $row['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][0];
      }
      $row['tstamp'] = date('d-m-Y', $row['tstamp']);
      $row['delete_status_url'] = $currentUrl . '?delete_status_log=' . $row['id'];
      if (count($return[$row['pid']]) < 3) {
        $return[$row['pid']][] = $row;
      }
    }
    return $return;
  }

}
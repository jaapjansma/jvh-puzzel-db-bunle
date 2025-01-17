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
use JvH\JvHPuzzelDbBundle\Model\CollectionStatusLogModel;
use JvH\JvHPuzzelDbBundle\Model\DoosModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;
use JvH\JvHPuzzelDbBundle\Model\SerieModel;
use JvH\JvHPuzzelDbBundle\Model\UitgeverModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class MijnCollectieReader extends AbstractModule
{

  protected $strTemplate = 'mod_jvh_db_mijn_collectie_reader';

  public function generate()
  {
    global $objPage;
    $request = System::getContainer()->get('request_stack')->getCurrentRequest();
    if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
      $objTemplate = new BackendTemplate('be_wildcard');
      $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['jvh_db_puzzel_mijn_collectie_reader'][0] . ' ###';
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
    global $objPage;
    System::loadLanguageFile('tl_jvh_db_puzzel_product');
    System::loadLanguageFile('tl_jvh_db_puzzel_formaat');
    System::loadLanguageFile('tl_jvh_db_puzzel_plaat');
    System::loadLanguageFile('tl_jvh_db_collection');
    System::loadLanguageFile('tl_jvh_db_collection_status_log');
    if (\is_array(Input::get('keywords'))) {
      throw new BadRequestHttpException('Expected string, got array');
    }

    global $objPage;
    $auto_item = \Contao\Input::get('auto_item');
    if (is_string($auto_item) && strlen($auto_item)) {
      $auto_item = '/' . $auto_item;
    } else {
      $auto_item = null;
    }
    $currentUrl = $objPage->getFrontendUrl($auto_item) . '?';
    $queryParams = [];
    foreach ($_GET as $key => $value) {
      if (in_array($key, ['collection', 'wishlist', 'auto_item', 'orderSRC'])) {
        continue;
      }
      $queryParams[$key] = $value;
    }
    $query = http_build_query($queryParams);
    if (strlen($query)) {
      $currentUrl .= $query;
    }

    $this->Template->uniqueId = $this->id;
    $objTarget = $this->objModel->getRelated('jumpTo');
    if (empty($objTarget)) {
      $objTarget = $objPage;
    }
    $id = Input::get('id');
    if (empty($id)) {
      $url = $objTarget->getFrontendUrl();
      $this->redirect($url);
    }
    $item = $this->getProduct($id);
    try {
      $item['link'] = $objTarget->getFrontendUrl('/' . $item['id']);
    } catch (RouteParametersException $e) {
      $item['link'] = '';
    }
    $item['class'] = [];
    switch ($item['collection']) {
      case CollectionModel::COLLECTION:
        $item['class'][] = 'collection';
        break;
      case CollectionModel::WISHLIST:
        $item['class'][] = 'wishlist';
        break;
    }
    $item['condition_id'] = $item['condition'];
    $item['condition'] = '';
    if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection']['condition_options'][$item['condition']])) {
      $item['condition'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection']['condition_options'][$item['condition']];
    }
    $item['collection_id'] = $item['collection'];
    $item['collection'] = '';
    if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection']['collection_options'][$item['collection']])) {
      $item['collection'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection']['collection_options'][$item['collection']];
    }
    $item['titel'] = $item['naam_' . $GLOBALS['TL_LANGUAGE']];
    $item['tstamp'] = date('d-m-Y', $item['tstamp']);
    $item['serie'] = SerieModel::getLabel($item['serie']);
    $item['doos'] = DoosModel::getLabel($item['doos']);
    $item['uitgever'] = UitgeverModel::getNaam($item['uitgever']);
    $item['release_date'] = Date::parse($objPage->dateFormat, $item['release_date']);
    $item['stukjes'] = '';
    $item['tekenaar'] = '';
    if (!empty($item['puzzel_formaat'])) {
      $item['stukjes'] = PuzzelProductModel::getStukjes($item['puzzel_formaat']);
      $item['tekenaar'] = PuzzelProductModel::getTekenaars($item['puzzel_formaat']);
    }
    $item['status_id'] = $item['status'];
    $item['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][0];
    if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$item['status']])) {
      $item['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$item['status']];
    }
    $item['collection_links'] = '';
    $item['delete_link'] = $objPage->getFrontendUrl() . '?delete=' . $item['id'];

    $item['figures'] = [];
    $orderSrc = '';
    if (isset($item['orderSRC'])) {
      $orderSrc = $item['orderSRC'];
    }
    if (isset($item['collection_orderSRC'])) {
      $orderSrc = $item['collection_orderSRC'];
    }
    if (isset($item['multiSRC'])) {
      $item['figures'] = PuzzelProductModel::generateFigureElements($item['multiSRC'], $orderSrc, $item['id'], $this->imgSize, (bool)$this->fullsize, 'lb-puzzel-product', true, $currentUrl);
    }
    $this->Template->statusLogs = $this->getStatusLog($id);
    $item['status_comment'] = '';
    if (isset($this->Template->statusLogs[0])) {
      $item['status_comment'] = $this->Template->statusLogs[0]['comment'];
    }

    $this->Template->formId = $this->id;
    $this->Template->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();

    if (Input::get('orderSRC') && isset($item['orderSRC'])) {
      $orderSrc = array_map('\Contao\StringUtil::binToUuid', StringUtil::deserialize($item['orderSRC'], true));
      $newOrderSrc[] = StringUtil::uuidToBin(Input::get('orderSRC'));
      foreach($orderSrc as $orderSrcItem) {
        if ($orderSrcItem != Input::get('orderSRC')) {
          $newOrderSrc[] = StringUtil::uuidToBin($orderSrcItem);
        }
      }
      $strNewOrderSrc = serialize($newOrderSrc);
      $objCollectionModel = CollectionModel::findByPk($item['id']);
      $objCollectionModel->orderSRC = $strNewOrderSrc;
      $objCollectionModel->save();
      if (isset($item['multiSRC'])) {
        $item['figures'] = PuzzelProductModel::generateFigureElements($item['multiSRC'], $strNewOrderSrc, $item['id'], $this->imgSize, (bool)$this->fullsize, 'lb-puzzel-product', true, $currentUrl);
      }
    }

    if (Input::post('FORM_SUBMIT') == $this->id) {
      $objCollectionModel = CollectionModel::findByPk($item['id']);
      $objCollectionModel->collection = Input::post('collection');
      $objCollectionModel->comment = Input::post('comment');
      if ($objCollectionModel->collection == 1) {
        $objCollectionModel->condition = Input::post('condition');
      }
      $objCollectionModel->save();
      if (Input::post('status') != $item['status_id']) {
        // A new status is submitted
        $statusLog = new CollectionStatusLogModel();
        $statusLog->pid = $item['id'];
        $statusLog->tstamp = time();
        $statusLog->status = Input::post('status');
        $statusLog->comment = Input::post('status_comment');
        $statusLog->save();
      } elseif (isset($this->Template->statusLogs[0])) {
        $statusLog = CollectionStatusLogModel::findByPk($this->Template->statusLogs[0]['id']);
        $statusLog->comment = Input::post('status_comment');
        $statusLog->save();
      }
      $url = $objTarget->getFrontendUrl();
      $this->redirect($url);
    }
    $this->Template->item = $item;
  }

  protected function getProduct(int $id): array
  {
    if (!$this->User->id) {
      return [];
    }

    $strQuery = "SELECT `tl_jvh_db_puzzel_product`.`naam_nl`, `tl_jvh_db_puzzel_product`.`naam_en`, `tl_jvh_db_puzzel_product`.`product_number`, `tl_jvh_db_puzzel_product`.`product_id`, `tl_jvh_db_puzzel_product`.`multiSRC`, `tl_jvh_db_puzzel_product`.`orderSRC`, `tl_jvh_db_puzzel_product`.`release_date`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`serie`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`doos`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`uitgever`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`puzzel_formaat`,";
    $strQuery .= "`tl_jvh_db_collection`.`collection`, `tl_jvh_db_collection`.`id`, `tl_jvh_db_collection`.`tstamp`, `tl_jvh_db_collection`.`comment`, `tl_jvh_db_collection`.`condition`, `tl_jvh_db_collection`.`orderSRC` AS `collection_orderSRC`, ";
    $strQuery .= "`tl_jvh_db_collection_status_log`.`status`";
    $strQuery .= " FROM `tl_jvh_db_collection`";
    $strQuery .= " LEFT JOIN (SELECT    MAX(`id`) `max_id`, `pid` FROM `tl_jvh_db_collection_status_log` GROUP BY  `pid`) `recent_status` ON (`recent_status`.`pid` = `tl_jvh_db_collection`.`id`)";
    $strQuery .= " LEFT JOIN `tl_jvh_db_collection_status_log` ON (`tl_jvh_db_collection_status_log`.`id` = `recent_status`.`max_id`)";
    $strQuery .= " INNER JOIN `tl_jvh_db_puzzel_product` ON `tl_jvh_db_collection`.`puzzel_product` = `tl_jvh_db_puzzel_product`.`id`";
    $strQuery .= " LEFT JOIN `tl_jvh_db_series` ON `tl_jvh_db_series`.`id` = `tl_jvh_db_puzzel_product`.`serie`";
    $strQuery .= " LEFT JOIN `tl_jvh_db_uitgever` ON `tl_jvh_db_uitgever`.`id` = `tl_jvh_db_puzzel_product`.`uitgever`";
    $strQuery .= " LEFT JOIN `tl_jvh_db_doos` ON `tl_jvh_db_doos`.`id` = `tl_jvh_db_puzzel_product`.`doos`";
    $strQuery .= " WHERE `tl_jvh_db_puzzel_product`.`visible` = '1'";
    $strQuery .= " AND `tl_jvh_db_collection`.`member` = ? AND `tl_jvh_db_collection`.`id` = ?";
    $arrValues[] = $this->User->id;
    $arrValues[] = $id;

    $objResultStmt = Database::getInstance()->prepare($strQuery);
    $objResult = $objResultStmt->execute(...$arrValues);
    return $objResult->fetchAssoc();
  }

  protected function getStatusLog(int $id) {
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
      $currentUrl .= '?id='.$id;
    }

    /** @var Connection $connections */
    $connection = System::getContainer()->get('database_connection');
    $objResult = $connection->executeQuery("SELECT * FROM `tl_jvh_db_collection_status_log` WHERE `pid` = ? ORDER BY `pid`, `tstamp` DESC", [$id]);
    $return = [];
    foreach ($objResult->fetchAllAssociative() as $row) {
      if (isset($GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$row['status']])) {
        $row['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][$row['status']];
      } else {
        $row['status'] = $GLOBALS['TL_LANG']['tl_jvh_db_collection_status_log']['collection_status'][0];
      }
      $row['tstamp'] = date('d-m-Y', $row['tstamp']);
      $row['delete_status_url'] = $currentUrl . '&delete_status_log=' . $row['id'];
      $return[] = $row;
    }
    return $return;
  }

}
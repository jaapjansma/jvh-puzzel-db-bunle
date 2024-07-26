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
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\Module;
use Contao\Search;
use Contao\StringUtil;
use Contao\System;
use JvH\JvHPuzzelDbBundle\Model\DoosModel;
use JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel;
use JvH\JvHPuzzelDbBundle\Model\SerieModel;
use JvH\JvHPuzzelDbBundle\Model\UitgeverModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PuzzelProductLijst extends Module
{

  protected $strTemplate = 'mod_jvh_db_puzzel_product_lijst';

  public function generate()
  {
    $request = System::getContainer()->get('request_stack')->getCurrentRequest();
    if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
      $objTemplate = new BackendTemplate('be_wildcard');
      $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['jvh_db_puzzel_product_lijst'][0] . ' ###';
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
    if (\is_array(Input::get('keywords')))
    {
      throw new BadRequestHttpException('Expected string, got array');
    }
    $strKeywords = trim(Input::get('keywords'));
    $this->Template->uniqueId = $this->id;
    $this->Template->keyword = StringUtil::specialchars($strKeywords);
    $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
    $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);

    $objTarget = $this->objModel->getRelated('jumpTo');
    if (empty($objTarget)) {
      $objTarget = $objPage;
    }
    $arrResult = $this->search($strKeywords, 3);
    foreach($arrResult as $index => $item) {
      $arrResult[$index]['link'] = $objTarget->getFrontendUrl('/'.$item['id']);
      $arrResult[$index]['serie'] = SerieModel::getLabel($arrResult[$index]['serie']);
      $arrResult[$index]['doos'] = DoosModel::getLabel($arrResult[$index]['doos']);
      $arrResult[$index]['uitgever'] = UitgeverModel::getNaam($arrResult[$index]['uitgever']);
      $arrResult[$index]['release_date'] = Date::parse($objPage->dateFormat, $arrResult[$index]['release_date']);
      $arrResult[$index]['stukjes'] = PuzzelProductModel::getStukjes($arrResult[$index]['puzzel_formaat']);

      $arrResult[$index]['figures'] = PuzzelProductModel::generateFigureElements($item['multiSRC'], $item['orderSRC'], $item['id'], $this->imgSize, (bool)$this->fullsize);

    }
    $count = count($arrResult);
    $this->Template->count = $count;
    $this->Template->keywords = $strKeywords;
    $this->Template->results = $arrResult;
  }

  protected function search(string $strKeywords, int $intMinlength): array
  {
    // Clean the keywords
    $strKeywords = StringUtil::decodeEntities($strKeywords);

    // Split keywords
    $arrChunks = array();
    preg_match_all('/"[^"]+"|\S+/', $strKeywords, $arrChunks);

    $arrPhrases = array();
    $arrKeywords = array();
    $arrWildcards = array();

    foreach (array_unique($arrChunks[0]) as $strKeyword) {
      if (($strKeyword[0] === '*' || substr($strKeyword, -1) === '*') && \strlen($strKeyword) > 1) {
        $arrWildcardWords = Search::splitIntoWords(trim($strKeyword, '*'), $GLOBALS['TL_LANGUAGE']);

        foreach ($arrWildcardWords as $intIndex => $strWord) {
          if ($intIndex === 0 && $strKeyword[0] === '*') {
            $strWord = '%' . $strWord;
          }

          if ($intIndex === \count($arrWildcardWords) - 1 && substr($strKeyword, -1) === '*') {
            $strWord .= '%';
          }

          if ($strWord[0] === '%' || substr($strWord, -1) === '%') {
            $arrWildcards[] = $strWord;
          } else {
            $arrKeywords[] = $strWord;
          }
        }

        continue;
      }

      switch (substr($strKeyword, 0, 1)) {
        // Phrases
        case '"':
          if ($strKeyword = trim(substr($strKeyword, 1, -1))) {
            $arrPhrases[] = $strKeyword;
          }
          break;

        // Normal keywords
        default:
          foreach (Search::splitIntoWords($strKeyword, $GLOBALS['TL_LANGUAGE']) as $strWord) {
            if ($intMinlength > 0 && \strlen($strWord) < $intMinlength) {
              continue;
            }

            $arrKeywords[] = '%' . $strWord . '%';
          }
          break;
      }
    }

    $strQuery = "SELECT `tl_jvh_db_puzzel_product`.`id`, `tl_jvh_db_puzzel_product`.`naam_nl`, `tl_jvh_db_puzzel_product`.`naam_en`, `tl_jvh_db_puzzel_product`.`product_number`, `tl_jvh_db_puzzel_product`.`product_id`, `tl_jvh_db_puzzel_product`.`multiSRC`, `tl_jvh_db_puzzel_product`.`orderSRC`, `tl_jvh_db_puzzel_product`.`release_date`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`serie`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`doos`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`uitgever`,";
    $strQuery .= "`tl_jvh_db_puzzel_product`.`puzzel_formaat`";
    $strQuery .= " FROM `tl_jvh_db_puzzel_product`";
    $strQuery .= " INNER JOIN `tl_jvh_db_series` ON `tl_jvh_db_series`.`id` = `tl_jvh_db_puzzel_product`.`serie`";
    $strQuery .= " INNER JOIN `tl_jvh_db_uitgever` ON `tl_jvh_db_uitgever`.`id` = `tl_jvh_db_puzzel_product`.`uitgever`";
    $strQuery .= " INNER JOIN `tl_jvh_db_doos` ON `tl_jvh_db_doos`.`id` = `tl_jvh_db_puzzel_product`.`doos`";
    $strQuery .= " WHERE `tl_jvh_db_puzzel_product`.`visible` = '1'";

    $arrValues = array();
    $arrAllKeywords = array();
    $fields = [
      "`tl_jvh_db_puzzel_product`.`naam_nl`",
      "`tl_jvh_db_puzzel_product`.`naam_en`",
      "`tl_jvh_db_puzzel_product`.`product_number`",
      "`tl_jvh_db_puzzel_product`.`opmerkingen_nl`",
      "`tl_jvh_db_puzzel_product`.`opmerkingen_en`",
      "`tl_jvh_db_series`.`label_en`",
      "`tl_jvh_db_series`.`label_nl`",
      "`tl_jvh_db_uitgever`.`naam`",
      "`tl_jvh_db_doos`.`label_en`",
      "`tl_jvh_db_doos`.`label_nl`",
    ];

    // Get wildcards
    foreach ($arrWildcards as $strKeyword)
    {
      foreach($fields as $field) {
        $arrAllKeywords[] = $field . ' LIKE ?';
        $arrValues[] = $strKeyword;
      }
    }

    // Get keywords
    foreach ($arrKeywords as $strKeyword)
    {
      foreach($fields as $field) {
        $arrAllKeywords[] = $field . ' LIKE ?';
        $arrValues[] = $strKeyword;
      }
    }

    // Get keywords from phrases
    foreach ($arrPhrases as $strPhrase)
    {
      foreach (Search::splitIntoWords($strPhrase, $GLOBALS['TL_LANGUAGE']) as $strKeyword)
      {
        foreach($fields as $field) {
          $arrAllKeywords[] = $field . '=?';
          $arrValues[] = $strKeyword;
        }
      }
    }
    $formaatIds = $this->searchPuzzelFormaten($arrWildcards, $arrKeywords, $arrPhrases);
    foreach($formaatIds as $formaatId) {
      $arrAllKeywords[] = "`puzzel_formaat` LIKE ?";
      $arrValues[] = '%;i:' . $formaatId . ';}%';
      $arrAllKeywords[] = "`puzzel_formaat` LIKE ?";
      $arrValues[] = '%;i:' . $formaatId . ';i%';
    }

    if (count($arrAllKeywords)) {
      $strQuery .= "AND (" . implode(" OR ", $arrAllKeywords) . ")";
    }

    $objResultStmt = Database::getInstance()->prepare($strQuery);
    $objResult = $objResultStmt->execute(...$arrValues);
    return $objResult->fetchAllAssoc();
  }

  protected function searchPuzzelFormaten(array $arrWildcards, array $arrKeywords, array $arrPhrases): array
  {
    $strQuery = "SELECT `tl_jvh_db_puzzel_formaat`.`id`";
    $strQuery .= " FROM `tl_jvh_db_puzzel_formaat`";
    $strQuery .= " INNER JOIN `tl_jvh_db_stukjes` ON `tl_jvh_db_stukjes`.`id` = `tl_jvh_db_puzzel_formaat`.`stukjes`";

    $arrValues = array();
    $arrAllKeywords = array();
    $fields = [
      "`tl_jvh_db_stukjes`.`label`",
    ];

    // Get wildcards
    foreach ($arrWildcards as $strKeyword)
    {
      foreach($fields as $field) {
        $arrAllKeywords[] = $field . ' LIKE ?';
        $arrValues[] = $strKeyword;
      }
    }

    // Get keywords
    foreach ($arrKeywords as $strKeyword)
    {
      foreach($fields as $field) {
        $arrAllKeywords[] = $field . ' LIKE ?';
        $arrValues[] = $strKeyword;
      }
    }

    // Get keywords from phrases
    foreach ($arrPhrases as $strPhrase)
    {
      foreach (Search::splitIntoWords($strPhrase, $GLOBALS['TL_LANGUAGE']) as $strKeyword)
      {
        foreach($fields as $field) {
          $arrAllKeywords[] = $field . '=?';
          $arrValues[] = $strKeyword;
        }
      }
    }

    if (count($arrAllKeywords)) {
      $strQuery .= "AND (" . implode(" OR ", $arrAllKeywords) . ")";
    }

    $objResultStmt = Database::getInstance()->prepare($strQuery);
    $objResult = $objResultStmt->execute(...$arrValues);
    $return = [];
    foreach($objResult->fetchAllAssoc() as $result) {
      $return[] = $result['id'];
    }
    return $return;
  }

}
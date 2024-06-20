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
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\Search;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PuzzelPlaatLijst extends Module {

  protected $strTemplate = 'mod_jvh_db_puzzel_plaat_lijst';

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
    return parent::generate();
  }

  /**
   * Compile the current element
   */
  protected function compile()
  {
    System::loadLanguageFile('tl_jvh_db_puzzel_plaat');
    if (\is_array(Input::get('keywords')))
    {
      throw new BadRequestHttpException('Expected string, got array');
    }
    $strKeywords = trim(Input::get('keywords'));
    $this->Template->uniqueId = $this->id;
    $this->Template->keyword = StringUtil::specialchars($strKeywords);
    $this->Template->keywordLabel = $GLOBALS['TL_LANG']['MSC']['keywords'];
    $this->Template->search = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['searchLabel']);

    // Redirect page
    if (($objTarget = $this->objModel->getRelated('jumpTo')) instanceof PageModel)
    {
      /** @var PageModel $objTarget */
      $this->Template->action = $objTarget->getFrontendUrl();
    }
    $arrResult = $this->search($strKeywords, 3);
    $count = count($arrResult);
    $this->Template->count = $count;
    $this->Template->keywords = $strKeywords;
    $this->Template->results = $arrResult;
  }

  protected function search(string $strKeywords, int $intMinlength): array {
    // Clean the keywords
    $strKeywords = StringUtil::decodeEntities($strKeywords);

    // Split keywords
    $arrChunks = array();
    preg_match_all('/"[^"]+"|\S+/', $strKeywords, $arrChunks);

    $arrPhrases = array();
    $arrKeywords = array();
    $arrWildcards = array();

    foreach (array_unique($arrChunks[0]) as $strKeyword)
    {
      if (($strKeyword[0] === '*' || substr($strKeyword, -1) === '*') && \strlen($strKeyword) > 1)
      {
        $arrWildcardWords = Search::splitIntoWords(trim($strKeyword, '*'), $GLOBALS['TL_LANGUAGE']);

        foreach ($arrWildcardWords as $intIndex => $strWord)
        {
          if ($intIndex === 0 && $strKeyword[0] === '*')
          {
            $strWord = '%' . $strWord;
          }

          if ($intIndex === \count($arrWildcardWords) - 1 && substr($strKeyword, -1) === '*')
          {
            $strWord .= '%';
          }

          if ($strWord[0] === '%' || substr($strWord, -1) === '%')
          {
            $arrWildcards[] = $strWord;
          }
          else
          {
            $arrKeywords[] = $strWord;
          }
        }

        continue;
      }

      switch (substr($strKeyword, 0, 1))
      {
        // Phrases
        case '"':
          if ($strKeyword = trim(substr($strKeyword, 1, -1)))
          {
            $arrPhrases[] = $strKeyword;
          }
          break;

        // Normal keywords
        default:
          foreach (Search::splitIntoWords($strKeyword, $GLOBALS['TL_LANGUAGE']) as $strWord)
          {
            if ($intMinlength > 0 && \strlen($strWord) < $intMinlength)
            {
              continue;
            }

            $arrKeywords[] = '%'.$strWord. '%';
          }
          break;
      }
    }

    $strQuery = "SELECT `tl_jvh_db_puzzel_plaat`.`id`,`tl_jvh_db_puzzel_plaat`.`alias_nl`, `tl_jvh_db_puzzel_plaat`.`alias_en`, `tl_jvh_db_puzzel_plaat`.`naam_nl`, `tl_jvh_db_puzzel_plaat`.`naam_en`, `tl_jvh_db_puzzel_plaat`.`jaar_uitgifte`, `tl_jvh_db_tekenaar`.`voornaam`, `tl_jvh_db_tekenaar`.`achternaam`";
    $strQuery .= " FROM `tl_jvh_db_puzzel_plaat`";
    $strQuery .= " INNER JOIN `tl_jvh_db_tekenaar` ON `tl_jvh_db_tekenaar`.`id` = `tl_jvh_db_puzzel_plaat`.`tekenaar`";
    $strQuery .= " WHERE `tl_jvh_db_puzzel_plaat`.`visible` = '1' AND `tl_jvh_db_tekenaar`.`visible` = '1'";

    $arrValues = array();
    $arrAllKeywords = array();
    $fields = [
      "`tl_jvh_db_puzzel_plaat`.`naam_nl`",
      "`tl_jvh_db_puzzel_plaat`.`naam_en`",
      "`tl_jvh_db_puzzel_plaat`.`opmerkingen_nl`",
      "`tl_jvh_db_puzzel_plaat`.`opmerkingen_en`",
      "`tl_jvh_db_tekenaar`.`voornaam`",
      "`tl_jvh_db_tekenaar`.`achternaam`",
      "`tl_jvh_db_tekenaar`.`omschrijving`",
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
    return $objResult->fetchAllAssoc();
  }


}
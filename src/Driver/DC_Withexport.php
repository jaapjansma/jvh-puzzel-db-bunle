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

namespace JvH\JvHPuzzelDbBundle\Driver;

use Contao\ArrayUtil;
use Contao\Database;
use Contao\DC_Table;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DC_Withexport extends DC_Table {

  protected bool $exporting = false;

  public function isExporting(): bool
  {
    return $this->exporting;
  }

  protected function export()
  {
    $this->exporting = true;
    // Custom filter
    if (!empty($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter']) && \is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter']))
    {
      foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'] as $filter)
      {
        if (\is_string($filter))
        {
          $this->procedure[] = $filter;
        }
        else
        {
          $this->procedure[] = $filter[0];
          $this->values[] = $filter[1];
        }
      }
    }
    $this->panel();

    $table = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_TREE_EXTENDED ? $this->ptable : $this->strTable;
    $orderBy = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'] ?? array('id');
    $firstOrderBy = preg_replace('/\s+.*$/', '', $orderBy[0]);

    if (\is_array($this->orderBy) && !empty($this->orderBy[0]))
    {
      $orderBy = $this->orderBy;
      $firstOrderBy = $this->firstOrderBy;
    }

    // Check the default labels (see #509)
    $labelNew = $GLOBALS['TL_LANG'][$this->strTable]['new'] ?? $GLOBALS['TL_LANG']['DCA']['new'];

    $query = "SELECT * FROM " . $this->strTable;

    if (!empty($this->procedure))
    {
      $query .= " WHERE " . implode(' AND ', $this->procedure);
    }

    if (!empty($this->root) && \is_array($this->root))
    {
      $query .= (!empty($this->procedure) ? " AND " : " WHERE ") . "id IN(" . implode(',', array_map('\intval', $this->root)) . ")";
    }

    if (\is_array($orderBy) && $orderBy[0])
    {
      foreach ($orderBy as $k=>$v)
      {
        list($key, $direction) = explode(' ', $v, 2) + array(null, null);

        $orderBy[$k] = $key;

        // If there is no direction, check the global flag in sorting mode 1 or the field flag in all other sorting modes
        if (!$direction)
        {
          if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_SORTED && isset($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag']) && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['flag'] % 2) == 0)
          {
            $direction = 'DESC';
          }
          elseif (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag']) && ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag'] % 2) == 0)
          {
            $direction = 'DESC';
          }
        }

        if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['foreignKey']))
        {
          $chunks = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['foreignKey'], 2);
          $orderBy[$k] = "(SELECT " . Database::quoteIdentifier($chunks[1]) . " FROM " . $chunks[0] . " WHERE " . $chunks[0] . ".id=" . $this->strTable . "." . $key . ")";
        }

        if (\in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['flag'] ?? null, array(self::SORT_DAY_ASC, self::SORT_DAY_DESC, self::SORT_MONTH_ASC, self::SORT_MONTH_DESC, self::SORT_YEAR_ASC, self::SORT_YEAR_DESC)))
        {
          $orderBy[$k] = "CAST(" . $orderBy[$k] . " AS SIGNED)"; // see #5503
        }

        if ($direction)
        {
          $orderBy[$k] .= ' ' . $direction;
        }

        if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['eval']['findInSet'] ?? null)
        {
          if (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'] ?? null))
          {
            $strClass = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'][0];
            $strMethod = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'][1];

            $this->import($strClass);
            $keys = $this->$strClass->$strMethod($this);
          }
          elseif (\is_callable($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback'] ?? null))
          {
            $keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options_callback']($this);
          }
          else
          {
            $keys = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['options'] ?? array();
          }

          if (($GLOBALS['TL_DCA'][$this->strTable]['fields'][$key]['eval']['isAssociative'] ?? null) || ArrayUtil::isAssoc($keys))
          {
            $keys = array_keys($keys);
          }

          $orderBy[$k] = $this->Database->findInSet($v, $keys);
        }
      }

      if (($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] ?? null) == self::MODE_SORTED_PARENT)
      {
        $firstOrderBy = 'pid';
        $showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];

        $query .= " ORDER BY (SELECT " . Database::quoteIdentifier($showFields[0]) . " FROM " . $this->ptable . " WHERE " . $this->ptable . ".id=" . $this->strTable . ".pid), " . implode(', ', $orderBy) . ', id';

        // Set the foreignKey so that the label is translated
        if (!($GLOBALS['TL_DCA'][$table]['fields']['pid']['foreignKey'] ?? null))
        {
          $GLOBALS['TL_DCA'][$table]['fields']['pid']['foreignKey'] = $this->ptable . '.' . $showFields[0];
        }

        // Remove the parent field from label fields
        array_shift($showFields);
        $GLOBALS['TL_DCA'][$table]['list']['label']['fields'] = $showFields;
      }
      else
      {
        $query .= " ORDER BY " . implode(', ', $orderBy) . ', id';
      }
    }

    $objRowStmt = $this->Database->prepare($query);

    $objRow = $objRowStmt->execute($this->values);
    $result = $objRow->fetchAllAssoc();
    // Automatically add the "order by" field as last column if we do not have group headers
    if (($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['showColumns'] ?? null) && false !== ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['showFirstOrderBy'] ?? null))
    {
      $blnFound = false;

      // Extract the real key and compare it to $firstOrderBy
      foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['fields'] as $f)
      {
        if (strpos($f, ':') !== false)
        {
          list($f) = explode(':', $f, 2);
        }

        if ($firstOrderBy == $f)
        {
          $blnFound = true;
          break;
        }
      }

      if (!$blnFound)
      {
        $GLOBALS['TL_DCA'][$this->strTable]['list']['label']['fields'][] = $firstOrderBy;
      }
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $colNr = 1;
    $rowNr = 1;

    // Generate the table header if the "show columns" option is active
    if ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['showColumns'] ?? null)
    {
      foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['fields'] as $f)
      {
        if (strpos($f, ':') !== false)
        {
          list($f) = explode(':', $f, 2);
        }
        $sheet->setCellValue([$colNr, $rowNr], (\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$f]['label'] ?? null) ? $GLOBALS['TL_DCA'][$this->strTable]['fields'][$f]['label'][0] : ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$f]['label'] ?? $f)));
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($rowNr))->setAutoSize(TRUE);
        $sheet->getStyle([$colNr, $rowNr])->getFont()->setBold(TRUE);
        $sheet->getStyle([$colNr, $rowNr])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $colNr++;
      }
    }

    foreach ($result as $row)
    {
      $rowNr ++;
      $colNr = 1;
      $this->current[] = $row['id'];
      $label = $this->generateRecordLabel($row, $this->strTable);

      if (!\is_array($label))
      {
        $label = array($label);
      }

      // Show columns
      if ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['showColumns'] ?? null)
      {
        foreach ($label as $j=>$arg)
        {
          $field = $GLOBALS['TL_DCA'][$this->strTable]['list']['label']['fields'][$j] ?? null;

          if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey']))
          {
            if ($arg)
            {
              $key = explode('.', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['foreignKey'], 2);

              $reference = $this->Database
                ->prepare("SELECT " . Database::quoteIdentifier($key[1]) . " AS value FROM " . $key[0] . " WHERE id=?")
                ->limit(1)
                ->execute($arg);

              if ($reference->numRows)
              {
                $arg = $reference->value;
              }
            }

            $value = $arg ?: '-';
          }
          else
          {
            $value = (string) $arg !== '' ? $arg : '-';
          }
          $sheet->setCellValue([$colNr, $rowNr], $value);
          $colNr++;
        }
      }
    }

    $writer = new Xlsx($spreadsheet);
    $response =  new StreamedResponse(
      function () use ($writer) {
        $writer->save('php://output');
      }
    );
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment;filename="'.$this->strTable.'_'.date('ymd').'.xlsx"');
    $response->headers->set('Cache-Control','max-age=0');
    $response->send();
    exit();
  }

}
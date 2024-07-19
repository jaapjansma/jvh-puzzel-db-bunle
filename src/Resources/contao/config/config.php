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

if (!isset($GLOBALS['BE_MOD']['jvh_puzzel_db']) || !\is_array($GLOBALS['BE_MOD']['jvh_puzzel_db']))
{
  \Contao\ArrayUtil::arrayInsert($GLOBALS['BE_MOD'], 2, array('jvh_puzzel_db' => array()));
}

\Contao\ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['jvh_puzzel_db'], 0, array
(
  'tl_jvh_db_puzzel_plaat' => array
  (
    'tables'            => array('tl_jvh_db_puzzel_plaat', 'tl_jvh_db_tekenaar'),
  ),
  'tl_jvh_db_puzzel_product' => array
  (
    'tables'            => array('tl_jvh_db_puzzel_product', 'tl_jvh_db_puzzel_formaat', 'tl_jvh_db_doos', 'tl_jvh_db_series', 'tl_jvh_db_stukjes', 'tl_jvh_db_uitgever'),
  ),
));

$GLOBALS['FE_MOD']['application']['jvh_db_puzzel_plaat_lijst'] = \JvH\JvHPuzzelDbBundle\Frontend\PuzzelPlaatLijst::class;
$GLOBALS['FE_MOD']['application']['jvh_db_puzzel_plaat_reader'] = \JvH\JvHPuzzelDbBundle\Frontend\PuzzelPlaatReader::class;

$GLOBALS['TL_MODELS']['tl_jvh_db_puzzel_plaat'] = \JvH\JvHPuzzelDbBundle\Model\PuzzelPlaatModel::class;
$GLOBALS['TL_MODELS']['tl_jvh_db_tekenaar'] = \JvH\JvHPuzzelDbBundle\Model\TekenaarModel::class;
$GLOBALS['TL_MODELS']['tl_jvh_db_puzzel_product'] = \JvH\JvHPuzzelDbBundle\Model\PuzzelProductModel::class;
$GLOBALS['TL_MODELS']['tl_jvh_db_series'] = \JvH\JvHPuzzelDbBundle\Model\SerieModel::class;
$GLOBALS['TL_MODELS']['tl_jvh_db_stukjes'] = \JvH\JvHPuzzelDbBundle\Model\StukjesModel::class;
$GLOBALS['TL_MODELS']['tl_jvh_db_uitgever'] = \JvH\JvHPuzzelDbBundle\Model\UitgeverModel::class;
$GLOBALS['TL_MODELS']['tl_jvh_db_puzzel_formaat'] = \JvH\JvHPuzzelDbBundle\Model\PuzzelFormaatModel::class;
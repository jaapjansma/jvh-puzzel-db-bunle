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

\Contao\System::loadLanguageFile('tl_jvh_db_puzzel_formaat');

$GLOBALS['TL_DCA']['tl_jvh_db_puzzel_formaat'] = array
(
  // Config
  'config' => array
  (
    'dataContainer'               => 'Table',
    'sql' => array
    (
      'keys' => array
      (
        'id' => 'primary'
      )
    )
  ),

  // List
  'list' => array
  (
    'sorting' => array
    (
      'mode'                    => 1,
      'fields'                  => array('stukjes'),
      'flag'                    => 11,
      'panelLayout'             => 'filter,search,limit'
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('puzzel_plaat', 'stukjes'),
    ),
    'global_operations' => array
    (
      'tl_jvh_db_puzzel_product' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'back.svg',
        'href'                => 'table=tl_jvh_db_puzzel_product',
      ),
      'tl_jvh_db_stukjes' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'featured.svg',
        'href'                => 'table=tl_jvh_db_stukjes',
      ),
      'all' => array
      (
        'href'                => 'act=select',
        'class'               => 'header_edit_all',
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
      )
    ),
    'operations' => array
    (
      'edit' => array
      (
        'href'                => 'act=edit',
        'icon'                => 'edit.svg',
      ),
      'toggle' => array
      (
        'href'                => 'act=toggle&amp;field=visible',
        'icon'                => 'visible.svg',
        'button_callback'     => array('\JvH\JvHPuzzelDbBundle\DCA\PuzzelFormaat', 'toggleIcon')
      ),
      'delete' => array
      (
        'href'                => 'act=delete',
        'icon'                => 'delete.svg',
        'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['tl_jvh_db_puzzel_formaat']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
      ),
    )
  ),

  // Palettes
  'palettes' => array
  (
    'default'                     => 'puzzel_plaat;stukjes;visible'
  ),

  // Subpalettes
  'subpalettes' => array
  (
  ),

  // Fields
  'fields' => array
  (
    'id' => array
    (
      'sql'                     => "int(10) unsigned NOT NULL auto_increment"
    ),
    'tstamp' => array
    (
      'sql'                     => "int(10) unsigned NOT NULL default 0"
    ),
    'puzzel_plaat' => array
    (
      'filter'                  => true,
      'search'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'relation'                => ['table' => 'tl_jvh_db_puzzel_plaat', 'type' => 'belongsToMany'],
      'foreignKey'              => 'tl_jvh_db_puzzel_plaat.concat(naam_nl, " / ", naam_en)',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'stukjes' => array
    (
      'filter'                  => true,
      'search'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'foreignKey'              => 'tl_jvh_db_stukjes.label',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'visible' => array
    (
      'toggle'                  => true,
      'filter'                  => true,
      'inputType'               => 'checkbox',
      'sql'                     => "char(1) COLLATE ascii_bin NOT NULL default '1'"
    ),
  )
);
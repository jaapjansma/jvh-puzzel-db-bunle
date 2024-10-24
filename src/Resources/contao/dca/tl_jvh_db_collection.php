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

\Contao\System::loadLanguageFile('tl_jvh_db_collection');

$GLOBALS['TL_DCA']['tl_jvh_db_collection'] = array
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
      'fields'                  => array(),
      'flag'                    => 11,
      'panelLayout'             => 'search,limit,filter',
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array(),
    ),
    'global_operations' => array
    (
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
      'delete' => array
      (
        'href'                => 'act=delete',
        'icon'                => 'delete.svg',
        'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['tl_jvh_db_collection']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
      ),
    )
  ),

  // Palettes
  'palettes' => array
  (
    'default'                     => 'member,puzzel_product'
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
    'member' => array
    (
      'filter'                  => true,
      'search'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'relation'                => ['table' => 'tl_member', 'type' => 'belongsToMany'],
      'foreignKey'              => 'tl_member.username',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'puzzel_product' => array
    (
      'filter'                  => true,
      'search'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'relation'                => ['table' => 'tl_jvh_db_puzzel_product', 'type' => 'belongsToMany'],
      'foreignKey'              => 'tl_jvh_db_puzzel_product.concat(naam_nl, " / ", naam_en)',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'collection' => array
    (
      'filter'                  => true,
      'search'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'options'                 => ['1' => 'collection', '2' => 'wishlist'],
      'reference'               => &$GLOBALS['TL_LANG']['tl_jvh_db_collection']['collection_options'],
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'condition' => array
    (
      'filter'                  => true,
      'search'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'options'                 => [],
      'reference'               => &$GLOBALS['TL_LANG']['tl_jvh_db_collection']['condition_options'],
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'comment' => array
    (
      'exclude'                 => true,
      'search'                  => true,
      'inputType'               => 'textarea',
      'eval'                    => array(),
      'sql'                     => "text NULL"
    ),
  )
);
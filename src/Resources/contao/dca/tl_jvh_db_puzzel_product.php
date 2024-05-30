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

\Contao\System::loadLanguageFile('tl_jvh_db_puzzel_product');

$GLOBALS['TL_DCA']['tl_jvh_db_puzzel_product'] = array
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
      'fields'                  => array('ean', 'naam_nl', 'naam_en'),
      'flag'                    => 11,
      'panelLayout'             => 'ean,naam_nl,naam_en,search,limit'
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('ean', 'naam_nl', 'naam_en', 'stukjes', 'serie', 'doos', 'uitgever'),
    ),
    'global_operations' => array
    (
      'tl_jvh_db_stukjes' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'featured.svg',
        'href'                => 'table=tl_jvh_db_stukjes',
      ),
      'tl_jvh_db_uitgever' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'db.svg',
        'href'                => 'table=tl_jvh_db_uitgever',
      ),
      'tl_jvh_db_doos' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'folderC.svg',
        'href'                => 'table=tl_jvh_db_doos',
      ),
      'tl_jvh_db_series' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'tablewizard.svg',
        'href'                => 'table=tl_jvh_db_series',
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
        'button_callback'     => array('\JvH\JvHPuzzelDbBundle\DCA\PuzzelProduct', 'toggleIcon')
      ),
      'delete' => array
      (
        'href'                => 'act=delete',
        'icon'                => 'delete.svg',
        'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['tl_jvh_db_puzzel_product']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
      ),
    )
  ),

  // Palettes
  'palettes' => array
  (
    'default'                     => 'naam_nl,naam_en;ean;singleSRC;puzzel_plaat;stukjes;serie;uitgever;release_date;doos;opmerkingen;opmerkingen_intern;visible'
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
    'naam_nl' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'naam_en' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'ean' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'singleSRC' => array
    (
      'exclude'                 => true,
      'inputType'               => 'fileTree',
      'eval'                    => array('filesOnly'=>true, 'fieldType'=>'radio', 'mandatory'=>false, 'tl_class'=>'clr'),
      'sql'                     => "binary(16) NULL"
    ),
    'puzzel_plaat' => array
    (
      'filter'                  => true,
      'inputType'               => 'picker',
      'eval'                    => array('multiple'=>true),
      'relation'                => ['table' => 'tl_jvh_db_puzzel_plaat'],
      'sql'                     => "blob NULL",
      'default'                 => '0',
    ),
    'stukjes' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'foreignKey'              => 'tl_jvh_db_stukjes.label',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'serie' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'foreignKey'              => 'tl_jvh_db_series.label',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'uitgever' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'foreignKey'              => 'tl_jvh_db_uitgever.naam',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'doos' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('includeBlankOption'=>true),
      'foreignKey'              => 'tl_jvh_db_doos.label',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'release_date' => array
    (
      'exclude'                 => true,
      'inputType'               => 'text',
      'eval'                    => array('rgxp'=>'date', 'datepicker'=>true, 'tl_class'=>'w50 wizard'),
      'sql'                     => "varchar(10) COLLATE ascii_bin NOT NULL default ''"
    ),
    'opmerkingen' => array
    (
      'search'                  => true,
      'inputType'               => 'textarea',
      'eval'                    => array('rte'=>'tinyMCE'),
      'sql'                     => "mediumtext NULL"
    ),
    'opmerkingen_intern' => array
    (
      'search'                  => true,
      'inputType'               => 'textarea',
      'eval'                    => array('rte'=>'tinyMCE'),
      'sql'                     => "mediumtext NULL"
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
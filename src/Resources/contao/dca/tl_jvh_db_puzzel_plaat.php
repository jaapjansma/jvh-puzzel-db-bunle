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

\Contao\System::loadLanguageFile('tl_jvh_db_puzzel_plaat');

$GLOBALS['TL_DCA']['tl_jvh_db_puzzel_plaat'] = array
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
      'fields'                  => array('naam_nl', 'naam_en'),
      'flag'                    => 11,
      'panelLayout'             => 'sort,filter,search,limit'
    ),
    'label' => array
    (
      'showColumns'             => true,
      'fields'                  => array('naam_nl', 'naam_en', 'tekenaar'),
    ),
    'global_operations' => array
    (
      'tl_jvh_db_tekenaars' => array
      (
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="t"',
        'icon'                => 'pickcolor.svg',
        'href'                => 'table=tl_jvh_db_tekenaar',
      ),
      'all' => array
      (
        'href'                => 'act=select',
        'class'               => 'header_edit_all',
        'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
      ),
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
        'button_callback'     => array('\JvH\JvHPuzzelDbBundle\DCA\PuzzelPlaat', 'toggleIcon')
      ),
      'delete' => array
      (
        'href'                => 'act=delete',
        'icon'                => 'delete.svg',
        'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['tl_jvh_db_puzzel_plaat']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
      ),
    )
  ),

  // Palettes
  'palettes' => array
  (
    'default'                     => 'naam_nl,naam_en;alias_nl,alias_en;tekenaar;singleSRC;jaar_uitgifte;opmerkingen_nl,opmerkingen_en;opmerkingen_intern;visible'
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
    'alias_nl' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('rgxp'=>'alias', 'doNotCopy'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'save_callback' => array
      (
        array('\JvH\JvHPuzzelDbBundle\DCA\PuzzelPlaat', 'generateAliasNL')
      ),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'alias_en' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('rgxp'=>'alias', 'doNotCopy'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'save_callback' => array
      (
        array('\JvH\JvHPuzzelDbBundle\DCA\PuzzelPlaat', 'generateAliasEN')
      ),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'naam_nl' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'naam_en' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
      'sql'                     => "varchar(255) NOT NULL default ''"
    ),
    'singleSRC' => array
    (
      'exclude'                 => true,
      'inputType'               => 'fileTree',
      'eval'                    => array('filesOnly'=>true, 'fieldType'=>'radio', 'mandatory'=>false, 'tl_class'=>'clr'),
      'sql'                     => "binary(16) NULL"
    ),
    'jaar_uitgifte' => array
    (
      'search'                  => true,
      'inputType'               => 'text',
      'eval'                    => array('mandatory'=>true, 'maxlength'=>4, 'tl_class'=>'w50'),
      'sql'                     => "varchar(4) NOT NULL default ''"
    ),
    'tekenaar' => array
    (
      'filter'                  => true,
      'inputType'               => 'select',
      'eval'                    => array('mandatory' => true),
      'foreignKey'              => 'tl_jvh_db_tekenaar.CONCAT(voornaam,\' \',achternaam)',
      'sql'                     => "int(10) unsigned NOT NULL default 0",
      'default'                 => '0',
    ),
    'opmerkingen_nl' => array
    (
      'search'                  => true,
      'inputType'               => 'textarea',
      'eval'                    => array('rte'=>'tinyMCE'),
      'sql'                     => "mediumtext NULL"
    ),
    'opmerkingen_en' => array
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
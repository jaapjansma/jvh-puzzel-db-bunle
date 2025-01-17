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

namespace JvH\JvHPuzzelDbBundle\Event;

use Contao\DataContainer;

class LabelCallback {

  const EVENT_NAME = 'jvh.puzzel.db.bundle.collection.dca.label';

  public array $row;

  public string $label;

  public DataContainer $dc;

  public array $labels;
  public function __construct(array $row, string $label, DataContainer $dc, array $labels) {
    $this->row = $row;
    $this->label = $label;
    $this->dc = $dc;
    $this->labels = $labels;
  }

}
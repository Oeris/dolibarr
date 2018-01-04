<?php
/* Copyright (C) 2007-2012  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2014-2016  Juanjo Menent       <jmenent@2byte.es>
 * Copyright (C) 2015       Florian Henry       <florian.henry@open-concept.pro>
 * Copyright (C) 2015       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2017       Neil Orley          <neil.orley@oeris.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */



// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productstockentrepot.class.php';

/**
 * Class ProductStockEntrepot
 *
 * Put here description of your class
 *
 * @see CommonObject
 */
class OerisProductStockEntrepot extends ProductStockEntrepot
{

	/**
	 * Get first available wharehouse id
	 *
	 * @param int    $fk_product Id product
	 *
	 * @return int <0 if KO, 0 if not found, >0 if OK
	 */
	public function getFirstAvailableWharehouse($fk_product)
	{
		if(empty($fk_product)) return -1;
		
		dol_syslog(__METHOD__, LOG_DEBUG);

		$sql  = 'SELECT';
		$sql .= ' ps.rowid,ps.tms,ps.fk_product,ps.fk_entrepot, ps.reel,ps.import_key';		
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product_stock as ps';
		$sql .= ' WHERE (ps.fk_product = '.$fk_product.') LIMIT 1';
		
		$resql = $this->db->query($sql);
		if ($resql) {
			$numrows = $this->db->num_rows($resql);
			if ($numrows) {
				$obj = $this->db->fetch_object($resql);
				$this->id = $obj->rowid;
				$this->tms = $this->db->jdate($obj->tms);
				$this->fk_product = $obj->fk_product;
				$this->fk_entrepot = $obj->fk_entrepot;
        $this->reel = $obj->reel;
				$this->import_key = $obj->import_key;
			}
			$this->db->free($resql);
      
			if ($numrows) {
				return 1;
			} else {
      
        	$sql  = 'SELECT';
      		$sql .= ' e.rowid,e.tms';		
      		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'entrepot as e LIMIT 1';
      		
      		$resql = $this->db->query($sql);
      		if ($resql) {
      			$numrows = $this->db->num_rows($resql);
      			if ($numrows) {
      				$obj = $this->db->fetch_object($resql);
      				$this->id = $obj->rowid;
      				$this->tms = $this->db->jdate($obj->tms);
      				$this->fk_product = $fk_product;
      				$this->fk_entrepot = $obj->rowid;
              $this->reel = 0;
      			}
      			$this->db->free($resql);
            
      			if ($numrows) {
      				return 1;
      			} else {
              return 0;
            }
			   }
      }
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . implode(',', $this->errors), LOG_ERR);

			return - 1;
		}
	}

}

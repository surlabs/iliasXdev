<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/
/**
* Class ilPaymentPrices
* 
* @author Stefan Meyer <smeyer@databay.de>
* @version $Id$
*
* @package ilias-core
*/
class ilPaymentPrices
{
	var $ilDB;

	var $pobject_id;
	var $unit_value;
	var $sub_unit_value;
	var $currency;
	var $duration;

	private $prices = array();
	
	function ilPaymentPrices($a_pobject_id = 0)
	{
		global $ilDB;

		$this->db =& $ilDB;

		$this->pobject_id = $a_pobject_id;

		$this->__read();
	}

	// SET GET
	function getPobjectId()
	{
		return $this->pobject_id;
	}

	function getPrices()
	{
		return $this->prices ? $this->prices : array();
	}
	function getPrice($a_price_id)
	{
		return $this->prices[$a_price_id] ? $this->prices[$a_price_id] : array();
	}

	// STATIC
	function _getPrice($a_price_id)
	{
		global $ilDB;

		$statement = $ilDB->prepare('
			SELECT * FROM payment_prices 
			WHERE price_id = ?',
			array('integer')
		);
		$data = array($a_price_id);
		$res = $ilDB->execute($statement, $data);
		
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$price['duration'] = $row->duration;
			$price['currency'] = $row->currency;
			$price['unit_value'] = $row->unit_value;
			$price['sub_unit_value'] = $row->sub_unit_value;
		}
		return count($price) ? $price : array();
	}

	function _countPrices($a_pobject_id)
	{
		global $ilDB;		
/*		$query = "SELECT count(price_id) FROM payment_prices ".
			"WHERE pobject_id = '".$a_pobject_id."'";

		$res = $this->db->query($query);
*/
		
		$statement = $ilDB->prepare('
			SELECT count(price_id) FROM payment_prices 
			WHERE pobject_id = ?',
			array('integer')
		);
		$data = array($a_pobject_id);
		$res = $ilDB->execute($statement, $data);
				
		$row = $res->fetchRow(DB_FETCHMODE_ARRAY);

		return ($row[0]);
	}

	function _getPriceString($a_price_id)
	{
		include_once './payment/classes/class.ilPaymentCurrency.php';
		
		global $lng;
		
		$price = ilPaymentPrices::_getPrice($a_price_id);
		
		return self::_formatPriceToString($price['unit_value'], $price['sub_unit_value']);		
	}
	
	public static function _formatPriceToString($unit_value, $subunit_value)
	{
		include_once './payment/classes/class.ilGeneralSettings.php';
		
		$genSet = new ilGeneralSettings();
		$unit_string = $genSet->get('currency_unit');
		
		$pr_str = number_format( ((int) $unit_value) . '.' . sprintf('%02d', ((int) $subunit_value)), 2, ',', '.');
		return $pr_str . ' ' . $unit_string;
	}
	
	public static function _formatPriceToFloat($unit_value, $subunit_value)
	{	
		return (float) number_format(((int) $unit_value).'.'.sprintf('%02d', ((int) $subunit_value)), 2, '.', '');
	}
	
	function _getPriceStringFromAmount($a_price)
	{
		include_once './payment/classes/class.ilPaymentCurrency.php';
		include_once './payment/classes/class.ilGeneralSettings.php';

		global $lng;

		$genSet = new ilGeneralSettings();
		$unit_string = $genSet->get("currency_unit");

		$pr_str = '';		

		$pr_str = number_format($a_price , 2, ",", ".");
		return $pr_str . " " . $unit_string;		
	}
	
	function _getPriceFromArray($a_price)
	{		
		return (float) (((int) $a_price["unit_value"]) . "." . sprintf("%02d", ((int) $a_price["sub_unit_value"])));
	}
			
	function _getTotalAmount($a_price_ids)
	{
		include_once './payment/classes/class.ilPaymentPrices.php';
#		include_once './payment/classes/class.ilPaymentCurrency.php';
		include_once './payment/classes/class.ilGeneralSettings.php';

		global $ilDB,$lng;

		$genSet = new ilGeneralSettings();
		$unit_string = $genSet->get("currency_unit");

		$amount = array();

		if (is_array($a_price_ids))
		{
			for ($i = 0; $i < count($a_price_ids); $i++)
			{
				$price_data = ilPaymentPrices::_getPrice($a_price_ids[$i]["id"]);

				$price = ((int) $price_data["unit_value"]) . "." . sprintf("%02d", ((int) $price_data["sub_unit_value"]));
				$amount[$a_price_ids[$i]["pay_method"]] += (float) $price;
			}
		}

		return $amount;

/*		foreach($a_price_ids as $id)
		{
			$price_data = ilPaymentPrices::_getPrice($id);

			$price_arr["$price_data[currency]"]['unit'] += (int) $price_data['unit_value'];
			$price_arr["$price_data[currency]"]['subunit'] += (int) $price_data['sub_unit_value'];
		}

		if(is_array($price_arr))
		{
			foreach($price_arr as $key => $value)
			{
				// CHECK cent bigger 100
				$value['unit'] += (int) ($value['subunit'] / 100);
				$value['subunit'] = (int) ($value['subunit'] % 100);

				$unit_string = $lng->txt('currency_'.ilPaymentCurrency::_getUnit($key));
				$subunit_string = $lng->txt('currency_'.ilPaymentCurrency::_getSubUnit($key));

				if((int) $value['unit'])
				{
					$pr_str .= $value['unit'].' '.$unit_string.' ';
				}
				if((int) $value['subunit'])
				{
					$pr_str .= $value['subunit'].' '.$subunit_string;
				}

				// in the moment only one price
				return $pr_str;
			}
		}
		return 0;*/
	}
		

	function setUnitValue($a_value = 0)
	{
		// substitute leading zeros with ''
		$this->unit_value = preg_replace('/^0+/','',$a_value);
	}
	function setSubUnitValue($a_value = 0)
	{
		$this->sub_unit_value = $a_value;
	}
	function setCurrency($a_currency_id)
	{
		$this->currency = $a_currency_id;
	}
	function setDuration($a_duration)
	{
		$this->duration = $a_duration;
	}

	function add()
	{
		$statement = $this->db->prepareManip('
			INSERT INTO payment_prices 
			SET pobject_id = ?,
				currency = ?,
				duration = ?,
				unit_value = ?,
				sub_unit_value = ?',
			array('integer', 'integer', 'integer', 'text', 'text')
		);
		
		$data = array(	$this->getPobjectId(),
						$this->__getCurrency(),
						$this->__getDuration(),
						$this->__getUnitValue(),
						$this->__getSubUnitValue()
		);
		
		$res = $this->db->execute($statement, $data);
		
		$this->__read();
		
		return true;
	}
	function update($a_price_id)
	{
		$statement = $this->db->prepareManip('
			UPDATE payment_prices SET
			currency = ?,
			duration = ?,
			unit_value = ?,
			sub_unit_value = ?',
			array('integer', 'integer', 'integer', 'text', 'integer')
		);
		
		$data = array(	$this->__getCurrency(),
						$this->__getDuration(),
						$this->__getUnitValue(),
						$this->__getSubUnitValue(),
						$a_price_id
		);
		$res = $this->db->execute($statement, $data);
		$this->__read();

		return true;
	}
	function delete($a_price_id)
	{
		$statement = $this->db->prepareManip('
			DELETE FROM payment_prices
			WHERE price_id = ?',
			array('integer')
		);

		$data = array($a_price_id);
		
		$res = $this->db->execute($statement, $data);

		$this->__read();

		return true;
	}
	function deleteAllPrices()
	{
		$statement = $this->db->prepareManip('
			DELETE FROM payment_prices
			WHERE pobject_id = ?',
			array('integer')
		);

		$data = array($this->getPobjectId());
		
		$res = $this->db->execute($statement, $data);
		
		$this->__read();

		return true;
	}

	function validate()
	{
		$duration_valid = false;
		$price_valid = false;

		if(preg_match('/^[1-9][0-9]{0,1}$/',$this->__getDuration()))
		{
			$duration_valid = true;
		}
		
		if(preg_match('/^[1-9]\d{0,4}$/',$this->__getUnitValue()) and
		   preg_match('/^\d{0,2}$/',$this->__getSubUnitValue()))
		{
			$price_valid = true;
		}
		else if(preg_match('/^\d{0,5}$/',$this->__getUnitValue()) and
				preg_match('/[1-9]/',$this->__getSubUnitValue()))
		{
			return true;
		}
		return $duration_valid and $price_valid;
	}
	// STATIC
	function _priceExists($a_price_id,$a_pobject_id)
	{
		global $ilDB;

		$statement = $ilDB->prepare('
			SELECT * FROM payment_prices
			WHERE price_id = ?
			AND pobject_id = ?',
			array('integer', 'integer')
		);
		
		$data = array($a_price_id, $a_pobject_id);
		$res = $ilDB->execute($statement, $data);
		
		return $res->numRows() ? true : false;
	}


				  
	// PRIVATE
	function __getUnitValue()
	{
		return $this->unit_value;
	}
	function __getSubUnitValue()
	{
		return $this->sub_unit_value;
	}
	function __getCurrency()
	{
		return $this->currency;
	}
	function __getDuration()
	{
		return $this->duration;
	}

	function __read()
	{
		$this->prices = array();

		$statement = $this->db->prepare('
			SELECT * FROM payment_prices
			WHERE pobject_id = ?
			ORDER BY duration', array('integer')
		);
		$data  = array($this->getPobjectId());
		$res = $this->db->execute($statement, $data);
				
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$this->prices[$row->price_id]['pobject_id'] = $row->pobject_id;
			$this->prices[$row->price_id]['price_id'] = $row->price_id;
			$this->prices[$row->price_id]['currency'] = $row->currency;
			$this->prices[$row->price_id]['duration'] = $row->duration;
			$this->prices[$row->price_id]['unit_value'] = $row->unit_value;
			$this->prices[$row->price_id]['sub_unit_value'] = $row->sub_unit_value;
		}
	}
	
	public function getNumberOfPrices()
	{
		return count($this->prices);
	}
	
	public function getLowestPrice()
	{				
		$lowest_price_id = 0;
		$lowest_price = 0;

		foreach ($this->prices as $price_id => $data)
		{
			$current_price = self::_formatPriceToFloat($data['unit_value'], $data['sub_unit_value']);

			if($lowest_price  == 0|| 
			   $lowest_price > (float)$current_price)
			{
				$lowest_price = (float)$current_price;
				$lowest_price_id = $price_id;
			}
		}
		
		return is_array($this->prices[$lowest_price_id]) ? $this->prices[$lowest_price_id] : array();
	}
}
?>
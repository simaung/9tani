<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Merchant_model extends Base_Model {

  public function __construct()
  {
    parent::__construct();
	}

  public function read($params = array())
	{
    if ( ! empty($params['latitude']) && ! empty($params['longitude']))
		{
      $latitude = $this->sanitize($this->conn['main'], $params['latitude']);
      $longitude = $this->sanitize($this->conn['main'], $params['longitude']);
      unset($params['latitude']);
      unset($params['longitude']);
		}

		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['merchant']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['merchant']);
    $limit_query    = $this->build_limit($this->conn['main'], $params);

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");

		if ( ! empty($latitude) && ! empty($longitude))
		{
			$sql = "SELECT
					`".$this->tables['merchant']."`.*,
					SHA1(CONCAT(`".$this->tables['merchant']."`.`id`, '".$this->config->item('encryption_key')."')) AS `id`,
					(SELECT GROUP_CONCAT(msg.`shipping_code` SEPARATOR ',') FROM `".$this->tables['merchant_to_shipping_gateway']."` msg WHERE msg.`merchant_id` = `".$this->tables['merchant']."`.`id`) AS `courier`,
					(111.111
					* DEGREES(ACOS(COS(RADIANS(`latitude`))
					* COS(RADIANS(".$latitude."))
					* COS(RADIANS(`longitude` - ".$longitude.")) + SIN(RADIANS(`latitude`))
					* SIN(RADIANS(".$latitude."))))) AS `distance`
					FROM `".$this->tables['merchant']."`" . $cond_query . " HAVING `distance` < 20 ORDER BY `distance` ASC " . $limit_query;
		}
		else
		{
			$sql = "SELECT
				`".$this->tables['merchant']."`.*,
				SHA1(CONCAT(`".$this->tables['merchant']."`.`id`, '".$this->config->item('encryption_key')."')) AS `id`,
				(SELECT GROUP_CONCAT(msg.`shipping_code` SEPARATOR ',') FROM `".$this->tables['merchant_to_shipping_gateway']."` msg WHERE msg.`merchant_id` = `".$this->tables['merchant']."`.`id`) AS `courier`
				FROM `".$this->tables['merchant']."`" . $cond_query . $order_query . $limit_query;
		}

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query)
		{
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row)
			{
				// Reconcile result
				if ( ! empty($row['image']))
				{
					$row['image'] = $this->config->item('storage_url') . 'merchant/' . $row['image'];
				}

				$row['courier'] = explode(',', $row['courier']);

				// Assign row to data
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['merchant'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['merchant']);

			// SET RESPONSE data
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $data,
				'summary' => $summary
			));
		}
		else
		{
			// SET RESPONSE data
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}

	public function total($params = array())
  {
		return $this->count_rows($this->conn['main'], $this->tables['merchant'], $params);
  }
}

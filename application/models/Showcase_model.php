<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Showcase_model extends Base_Model {

  public function __construct()
  {
    parent::__construct();
	}

  public function read($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['showcase']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['showcase']);
    $limit_query    = $this->build_limit($this->conn['main'], $params);

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`".$this->tables['showcase']."`.*,
					SHA1(CONCAT(`".$this->tables['showcase']."`.`id`, '".$this->config->item('encryption_key')."')) AS `id`,
					SHA1(CONCAT(`".$this->tables['showcase']."`.`merchant_id`, '".$this->config->item('encryption_key')."')) AS `merchant_id`,
					SHA1(CONCAT(`".$this->tables['showcase']."`.`created_by`, '".$this->config->item('encryption_key')."')) AS `created_by`,
					SHA1(CONCAT(`".$this->tables['showcase']."`.`modified_by`, '".$this->config->item('encryption_key')."')) AS `modified_by`,
					(SELECT COALESCE(COUNT(p.`id`), 0) FROM `".$this->tables['product']."` p WHERE `id` IN (SELECT ps.`product_id` FROM `".$this->tables['product_to_showcase']."` ps WHERE ps.`showcase_id` = `".$this->tables['showcase']."`.`id`)) AS `total_product`
					FROM `".$this->tables['showcase']."`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query)
		{
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row)
			{
				// Assign row to data
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['showcase'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['showcase']);

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
		return $this->count_rows($this->conn['main'], $this->tables['showcase'], $params);
  }
}

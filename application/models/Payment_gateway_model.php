<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_gateway_model extends Base_Model {

  public function __construct()
  {
    parent::__construct();
  }

  public function read($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['payment_gateway']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['payment_gateway']);
    $limit_query    = $this->build_limit($this->conn['main'], $params);

		// SET the QUERY
		$sql = "SELECT
					`".$this->tables['payment_gateway']."`.*
					FROM `".$this->tables['payment_gateway']."`" . $cond_query . $order_query . $limit_query;

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
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['payment_gateway'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['payment_gateway']);

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
		return $this->count_rows($this->conn['main'], $this->tables['payment_gateway'], $params);
  }
}

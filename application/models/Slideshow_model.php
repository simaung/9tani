<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Slideshow_model extends Base_Model {

  public function __construct()
  {
    parent::__construct();
  }

  public function read($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['slideshow']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['slideshow']);
    $limit_query    = $this->build_limit($this->conn['main'], $params);

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`".$this->tables['slideshow']."`.*,
					SHA1(CONCAT(`".$this->tables['slideshow']."`.`id`, '".$this->config->item('encryption_key')."')) AS `id`
					FROM `".$this->tables['slideshow']."`" . $cond_query . $order_query . $limit_query;

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
					$row['image'] = $this->config->item('storage_url') . 'slideshow/' . $row['image'];
				}

				// Assign row to data
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['slideshow'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['slideshow']);

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
		return $this->count_rows($this->conn['main'], $this->tables['slideshow'], $params);
  }
}

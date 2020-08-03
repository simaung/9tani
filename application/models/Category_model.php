<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Category_model extends Base_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	public function read($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['category']);
		$order_query    = $this->build_order($this->conn['main'], $params, $this->tables['category']);
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`" . $this->tables['category'] . "`.*,
					SHA1(CONCAT(`" . $this->tables['category'] . "`.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
					SHA1(CONCAT(`" . $this->tables['category'] . "`.`parent_id`, '" . $this->config->item('encryption_key') . "')) AS `parent_id`,
					(SELECT COUNT(c.`id`) FROM `" . $this->tables['category'] . "` c WHERE c.`parent_id` = `" . $this->tables['category'] . "`.`id`) AS `count_childs`
					FROM `" . $this->tables['category'] . "`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query) {
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row) {
				// Reconcile result
				if (!empty($row['icon'])) {
					$row['icon'] = $this->config->item('storage_url') . 'category/' . $row['icon'];
				}

				$row['childs'] = array();
				if (!empty($row['count_childs'])) {
					$child_params['ENCRYPTED::parent_id'] = $row['id'];
					$child_params['length'] = 'all';

					$get_childs = $this->read($child_params);

					$row['childs'] = (isset($get_childs['code']) && ($get_childs['code'] == 200) ? $get_childs['response']['data'] : array());
				}

				// Remove unnecessary data
				unset($row['count_childs']);

				// Assign row to data
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['category'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['category']);

			// SET RESPONSE data
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $data,
				'summary' => $summary
			));
		} else {
			// SET RESPONSE data
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}

	public function total($params = array())
	{
		return $this->count_rows($this->conn['main'], $this->tables['category'], $params);
	}
}

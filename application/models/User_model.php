<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User_model extends Base_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	public function create($data = array())
	{
		// PREPARING data
		$data = $this->prepare_data($data);

		// QUERY process
		$query = $this->conn['main']->insert($this->tables['user'], $data);

		// CONDITION for QUERY result
		if ($query) {
			$insert_id = $this->conn['main']->insert_id();
			$id = $insert_id;

			$referral_code = 'C' . str_pad($id, 3, '0', STR_PAD_LEFT);

			$this->conn['main']
				->set('referral_code', $referral_code)
				->where(array('partner_id' => $id))
				->update($this->tables['user']);

			// GET data result for RESPONSE
			$read_data = $this->read(array('partner_id' => $id));

			// SET RESPONSE data
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' => $read_data['response']['data'][0]
			));
		} else {
			// SET RESPONSE data
			$this->set_response('', $this->conn['main']->error());
		}

		return $this->get_response();
	}

	public function read($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['user']);
		$order_query    = $this->build_order($this->conn['main'], $params, $this->tables['user']);
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`" . $this->tables['user'] . "`.*,
					SHA1(CONCAT(`" . $this->tables['user'] . "`.`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `partner_id`,
					SHA1(CONCAT(`" . $this->tables['user'] . "`.`merchant_id`, '" . $this->config->item('encryption_key') . "')) AS `merchant_id`
					FROM `" . $this->tables['user'] . "`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		unset($query[0]['password']); # unset password
		unset($query[0]['status_active']); # unset password

		// CONDITION for QUERY result
		if ($query) {
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row) {
				// Reconcile result
				if (!empty($row['img']) && file_exists($this->config->item('storage_path') . 'user/' . $row['img'])) {
					$row['img'] = $this->config->item('storage_url') . 'user/' . $row['img'];
				} else {
					$row['img'] = $this->config->item('storage_url') . 'user/no-image.png';
				}

				// Assign row to data
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['user'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['user']);

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

	public function update($id = '', $params = array())
	{
		if (!empty($id)) {
			$read_data = $this->read(array('ENCRYPTED::partner_id' => $id));

			if ($read_data['code'] == 200) {
				// Set request params
				$request = $params;
				if (isset($request['partner_id'])) unset($request['partner_id']);

				// SET query data preparation
				$field_to_set = $this->build_field($this->conn['main'], $this->tables['user'], $request);
				$field_to_set = ltrim($field_to_set, ", ");

				// QUERY process
				$sql = "UPDATE `" . $this->tables['user'] . "` SET " . $field_to_set . " WHERE SHA1(CONCAT(`" . $this->tables['user'] . "`.`partner_id`,'" . $this->config->item('encryption_key') . "')) = '{$id}'";
				$query = $this->conn['main']->simple_query($sql);
				// CONDITION for QUERY result
				if ($query) {
					// GET data result for RESPONSE
					$read_data = $this->read(array('ENCRYPTED::partner_id' => $id));

					// SET RESPONSE data
					$this->set_response('code', 200);
					$this->set_response('response', array(
						'data' => $read_data['response']['data'][0]
					));
				} else {
					$this->set_response('', $this->conn['main']->error());
				}
			} else {
				$this->set_response('code', 404);
			}
		} else {
			$this->set_response('code', 400);
		}

		return $this->get_response();
	}

	public function get_user($params = array())
	{
		$query = $this->conn['main']->select('*')
			->select("SHA1(CONCAT(`" . $this->tables['user'] . "`.`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `partner_id`,")
			->from($this->tables['user'])
			->where($params)
			->get()->result_array();

		if ($query) {
			return $query;
		} else {
			return FALSE;
		}
	}

	public function get_user_id($params = array())
	{
		$query = $this->conn['main']->select("SHA1(CONCAT(`" . $this->tables['user'] . "`.`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `partner_id`")
			->from($this->tables['user'])
			->where($params)
			->get()->result_array();

		if ($query) {
			return $query[0]['partner_id'];
		} else {
			return FALSE;
		}
	}

	public function get_user_id_decode($params = array())
	{
		$query = $this->conn['main']->select("partner_id")
			->from($this->tables['user'])
			->where($params)
			->get()->result_array();

		if ($query) {
			return $query[0]['partner_id'];
		} else {
			return FALSE;
		}
	}

	public function get_user_password($params = array())
	{
		$query = $this->conn['main']->select($this->tables['user'] . '.password')
			->from($this->tables['user'])
			->where($params)
			->get()->result_array();

		if ($query) {
			return $query[0]['password'];
		} else {
			return FALSE;
		}
	}

	public function get_user_email($params = array())
	{
		$query = $this->conn['main']->select($this->tables['user'] . '.email')
			->from($this->tables['user'])
			->where($params)
			->get()->result_array();

		if ($query) {
			return $query[0]['email'];
		} else {
			return FALSE;
		}
	}

	public function set_device($params = array())
	{
		$token = $params['token'];

		$request = $params;
		unset($request['token']);

		$get_user = $this->conn['main']
			->select('a.id, b.partner_id')
			->where("`b.partner_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')")
			->join($this->tables['user'] . ' b', 'b.partner_id = a.partner_id', 'RIGHT')
			->get($this->tables['user_device'] . ' a')->row();

		if ($get_user->id != '') {
			// update device
			$query = $this->conn['main']
				->set('device_id', $request['device_id'])
				->where(array('partner_id' => $get_user->partner_id))
				->update($this->tables['user_device']);
		} else {
			// insert device
			$request['partner_id'] = $get_user->partner_id;

			$query = $this->conn['main']
				->insert($this->tables['user_device'], $request);
		}

		if ($query) {
			return $query;
		} else {
			return False;
		}
	}

	public function update_status_active($params)
	{
		$token = $params['token'];

		$request = $params;
		unset($request['token']);

		$get_user = $this->conn['main']
			->select('a.*')
			->where("`a.partner_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')")
			->get($this->tables['user'] . ' a')->row();

		if ($get_user->partner_id != '') {
			// update device
			$query = $this->conn['main']
				->set('status_active', $request['status_active'])
				->where(array('partner_id' => $get_user->partner_id))
				->update($this->tables['user']);
		}

		// GET data result for RESPONSE
		$read_data = $this->read(array('partner_id' => $get_user->partner_id));

		return $this->get_response();
	}

	function save_mitra_favorit($data = array())
	{
		$cek_favorit = $this->conn['main']
			->where('user_id', $data['user_id'])
			->where('mitra_id', $data['mitra_id'])
			->get('mitra_favorit')->result();

		if (count($cek_favorit) == 0) {
			$insert_data = $this->conn['main']->insert('mitra_favorit', $data);
			if ($insert_data) {
				$this->set_response('code', 200);
			} else {
				$this->set_response('', $this->conn['main']->error());
			}
		} else {
			$this->set_response('code', 400);
			$this->set_response('message', 'Proses Gagal!, Mitra sudah ada di daftar favorit');
		}
		return $this->get_response();
	}

	function list_mitra_favorit($where)
	{
		$query = $this->conn['main']
			->select('b.*')
			->select("SHA1(CONCAT(b.partner_id, '" . $this->config->item('encryption_key') . "')) as partner_id")
			->where($where)
			->join('user_partner b', 'a.mitra_id = b.partner_id')
			->get('mitra_favorit a')->result();

		if ($query) {
			return $query;
		} else {
			return FALSE;
		}
	}

	public function total($params = array())
	{
		return $this->count_rows($this->conn['main'], $this->tables['user'], $params);
	}
}

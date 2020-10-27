<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mitra_model extends Base_Model
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

			$referral_code = 'MP' . str_pad($id, 3, '0', STR_PAD_LEFT);

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
					`" . $this->tables['user'] . "`.*, '' as rating,
					SHA1(CONCAT(`" . $this->tables['user'] . "`.`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `partner_id`,
					SHA1(CONCAT(`" . $this->tables['user'] . "`.`merchant_id`, '" . $this->config->item('encryption_key') . "')) AS `merchant_id`,
					(select sum(rate) / count(rate) from mitra_rating where mitra_rating.`partner_id` = `" . $this->tables['user'] . "`.`partner_id`) as rate,
					(SELECT mj.`jasa_id` FROM `" . $this->tables['mitra_jasa'] . "` mj WHERE mj.`partner_id`  = `" . $this->tables['user'] . "`.`partner_id`) AS `service`,
					(SELECT mcl.`latitude` FROM `mitra_current_location` mcl WHERE mcl.`partner_id`  = `" . $this->tables['user'] . "`.`partner_id`) AS `latitude`,
					(SELECT mcl.`longitude` FROM `mitra_current_location` mcl WHERE mcl.`partner_id`  = `" . $this->tables['user'] . "`.`partner_id`) AS `longitude`
					FROM `" . $this->tables['user'] . "`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query) {
			if ($query[0]['user_type'] == 'mitra') {
				$query[0]['rating'] = round($query[0]['rate'], 2);
				$get_versi = $this->getWhere('versi_app', array('type_app' => 'mitra'));
				if ($get_versi[0]->version == '1.0.7.6') {
					$query[0]['rating'] = round($query[0]['rate']);
				} else {
					$query[0]['rating'] = number_format((float)$query[0]['rate'], 2, '.', '');
				}
			}
			unset($query[0]['password']); # unset password
			unset($query[0]['rate']); # unset password
			unset($query[0]['total_order']); # unset password

			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row) {
				// Reconcile result
				if (!empty($row['img']) && file_exists($this->config->item('storage_path') . 'user/' . $row['img'])) {
					$row['img'] = $this->config->item('storage_url') . 'user/' . $row['img'];
				} else {
					$row['img'] = $this->config->item('storage_url') . 'user/no-image.png';
				}

				// service mitra jasa
				if (!empty($row['service'])) {
					$row['service'] = $this->conn['main']->query("SELECT
						SHA1(CONCAT(j.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
						j.`id`,
						j.`name`
					  FROM `" . $this->tables['jasa'] . "` j WHERE j.`id` != 0 AND j.`id` IN (" . $row['service'] . ")")->result_array();
				} else {
					$row['service'] = array();
				}

				if ($row['verified'] == '1') {
					$row['verified'] = true;
				} else {
					$row['verified'] = false;
				}

				if ($row['status_active'] == '1') {
					$row['status_active'] = true;
				} else {
					$row['status_active'] = false;
				}

				if ($row['suspend'] == '1') {
					$row['suspend'] = true;
				} else {
					$row['suspend'] = false;
				}

				if ($row['user_type'] != 'mitra') {
					unset($row['service']);
				}
				unset($row['mitra']);

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
			->select("(select round(sum(rate) / count(rate), 2) from mitra_rating where mitra_rating.`partner_id` = `" . $this->tables['user'] . "`.`partner_id`) as rate")
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

	public function get_deviceID($id_mitra)
	{
		$query = $this->conn['main']
			->select('*')
			->where('partner_id', $id_mitra)
			->get($this->tables['user_device']);

		if ($query) {
			return $query;
		} else {
			return False;
		}
	}

	public function update_profile($params)
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
				->set($request)
				->where(array('partner_id' => $get_user->partner_id))
				->update($this->tables['user']);
		}

		// GET data result for RESPONSE
		$read_data = $this->read(array('partner_id' => $get_user->partner_id));

		return $this->get_response();
	}

	public function update_current_location($params)
	{
		$token = $params['token'];

		$request = $params;
		unset($request['token']);

		$get_user = $this->conn['main']
			->select('a.*')
			->where("`a.partner_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')")
			->get($this->tables['user'] . ' a')->row();

		$get_mitra_location = $this->conn['main']
			->select('*')
			->where('partner_id', $get_user->partner_id)
			->get('mitra_current_location')->row();

		if ($get_user->partner_id != '') {
			if (!empty($get_mitra_location)) {
				$query = $this->conn['main']
					->set($request)
					->where(array('partner_id' => $get_user->partner_id))
					->update('mitra_current_location');
			} else {
				$request['partner_id'] = $get_user->partner_id;
				$query = $this->conn['main']
					->insert('mitra_current_location', $request);
			}
		}

		if ($query) {
			$get_mitra_location = $this->conn['main']
				->select("SHA1(CONCAT(`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `partner_id`, latitude, longitude")
				->where('partner_id', $get_user->partner_id)
				->get('mitra_current_location')->result();

			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $get_mitra_location,
			));
		} else {
			// SET RESPONSE data
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}


	public function get_order_stat($token)
	{
		$get_user = $this->conn['main']
			->select('a.*')
			->where("`a.partner_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')")
			->get($this->tables['user'] . ' a')->row();

		$get_data_hari = $this->search_order($get_user->partner_id, "and a.shipping_date=DATE(NOW()) group by a.shipping_date");
		$get_data_minggu = $this->search_order($get_user->partner_id, "and YEARWEEK(a.shipping_date)=YEARWEEK(NOW()) group by YEARWEEK(a.shipping_date)");
		$get_data_bulan = $this->search_order($get_user->partner_id, "and CONCAT(YEAR(a.shipping_date),'/',MONTH(a.shipping_date))=CONCAT(YEAR(NOW()),'/',MONTH(NOW())) group by YEAR(a.shipping_date),MONTH(a.shipping_date)");
		$get_data_all = $this->search_order($get_user->partner_id, "");
		$current_deposit = $this->conn['main']->select('current_deposit')->where('partner_id', $get_user->partner_id)->get('user_partner')->row();

		$data = array(
			'saldo'	=> (int)$current_deposit->current_deposit,
			'day'	=> (!empty($get_data_hari)) ? (int)$get_data_hari->jml : 0,
			'week'	=> (!empty($get_data_minggu)) ? (int)$get_data_minggu->jml : 0,
			'month'	=> (!empty($get_data_bulan)) ? (int)$get_data_bulan->jml : 0,
			'all'	=> (!empty($get_data_all)) ? (int)$get_data_all->jml : 0,
		);

		$this->set_response('code', 200);
		$this->set_response('response', array(
			'data' 		=> $data,
		));

		return $this->get_response();
	}

	function search_order($partner_id, $cond)
	{
		$sql = "
			select 
			coalesce(count(a.shipping_date),0) as jml
			from mall_order a
			left join mall_transaction b on a.id = b.order_id
			where
			b.transaction_status_id = '4'
			and b.merchant_id = '$partner_id'
			$cond
			";
		return $this->conn['main']->query($sql)->row();
	}

	public function get_deposit_history($token, $params)
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, 'deposit_history');
		// $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['user']);
		$order_query	= 'order by dtd_id DESC';
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		$cek_user = $this->conn['main']->query("select partner_id from " . $this->tables['user'] . " where ecommerce_token = '$token'")->row();

		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					SHA1(CONCAT(dtd_id, '" . $this->config->item('encryption_key') . "')) AS id,
					deposit_history.*
					FROM deposit_history WHERE 
					partner_id = '$cek_user->partner_id'" . $cond_query . $order_query;

		$query_all = $this->conn['main']->query($sql)->result_array();

		$sql .= $limit_query;
		$query = $this->conn['main']->query($sql)->result_array();

		$summary = array(
			'total_show'	=> count($query),
			'total_filter'	=> count($query_all),
		);

		if ($query) {
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $query,
				'summary' 	=> $summary
			));
		} else {
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}

	public function get_data_topup($token, $params)
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, 'deposit_history');
		// $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['user']);
		$order_query	= 'order by id DESC';
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		$cek_user = $this->conn['main']->query("select partner_id from " . $this->tables['user'] . " where ecommerce_token = '$token'")->row();

		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					deposit_topup.*,
					SHA1(CONCAT(id, '" . $this->config->item('encryption_key') . "')) AS id
					FROM deposit_topup WHERE 
					user_id = '$cek_user->partner_id' and payment_status = 'pending'" . $cond_query . $order_query;

		$query_all = $this->conn['main']->query($sql)->result_array();

		$sql .= $limit_query;
		$query = $this->conn['main']->query($sql)->result_array();
		foreach ($query as $key => $row) {
			if ($row['payment_channel_id'] == 6 && $row['payment_data'] != '') {
				$row['payment_data'] = substr($row['payment_data'], 1, -1);
				$query[$key]['payment_data'] = json_decode(preg_replace("!\r?\n!", "", $row['payment_data']), 1);
			}
		}

		$summary = array(
			'total_show'	=> count($query),
			'total_filter'	=> count($query_all),
		);

		if ($query) {
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $query,
				'summary' 	=> $summary
			));
		} else {
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}

	public function total($params = array())
	{
		return $this->count_rows($this->conn['main'], $this->tables['user'], $params);
	}
}

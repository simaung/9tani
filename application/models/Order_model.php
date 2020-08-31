<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Order_model extends Base_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	public function create($params = array())
	{
		if (!empty($params['token'])) {
			// SET reconciliation parameters
			$token = $params['token'];

			// Set request params
			$request = $params;
			unset($request['token']);

			if (isset($request['id'])) unset($request['id']);

			$request['created_at'] = $request['modified_at'] = date('Y-m-d H:i:s');

			$random_hours = rand(10, 12);
			// $request['send_at'] = date('Y-m-d '.$random_hours.':00:00', strtotime($request['created_at'] . '+ 1 day' ));

			if (isset($request['cod']) == 1) {
				$request['payment_code'] = 'cod';
				$request['payment_channel_id'] = 'cod';
			}

			// SET query data preparation
			$field_to_set = $this->build_field($this->conn['main'], $this->tables['order'], $request);

			// SET reconciliation field
			$field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')";

			$field_to_set = ltrim($field_to_set, ", ");

			// QUERY process
			$sql = "INSERT INTO `" . $this->tables['order'] . "` SET " . $field_to_set;
			$query = $this->conn['main']->simple_query($sql);

			// CONDITION for QUERY result
			if ($query) {
				$insert_id = $this->conn['main']->insert_id();
				$id = $insert_id;

				// INVOICE
				$this->conn['main']->query("UPDATE `" . $this->tables['order'] . "` SET `invoice_code` = 'ST" . date('Ymd') . str_pad($id, 3, '0', STR_PAD_LEFT) . "' WHERE `id` = '{$id}'");

				// GET data result for RESPONSE
				$read_data = $this->read(array('id' => $id));

				// SET RESPONSE data
				$this->set_response('code', 200);
				$this->set_response('response', array(
					'data' => $read_data['response']['data'][0]
				));
			} else {
				$this->set_response('', $this->conn['main']->error());
			}
		} else {
			$this->set_response('code', 400);
		}

		return $this->get_response();
	}

	public function read($params = array())
	{
		if (!empty($params['token'])) {
			$token = $this->sanitize($this->conn['main'], $params['token']);
			unset($params['token']);
		}

		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['order']);
		$order_query    = $this->build_order($this->conn['main'], $params, $this->tables['order']);
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		if (!empty($token)) {
			$cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['order'] . "`.`user_id` = (SELECT `" . $this->tables['user'] . "`.`partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%{$token}'))";
		}

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`" . $this->tables['order'] . "`.*,
					SHA1(CONCAT(`" . $this->tables['order'] . "`.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
					SHA1(CONCAT(`" . $this->tables['order'] . "`.`user_id`, '" . $this->config->item('encryption_key') . "')) AS `user_id`,
					(SELECT SUM(`" . $this->tables['transaction_item'] . "`.`price`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = (SELECT `" . $this->tables['transaction'] . "`.`id` FROM `" . $this->tables['transaction'] . "` WHERE `" . $this->tables['transaction'] . "`.`order_id` = `" . $this->tables['order'] . "`.`id` LIMIT 1)) AS `total_price`,
					(SELECT SUM(`" . $this->tables['transaction_item'] . "`.`discount`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = (SELECT `" . $this->tables['transaction'] . "`.`id` FROM `" . $this->tables['transaction'] . "` WHERE `" . $this->tables['transaction'] . "`.`order_id` = `" . $this->tables['order'] . "`.`id` LIMIT 1)) AS `total_discount`,
					(SELECT SUM(`" . $this->tables['transaction_item'] . "`.`quantity`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = (SELECT `" . $this->tables['transaction'] . "`.`id` FROM `" . $this->tables['transaction'] . "` WHERE `" . $this->tables['transaction'] . "`.`order_id` = `" . $this->tables['order'] . "`.`id` LIMIT 1)) AS `total_quantity`,
					(SELECT SUM(`" . $this->tables['transaction'] . "`.`shipping_cost`) FROM `" . $this->tables['transaction'] . "` WHERE `" . $this->tables['transaction'] . "`.`order_id` = `" . $this->tables['order'] . "`.`id`) AS `total_shipping_cost`
					FROM `" . $this->tables['order'] . "`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query) {
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row) {
				// Assign row to data
				$row['price_after_discount'] = strval($row['total_price'] - $row['total_discount']);
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['order'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['order']);

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
		return $this->count_rows($this->conn['main'], $this->tables['order'], $params);
	}

	public function get_order_expired()
	{
		$query = $this->conn['main']
			->select('*')
			->where('created_at <', date('Y-m-d', strtotime(date('Y-m-d') . ' - 1 days')))
			->where('payment_status', 'pending')
			->get($this->tables['order'])->result_array();

		return $query;
	}

	public function set_order_expired($id)
	{
		$update_transaction = $this->conn['main']->set(array('transaction_status_id' => '6'))
			->where('order_id', $id)
			->update($this->tables['transaction']);

		$update_order = $this->conn['main']->set(array('payment_status' => 'expired'))
			->where('id', $id)
			->update($this->tables['order']);
	}

	public function set_order_paid($invoice_code, $payment_data = '')
	{
		if (substr($invoice_code, 0, 2) == 'ST') {
			$update_order = $this->conn['main']
				->set(array(
					'payment_status' 	=> 'paid',
					'payment_data'		=> $payment_data
				))
				->where('invoice_code', $invoice_code)
				->update($this->tables['order']);
		} else {
			$update_order = $this->conn['main']
				->set(array(
					'payment_status' 	=> 'paid',
					'payment_data'		=> $payment_data
				))
				->where('invoice_code', $invoice_code)
				->update('deposit_topup');
		}

		$update_order = $this->conn['main']
			->set(array(
				'status' 	=> 'approve',
			))
			->where('transaction_invoice', $invoice_code)
			->update($this->tables['payment_transfer']);
	}

	public function get_order($token, $active, $params)
	{
		$cek_user = $this->conn['main']->query("select partner_id from " . $this->tables['user'] . " where ecommerce_token = '$token'")->row();

		if ($active == 'active') {
			$cond_active = 'confirm';
			$cond_status = "AND c.transaction_status_id in (7,8,9,10)";
			$order_query = 'order by tgl_pelayanan asc';
		} else if ($active == 'completed') {
			$cond_active = 'completed';
			$cond_status = "AND c.transaction_status_id in (4)";
			$order_query = 'order by b.created_at desc';
		} else {
			$cond_active = 'pending';
			$cond_status = "AND c.transaction_status_id in (1,8)";
			$order_query = '';
		}

		$limit_query    = $this->build_limit($this->conn['main'], $params);

		$sql =
			"
			SELECT
			SHA1(CONCAT(a.`order_id`, '" . $this->config->item('encryption_key') . "')) AS `order_id`, a.distance,
			b.invoice_code, f.name as status_order, f.description as description_status_order, g.description as payment_name,
			e.full_name as customer, b.tipe_customer as jk_customer, e.img as customer_image,e.mobile_number customer_phone,
			b.shipping_date, b.send_at, b.service_type, concat(b.shipping_date,' ', b.send_at) as tgl_pelayanan, b.payment_code, d.discount,
			(d.price - d.discount) as price_after_discount, c.start_time,
			c.address_data, d.product_data
			FROM order_to_mitra a
			LEFT JOIN mall_order b on b.id = a.order_id
			LEFT JOIN mall_transaction c on c.order_id = a.order_id
			LEFT JOIN mall_transaction_item d on d.transaction_id = c.id
			LEFT JOIN user_partner e on e.partner_id = b.user_id
			LEFT JOIN mall_transaction_status f on f.id = c.transaction_status_id
			LEFT JOIN payment_channel g on g.id = b.payment_channel_id
			WHERE 
			a.mitra_id = '$cek_user->partner_id'
			AND a.status_order = '$cond_active'
			$cond_status $order_query
			";

		$query_all = $this->conn['main']->query($sql)->result_array();

		$sql .= $limit_query;
		$query = $this->conn['main']->query($sql)->result_array();

		$summary = array(
			'total_show'	=> count($query),
			'total_filter'	=> count($query_all),
		);

		if ($query) {
			foreach ($query as $key => $value) {
				if (!empty($value['customer_image']) && file_exists($this->config->item('storage_path') . 'user/' . $value['customer_image'])) {
					$query[$key]['customer_image'] = $this->config->item('storage_url') . 'user/' . $value['customer_image'];
				} else {
					$query[$key]['customer_image'] = $this->config->item('storage_url') . 'user/no-image.png';
				}

				if ($value['payment_code'] == 'cod') {
					$query[$key]['payment_name'] = 'tunai';
				}

				if ($value['jk_customer'] == 'P') {
					$query[$key]['jk_customer'] = 'Pria';
				} elseif ($value['jk_customer'] == 'W') {
					$query[$key]['jk_customer'] = 'Wanita';
				} else {
					$query[$key]['jk_customer'] = '-';
				}

				$query[$key]['address_data'] = json_decode(preg_replace("!\r?\n!", "", $value['address_data']), 1);
				$query[$key]['product_data'] = json_decode(preg_replace("!\r?\n!", "", $value['product_data']), 1);
			}

			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' => $query,
				'summary' => $summary
			));
		} else {
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}

	public function take_order($params = array())
	{
		$cek_user = $this->conn['main']->query("select partner_id from " . $this->tables['user'] . " where ecommerce_token = '" . $params['token'] . "'")->row();

		$get_order = $this->conn['main']->query("select id, payment_status, payment_code from mall_order where SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $params['order_id'] . "'")->row();

		// cek status order
		$sql = "SELECT status_order FROM order_to_mitra WHERE order_id = '" . $get_order->id . "' and status_order != 'canceled' group by status_order";
		$cek_order = $this->conn['main']->query($sql)->result();

		if (!empty($cek_order)) {
			if (count($cek_order) > 1) {
				// order sudah tidak berstatus pending
				$this->set_response('code', 400);
				$this->set_response('message', 'Orderan sudah tidak tersedia');
			} else {
				// update status order
				$status = ($get_order->payment_status == 'pending') ? 7 : 8;

				if ($get_order->payment_code == 'cod') {
					$status = 8;
					$this->set_response('type_payment', 'cod');
				}

				$update_status_order = $this->conn['main']
					->set(array('status_order' => 'confirm'))
					->where('order_id', $get_order->id)
					->where('mitra_id', $cek_user->partner_id)
					->update('order_to_mitra');

				// update merchant_id di mall_order
				$update_merchant_id = $this->conn['main']
					->set(array('merchant_id' => $cek_user->partner_id, 'transaction_status_id' => $status))
					->where('order_id', $get_order->id)
					->update('mall_transaction');

				$this->set_response('code', 200);
				$this->set_response('message', 'Orderan berhasil di ambil');
			}
		} else {
			$this->set_response('code', 404);
		}
		return $this->get_response();
	}

	public function update($params = array())
	{
		$cek_order = $this->conn['main']
			->select('a.*,b.merchant_id as mitra_id, c.price, c.discount')
			->where("SHA1(CONCAT(a.`id`, '" . $this->config->item('encryption_key') . "')) = ", $params['id_order'])
			->join("mall_transaction b", "b.order_id = a.id", "left")
			->join("mall_transaction_item c", "c.transaction_id = b.id", "left")
			->get('mall_order a')->row();

		if ($cek_order->payment_status == 'paid' || $cek_order->payment_code == 'cod' || $params['status'] == 5) {

			$status_mitra = array(9, 10, 4);

			if ($params['status'] == 5 && $params['user_type'] != 'mitra') {
				$update_data = $this->conn['main']
					->set(array('transaction_status_id' => $params['status']))
					->where("order_id", $cek_order->id)
					->update('mall_transaction');

				$this->conn['main']
					->where("order_id", $cek_order->id)
					->where("status_order !=", 'canceled')
					->delete('order_to_mitra');

				$this->conn['main']
					->set(array('payment_status' => 'cancel'))
					->where("id", $cek_order->id)
					->update('mall_order');

				$this->curl->push($cek_order->mitra_id, 'Status Order', 'Customer membatalkan orderan', 'order_canceled');
			} elseif ($params['status'] == 5 && $params['user_type'] == 'mitra') {
				$this->conn['main']
					->set(array('status_order' => 'canceled'))
					->where("order_id", $cek_order->id)
					->where("SHA1(CONCAT(mitra_id, '" . $this->config->item('encryption_key') . "')) = ", $params['mitra_id'])
					->update('order_to_mitra');

				// $this->conn['main']
				// 	->set(array('transaction_status_id' => 1))
				// 	->where("order_id", $cek_order->id)
				// 	->update('mall_transaction');

				// $this->conn['main']
				// 	->where("order_id", $cek_order->id)
				// 	->where('status_order', 'pending')
				// 	->delete('order_to_mitra');
			} elseif ($params['user_type'] == 'mitra' && in_array($params['status'], $status_mitra)) {
				$set_data = array(
					'transaction_status_id' => $params['status']
				);

				if ($params['status'] == 10) {
					$set_data = array_merge($set_data, array('start_time' => date('Y-m-d H:i:s')));
				} elseif ($params['status'] == 4) {
					$set_data = array_merge($set_data, array('end_time' => date('Y-m-d H:i:s')));
				}

				$update_data = $this->conn['main']
					->set($set_data)
					->where("order_id", $cek_order->id)
					->update('mall_transaction');
			}

			if ($update_data) {
				if ($params['status'] == 4) {

					$this->load->library('deposit');

					if ($cek_order->payment_code != 'cod') {
						// insert deposit to mitra
						$this->deposit->add_deposit($cek_order);
					} else {
						// kurangi deposit dari mitra
						$this->deposit->less_deposit($cek_order);

						// insert cashback if discount existing
						if ($cek_order->discount != 0) {
							$this->deposit->add_deposit_cashback_diskon($cek_order);
						}

						$this->conn['main']
							->set(array('payment_status' => 'paid'))
							->where("id", $cek_order->id)
							->update('mall_order');
					}

					// update status completed
					$this->conn['main']
						->set(array('status_order' => 'completed'))
						->where("order_id", $cek_order->id)
						->where('mitra_id', $cek_order->mitra_id)
						->update('order_to_mitra');

					// hapus data order di order_to_mitra yang berstatus pending dan canceled
					$status_hapus = array('pending', 'canceled');
					$this->conn['main']
						->where("order_id", $cek_order->id)
						->where_in('status_order', $status_hapus)
						->delete('order_to_mitra');
				}

				$this->set_response('code', 200);
				$this->set_response('message', 'Update success');
			} else {
				$this->set_response('', $this->conn['main']->error());
			}
		} else if ($cek_order->payment_status == 'pending') {
			$this->set_response('code', 400);
			$this->set_response('message', 'Order waiting payment');
		}
		return $this->get_response();
	}

	function get_detail_order($params)
	{
		$get_order = $this->conn['main']->query("select * from mall_order where SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $params['id_order'] . "'")->row();

		return $get_order;
	}
}

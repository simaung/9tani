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

			if (!empty($request['cod'])) {
				if ($request['cod'] == 1) {
					$request['payment_code'] = 'cod';
					$request['payment_channel_id'] = 'cod';
				} elseif ($request['cod'] == 2) {
					$request['payment_code'] = 'gopay';
					$request['payment_channel_id'] = 11;
				}
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

				if ($request['service_type'] == 'clean') {
					$code = 'SC';
				} elseif ($request['service_type'] == 'massage') {
					$code = 'SM';
				} elseif ($request['service_type'] == 'super_clean') {
					$code = 'SD';
				} else {
					$code = 'ST';
				}

				// INVOICE
				$this->conn['main']->query("UPDATE `" . $this->tables['order'] . "` SET `invoice_code` = '" . $code . date('Ymd') . str_pad($id, 3, '0', STR_PAD_LEFT) . "' WHERE `id` = '{$id}'");

				// GET data result for RESPONSE
				$read_data = $this->read(array('id' => $id), 'create');

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

	public function read($params = array(), $action = '')
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
					(SELECT COUNT(`" . $this->tables['transaction_item'] . "`.`id`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = (SELECT `" . $this->tables['transaction'] . "`.`id` FROM `" . $this->tables['transaction'] . "` WHERE `" . $this->tables['transaction'] . "`.`order_id` = `" . $this->tables['order'] . "`.`id` LIMIT 1)) AS `total_item`,
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
				if ($action == '') {
					$row['total_discount'] = strval($row['total_discount'] / $row['total_item']);
					$row['price_after_discount'] = strval($row['total_price'] - $row['total_discount']);
					if ($row['payment_channel_id'] == 11) {
						$row['link_payment'] = base_url('payment/gopay/?invoice_code=' . $row['invoice_code']);
					} elseif ($row['payment_code'] != 'cod' || $row['payment_code'] != 'gopay') {
						$row['link_payment'] = base_url('payment/?invoice_code=' . $row['invoice_code']);
					}
				}
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
		if (in_array(substr($invoice_code, 0, 2), array('ST', 'SM', 'SC'))) {
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

		$cond_id = '';
		if (!empty($params['order_id'])) {
			$cond_id = "AND SHA1(CONCAT(b.id, '" . $this->config->item('encryption_key') . "')) = '" . $params['order_id'] . "'";
		}

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
			$cond_status = "AND c.transaction_status_id in (1)";
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
			(d.price - d.discount) as price_after_discount, c.start_time, b.note,
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
			$cond_id $cond_status $order_query
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

		$get_order = $this->conn['main']->query("
			select a.*, c.product_data, c.variant_id, d.full_name, d.mobile_number, b.merchant_id, b.transaction_status_id
			from mall_order a
			left join mall_transaction b on a.id = b.order_id
			left join mall_transaction_item c on b.id = c.transaction_id
			left join user_partner d on a.user_id = d.partner_id
			where SHA1(CONCAT(a.`id`, '" . $this->config->item('encryption_key') . "')) = '" . $params['order_id'] . "'
			")->row();

		if ($get_order) {
			if ($get_order->merchant_id != 0 && $get_order->transaction_status_id != '1') {
				$this->set_response('code', 400);
				$this->set_response('message', 'Orderan sudah tidak tersedia');
			} elseif ($get_order->transaction_status_id == '5' || $get_order->transaction_status_id == '6') {
				$this->set_response('code', 400);
				$this->set_response('message', 'Orderan sudah tidak tersedia');
			} else {
				if ($get_order->payment_code == 'cod') {
					$status = 8;
					$this->set_response('type_payment', 'cod');
				}

				if ($get_order->payment_status == 'paid') {
					$status = 8;
					$this->set_response('payment_status', 'paid');
				} elseif ($get_order->payment_status == 'pending' && $get_order->payment_code != 'cod') {
					$status = 7;
					$this->set_response('payment_status', 'pending');
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

				if (!empty($get_order->product_data)) {
					$item = json_decode($get_order->product_data, true);
					$this->send->index('order', $get_order->mobile_number, $get_order->full_name, $get_order->invoice_code,  $item['name'],  $item['variant_price']['layanan']);
				}

				//cek mitra_id di rating_sistem
				$cek_mitra_rating = $this->conn['main']
					->select('*')
					->where('mitra_id', $cek_user->partner_id)
					->get('rating_sistem')->row();

				if ($cek_mitra_rating) {
					$this->conn['main']->query("update rating_sistem set confirm = confirm + 1 where mitra_id = $cek_user->partner_id");
				} else {
					$data = array(
						'mitra_id'  => $cek_user->partner_id,
						'confirm' => 1
					);
					$this->conn['main']->insert('rating_sistem', $data);
				}

				$mitra_id_encode = $this->order_model->encoded($cek_user->partner_id);
				$this->insert_realtime_database($mitra_id_encode, 'false', 'coming_order');

				$varWhere = array(
					'order_id' => $get_order->id,
					'mitra_id !=' => 0,
					'status_order' => 'pending'
				);
				$get_mitra_pending_order = $this->getWhere('order_to_mitra', $varWhere);
				foreach ($get_mitra_pending_order as $row) {
					$mitra_id_encode = $this->order_model->encoded($row->mitra_id);
					$this->insert_realtime_database($mitra_id_encode, 'false', 'coming_order');
				}

				$this->set_response('code', 200);
				$this->set_response('message', 'Orderan berhasil di ambil');
			}
		} else {
			$this->set_response('code', 404);
		}
		return $this->get_response();
	}

	public function update_lama($params = array())
	{
		$cek_order = $this->conn['main']
			->select('a.*,b.merchant_id as mitra_id, b.transaction_status_id, c.price, c.discount')
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
				if ($cek_order->transaction_status_id == 5) {
					$this->set_response('code', 400);
					$this->set_response('message', 'Orderan sudah dibatalkan customer');
				} else {
					$update_data = $this->conn['main']
						->set(array('status_order' => 'canceled'))
						->where("order_id", $cek_order->id)
						->where("SHA1(CONCAT(mitra_id, '" . $this->config->item('encryption_key') . "')) = ", $params['mitra_id'])
						->update('order_to_mitra');

					$mitra_id = $this->user_model->getValueEncode('partner_id', 'user_partner', $params['mitra_id']);

					if ($cek_order->mitra_id == $mitra_id) {
						$this->conn['main']
							->set(array('transaction_status_id' => 1, 'merchant_id' => ''))
							->where("order_id", $cek_order->id)
							->update('mall_transaction');

						$this->conn['main']
							->where("order_id", $cek_order->id)
							->where('status_order', 'pending')
							->where('mitra_id !=', 0)
							->delete('order_to_mitra');
					}
				}
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

	public function update($params = array())
	{
		$cek_order = $this->conn['main']
			->select('a.*,b.merchant_id as mitra_id, b.transaction_status_id, c.price, c.discount')
			->where("SHA1(CONCAT(a.`id`, '" . $this->config->item('encryption_key') . "')) = ", $params['id_order'])
			->join("mall_transaction b", "b.order_id = a.id", "left")
			->join("mall_transaction_item c", "c.transaction_id = b.id", "left")
			->get('mall_order a')->row();

		if ($cek_order->payment_status == 'paid' || $cek_order->payment_code == 'cod' || $params['status'] == 5 || $params['status'] == 13) {

			$status_mitra = array(9, 10, 4);

			if (($params['status'] == 5 && $params['user_type'] != 'mitra') || $params['status'] == 13) {
				$update_data = $this->conn['main']
					->set(array('transaction_status_id' => $params['status']))
					->set(array('note_cancel' => $params['alasan']))
					->where("order_id", $cek_order->id)
					->update('mall_transaction');

				$this->conn['main']
					->where("order_id", $cek_order->id)
					->where("status_order !=", 'canceled')
					->delete('order_to_mitra');

				if ($cek_order->payment_status != 'paid') {
					$this->conn['main']
						->set(array('payment_status' => 'cancel'))
						->where("id", $cek_order->id)
						->update('mall_order');
				}

				if ($params['status'] == 13) {
					$this->curl->push($cek_order->mitra_id, 'Status Order', 'Order dibatalkan oleh admin', 'order_canceled');
				} else {
					$this->curl->push($cek_order->mitra_id, 'Status Order', 'Customer membatalkan orderan', 'order_canceled');
				}

				$this->set_response('code', 200);
				$this->set_response('message', 'Update success');
			} elseif ($params['status'] == 5 && $params['user_type'] == 'mitra') {
				if ($cek_order->transaction_status_id == 5 || $cek_order->transaction_status_id == 13) {
					$this->set_response('code', 400);
					if ($params['status'] == 5) {
						$this->set_response('message', 'Orderan sudah dibatalkan customer');
					} elseif ($params['status'] == 13) {
						$this->set_response('message', 'Orderan sudah dibatalkan admin');
					}
				} else {
					$mitra_id = $this->user_model->getValueEncode('partner_id', 'user_partner', $params['mitra_id']);

					$this->conn['main']
						->where("order_id", $cek_order->id)
						->where('status_order', 'pending')
						->where('mitra_id !=', 0)
						->delete('order_to_mitra');

					$get_mitra_from_order = $this->conn['main']
						->select('a.mitra_id, a.status_order, b.mitra_id as mitra_id_rating')
						->where('a.mitra_id !=', 0)
						->where('a.note_cancel is null', null, false)
						->where('order_id', $cek_order->id)
						->join('rating_sistem b', 'a.mitra_id = b.mitra_id', 'left')
						->get('order_to_mitra a')->result();

					foreach ($get_mitra_from_order as $row) {
						if (!empty($row->mitra_id_rating)) {
							$status = array('confirm', 'canceled');

							if (in_array($row->status_order, $status)) {
								if ($mitra_id == $row->mitra_id) {
									$data = "cancel = cancel + 1";
								} else {
									$data = "abaikan = abaikan + 1";
								}
								$sql = "update rating_sistem set $data where mitra_id = $row->mitra_id";
								$this->conn['main']->query($sql);
							}
						} else {
							$status = array('confirm', 'canceled');

							if (in_array($row->status_order, $status)) {
								if ($mitra_id == $row->mitra_id) {
									$data = array(
										'mitra_id'  => $row->mitra_id,
										'cancel' => 1
									);
								} else {
									$data = array(
										'mitra_id'  => $row->mitra_id,
										'abaikan' => 1
									);
								}
								$this->conn['main']->insert('rating_sistem', $data);
							}
						}
					}

					$update_data = $this->conn['main']
						->set(array('status_order' => 'canceled'))
						->set(array('note_cancel' => $params['alasan']))
						->where("order_id", $cek_order->id)
						->where("SHA1(CONCAT(mitra_id, '" . $this->config->item('encryption_key') . "')) = ", $params['mitra_id'])
						->update('order_to_mitra');

					if ($cek_order->mitra_id == $mitra_id) {
						$this->conn['main']
							->set(array('transaction_status_id' => 1, 'merchant_id' => ''))
							->where("order_id", $cek_order->id)
							->update('mall_transaction');

						$this->conn['main']
							->where("order_id", $cek_order->id)
							// ->where('status_order', 'pending')
							->where('note_cancel is null', null, false)
							->where('mitra_id !=', 0)
							->delete('order_to_mitra');
					}

					$this->set_response('code', 200);
					$this->set_response('message', 'Update success');
				}
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
					if ($cek_order->transaction_status_id == '4') {
						$this->set_response('code', 400);
						$this->set_response('message', 'Transaksi sudah selesai');
					} else {
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
		$get_order = $this->conn['main']->query(
			"select a.*, b.merchant_id, c.full_name, c.mobile_number
			from mall_order a 
			left join mall_transaction b on a.id = b.order_id 
			left join user_partner c on a.user_id = c.partner_id 
			where SHA1(CONCAT(a.`id`, '" . $this->config->item('encryption_key') . "')) = '" . $params['id_order'] . "'"
		)->row();

		return $get_order;
	}

	function insert_realtime_database($id_order, $status)
	{
		$data = array(
			$id_order => $status
		);
		if (empty($data) || !isset($data)) {
			return FALSE;
		}

		foreach ($data as $key => $value) {
			$this->db->getReference()->getChild('coming_order')->getChild($key)->set($value);
		}
		return TRUE;
	}

	function get_radius($get_address)
	{
		$location = $get_address;
		$sql = "select a.user_id, (111.111
              * DEGREES(ACOS(COS(RADIANS(`latitude`))
              * COS(RADIANS(" . $location->latitude . "))
              * COS(RADIANS(`longitude` - " . $location->longitude . ")) + SIN(RADIANS(`latitude`))
			  * SIN(RADIANS(" . $location->latitude . "))))) AS `distance` 
				FROM mall_address a
				WHERE a.user_id = 1
				and a.label = 'kantor'
                ";

		$query = $this->conn['main']->query($sql)->result();
		return $query;
	}
}

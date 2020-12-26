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

				if ($request['service_type'] == 'clean') {
					$code = 'SC';
				} elseif ($request['service_type'] == 'massage') {
					$code = 'SM';
				}

				// INVOICE
				$this->conn['main']->query("UPDATE `" . $this->tables['order'] . "` SET `invoice_code` = '" . $code . date('Ymd') . str_pad($id, 3, '0', STR_PAD_LEFT) . "' WHERE `id` = '{$id}'");

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
		if (empty($params['order_id'])) {
			if (!empty($active)) {
				$get_order = $this->order_active($cek_user, $active, $params);
			} else {
				$get_order = $this->order_pending($cek_user);
			}
		} else {
			$get_order = $this->order_detail($params);
		}
		if ($get_order['code'] == '200') {
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' => $get_order['data'],
				'summary' => $get_order['summary']
			));
		} else {
			$this->set_response('code', 404);
		}
		return $this->get_response();
	}

	private function order_active($user, $active, $params)
	{
		if ($active == 'active') {
			$cond_query = 'and a.transaction_status_id in (7,8,9,10)';
		} else {
			$cond_query = 'and a.transaction_status_id in (4)';
		}
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		$sql = "
			select
			SHA1(CONCAT(a.`order_id`, '" . $this->config->item('encryption_key') . "')) AS `order_id`,
			b.invoice_code, b.service_type, b.shipping_date, b.send_at, a.start_time,
			c.full_name as customer, b.tipe_customer as jk_customer, c.img as customer_image, c.mobile_number customer_phone,
			concat(b.shipping_date,' ', b.send_at) as tgl_pelayanan,
			d.product_data
			from 
			mall_transaction a
			left join mall_order b on a.order_id = b.id
			left join user_partner c on c.partner_id = b.user_id
			left join mall_transaction_item d on d.transaction_id = a.id
			where
			a.merchant_id = " . $user->partner_id . "
			$cond_query
			group by b.invoice_code
			order by tgl_pelayanan asc
		";
		$get_order = $this->conn['main']->query($sql)->result_array();

		$sql 	.= $limit_query;
		$filter = $this->conn['main']->query($sql)->result_array();

		if ($get_order) {
			foreach ($get_order as $key => $value) {
				if (!empty($value['customer_image']) && file_exists($this->config->item('storage_path') . 'user/' . $value['customer_image'])) {
					$get_order[$key]['customer_image'] = $this->config->item('storage_url') . 'user/' . $value['customer_image'];
				} else {
					$get_order[$key]['customer_image'] = $this->config->item('storage_url') . 'user/no-image.png';
				}

				if ($value['jk_customer'] == 'P') {
					$get_order[$key]['jk_customer'] = 'Pria';
				} elseif ($value['jk_customer'] == 'W') {
					$get_order[$key]['jk_customer'] = 'Wanita';
				} else {
					$get_order[$key]['jk_customer'] = '-';
				}

				$get_order[$key]['product_data'] = json_decode(preg_replace("!\r?\n!", "", $value['product_data']), 1);

				if ($value['service_type'] == 'super_clean') {
					$get_order[$key]['product_data']['name'] = 'Daddy Super Clean';
					$get_order[$key]['product_data']['variant_price']['layanan'] = ' - ';
				}
			}
			$summary = array(
				'total_show'	=> count($get_order),
				'total_filter'	=> count($filter),
			);

			$data = array(
				'code'		=> '200',
				'data' 		=> $get_order,
				'summary'	=> $summary
			);
		} else {
			$data = array(
				'code'		=> '404'
			);
		}

		return $data;
	}

	private function order_pending($user)
	{
		$get_order = $this->conn['main']
			->select('a.*')
			->select("SHA1(CONCAT(a.`order_id`, '" . $this->config->item('encryption_key') . "')) AS `order_id`")
			->select('b.invoice_code, b.service_type, b.shipping_date, b.send_at')
			->select("concat(b.shipping_date,' ', b.send_at) as tgl_pelayanan")
			->select('c.id, c.address_data')
			->select('d.product_data')
			->select('e.full_name as customer, b.tipe_customer as jk_customer, e.img as customer_image, e.mobile_number as customer_phone')
			->where('a.mitra_id', $user->partner_id)
			->where('a.status_order', 'pending')
			->where('c.transaction_status_id', '1')
			->join('mall_order b', 'b.id = a.order_id')
			->join('mall_transaction c', 'c.order_id = a.order_id')
			->join('mall_transaction_item d', 'd.transaction_id = c.id')
			->join('user_partner e', 'e.partner_id = b.user_id')
			->group_by('b.invoice_code')
			->get('order_to_mitra a')->result_array();

		if ($get_order) {
			foreach ($get_order as $key => $row) {
				if (!empty($row['customer_image']) && file_exists($this->config->item('storage_path') . 'user/' . $row['customer_image'])) {
					$get_order[$key]['customer_image'] = $this->config->item('storage_url') . 'user/' . $row['customer_image'];
				} else {
					$get_order[$key]['customer_image'] = $this->config->item('storage_url') . 'user/no-image.png';
				}

				if ($row['jk_customer'] == 'P') {
					$get_order[$key]['jk_customer'] = 'Pria';
				} elseif ($row['jk_customer'] == 'W') {
					$get_order[$key]['jk_customer'] = 'Wanita';
				} else {
					$get_order[$key]['jk_customer'] = '-';
				}

				$get_order[$key]['address_data'] = json_decode(preg_replace("!\r?\n!", "", $row['address_data']), 1);
				$get_order[$key]['product_data'] = json_decode(preg_replace("!\r?\n!", "", $row['product_data']), 1);
				if ($row['service_type'] == 'super_clean') {
					$get_order[$key]['product_data']['name'] = 'Daddy Super Clean';
					$get_order[$key]['product_data']['variant_price']['layanan'] = ' - ';
				}
			}
			$data = array(
				'code'		=> '200',
				'data'		=> $get_order,
				'summary'	=> array(
					'total_show'	=> count($get_order),
				)
			);
		} else {
			$data = array(
				'code'		=> '404'
			);
		}
		return $data;
	}

	private function order_detail($params)
	{
		$limit_query    = $this->build_limit($this->conn['main'], $params);
		$cond_id = "SHA1(CONCAT(b.id, '" . $this->config->item('encryption_key') . "')) = '" . $params['order_id'] . "'";

		$sql = "
			select
			a.id,
			SHA1(CONCAT(a.`order_id`, '" . $this->config->item('encryption_key') . "')) AS `order_id`,
			b.invoice_code, b.service_type, b.shipping_date, b.send_at, b.payment_code,
			d.name as status_order, d.description as description_status_order, e.description as payment_name,
			0 as price, 0 as discount, 0 as price_after_discount, a.start_time,
			c.full_name as customer, b.tipe_customer as jk_customer, c.img as customer_image, c.mobile_number customer_phone,
			concat(b.shipping_date,' ', b.send_at) as tgl_pelayanan,
			a.address_data
			from 
			mall_transaction a
			left join mall_order b on a.order_id = b.id
			left join user_partner c on c.partner_id = b.user_id
			left join mall_transaction_status d on d.id = a.transaction_status_id
			left join payment_channel e on e.id = b.payment_channel_id
			where
			$cond_id
		";
		$get_order = $this->conn['main']->query($sql)->result_array();

		if ($get_order) {
			$get_product = $this->conn['main']
				->select('*')
				->where('transaction_id', $get_order[0]['id'])
				->get('mall_transaction_item')->result_array();

			$price = 0;
			foreach ($get_product as $row) {
				$get_order[0]['product_data'][] = json_decode(preg_replace("!\r?\n!", "", $row['product_data']), 1);
				$price += $row['price'];
				$discount = $row['discount'];
			}

			foreach ($get_order as $key => $value) {
				if (!empty($value['customer_image']) && file_exists($this->config->item('storage_path') . 'user/' . $value['customer_image'])) {
					$get_order[$key]['customer_image'] = $this->config->item('storage_url') . 'user/' . $value['customer_image'];
				} else {
					$get_order[$key]['customer_image'] = $this->config->item('storage_url') . 'user/no-image.png';
				}
				if ($value['payment_code'] == 'cod') {
					$get_order[$key]['payment_name'] = 'tunai';
				}

				if ($value['jk_customer'] == 'P') {
					$get_order[$key]['jk_customer'] = 'Pria';
				} elseif ($value['jk_customer'] == 'W') {
					$get_order[$key]['jk_customer'] = 'Wanita';
				} else {
					$get_order[$key]['jk_customer'] = '-';
				}

				$get_order[$key]['price'] = strval($price);
				$get_order[$key]['discount'] = $discount;
				$get_order[$key]['price_after_discount'] = strval($price - $discount);
				$get_order[$key]['durasi'] = $get_order[$key]['product_data'][0]['variant_price']['durasi'];
				$get_order[$key]['address_data'] = json_decode(preg_replace("!\r?\n!", "", $value['address_data']), 1);
			}

			$data = array(
				'code'		=> '200',
				'data' 		=> $get_order,
				'summary'	=> array(
					'total_show'	=> 1
				)
			);
		} else {
			$data = array(
				'code'		=> '404'
			);
		}

		return $data;
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
			->select('a.*, b.id as transaction_id, b.merchant_id as mitra_id, b.transaction_status_id, c.price, c.discount')
			->where("SHA1(CONCAT(a.`id`, '" . $this->config->item('encryption_key') . "')) = ", $params['id_order'])
			->join("mall_transaction b", "b.order_id = a.id", "left")
			->join("mall_transaction_item c", "c.transaction_id = b.id", "left")
			->get('mall_order a')->row();

		if ($cek_order->service_type == 'super_clean') {
			$get_price = $this->conn['main']
				->select('sum(price) as price')
				->where('transaction_id', $cek_order->transaction_id)
				->get('mall_transaction_item')->row();
			$cek_order->price = $get_price->price;
		}

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
									if (empty($params['alasan'])) {
										$this->set_response('code', 400);
										$this->set_response('message', 'alasan diperlukan');
										return $this->get_response();
									} else {
										$data = "cancel = cancel + 1";
									}
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
									if (empty($params['alasan'])) {
										$this->set_response('code', 400);
										$this->set_response('message', 'alasan diperlukan');
										return $this->get_response();
									} else {
										$data = array(
											'mitra_id'  => $row->mitra_id,
											'cancel' => 1
										);
									}
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
						$this->set_response('message', 'Maaf, status transaksi sudah selesai!, silakan cek riwayat transaksi di tab completed');
					} else {
						$this->load->library('deposit');

						if ($cek_order->payment_code != 'cod') {
							$this->deposit->add_deposit($cek_order);
						} else {
							$this->deposit->less_deposit($cek_order);

							if ($cek_order->discount != 0) {
								$this->deposit->add_deposit_cashback_diskon($cek_order);
							}

							$this->conn['main']
								->set(array('payment_status' => 'paid'))
								->where("id", $cek_order->id)
								->update('mall_order');
						}

						$this->conn['main']
							->set(array('status_order' => 'completed'))
							->where("order_id", $cek_order->id)
							->where('mitra_id', $cek_order->mitra_id)
							->update('order_to_mitra');

						$status_hapus = array('pending', 'canceled');
						$this->conn['main']
							->where("order_id", $cek_order->id)
							->where_in('status_order', $status_hapus)
							->delete('order_to_mitra');

						$this->set_response('code', 200);
						$this->set_response('message', 'Update success');
					}
				} else {
					$this->set_response('code', 200);
					$this->set_response('message', 'Update success');
				}
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
}

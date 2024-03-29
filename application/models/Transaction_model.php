<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Transaction_model extends Base_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	public function create($params = array(), $mitra_code = '')
	{
		if (!empty($params['order_id'])) {
			if (empty($params['service_type'])) {
				if (empty($params['merchant_id'])) {
					$this->set_response('code', 400);
					return $this->get_response();
				} else {
					$merchant_id  = $params['merchant_id'];
				}
			}

			// SET reconciliation parameters
			$order_id     = $params['order_id'];

			// Set request params
			$request = $params;
			unset($request['order_id']);
			unset($request['merchant_id']);

			if (isset($request['id'])) unset($request['id']);

			if (isset($request['product'])) {
				$product = $this->sanitize($this->conn['main'], $request['product']);
				unset($request['product']);
			}

			if (isset($request['address'])) {
				$request['address_data'] = $request['address'];
				unset($request['address']);
			}

			if (!empty($request['shipping']['shipping_data']) && !empty($request['shipping']['shipping_cost'])) {
				$request['shipping_data'] = $request['shipping']['shipping_data'];
				$request['shipping_cost'] = $request['shipping']['shipping_cost'];
				unset($request['shipping']);
			}

			if (!empty($request['dropship']['phone']) && !empty($request['dropship']['name'])) {
				$request['dropship_data'] = $request['dropship'];
				unset($request['dropship']);
			}
			// SET query data preparation
			if (empty($mitra_code) || $mitra_code == '') {
				$field_to_set = $this->build_field($this->conn['main'], $this->tables['transaction'], $request);
			} else {
				$field_to_set = "address_data = '" . $request['address_data'] . "', transaction_status_id = 8";
			}

			// SET reconciliation field
			$field_to_set .= ", `order_id` = (SELECT `id` FROM `" . $this->tables['order'] . "` WHERE SHA1(CONCAT(`" . $this->tables['order'] . "`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $order_id . "')";

			if (empty($params['service_type'])) {
				$field_to_set .= ", `merchant_id` = (SELECT `id` FROM `" . $this->tables['merchant'] . "` WHERE SHA1(CONCAT(`" . $this->tables['merchant'] . "`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $merchant_id . "')";
			}

			if (!empty($mitra_code)) {
				$field_to_set .= ", `merchant_id` = '$mitra_code'";
			}

			$field_to_set = ltrim($field_to_set, ", ");
			// QUERY process
			$sql = "INSERT INTO `" . $this->tables['transaction'] . "` SET " . $field_to_set;
			$query = $this->conn['main']->simple_query($sql);

			// CONDITION for QUERY result
			if ($query) {
				$insert_id = $this->conn['main']->insert_id();
				$id = $insert_id;

				// TRANSACTION ITEM
				if (!empty($product)) {
					if (!is_array($product)) $product = array($product);

					$get_diskon = $this->getWhere('mst_voucher', array('name' => 'global_discount', 'status_active' => '1'));
					if (!empty($get_diskon)) {
						if ($get_diskon[0]->amount != 0) {
							$get_diskon = $get_diskon[0]->amount;
						} else {
							$get_diskon = $get_diskon[0]->percent;
						}
					}

					$order_id = $this->getValueEncode('id', 'mall_order', $order_id);
					$voucher_name = $this->getValue('voucher_code', 'mall_order', array('id' => $order_id));
					$get_voucher = $this->getWhere('mst_voucher', array('name' => $voucher_name));

					if (!empty($get_voucher)) {
						if ($get_voucher[0]->amount != 0) {
							$get_voucher = $get_voucher[0]->amount;
						} else {
							$get_voucher = $get_voucher[0]->percent;
						}
					}

					if (empty($params['service_type']) || $params['service_type'] == 'super_clean') {
						if (empty($params['service_type'])) {
							$price = 0;
							foreach ($product as $key => $value) {
								$this->conn['main']->query("INSERT INTO `" . $this->tables['transaction_item'] . "` SET
								`transaction_id` = '{$id}',
								`price` = '" . (float) $value['price'] . "',
								`discount` = '" . (float) $value['discount'] . "',
								`quantity` = '" . (int) $value['quantity'] . "',
								`product_data` = '" . json_encode($value['product_data']) . "',
								`variant_id` = " . (!empty($value['variant_id']) ? "(SELECT `" . $this->tables['product_variant'] . "`.`id` FROM `" . $this->tables['product_variant'] . "` WHERE SHA1(CONCAT(`" . $this->tables['product_variant'] . "`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $value['variant_id'] . "')"  : "NULL") . ",
								`note` = '" . $value['note'] . "'");

								$price += (float) $value['price'];
							}

							if (!empty($get_diskon)) {
								if ($get_diskon <= 100) {
									$price_discount = $price * $get_diskon / 100;
								} else {
									$price_discount = $get_diskon;
								}
							}

							if (!empty($get_voucher)) {
								if ($get_voucher <= 100) {
									$price_discount = $price * $get_voucher / 100;
								} else {
									$price_discount = $get_voucher;
								}
								$this->conn['main']->query("update mall_transaction_item set discount = $price_discount where transaction_id = $id");
							}
						} elseif ($params['service_type'] == 'super_clean') {
							$price = 0;
							foreach ($product as $key => $value) {
								$price_discount = 0;

								// if (!empty($get_diskon)) {
								// 	if ($get_diskon <= 100) {
								// 		$price_discount = $value['variant_price']['harga'] * $get_diskon / 100;
								// 	} else {
								// 		$price_discount = $get_diskon;
								// 	}
								// }

								// if (!empty($get_voucher)) {
								// 	if ($get_voucher <= 100) {
								// 		$price_discount = $value['variant_price']['harga'] * $get_voucher / 100;
								// 	} else {
								// 		$price_discount = $get_voucher;
								// 	}
								// }

								$this->conn['main']->query("INSERT INTO `" . $this->tables['transaction_item'] . "` SET
								`transaction_id` = '{$id}',
								`price` = '" . (float) $value['variant_price']['harga'] . "',
								`discount` = '$price_discount',
								`quantity` = '1',
								`product_data` = '" . json_encode($value) . "',
								`variant_id` = " . (!empty($value['variant_price']['id']) ? "(SELECT `" . $this->tables['jasa_price'] . "`.`id` FROM `" . $this->tables['jasa_price'] . "` WHERE SHA1(CONCAT(`" . $this->tables['jasa_price'] . "`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $value['variant_price']['id'] . "')"  : "NULL") . "
								");

								$price += (float) $value['variant_price']['harga'];
							}

							if (!empty($get_diskon)) {
								if ($get_diskon <= 100) {
									$price_discount = $price * $get_diskon / 100;
								} else {
									$price_discount = $get_diskon;
								}
							}

							if (!empty($get_voucher)) {
								if ($get_voucher <= 100) {
									$price_discount = $price * $get_voucher / 100;
								} else {
									$price_discount = $get_voucher;
								}
								$this->conn['main']->query("update mall_transaction_item set discount = $price_discount where transaction_id = $id");
							}
						}
					} else {
						$price_discount = 0;
						if (!empty($get_diskon)) {
							if ($get_diskon <= 100) {
								$price_discount = $product['variant_price']['harga'] * $get_diskon / 100;
							} else {
								$price_discount = $get_diskon;
							}
						}

						if (!empty($get_voucher)) {
							if ($get_voucher <= 100) {
								$price_discount = $product['variant_price']['harga'] * $get_voucher / 100;
							} else {
								$price_discount = $get_voucher;
							}
						}

						$this->conn['main']->query("INSERT INTO `" . $this->tables['transaction_item'] . "` SET
              				`transaction_id` = '{$id}',
							`price` = '" . (float) $product['variant_price']['harga'] . "',
              				`discount` = '$price_discount',
              				`quantity` = '1',
							`product_data` = '" . json_encode($product) . "',
							`variant_id` = " . (!empty($product['variant_price']['id']) ? "(SELECT `" . $this->tables['jasa_price'] . "`.`id` FROM `" . $this->tables['jasa_price'] . "` WHERE SHA1(CONCAT(`" . $this->tables['jasa_price'] . "`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $product['variant_price']['id'] . "')"  : "NULL") . "
              				");
					}
				}

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
			$active = isset($params['active']);
			unset($params['active']);

			$user_id = $this->conn['main']->query("select partner_id from " . $this->tables['user'] . " where ecommerce_token = '$token'")->row();
		}

		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['transaction']);
		$cond_query_ppob = '';
		$sqlPPOB = '';
		// $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['transaction']);
		$order_query    = 'ORDER BY created_at DESC';
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		if (!empty($token)) {
			$cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['transaction'] . "`.`order_id` IN (SELECT `" . $this->tables['order'] . "`.`id` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`user_id` = (SELECT `" . $this->tables['user'] . "`.`partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%{$token}')))";

			if (empty($params['ENCRYPTED::id']) && empty($params['ENCRYPTED::order_id'])) {
				if (!empty($active)) {
					if ($active == 'active') {
						$cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "( `mall_transaction`.`transaction_status_id` NOT IN ( '4', '5', '6' )
						OR (
							`mall_transaction`.`transaction_status_id` IN ('5') 
							AND `mall_transaction`.`order_id` IN ( SELECT `mall_order`.`id` FROM `mall_order` WHERE `mall_order`.`payment_status` = 'paid' ) ) )";

						$cond_query_ppob = "AND ppob_order.status NOT IN ('success', 'failed', 'paid')";
					}
				} else {
					$cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "( `mall_transaction`.`transaction_status_id` = 4)";
					$cond_query_ppob = "AND ppob_order.status NOT IN ('pending', 'process')";
				}
				$sqlPPOB = "UNION ALL (SELECT
					`ppob_order`.`id` AS `id`,
					`ppob_order`.`id` AS `order_id`,
					null AS `merchant_id`,
					null AS `address_data`,
					null AS `shipping_data`,
					0 AS `shipping_cost`,
					null AS `shipping_number`,
					null AS `dropship_data`,
					null AS `transaction_status_id`,
					0 AS `status_review`,
					null AS `start_time`,
					null AS `end_time`,
					null AS `note_cancel`,
					0 AS `diskon_voucher`,
					`ppob_order`.`id` AS `id`,
					`ppob_order`.`id` AS `order_id`,
					null AS `merchant_id`,
					null AS `transaction_item`,
					null AS `transaction_status_id`,
					null AS `transaction_status_name`,
					null AS `transaction_status_description`,
					`ppob_order`.`jumlah_bayar` AS `total_price`,
					0 AS `total_discount`,
					1 AS `total_item`,
					1 AS `total_quantity`,
					'".$token."' AS `user_id`,
					`ppob_order`.`type` AS `service_type`,
					`ppob_order`.`refid` AS `invoice_code`,
					`ppob_order`.`payment_method` AS `payment_code`,
					null AS `payment_name`,
					`ppob_order`.`status` AS `payment_status`,
					`ppob_order`.`payment_method` AS `payment_channel_id`,
					`ppob_order`.`payment_data` AS `payment_data`,
					`ppob_order`.`dtp_transaction_date` AS `created_at`,
					`ppob_order`.`dtp_transaction_date` AS `modified_at`,
					null AS `send_at`,
					`ppob_order`.`dtp_transaction_date` AS `shipping_date`
					FROM `ppob_order`
					WHERE `user_id` = ".$user_id->partner_id." ". $cond_query_ppob ."
					)
				";
			}
		}


		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`" . $this->tables['transaction'] . "`.*,
					SHA1(CONCAT(`" . $this->tables['transaction'] . "`.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
					SHA1(CONCAT(`" . $this->tables['transaction'] . "`.`order_id`, '" . $this->config->item('encryption_key') . "')) AS `order_id`,
					SHA1(CONCAT(`" . $this->tables['transaction'] . "`.`merchant_id`, '" . $this->config->item('encryption_key') . "')) AS `merchant_id`,
					(SELECT GROUP_CONCAT(ti.`id` SEPARATOR ',') FROM `" . $this->tables['transaction_item'] . "` ti WHERE ti.`id` != 0 AND ti.`id` AND ti.`transaction_id` = `" . $this->tables['transaction'] . "`.`id`) AS `transaction_item`,
					SHA1(CONCAT(`" . $this->tables['transaction'] . "`.`transaction_status_id`, '" . $this->config->item('encryption_key') . "')) AS `transaction_status_id`,
					(SELECT `" . $this->tables['transaction_status'] . "`.`name` FROM `" . $this->tables['transaction_status'] . "` WHERE `" . $this->tables['transaction_status'] . "`.`id` = `" . $this->tables['transaction'] . "`.`transaction_status_id`) AS `transaction_status_name`,
					(SELECT `" . $this->tables['transaction_status'] . "`.`description` FROM `" . $this->tables['transaction_status'] . "` WHERE `" . $this->tables['transaction_status'] . "`.`id` = `" . $this->tables['transaction'] . "`.`transaction_status_id`) AS `transaction_status_description`,
					(SELECT SUM(`" . $this->tables['transaction_item'] . "`.`price`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = `" . $this->tables['transaction'] . "`.`id`) AS `total_price`,
					(SELECT SUM(`" . $this->tables['transaction_item'] . "`.`discount`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = `" . $this->tables['transaction'] . "`.`id`) AS `total_discount`,
					(SELECT COUNT(`" . $this->tables['transaction_item'] . "`.`id`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = `" . $this->tables['transaction'] . "`.`id`) AS `total_item`,
					(SELECT SUM(`" . $this->tables['transaction_item'] . "`.`quantity`) FROM `" . $this->tables['transaction_item'] . "` WHERE `" . $this->tables['transaction_item'] . "`.`transaction_id` = `" . $this->tables['transaction'] . "`.`id`) AS `total_quantity`,
					(SELECT SHA1(CONCAT(`" . $this->tables['order'] . "`.`user_id`, '" . $this->config->item('encryption_key') . "')) FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `user_id`,
					(SELECT `" . $this->tables['order'] . "`.`service_type` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `service_type`,
					(SELECT `" . $this->tables['order'] . "`.`invoice_code` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `invoice_code`,
					(SELECT `" . $this->tables['order'] . "`.`payment_code` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `payment_code`,
					(SELECT `payment_name` FROM `" . $this->tables['payment_gateway'] . "` WHERE `" . $this->tables['payment_gateway'] . "`.`payment_code` = (SELECT `" . $this->tables['order'] . "`.`payment_code` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`)) AS `payment_name`,
					(SELECT `" . $this->tables['order'] . "`.`payment_status` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `payment_status`,
					(SELECT `" . $this->tables['order'] . "`.`payment_channel_id` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `payment_channel_id`,
					(SELECT `" . $this->tables['order'] . "`.`payment_data` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `payment_data`,
					(SELECT `" . $this->tables['order'] . "`.`created_at` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `created_at`,
					(SELECT `" . $this->tables['order'] . "`.`modified_at` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `modified_at`,
					(SELECT `" . $this->tables['order'] . "`.`send_at` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `send_at`,
					(SELECT `" . $this->tables['order'] . "`.`shipping_date` FROM `" . $this->tables['order'] . "` WHERE `" . $this->tables['order'] . "`.`id` = `" . $this->tables['transaction'] . "`.`order_id`) AS `shipping_date`
					FROM `" . $this->tables['transaction'] . "`" . $cond_query . $sqlPPOB . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();
		// echo $this->conn['main']->last_query();
		// die;

		// CONDITION for QUERY result
		if ($query) {
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row) {
				$row['address_data'] = json_decode(preg_replace("!\r?\n!", "", $row['address_data']), 1);
				$row['shipping_data'] = json_decode(preg_replace("!\r?\n!", "", $row['shipping_data']), 1);
				$row['dropship_data'] = json_decode(preg_replace("!\r?\n!", "", $row['dropship_data']), 1);
				if ($row['payment_channel_id'] == 6 && $row['payment_data'] != '') {
					$row['payment_data'] = substr($row['payment_data'], 1, -1);
				}
				$row['payment_data'] = json_decode(preg_replace("!\r?\n!", "", $row['payment_data']), 1);

				if ($row['service_type'] == 'super_clean') {
					if ($row['total_discount'] != 0) {
						$row['total_discount'] = strval($row['total_discount'] / $row['total_quantity']);
					}
				} elseif ($row['service_type'] == 'ecommerce') {
					if ($row['total_discount'] != 0) {
						$row['total_discount'] = strval($row['total_discount'] / $row['total_item']);
					}
				}
				$row['price_after_discount'] = strval($row['total_price'] + $row['shipping_cost'] - $row['total_discount']);

				// unset payment_data
				// unset($row['payment_data']['merchantCode']);
				// unset($row['payment_data']['statusCode']);
				// unset($row['payment_data']['statusMessage']);

				// GET MITRA
				$get_mitra_data = $this->conn['main']->query("
				select a.full_name, a.img, a.mobile_number, a.vaksin, a.vaksin_verified,
				(select sum(rate) from mitra_rating where SHA1(CONCAT(a.`partner_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['merchant_id'] . "') as rate,
				(select count(rate) from mitra_rating where SHA1(CONCAT(a.`partner_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['merchant_id'] . "') as total_order,
				(select distance from order_to_mitra where SHA1(CONCAT(a.`partner_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['merchant_id'] . "' and SHA1(CONCAT(order_to_mitra.`order_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['order_id'] . "' and order_to_mitra.status_order in ('confirm','completed')) as distance
				from " . $this->tables['user'] . " a
				where SHA1(CONCAT(a.`partner_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['merchant_id'] . "'
				")->row();

				if (!empty($get_mitra_data)) {
					if (!empty($get_mitra_data->img) && file_exists($this->config->item('storage_path') . 'user/' . $get_mitra_data->img)) {
						$get_mitra_data->img = $this->config->item('storage_url') . 'user/' . $get_mitra_data->img;
					} else {
						$get_mitra_data->img = $this->config->item('storage_url') . 'user/no-image.png';
					}

					if (!empty($get_mitra_data->vaksin) && file_exists($this->config->item('storage_path') . 'user/' . $get_mitra_data->vaksin)) {
						$get_mitra_data->vaksin = $this->config->item('storage_url') . 'vaksin/' . $get_mitra_data->vaksin;
					} else {
						$get_mitra_data->vaksin = $this->config->item('storage_url') . 'user/no-image.png';
					}

					$rate = (!empty($get_mitra_data->rate)) ? round($get_mitra_data->rate / $get_mitra_data->total_order) : 0;

					$mitra_favorit = $this->conn['main']->query("select * from mitra_favorit 
					where SHA1(CONCAT(`user_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['user_id'] . "'
					and SHA1(CONCAT(`mitra_id`, '" . $this->config->item('encryption_key') . "')) = '" . $row['merchant_id'] . "'
					")->row();

					if ($mitra_favorit) {
						$is_favorited = 1;
					} else {
						$is_favorited = 0;
					}

					$data_mitra = array(
						'mitra_name'	=> $get_mitra_data->full_name,
						'mitra_image'	=> $get_mitra_data->img,
						'mitra_phone'	=> $get_mitra_data->mobile_number,
						'vaksin'		=> $get_mitra_data->vaksin,
						'vaksin_verified'	=> $get_mitra_data->vaksin_verified,
						'distance'		=> number_format((float) $get_mitra_data->distance, 1),
						'rate'			=> $rate,
						'is_favorited'	=> $is_favorited
					);
					$row['mitra_data'] = array($data_mitra);
				} else {
					$row['mitra_data'] = array();
				}

				// ITEM
				if (!empty($row['transaction_item'])) {
					$get_transaction_item = $this->conn['main']->query("
						SELECT
						SHA1(CONCAT(ti.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
						ti.`price`,
						ti.`discount`,
						ti.`quantity`,
						ti.`product_data`,
						SHA1(CONCAT(ti.`variant_id`, '" . $this->config->item('encryption_key') . "')) AS `variant_id`,
						ti.`note`
						FROM `" . $this->tables['transaction_item'] . "` ti WHERE ti.`id` != 0 AND ti.`id` IN (" . $row['transaction_item'] . ")
					")->result_array();

					$row['transaction_item'] = array();

					if ($get_transaction_item) {
						foreach ($get_transaction_item as $transaction_item) {
							$transaction_item['product_data'] = json_decode(preg_replace("!\r?\n!", "", $transaction_item['product_data']), 1);

							if (!empty($transaction_item['variant_id']) && !empty($transaction_item['product_data']['product_variant'])) {
								foreach ($transaction_item['product_data']['product_variant'] as $variant) {
									if ($transaction_item['variant_id'] == $variant['id']) {
										$transaction_item['product_data']['name'] = $transaction_item['product_data']['name'] . ' / ' . $variant['name'];
									} else {
										$transaction_item['product_data']['name'] = $transaction_item['product_data']['name'] . ' / ' . $transaction_item['product_data']['price_unit'];
									}
								}
							}
							$row['transaction_item'][] = $transaction_item;
						}
					}
				} else {
					$row['transaction_item'] = array();
				}

				// Assign row to data
				$data[] = $row;
			}


			// GET summary data for RESPONSE
			if (!empty($token)) {
				$params['user_id'] = $user_id->partner_id;
			}
			$query_total_filter = "
				select mall_transaction.id from " . $this->tables['transaction'] . " mall_transaction
				left join " . $this->tables['order'] . " mall_order on mall_transaction.order_id = mall_order.id
				$cond_query
				";
				
			$total_filter = $this->conn['main']->query($query_total_filter)->num_rows();
			
			if ($sqlPPOB == '') {
				$total_filter_ppob = null;
			} else {
				$query_total_filter_ppob = "SELECT id FROM ppob_order WHERE user_id = ".$user_id->partner_id." ". $cond_query_ppob ."";
				$total_filter_ppob = $this->conn['main']->query($query_total_filter_ppob)->num_rows();
			}

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (($total_filter) ? $total_filter : 0) + (($total_filter_ppob) ? $total_filter_ppob : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['transaction']);

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

	public function create_review_transaction($params = array())
	{
		if (!empty($params['token']) && !empty($params['transaction_id'])) {
			// SET reconciliation parameters
			$token      = $params['token'];
			$transaction_id = $params['transaction_id'];

			// Set request params
			$request = $params;
			unset($request['token']);
			unset($request['transaction_id']);

			// GET Transactions
			$sql = "SELECT * FROM " . $this->tables['transaction'] . " WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $transaction_id . "'";
			$get_transaction = $this->conn['main']->query($sql)->row();
			if ($get_transaction->status_review == '1') {
				$this->set_response('code', 400);
				$this->set_response('message', 'Anda sudah mereview transaksi ini');
			} else {

				$request['created_at'] = date('Y-m-d H:i:s');

				// SET query data preparation
				$field_to_set = $this->build_field($this->conn['main'], $this->tables['transaction_review'], $request);

				// SET reconciliation field
				$field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')";
				$field_to_set .= ", `transaction_id` = (SELECT `id` FROM `" . $this->tables['transaction'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $transaction_id . "')";

				$field_to_set = ltrim($field_to_set, ", ");

				// QUERY process
				$sql = "INSERT INTO `" . $this->tables['transaction_review'] . "` SET " . $field_to_set;
				$query = $this->conn['main']->simple_query($sql);

				// UPDATE STATUS_REVIEW
				$this->conn['main']->query("UPDATE `" . $this->tables['transaction'] . "` SET `status_review` = '1' WHERE
              `id` = (SELECT * FROM (SELECT `id` FROM `" . $this->tables['transaction'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $transaction_id . "')mallTransaction)");

				// CONDITION for QUERY result
				if ($query) {
					$insert_id = $this->conn['main']->insert_id();
					$id = $insert_id;

					$temp_path = $this->config->item('storage_path') . 'review/';

					$config['upload_path']      = $temp_path;
					$config['allowed_types']  = 'jpg|jpeg|png';
					$config['max_size']       = 4096; // 4 MB
					$config['file_name'] = md5(time() . uniqid());

					$this->load->library('upload', $config);
					$this->upload->initialize($config);

					$jumlah_img = count($_FILES['img']['name']);
					for ($i = 0; $i < $jumlah_img; $i++) {
						if (!empty($_FILES['img']['name'][$i])) {

							$_FILES['file']['name'] = $_FILES['img']['name'][$i];
							$_FILES['file']['type'] = $_FILES['img']['type'][$i];
							$_FILES['file']['tmp_name'] = $_FILES['img']['tmp_name'][$i];
							$_FILES['file']['error'] = $_FILES['img']['error'][$i];
							$_FILES['file']['size'] = $_FILES['img']['size'][$i];

							if ($this->upload->do_upload('file')) {
								$uploadData = $this->upload->data();
								$data['review_id'] = $id;
								$data['img'] = $uploadData['file_name'];

								$query = $this->conn['main']->insert($this->tables['transaction_review_image'], $data);
							} else {
								$this->set_response('code', 400);
								$this->set_response('message', $this->upload->display_errors('', ''));
								return $this->get_response();
							}
						}
					}
					// SET RESPONSE data
					$this->set_response('code', 200);
				} else {
					$this->set_response('', $this->conn['main']->error());
				}
			}
		} else {
			$this->set_response('code', 400);
		}

		return $this->get_response();
	}

	public function get_review_transaction($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['transaction_review']);
		$order_query    = $this->build_order($this->conn['main'], $params, $this->tables['transaction_review']);
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		$sql = "
				SELECT 
				SHA1(CONCAT(" . $this->tables['transaction_review'] . ".`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
				SHA1(CONCAT(u.`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `user_id`,
				u.full_name, u.img as img_user,
				" . $this->tables['transaction_review'] . ".comment, " . $this->tables['transaction_review'] . ".rate,
				(SELECT GROUP_CONCAT(ir.`id` SEPARATOR ',') FROM `" . $this->tables['transaction_review_image'] . "` ir WHERE ir.`id` != 0 AND ir.`id` AND ir.`review_id` = `" . $this->tables['transaction_review'] . "`.`id`) AS `image_review`
				";
		$selection_query = " FROM " . $this->tables['transaction_review'] . " LEFT JOIN " . $this->tables['user'] . " u on " . $this->tables['transaction_review'] . ".user_id = u.partner_id";

		$query = $this->conn['main']->query($sql . $selection_query . $cond_query . $order_query . $limit_query)->result_array();

		if ($query) {
			$data = array();

			foreach ($query as $key => $row) {
				if (!empty($row['img_user']) && file_exists($this->config->item('storage_path') . 'user/' . $row['img_user'])) {
					$query[$key]['img_user'] = $this->config->item('storage_url') . 'user/' . $row['img_user'];
				} else {
					$query[$key]['img_user'] = $this->config->item('storage_url') . 'user/no-image.png';
				}

				if (!empty($row['image_review'])) {
					$query[$key]['image_review'] = $this->conn['main']->query("SELECT pv.`img` FROM `" . $this->tables['transaction_review_image'] . "` pv WHERE pv.`id` != 0 AND pv.`id` IN (" . $row['image_review'] . ")")->result_array();
				} else {
					$query[$key]['image_review'] = array();
				}

				foreach ($query[$key]['image_review'] as $key2 => $row2) {
					if (!empty($row2['img']) && file_exists($this->config->item('storage_path') . 'review/' . $row2['img'])) {
						$query[$key]['image_review'][$key2]['img'] = $this->config->item('storage_url') . 'review/' . $row2['img'];
					} else {
						$query[$key]['image_review'][$key2]['img'] = $this->config->item('storage_url') . 'review/no-image.png';
					}
				}
			}
			$data = $query;

			$total_filter = $this->conn['main']->query($sql . $selection_query . $cond_query)->num_rows();

			$summary['total_show']    = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data']    = (float) $this->conn['main']->count_all($this->tables['transaction_review']);

			// SET RESPONSE data
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data'     => $data,
				'summary' => $summary
			));
		} else {
			$this->set_response('code', 404);
		}
		return $this->get_response();
	}

	public function orderToMitra($id_transaction, $mitra_code = '')
	{
		$firebase = $this->firebase->init();
		$this->db = $firebase->getDatabase();

		$get_transaction 	= $this->conn['main']->query("
		SELECT a.*, c.payment_code, c.penyedia_jasa, c.tipe_customer, c.invoice_code, c.service_type, c.user_id, c.favorited, c.tunanetra
		FROM `" . $this->tables['transaction'] . "` a
		LEFT JOIN " . $this->tables['transaction_item'] . " b on a.id = b.transaction_id
		LEFT JOIN " . $this->tables['order'] . " c on a.order_id = c.id
		WHERE SHA1(CONCAT(`a`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $id_transaction . "'")->row();

		// cek user order ke user_to_mitra
		$get_user = $this->conn['main']
			->select('*')
			->where('user_id', $get_transaction->user_id)
			->get('user_to_mitra')->row();

		if (!empty($get_user) && $get_user->mitra_id != '') {

			$location = (json_decode($get_transaction->address_data));

			$sql = "select a.partner_id, (111.111
              * DEGREES(ACOS(COS(RADIANS(`latitude`))
              * COS(RADIANS(" . $location->latitude . "))
              * COS(RADIANS(`longitude` - " . $location->longitude . ")) + SIN(RADIANS(`latitude`))
			  * SIN(RADIANS(" . $location->latitude . "))))) AS `distance` 
				FROM mitra_current_location a
                WHERE a.partner_id = $get_user->mitra_id
                ";

			$query = $this->conn['main']->query($sql)->result();

			$data_dummy = array(
				'order_id'	=> $get_transaction->order_id,
				'mitra_id'	=> $get_user->mitra_id,
				'distance'	=> $query[0]->distance,
			);
			$this->conn['main']->insert('order_to_mitra', $data_dummy);

			//send push notification order to mitra
			$this->curl->push($get_user->mitra_id, 'Orderan menunggumu', 'Ayo ambil orderanmu sekarang juga', 'order_pending');

			return true;
		} elseif (empty($mitra_code) || $mitra_code == '') {
			$get_mitra_on_orderToMitra	= $this->conn['main']->query("SELECT * FROM `order_to_mitra` WHERE order_id = '$get_transaction->order_id'")->result_array();

			$mitra_id = array_map(function ($value) {
				return $value['mitra_id'];
			}, $get_mitra_on_orderToMitra);

			implode(", ", $mitra_id);
			$mitra_id = join(',', $mitra_id);

			$cond_query = '';
			if (!empty($mitra_id))
				$cond_query = "AND b.partner_id not in ($mitra_id)";

			// get mitra dengan service yang sesuai dengan order
			$data_product = $this->conn['main']->query("
				select b.product_data FROM `" . $this->tables['transaction'] . "` a
				LEFT JOIN " . $this->tables['transaction_item'] . " b on a.id = b.transaction_id
				WHERE SHA1(CONCAT(`a`.`id`,'" . $this->config->item('encryption_key') . "')) = '" . $id_transaction . "'
			")->result();

			foreach ($data_product as $key => $value) {
				$obj = json_decode($value->product_data);
				$sql = "SELECT id FROM " . $this->tables['jasa'] . " WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $obj->id . "'";
				$id_jasa = $this->conn['main']->query($sql)->row();
				$cond_query .= " AND FIND_IN_SET ('$id_jasa->id', c.jasa_id) > 0";
			}

			if ($get_transaction->payment_code == 'cod') {
				// $cond_query .= " AND b.current_deposit >= " . $product_data->variant_price->harga * 30 / 100;
				// $cond_query .= " AND b.current_deposit >= -50000";
				$cond_query .= " AND b.current_deposit >= 10000";
			}

			if ($get_transaction->penyedia_jasa == 'W') {
				$cond_query .= " AND b.jenis_kelamin = 'P'";
			} elseif ($get_transaction->penyedia_jasa == 'P') {
				$cond_query .= " AND b.jenis_kelamin = 'L'";
			}

			if ($get_transaction->tipe_customer == 'W') {
				$cond_query .= " AND b.tipe_customer in ('P','T')";
			} elseif ($get_transaction->tipe_customer == 'P') {
				$cond_query .= " AND b.tipe_customer in ('L','T')";
			}

			if ($get_transaction->service_type == 'massage') {
				if ($get_transaction->tipe_customer == 'T') {
					$cond_query .= " AND b.tipe_customer in ('L','T')";
				}
			}

			if ($get_transaction->tunanetra == '1') {
				$cond_query .= " AND b.tunanetra = '1'";
				$cond_query .= " AND b.partner_id != '4125'";  // partner id mitra admin tunanetra
			} else {
				$cond_query .= " AND b.tunanetra = '0'";
			}

			$cond_query .= " AND b.suspend = '0'";

			if ($get_transaction->favorited != '0') {
				//cek mitra favorit
				$get_mitra_favorit = $this->conn['main']
					->select('*')
					->where('a.user_id', $get_transaction->user_id)
					->where('b.status_active', '1')
					->join('user_partner b', 'a.mitra_id = b.partner_id', 'left')
					->get('mitra_favorit a')->result_array();

				if ($get_mitra_favorit) {
					$mitra_id = array_map(function ($value) {
						return $value['mitra_id'];
					}, $get_mitra_favorit);
					implode(", ", $mitra_id);
					$mitra_id = join(',', $mitra_id);
					$cond_query .= "AND b.partner_id in ($mitra_id)";
				}
			}

			$location = (json_decode($get_transaction->address_data));

			$sql = "select a.partner_id, device_id, b.allowed_distance, (111.111
              * DEGREES(ACOS(COS(RADIANS(`latitude`))
              * COS(RADIANS(" . $location->latitude . "))
              * COS(RADIANS(`longitude` - " . $location->longitude . ")) + SIN(RADIANS(`latitude`))
			  * SIN(RADIANS(" . $location->latitude . "))))) AS `distance`
				FROM mitra_current_location a
				LEFT JOIN user_partner b on a.partner_id = b.partner_id
				LEFT JOIN mitra_jasa c on a.partner_id = c.partner_id
				LEFT JOIN user_partner_device d on d.partner_id = b.partner_id
				WHERE b.status_active = '1'
				AND b.user_type = 'mitra'
				AND b.partner_id NOT IN (select merchant_id from mall_transaction where transaction_status_id = '10')
				" . $cond_query . "
				HAVING distance <= b.allowed_distance
				ORDER BY distance ASC LIMIT 10";

			$query = $this->conn['main']->query($sql)->result();

			if ($query) {

				// kirim data dummy untuk pemicu cronjob dari order yang belum dapat mitra
				$data_dummy = array(
					'order_id'	=> $get_transaction->order_id,
					'mitra_id'	=> 0,
				);
				$this->conn['main']->insert('order_to_mitra', $data_dummy);

				foreach ($query as $row) {
					$data = array(
						'order_id'	=> $get_transaction->order_id,
						'mitra_id'	=> $row->partner_id,
						'distance'	=> round($row->distance, 1),
					);

					$this->conn['main']->insert('order_to_mitra', $data);

					// set true for mitra_id
					$mitra_id_encode = $this->encoded($row->partner_id);
					$this->insert_realtime_database($mitra_id_encode, 'true');

					//send push notification order to mitra
					$this->curl->push($row->partner_id, 'Orderan menunggumu', 'Ayo ambil orderanmu sekarang juga', 'order_pending');
				}
				return true;
			} else {
				if ($get_transaction->favorited == '0' || $get_transaction->favorited == '2') {

					$this->conn['main']
						->set(array('transaction_status_id' => 5, 'note_cancel' => 'lokasi diluar jangkauan mitra'))
						->where('order_id', $get_transaction->order_id)
						->update('mall_transaction');

					$this->conn['main']
						->set(array('payment_status' => 'cancel'))
						->where('id', $get_transaction->order_id)
						->update('mall_order');

					$this->curl->push($get_transaction->user_id, 'Orderan ' . $get_transaction->invoice_code . ' batal', 'Belum terdapat mitra pada lokasi anda', 'order_canceled', 'customer');

					$this->load->model('order_model');
					$order_id_encode = $this->order_model->encoded($get_transaction->order_id);
					$this->insert_realtime_database($order_id_encode, 'Di luar jangkauan', 'order');

					return false;
				}
			}
		} else {
			$data = array(
				'order_id'		=> $get_transaction->order_id,
				'mitra_id'		=> $mitra_code,
				'status_order'	=> 'confirm',
				'distance'		=> 0,
			);

			$this->conn['main']->insert('order_to_mitra', $data);
		}
	}

	public function total($params = array())
	{
		return $this->count_rows($this->conn['main'], $this->tables['transaction'], $params);
	}

	function insert_realtime_database($id_order, $status, $key_data = '')
	{
		$data = array(
			$id_order => $status
		);
		if (empty($data) || !isset($data)) {
			return FALSE;
		}

		if ($key_data == 'order') {
			$key_data = 'order';
		} else {
			$key_data = 'coming_order';
		}

		foreach ($data as $key => $value) {
			$this->db->getReference()->getChild($key_data)->getChild($key)->set($value);
		}
		return TRUE;
	}
}

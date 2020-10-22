<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Withdraw_model extends Base_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	function read($params = array())
	{
		$query = $this->conn['main']
			->select('*')
			->select("SHA1(concat(id, '" . $this->config->item('encryption_key') . "')) as id")
			->select("SHA1(concat(user_id, '" . $this->config->item('encryption_key') . "')) as user_id")
			->where($params)
			->get('withdraw_request')->result();

		if ($query) {
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $query,
			));
		} else {
			$this->set_response('code', 404);
		}
		return $this->get_response();
	}

	function create($params)
	{
		$get_user = $this->conn['main']
			->select('a.partner_id, b.created_at')
			->where('a.partner_id', $params['withdraw'])
			->join('withdraw_request b', 'b.user_id = a.partner_id', 'left')
			->get('user_partner a')
			->row();

		if ($get_user) {
			$data = array(
				'amount'		=> $params['amount'],
				'user_id'		=> $get_user->partner_id,
				'bank_id'		=> $params['bank_id'],
				'created_at'	=> $params['created_at'],
				'id_vendor'		=> $params['id_vendor']
			);

			$query = $this->conn['main']->insert('withdraw_request', $data);

			if ($query) {
				$insert_id = $this->conn['main']->insert_id();
				$id = $insert_id;

				// INVOICE
				$this->conn['main']->query("UPDATE `withdraw_request` SET `invoice_code` = 'WD" . date('Ymd') . str_pad($id, 3, '0', STR_PAD_LEFT) . "' WHERE `id` = '{$id}'");

				$invoice_code = $this->conn['main']->select('invoice_code')->where('id', $id)->get('withdraw_request')->row();

				$data['invoice_code'] = $invoice_code->invoice_code;

				// insert to deposit history
				$this->load->library('deposit');

				$get_cost_withdraw = $this->getValue('value', 'global_setting', array('group' => 'price', 'name' => 'withdraw-cost'));
				if ($get_cost_withdraw) {
					$data['amount'] += $get_cost_withdraw;
				} else {
					$data['amount'] += 0;
				}
				$this->deposit->less_deposit_withdraw($data);
				$data = array(
					'user_id'						=> $get_user->partner_id,
					'payment_status'				=> 'pending',
					'substr(created_at, 1, 10) ='	=> date('Y-m-d')
				);
				$read_data = $this->read($data);

				$this->set_response('code', 200);
				// $this->set_response('response', array(
				// 	'data' => $read_data['response']['data']
				// ));
			}
		}
		return $this->get_response();
	}

	function get_bank($params = array())
	{
		$query = $this->conn['main']
			->select('*')
			->select("SHA1(concat(id, '" . $this->config->item('encryption_key') . "')) as id")
			->select("SHA1(concat(partner_id, '" . $this->config->item('encryption_key') . "')) as partner_id")
			->where($params)
			->get('user_bank')->result();

		if ($query) {
			$this->set_response('code', 200);
			$this->set_response('response', array(
				'data' 		=> $query,
			));
		} else {
			$this->set_response('code', 404);
		}
		return $this->get_response();
	}

	function cek_saldo($mitra_id)
	{
		$saldo = $this->conn['main']
			->select('a.current_deposit as total_saldo')
			->where('a.partner_id', $mitra_id)
			->get('user_partner a')->row();

		return $saldo;
	}

	function cek_saldo_lama($mitra_id)
	{
		$saldo = $this->conn['main']
			->select('(a.current_deposit - coalesce(sum(b.amount),0)) as total_saldo')
			->where('a.partner_id', $mitra_id)
			->where('b.payment_status', 'pending')
			->where('substr(created_at, 1, 10) =', date('Y-m-d'))
			->join('withdraw_request b', 'b.user_id = a.partner_id', 'left')
			->get('user_partner a')->row();

		return $saldo;
	}
}

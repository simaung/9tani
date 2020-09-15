<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Payment_model extends Base_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function get_payment_channel($params = array())
    {
        $query = $this->conn['main']
            ->select('*')
            ->from($this->tables['payment_channel'])
            ->where('status_installment', $params['status_installment'])
            ->where_in('provider', $params['provider'])
            ->order_by('sort_order', 'asc')
            ->get()->result_array();

        if ($query) {
            return $query;
        } else {
            return FALSE;
        }
    }

    public function get_payment_channel_id($params = array())
    {
        $query = $this->conn['main']
            ->select('*')
            ->from($this->tables['payment_channel'])
            ->where($params)
            ->order_by('sort_order', 'asc')
            ->get()->result_array();

        if ($query) {
            return $query;
        } else {
            return FALSE;
        }
    }

    public function get_bank_account()
    {
        $query = $this->conn['main']->select('*')
            ->from($this->tables['bank_account'])
            ->where('status', 'on')
            ->get()->result_array();

        if ($query) {
            return $query;
        } else {
            return FALSE;
        }
    }

    public function get_payment_transfer($params = array())
    {
        $query = $this->conn['main']->select('*')
            ->from($this->tables['payment_transfer'])
            ->where($params)
            ->get()->result_array();

        if ($query) {
            return $query;
        } else {
            return FALSE;
        }
    }

    public function set_payment_transfer($data = array(), $params = array())
    {
        if (!empty($params)) {
            $query = $this->conn['main']
                ->set($data)
                ->where($params)
                ->update($this->tables['payment_transfer']);
            $cid = $data['id'];
        } else {
            $query = $this->conn['main']
                ->set($data)
                ->insert($this->tables['payment_transfer']);
            $cid = $this->conn['main']->insert_id();
        }

        $uniq_code = ((strlen($cid) > 3) ? substr($cid, -3) : str_pad($cid, 3, '0', STR_PAD_LEFT));

        if ($query) {
            $data = array(
                'amount'    => $data['amount'] + $uniq_code,
            );

            $query = $this->conn['main']
                ->set($data)
                ->where('id', $cid)
                ->update($this->tables['payment_transfer']);

            return $data['amount'];
        } else {
            return $this->conn['main']->error();
        }
    }
}

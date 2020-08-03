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
        } else {
            $query = $this->conn['main']
                ->set($data)
                ->insert($this->tables['payment_transfer']);
        }

        if ($query) {
            return true;
        } else {
            return $this->conn['main']->error();
        }
    }
}

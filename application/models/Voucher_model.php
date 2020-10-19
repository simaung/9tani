<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Voucher_model extends Base_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function read($params = array(), $action = '')
    {
        $this->conn['main']->select('name, type_product, name, description, term, amount, percent,start_periode, end_periode, min_transaksi');
        $this->conn['main']->where('status_active', '1');
        $this->conn['main']->where('name !=', 'global_discount');
        $this->conn['main']->where('( CASE WHEN start_periode IS NOT NULL THEN NOW() BETWEEN start_periode AND end_periode ELSE start_periode IS NULL END )', null, false);
        if (!empty($params['type_product']))
            $this->conn['main']->where('type_product =', $params['type_product']);

        $query = $this->conn['main']->get('mst_voucher')->result();

        if ($query) {
            $this->set_response('code', 200);
            $this->set_response('response', array(
                'data'     => $query
            ));
        } else {
            $this->set_response('code', 404);
        }
        return $this->get_response();
    }
}

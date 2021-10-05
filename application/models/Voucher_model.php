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
        if (!empty($params['type_product'])) {
            switch ($params['type_product']) {
                case 'tani':
                    $where = "tani = '1'";
                    break;
                case 'massage':
                    $where = "massage = '1'";
                    break;
                case 'clean':
                case 'dsc':
                    $where = "clean = '1'";
                    break;
                default:
                    break;
            }
            $this->conn['main']->where($where);
        }

        $this->conn['main']->select('name, description, term, amount, percent,start_periode, end_periode, min_transaksi, product_id, variant_id, poin');
        $this->conn['main']->where('status_active', '1');
        $this->conn['main']->where('show', '1');
        $this->conn['main']->where('name !=', 'global_discount');
        $this->conn['main']->where('( CASE WHEN start_periode IS NOT NULL THEN NOW() BETWEEN start_periode AND end_periode ELSE start_periode IS NULL END )', null, false);

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

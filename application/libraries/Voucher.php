<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Library Curl
 * Digunakan untuk menggunakan fungsi PHP curl.
 *
 * @author Fajar <delve_brain@hotmail.com>
 */
class Voucher
{

    private $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->model('jasa_model');
        $this->result = array('code' => 200);
    }

    function validation_voucher($req_params)
    {
        $req_params['product_id'] = $this->ci->jasa_model->getValueEncode('id', 'product_jasa', $req_params['product_id']);
        $req_params['variant_id'] = $this->ci->jasa_model->getValueEncode('id', 'product_jasa_price', $req_params['variant_id']);

        $now = date('Y-m-d H:i:s');
        $varWhere = array(
            'name' => strtolower($req_params['voucher_code']),
            'status_active' => '1',
        );
        $data_voucher = $this->ci->jasa_model->getWhere('mst_voucher', $varWhere);
        $data_voucher = $data_voucher[0];
        $this->result['data'] = $data_voucher;

        if (empty($data_voucher) || $data_voucher->status_active == 0 || $data_voucher->type_product == 'tani') {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode promo yang kamu masukkan salah'
            );
        } else {
            if ($data_voucher->start_periode != Null && $data_voucher->end_periode != Null) {
                if ($data_voucher->start_periode <= $now && $data_voucher->end_periode >= $now) {
                    $this->validation_voucher_kedua($req_params);
                } else {
                    $this->result = array(
                        'code' => 400,
                        'message' => 'Kode promo yang kamu masukkan salah'
                    );
                }
            } else {
                $this->validation_voucher_kedua($req_params);
            }
        }
        return $this->result;
    }

    function validation_voucher_kedua($req_params)
    {
        if ($this->result['data']->new_user == '1') {
            $this->result = $this->cek_new_user($req_params);
        } elseif ($this->result['data']->max_order_per_user != Null) {
            $this->result = $this->cek_max_used_voucher($req_params);
        }
    }

    function cek_new_user($req_params)
    {
        $cek_order = $this->ci->jasa_model->getWhere('mall_order', array('user_id' => $req_params['user_id'], 'payment_status' => 'paid'), '1');

        if (count($cek_order) > 0) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode promo hanya berlaku untuk user baru'
            );
        }
        return $this->result;
    }

    function cek_max_used_voucher($req_params)
    {
        $cek_order = $this->ci->jasa_model->getWhere('mall_order', array('user_id' => $req_params['user_id'], 'payment_status' => 'paid', 'voucher_code' => $req_params['voucher_code']));

        if (count($cek_order) >= $this->result['data']->max_order_per_user) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode promo sudah melewati batas penggunaan'
            );
        }
        return $this->result;
    }
}

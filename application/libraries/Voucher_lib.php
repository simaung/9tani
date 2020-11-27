<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Voucher_lib
{

    private $ci;

    public function __construct()
    {
        $this->ci = &get_instance();
        $this->ci->load->model('jasa_model');
        $this->result = array('code' => 200);
    }

    function validation_voucher($req_params, $type_product)
    {
        $req_params['product_id'] = $this->ci->jasa_model->getValueEncode('id', 'product_jasa', $req_params['product_id']);
        $req_params['variant_id'] = $this->ci->jasa_model->getValueEncode('id', 'product_jasa_price', $req_params['variant_id']);

        $now = date('Y-m-d H:i:s');
        $varWhere = array(
            'name' => strtolower($req_params['voucher_code']),
            'status_active' => '1',
        );

        $varWhere = "name = '" . strtolower($req_params['voucher_code']) . "' and status_active = '1' and type_product in('both', '$type_product')";

        $data_voucher = $this->ci->jasa_model->getWhere('mst_voucher', $varWhere);

        if (empty($data_voucher)) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode voucher yang kamu masukkan salah'
            );
        } else {
            $data_voucher = $data_voucher[0];
            $this->result['data'] = $data_voucher;
            if ($data_voucher->start_periode != Null && $data_voucher->end_periode != Null) {
                if ($data_voucher->start_periode <= $now && $data_voucher->end_periode >= $now) {
                    $this->validation_voucher_kedua($req_params);
                } else {
                    $this->result = array(
                        'code' => 400,
                        'message' => 'Kode voucher yang kamu masukkan salah'
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
        $this->return = TRUE;

        if ($this->result['data']->new_user == '1' && $this->return == TRUE) {
            $this->result = $this->cek_new_user($req_params);
        }

        if ($this->result['data']->limit_voucher_per_user != Null && $this->return == TRUE) {
            $this->result = $this->limit_voucher_per_user($req_params);
        }

        if ($this->result['data']->max_used_voucher != Null && $this->return == TRUE) {
            $this->result = $this->cek_max_used_voucher($req_params);
        }

        if ($this->result['data']->min_transaksi != Null && $this->return == TRUE) {
            $this->result = $this->cek_min_transaksi($req_params);
        }

        if ($this->result['data']->day != Null && $this->return == TRUE) {
            // $this->result = $this->cek_day($req_params);
            $this->result = $this->cek_week($req_params);
        }
    }

    function cek_new_user($req_params)
    {
        $cek_order = $this->ci->jasa_model->getWhere('mall_order', array('user_id' => $req_params['user_id'], 'payment_status' => 'paid'), '1');

        if (count($cek_order) > 0) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode promo hanya berlaku untuk user baru',
                'data'    =>  $this->result['data']
            );
            $this->return = FALSE;
        }
        return $this->result;
    }

    function cek_max_used_voucher($req_params)
    {
        $cek_order = $this->ci->jasa_model->getWhere('mall_order', array('payment_status' => 'paid', 'voucher_code' => $req_params['voucher_code']));

        if (count($cek_order) >= $this->result['data']->max_used_voucher && $this->result['data']->max_used_voucher != 0) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode promo sudah melewati batas limit penggunaan',
                'data'    =>  $this->result['data']
            );
            $this->return = FALSE;
        }
        return $this->result;
    }

    function limit_voucher_per_user($req_params)
    {
        $cek_order = $this->ci->jasa_model->getWhere('mall_order', array('user_id' => $req_params['user_id'], 'payment_status' => 'paid', 'voucher_code' => $req_params['voucher_code']));

        if (count($cek_order) >= $this->result['data']->limit_voucher_per_user && $this->result['data']->limit_voucher_per_user != 0) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode promo sudah melewati batas limit penggunaan',
                'data'    =>  $this->result['data']
            );
            $this->return = FALSE;
        }
        return $this->result;
    }

    function cek_min_transaksi($req_params)
    {
        $cek_price = $this->ci->jasa_model->getWhere('product_jasa_price', array('id' => $req_params['variant_id']));

        if ($cek_price[0]->harga <= $this->result['data']->min_transaksi) {
            $this->result = array(
                'code' => 400,
                'message' => 'Total harga transaksi tidak mencukupi untuk voucher ini.',
                'data'    =>  $this->result['data']
            );
            $this->return = FALSE;
        }
        return $this->result;
    }

    function cek_day($req_params)
    {
        $day = explode(',', $this->result['data']->day);

        if (!in_array(strtolower(date('l')), $day)) {
            $this->result = array(
                'code' => 400,
                'message' => 'Kode voucher tidak berlaku untuk hari ' . nama_hari(date('l')),
            );
            $this->return = FALSE;
        }
        return $this->result;
    }

    function cek_week($req_params)
    {
        if ($this->result['data']->day == 'weekday') {
            $week = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
        } elseif ($this->result['data']->day == 'weekend') {
            $week = array('saturday', 'sunday');
        };

        if (!in_array(strtolower(date('l')), $week)) {
            $this->result = array(
                'code' => 400,
                // 'message' => 'Kode voucher tidak berlaku untuk hari ' . nama_hari(date('l')),
                'message' => 'Kode voucher yang kamu masukkan salah',
                'data'    =>  $this->result['data']
            );
            $this->return = FALSE;
        }
        return $this->result;
    }
}

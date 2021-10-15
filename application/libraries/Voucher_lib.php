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

    function validation_voucher($req_params)
    {
        if (!empty($req_params['item'])) {
            if ($req_params['type_product'] == 'dsc') {
                $harga = 0;
                foreach ($req_params['item'] as $key => $value) {
                    if (!empty($value['variant_id'])) {
                        $variant_id = $this->ci->jasa_model->getValueEncode('id', 'product_jasa_price', $value['variant_id']);
                        $price = $this->ci->jasa_model->getWhere('product_jasa_price', array('id' => $variant_id));
                        $harga += ($price[0]->harga * $value['quantity']);
                    } else {
                        $product_id = $this->ci->jasa_model->getValueEncode('id', 'product_jasa', $value['product_id']);
                        $price = $this->ci->jasa_model->getWhere('product_jasa', array('id' => $product_id));
                        $price = (!empty($price[0]->price_discount) ? $price[0]->price_discount : $price[0]->price_selling);
                        $harga += ($price * $value['quantity']);
                    }
                }
                $req_params['price'] = $harga;
            } elseif ($req_params['type_product'] == 'tani') {
                $harga = 0;
                foreach ($req_params['item'] as $key => $value) {
                    if (!empty($value['variant_id'])) {
                        $variant_id = $this->ci->jasa_model->getValueEncode('id', 'mall_product_variant', $value['variant_id']);
                        $price = $this->ci->jasa_model->getWhere('mall_product_variant', array('id' => $variant_id));
                        $harga += ($price[0]->stock * $value['quantity']);
                    } else {
                        $product_id = $this->ci->jasa_model->getValueEncode('id', 'mall_product', $value['product_id']);
                        $price = $this->ci->jasa_model->getWhere('mall_product', array('id' => $product_id));
                        $price = (!empty($price[0]->price_discount) ? $price[0]->price_discount : $price[0]->price_selling);
                        $harga += ($price * $value['quantity']);
                    }
                }
                $req_params['price'] = $harga;
            }
        } else {
            if ($req_params['type_product'] == 'kita') {
                $req_params['product_id'] = $this->ci->jasa_model->getValueEncode('id', 'product_jasa', $req_params['product_id']);
                $req_params['variant_id'] = $this->ci->jasa_model->getValueEncode('id', 'product_jasa_price', $req_params['variant_id']);

                $req_params['price'] = $this->ci->jasa_model->getWhere('product_jasa_price', array('id' => $req_params['variant_id']));
            }
        }

        unset($req_params['item']);

        $now = date('Y-m-d H:i:s');
        $varWhere = "name = '" . strtolower($req_params['voucher_code']) . "' and status_active = '1' and type_product in('both', '" . $req_params['type_product'] . "')";
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

        if ($this->result['data']->poin > 0) {
            $this->result = $this->cek_poin_user($req_params);
        }

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

    function cek_poin_user($req_params)
    {
        $poin_user = $this->ci->jasa_model->getValue('point', 'user_partner', array('partner_id' => $req_params['user_id']));
        if ($poin_user < $this->result['data']->poin) {
            $this->result = array(
                'code' => 400,
                'message' => 'Point anda kurang',
                'data'    =>  $this->result['data']
            );
            $this->return = FALSE;
        }
        return $this->result;
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
        if ($req_params['price'] <= $this->result['data']->min_transaksi) {
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

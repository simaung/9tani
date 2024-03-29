<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Deposit
{

    public function __construct()
    {
        // Assign the CodeIgniter super-object
        $this->CI = &get_instance();
    }

    public function add_deposit($data_order)
    {
        $this->CI->load->model('order_model');
        $cond = array(
            'partner_id' => $data_order->mitra_id,
        );
        $get_current_deposit = $this->CI->order_model->getValue('current_deposit', 'user_partner', $cond);
        $stat_tunanetra = $this->CI->order_model->getValue('tunanetra', 'user_partner', $cond);

        $where['group'] = 'deposit';
        if ($stat_tunanetra == '0') {
            $where['name'] = 'komisi-mitra';
        } else {
            $where['name'] = 'komisi-mitra-tunanetra';
        }

        // $where = array(
        //     'group' => 'deposit',
        //     'name' => 'komisi-mitra',
        // );
        $get_persentase_komisi = $this->CI->order_model->getValue('value', 'global_setting', $where);

        $komisi = $data_order->price * $get_persentase_komisi / 100;

        // insert deposit history to mitra
        $data = array(
            'partner_id'                => $data_order->mitra_id,
            'payment_date'              => date('Y-m-d H:i:s'),
            'payment_amount'            => $komisi,
            'payment_last_deposit'      => $get_current_deposit,
            'payment_type'              => 'debet',
            'payment_referensi'         => $data_order->invoice_code,
            'payment_status'            => 'ok',
            'payment_message'           => "Penambahan saldo " . ($get_persentase_komisi) . "% dari transaksi sebesar Rp. " . number_format($data_order->price, 2, ',', '.')
        );

        $save = $this->CI->order_model->save($data, 'deposit_history');

        $data_update = array(
            'current_deposit'   => $get_current_deposit + $komisi
        );

        $this->CI->order_model->update_data(array('partner_id' => $data_order->mitra_id), $data_update, 'user_partner');

        //send wa
        $get_user = $this->CI->order_model->getWhere('user_partner', array('partner_id' => $data_order->mitra_id));
        $this->CI->send->index('saldo', $get_user[0]->mobile_number, $get_user[0]->full_name, '', '', '', $komisi, 'bertambah', $data['payment_message']);
    }

    public function less_deposit($data_order)
    {
        $this->CI->load->model('order_model');
        $cond = array(
            'partner_id' => $data_order->mitra_id,
        );
        $get_current_deposit = $this->CI->order_model->getValue('current_deposit', 'user_partner', $cond);
        $stat_tunanetra = $this->CI->order_model->getValue('tunanetra', 'user_partner', $cond);

        $where['group'] = 'deposit';
        if ($stat_tunanetra == '0') {
            $where['name'] = 'komisi-mitra';
        } else {
            $where['name'] = 'komisi-mitra-tunanetra';
        }

        // $where = array(
        //     'group' => 'deposit',
        //     'name' => 'komisi-mitra',
        // );


        $get_persentase_komisi = $this->CI->order_model->getValue('value', 'global_setting', $where);

        $komisi = $data_order->price * (100 - $get_persentase_komisi) / 100;

        // insert deposit history to mitra
        $data = array(
            'partner_id'                => $data_order->mitra_id,
            'payment_date'              => date('Y-m-d H:i:s'),
            'payment_amount'            => $komisi,
            'payment_last_deposit'      => $get_current_deposit,
            'payment_type'              => 'kredit',
            'payment_referensi'         => $data_order->invoice_code,
            'payment_status'            => 'ok',
            'payment_message'           => "Pengurangan saldo " . (100 - $get_persentase_komisi) . "%  dari transaksi tunai sebesar Rp. " . number_format($data_order->price, 2, ',', '.')
        );

        $save = $this->CI->order_model->save($data, 'deposit_history');

        $data_update = array(
            'current_deposit'   => $get_current_deposit - $komisi
        );

        $this->CI->order_model->update_data(array('partner_id' => $data_order->mitra_id), $data_update, 'user_partner');

        //send wa
        $get_user = $this->CI->order_model->getWhere('user_partner', array('partner_id' => $data_order->mitra_id));
        $this->CI->send->index('saldo', $get_user[0]->mobile_number, $get_user[0]->full_name, '', '', '', $komisi, 'berkurang', $data['payment_message']);
    }

    public function add_deposit_cashback_diskon($data_order)
    {
        $this->CI->load->model('order_model');
        $cond = array(
            'partner_id' => $data_order->mitra_id,
        );
        $get_current_deposit = $this->CI->order_model->getValue('current_deposit', 'user_partner', $cond);

        $where = array(
            'group' => 'deposit',
            'name' => 'komisi-mitra',
        );

        $get_persentase_komisi = $data_order->discount / $data_order->price * 100;

        $komisi = $data_order->price * $get_persentase_komisi / 100;

        // insert deposit history to mitra
        $data = array(
            'partner_id'                => $data_order->mitra_id,
            'payment_date'              => date('Y-m-d H:i:s'),
            'payment_amount'            => $komisi,
            'payment_last_deposit'      => $get_current_deposit,
            'payment_type'              => 'debet',
            'payment_referensi'         => $data_order->invoice_code,
            'payment_status'            => 'ok',
            'payment_message'           => "Cashback " . ($get_persentase_komisi) . "% dari transaksi tunai dengan promo diskon sebesar Rp. " . number_format($data_order->price, 2, ',', '.')
        );

        $save = $this->CI->order_model->save($data, 'deposit_history');

        $data_update = array(
            'current_deposit'   => $get_current_deposit + $komisi
        );

        $this->CI->order_model->update_data(array('partner_id' => $data_order->mitra_id), $data_update, 'user_partner');

        //send wa
        $get_user = $this->CI->order_model->getWhere('user_partner', array('partner_id' => $data_order->mitra_id));
        $this->CI->send->index('saldo', $get_user[0]->mobile_number, $get_user[0]->full_name, '', '', '', $komisi, 'bertambah', $data['payment_message']);
    }

    public function topup_deposit($data_topup)
    {
        $this->CI->load->model('order_model');
        $cond = array(
            'partner_id' => $data_topup->user_id,
        );
        $get_current_deposit = $this->CI->order_model->getValue('current_deposit', 'user_partner', $cond);

        // insert deposit history to mitra
        $data = array(
            'partner_id'                => $data_topup->user_id,
            'payment_date'              => date('Y-m-d H:i:s'),
            'payment_amount'            => $data_topup->amount,
            'payment_last_deposit'      => $get_current_deposit,
            'payment_type'              => 'debet',
            'payment_referensi'         => $data_topup->invoice_code,
            'payment_status'            => 'ok',
            'payment_message'           => "Topup saldo"
        );

        $save = $this->CI->order_model->save($data, 'deposit_history');

        $data_update = array(
            'current_deposit'   => $get_current_deposit + $data_topup->amount
        );

        $this->CI->order_model->update_data(array('partner_id' => $data_topup->user_id), $data_update, 'user_partner');

        $this->CI->curl->push($data_topup->user_id, 'Topup Saldo', 'Selamat proses topup saldo anda berhasil', 'topup_saldo');

        //send wa
        $get_user = $this->CI->order_model->getWhere('user_partner', array('partner_id' => $data_topup->user_id));
        $this->CI->send->index('saldo', $get_user[0]->mobile_number, $get_user[0]->full_name, '', '', '', $data_topup->amount, 'bertambah', $data['payment_message']);
    }

    public function less_deposit_withdraw($data_withdraw)
    {
        $this->CI->load->model('order_model');
        $cond = array(
            'partner_id' => $data_withdraw['user_id'],
        );
        $get_current_deposit = $this->CI->order_model->getValue('current_deposit', 'user_partner', $cond);

        // insert deposit history to mitra
        $data = array(
            'partner_id'                => $data_withdraw['user_id'],
            'payment_date'              => $data_withdraw['created_at'],
            'payment_amount'            => $data_withdraw['amount'],
            'payment_last_deposit'      => $get_current_deposit,
            'payment_type'              => 'kredit',
            'payment_referensi'         => $data_withdraw['invoice_code'],
            'payment_status'            => 'pending',
            'payment_message'           => 'penarikan saldo'
        );

        $save = $this->CI->order_model->save($data, 'deposit_history');

        $data_update = array(
            'current_deposit'   => $get_current_deposit - $data_withdraw['amount']
        );

        $this->CI->order_model->update_data(array('partner_id' => $data_withdraw['user_id']), $data_update, 'user_partner');

        //send wa
        // $get_user = $this->CI->order_model->getWhere('user_partner', array('partner_id' => $data_withdraw->user_id));
        // $this->CI->send->index('saldo', $get_user[0]->mobile_number, $get_user[0]->full_name, '', '', '', $komisi, 'berkurang', $data['payment_message']);
    }

    public function back_deposit_withdraw($data_withdraw)
    {
        $this->CI->load->model('order_model');
        $cond = array(
            'partner_id' => $data_withdraw->user_id,
        );
        $get_current_deposit = $this->CI->order_model->getValue('current_deposit', 'user_partner', $cond);

        $data = array(
            'partner_id'                => $data_withdraw->user_id,
            'payment_date'              => $data_withdraw->created_at,
            'payment_amount'            => $data_withdraw->amount,
            'payment_last_deposit'      => $get_current_deposit,
            'payment_type'              => 'debet',
            'payment_referensi'         => $data_withdraw->invoice_code,
            'payment_status'            => 'ok',
            'payment_message'           => 'pengembalian saldo gagal withdraw'
        );

        $save = $this->CI->order_model->save($data, 'deposit_history');

        $data_update = array(
            'current_deposit'   => $get_current_deposit + $data_withdraw->amount
        );

        $this->CI->order_model->update_data(array('partner_id' => $data_withdraw->user_id), $data_update, 'user_partner');

        //send wa
        // $get_user = $this->CI->order_model->getWhere('user_partner', array('partner_id' => $data_withdraw->user_id));
        // $this->CI->send->index('saldo', $get_user[0]->mobile_number, $get_user[0]->full_name, '', '', '', $komisi, 'berkurang', $data['payment_message']);
    }
}

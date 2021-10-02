<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Point
{

    public function __construct()
    {
        // Assign the CodeIgniter super-object
        $this->CI = &get_instance();
    }

    public function add_point($data_point)
    {
        if ($data_point['type'] == 'register') {
            $data = "download = download + 1";
            $sql = "update data_referral set $data where code = '" . $data_point['code'] . "'";
            $this->CI->conn['main']->query($sql);
        } elseif ($data_point['type'] == 'transaksi') {
            $data = "transaksi = transaksi + 1";
            $sql = "update data_referral set $data where code = '" . $data_point['code'] . "'";
            $this->CI->conn['main']->query($sql);
        }

        $get_data_referral = $this->CI->conn['main']
            ->select('*')
            ->where('code', $data_point['code'])
            ->get('data_referral')
            ->row();

        $this->CI->conn['main']->select('*');
        $this->CI->conn['main']->where('referral_id', $get_data_referral->referral_id);

        if ($data_point['type'] == 'register') {
            $this->CI->conn['main']->where('jumlah', $get_data_referral->download);
        } elseif ($data_point['type'] == 'transaksi') {
            $this->CI->conn['main']->where('jumlah', $get_data_referral->transaksi);
        }
        $master_referral_point = $this->CI->conn['main']->get('mstr_referral_point')->row();

        if ($master_referral_point) {
            if ($data_point['type'] == 'register') {
                $point = $master_referral_point->download;
            } elseif ($data_point['type'] == 'transaksi') {
                $point = $master_referral_point->transaksi;
            }
        } else {
            if ($data_point['type'] == 'register') {
                $point = 1;
            } elseif ($data_point['type'] == 'transaksi') {
                $point = 3;
            }
        }

        $data = "poin = poin + " . $point;
        $sql = "update data_referral set $data where code = '" . $data_point['code'] . "'";
        $this->CI->conn['main']->query($sql);

        $point_user = "point = point + " . $point;
        $sql = "update user_partner set $point_user where partner_id = '" . $get_data_referral->user_id . "'";
        $this->CI->conn['main']->query($sql);
    }
}

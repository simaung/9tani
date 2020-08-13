<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cron extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model('order_model');

        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();
    }

    public function expired()
    {
        $get_data = $this->order_model->get_order_expired();
        foreach ($get_data as $row) {
            $this->order_model->set_order_expired($row['id']);
        }
    }

    public function cek_mutasi()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);

        $this->load->model('payment_model');
        $link_url = 'https://bank.appsku.net/api/getMutasi/NGNKbFhSRnZNNmF1L2tHdmhJckNVUT09/';

        $get_payment_transfer = $this->payment_model->get_payment_transfer(array('status' => 'pending'));
        if ($get_payment_transfer) {
            foreach ($get_payment_transfer as $row) {
                $amount = $row['amount'];
                $date = $row['date'];
                $transaction_invoice = $row['transaction_invoice'];

                $get_mutasi = $this->curl->get($link_url . $amount . '/' . $date, '', '', 'true');
                if ($get_mutasi->rest_no == 0) {
                    $this->order_model->set_order_paid($transaction_invoice, json_encode($get_mutasi->mutasi_data));

                    $get_transaction = $this->conn['main']
                        ->select('a.*, b.id as transaction_id, b.merchant_id, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
                        ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                        ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                        ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                        ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                        ->where('invoice_code', $transaction_invoice)
                        ->get('mall_order a')->row();

                    $product = $this->conn['main']
                        ->select('a.*')
                        ->select("SHA1(CONCAT(variant_id, '" . $this->config->item('encryption_key') . "')) as variant_id")
                        ->where('transaction_id', $get_transaction->transaction_id)
                        ->get('mall_transaction_item a')->result_array();

                    foreach ($product as $product_row) {
                        $item = json_decode($product_row['product_data'], true);

                        if ($product_row['variant_id'] != '') {
                            if (!empty($item['product_variant'])) {
                                foreach ($item['product_variant'] as $variant) {
                                    if ($product_row['variant_id'] == $variant['id']) {
                                        $trans_item[] = array(
                                            'name'      => $item['name'],
                                            'unit'      => $variant['name'],
                                            'price'     => $variant['harga'],
                                            'qty'       => $product_row['quantity'],
                                        );
                                    }
                                }
                            }
                        } else {
                            $trans_item[] = array(
                                'name'      => $item['name'],
                                'unit'      => $item['price_unit'],
                                'price'     => $item['price_selling'],
                                'qty'       => $product_row['quantity'],
                            );
                        }

                        // update realtime database
                        if ($get_transaction->service_type != 'ecommerce') {
                            $this->insert_realtime_database($get_transaction->order_id, 'Pesanan sudah dijadwalkan');
                        }

                        $update_status_order = $this->conn['main']
                            ->set(array('status_order' => 'confirm'))
                            ->where('order_id', $get_transaction->id)
                            ->where('mitra_id', $get_transaction->merchant_id)
                            ->update('order_to_mitra');

                        // update merchant_id di mall_order
                        $update_merchant_id = $this->conn['main']
                            ->set(array('merchant_id' => $get_transaction->merchant_id, 'transaction_status_id' => 8))
                            ->where('order_id', $get_transaction->id)
                            ->update('mall_transaction');

                        //send push notification order to mitra
                        $this->curl->push($get_transaction->merchant_id, 'Orderan ' . $transaction_invoice . ' telah dibayar', 'Orderanmu siap di lanjutkan!', 'order_pending');


                        $user_email = $get_transaction->email;
                        $order = $get_transaction;
                        $order_item = $trans_item;

                        $this->send_email_payment_success($order, $order_item, $user_email);
                        $this->send_email_payment_success_image($user_email);
                    }
                }
            }
        }
    }

    public function send_email_payment_success($order, $order_item, $user_email)
    {
        $this->load->library('email');

        $data['order'] = $order;
        $data['order_item'] = $order_item;

        $email_body = $this->load->view('email/payment_success', $data, TRUE);

        $get_email_sender = $this->common_model->get_global_setting(array(
            'group' => 'email',
            'name' => 'post-master'
        ));

        $this->email->from($get_email_sender['value'], '9tani');
        $this->email->to($user_email);
        $this->email->cc('sembilantaniindonesia@gmail.com');

        $this->email->subject('Pembayaran nomor invoice ' . $data['order']->invoice_code . ' berhasil');
        $this->email->message($email_body);

        $this->email->send();
    }

    public function send_email_payment_success_image($user_email)
    {
        $this->load->library('email');

        $email_body = $this->load->view('email/payment_success_image', '', TRUE);

        $get_email_sender = $this->common_model->get_global_setting(array(
            'group' => 'email',
            'name' => 'post-master'
        ));

        $this->email->from($get_email_sender['value'], '9tani');
        $this->email->to($user_email);
        $this->email->bcc('sembilantaniindonesia@gmail.com');

        $this->email->subject('Ucapan terimakasih dari SEMBILAN TANI');
        $this->email->message($email_body);

        $this->email->send();
    }

    function insert_realtime_database($id_order, $status)
    {
        $data = array(
            $id_order => $status
        );
        if (empty($data) || !isset($data)) {
            return FALSE;
        }

        foreach ($data as $key => $value) {
            $this->db->getReference()->getChild('order')->getChild($key)->set($value);
        }
        return TRUE;
    }

    function addOrderToMitra()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);

        $getOrder = $this->conn['main']->query("select order_id from order_to_mitra where status_order = 'pending' group by order_id")->result();

        if ($getOrder) {
            foreach ($getOrder as $row) {
                $getOrderConfirm = $this->conn['main']->query("select order_id from order_to_mitra where order_id = '$row->order_id' and status_order = 'confirm'")->result();

                if (count($getOrderConfirm) < 1) {
                    $get_transaction   = $this->conn['main']->query("
                        SELECT a.*, b.product_data, c.payment_code FROM `mall_transaction` a 
                        LEFT JOIN mall_transaction_item b on a.id = b.transaction_id
                        LEFT JOIN mall_order c on a.order_id = c.id
                        WHERE `order_id`  = '" . $row->order_id . "'
                    ")->row();

                    $product_data = json_decode($get_transaction->product_data);

                    // get mitra dengan service yang sesuai dengan order
                    // tambah kondisi apabila pembayaran cod / tunai
                    $sql = "SELECT id FROM product_jasa WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_data->id . "'";
                    $id_jasa = $this->conn['main']->query($sql)->row();

                    $get_mitra_on_orderToMitra   = $this->conn['main']->query("SELECT * FROM `order_to_mitra` WHERE order_id = '$row->order_id'")->result_array();

                    $mitra_id = array_map(function ($value) {
                        return $value['mitra_id'];
                    }, $get_mitra_on_orderToMitra);

                    implode(", ", $mitra_id);
                    $mitra_id = join(',', $mitra_id);
                    $cond_query = '';
                    if (!empty($mitra_id))
                        $cond_query = "AND b.partner_id not in ($mitra_id)";

                    if ($get_transaction->payment_code == 'cod') {
                        $cond_query .= "AND b.current_deposit >= " . $product_data->variant_price->harga;
                    }

                    $location = (json_decode($get_transaction->address_data));

                    $sql = "select a.partner_id, device_id, (111.111
						* DEGREES(ACOS(COS(RADIANS(`latitude`))
						* COS(RADIANS(" . $location->latitude . "))
						* COS(RADIANS(`longitude` - " . $location->longitude . ")) + SIN(RADIANS(`latitude`))
						* SIN(RADIANS(" . $location->latitude . "))))) AS `distance` 
							FROM mitra_current_location a
							LEFT JOIN user_partner b on a.partner_id = b.partner_id
							LEFT JOIN mitra_jasa c on a.partner_id = c.partner_id
							LEFT JOIN user_partner_device d on d.partner_id = b.partner_id
							WHERE b.status_active = '1'
							AND b.user_type = 'mitra'
							AND FIND_IN_SET ('$id_jasa->id', c.jasa_id) > 0
							" . $cond_query . "
							HAVING distance <= 5
							ORDER BY distance ASC LIMIT 10";

                    $query = $this->conn['main']->query($sql)->result();

                    if ($query) {
                        foreach ($query as $value) {
                            $data = array(
                                'order_id'  => $row->order_id,
                                'mitra_id'  => $value->partner_id,
                            );

                            //send push notification order to mitra
                            $this->curl->push($value->partner_id, 'Orderan menunggumu', 'Ayo ambil orderanmu sekarang juga', 'order_pending');
                            $this->conn['main']->insert('order_to_mitra', $data);
                        }
                    }
                }
            }
        }
    }

    function set_expired_order_pending_without_confirm()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);

        $get_order_pending = $this->conn['main']
            ->select('a.*')
            ->where('a.payment_status', 'pending')
            ->where('a.service_type !=', 'ecommerce')
            ->where_not_in('b.transaction_status_id', array(6))
            ->where("NOT EXISTS (
                SELECT * FROM order_to_mitra om
                WHERE om.order_id = c.order_id AND om.status_order ='confirm'
            )")
            ->join('mall_transaction b', 'b.order_id = a.id', 'left')
            ->join('order_to_mitra c', 'c.order_id = a.id', 'left')
            ->group_by('a.id, c.status_order')
            ->get('mall_order a')->result();

        foreach ($get_order_pending as $row) {
            $date_order = strtotime($row->created_at);
            $date_now = strtotime(date('Y-m-d H:i:s'));

            $diff   = $date_now - $date_order;
            $jam    = floor($diff / (60 * 60));
            $menit  = ($diff - $jam * (60 * 60));

            if ($menit > 180) { // set 3 menit = 180 (60 * 3)
                // update mall_order expired
                // $this->conn['main']
                //     ->set(array('payment_status' => 'expired'))
                //     ->where('id', $row->id)
                //     ->update('mall_order');

                // update mall_transaction expired
                $this->conn['main']
                    ->set(array('transaction_status_id' => 6))
                    ->where('order_id', $row->id)
                    ->update('mall_transaction');

                // delete order to mitra
                $this->conn['main']->delete('order_to_mitra', array('order_id' => $row->id));
            }
        }
    }

    function set_expired_order_pending_with_confirm()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);

        $get_order_pending = $this->conn['main']
            ->select('a.*')
            ->where('a.payment_status', 'pending')
            ->where('a.service_type !=', 'ecommerce')
            ->where('a.payment_code !=', 'cod')
            ->where_not_in('b.transaction_status_id', array(6))
            ->where("EXISTS (
                SELECT * FROM order_to_mitra om
                WHERE om.order_id = c.order_id AND om.status_order ='confirm'
            )")
            ->join('mall_transaction b', 'b.order_id = a.id', 'left')
            ->join('order_to_mitra c', 'c.order_id = a.id', 'left')
            ->group_by('a.id')
            ->get('mall_order a')->result();

        foreach ($get_order_pending as $row) {
            $date_order = strtotime($row->created_at);
            $date_now = strtotime(date('Y-m-d H:i:s'));

            $diff   = $date_now - $date_order;
            $jam    = floor($diff / (60 * 60));
            $menit  = ($diff - $jam * (60 * 60));

            if ($menit > 1800) { // set 30 menit = 1800 (60 * 30)
                // update mall_order expired
                // $this->conn['main']
                //     ->set(array('payment_status' => 'expired'))
                //     ->where('id', $row->id)
                //     ->update('mall_order');

                // update mall_transaction expired
                $this->conn['main']
                    ->set(array('transaction_status_id' => 6))
                    ->where('order_id', $row->id)
                    ->update('mall_transaction');

                // delete order to mitra
                $this->conn['main']->delete('order_to_mitra', array('order_id' => $row->id));
            }
        }
    }

    function get_pending_order()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);

        $get_order_pending = $this->conn['main']
            ->select('a.*, b.transaction_status_id')
            ->where('a.payment_status', 'pending')
            ->where('a.service_type !=', 'ecommerce')
            ->where_not_in('b.transaction_status_id', array(6))
            ->join('mall_transaction b', 'b.order_id = a.id', 'left')
            ->get('mall_order a')->result();

        print_r($get_order_pending);
    }
}

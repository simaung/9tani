<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cron extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->conn['main'] = $this->load->database('default', TRUE);

        // Load model
        $this->load->model('order_model');
        $this->load->model('deposit_model');

        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();

        $this->func_name =  $this->uri->segment(2);
    }

    public function expired()
    {
        $get_data = $this->order_model->get_order_expired();
        foreach ($get_data as $row) {
            $this->order_model->set_order_expired($row['id']);
            $this->deposit_model->set_payment_transfer_expired($row['invoice_code']);
        }

        update_cron($this->func_name);
    }

    function set_expired_topup_deposit()
    {
        $get_data = $this->deposit_model->get_request_expired();
        foreach ($get_data as $row) {
            $this->deposit_model->set_topup_expired($row['id']);
            $this->deposit_model->set_payment_transfer_expired($row['invoice_code']);
        }

        update_cron($this->func_name);
    }

    public function cek_mutasi()
    {
        $this->load->model('payment_model');
        $link_url = 'https://bank.sembilankita.com/api/getMutasi/NGNKbFhSRnZNNmF1L2tHdmhJckNVUT09/';

        $get_payment_transfer = $this->payment_model->get_payment_transfer(array('status' => 'pending', 'date' => date('Y-m-d')));
        if ($get_payment_transfer) {
            foreach ($get_payment_transfer as $row) {
                $amount = $row['amount'];
                $date = $row['date'];
                $transaction_invoice = $row['transaction_invoice'];

                $get_mutasi = $this->curl->get($link_url . $amount . '/' . $date, '', '', 'true');
                if ($get_mutasi->rest_no == 0 && $get_mutasi != "" && $get_mutasi->mutasi_data[0]->bank_mutation_amount == $amount) {
                    $this->order_model->set_order_paid($transaction_invoice, json_encode($get_mutasi->mutasi_data));

                    if (in_array(substr($transaction_invoice, 0, 2), array('ST', 'SM', 'SC'))) {
                        $get_transaction = $this->conn['main']
                            ->select('a.*, b.id as transaction_id, b.merchant_id, c.full_name, c.mobile_number, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
                            ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                            ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                            ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                            ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                            ->where('invoice_code', $transaction_invoice)
                            ->group_by('a.id, b.id')
                            ->get('mall_order a')->row();
                    } else {
                        $get_transaction = $this->conn['main']
                            ->select('a.*, c.email, c.full_name')
                            ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                            ->where('invoice_code', $transaction_invoice)
                            ->get('deposit_topup a')->row();

                        $amount = $get_transaction->amount;

                        if ($get_transaction->id != '') {
                            $this->load->library('deposit');
                            $this->deposit->topup_deposit($get_transaction);
                        }
                    }

                    if (in_array(substr($transaction_invoice, 0, 2), array('ST', 'SM', 'SC'))) {
                        $product = $this->conn['main']
                            ->select('a.*')
                            ->select("SHA1(CONCAT(variant_id, '" . $this->config->item('encryption_key') . "')) as variant_id")
                            ->where('transaction_id', $get_transaction->transaction_id)
                            ->get('mall_transaction_item a')->result_array();

                        foreach ($product as $product_row) {
                            $item = json_decode($product_row['product_data'], true);

                            if ($product_row['variant_id'] != '') {
                                if ($get_transaction->service_type == 'ecommerce') {
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
                                    if (!empty($item['variant_price'])) {
                                        $trans_item[] = array(
                                            'name'      => $item['name'],
                                            'unit'      => $item['variant_price']['layanan'],
                                            'price'     => $item['variant_price']['harga'],
                                            'qty'       => $product_row['quantity'],
                                        );
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

                            if ($get_transaction->service_type != 'ecommerce') {
                                $this->insert_realtime_database($get_transaction->order_id, 'Pesanan sudah dijadwalkan');
                                $this->curl->push($get_transaction->merchant_id, 'Orderan ' . $transaction_invoice . ' telah dibayar', 'Orderanmu siap di lanjutkan!', 'order_pending');
                                $this->curl->push($get_transaction->user_id, 'Pembayaran Order ' . $transaction_invoice . ' telah diterima', 'Selamat menikmati layanan kami', 'order_pending', 'customer');
                            }

                            $user_email = $get_transaction->email;
                            $order = $get_transaction;
                            $order_item = $trans_item;

                            // send wa payment paid
                            if ($get_transaction->service_type == 'clean') {
                                $this->send->index('paid9clean', $get_transaction->mobile_number, $get_transaction->full_name, $get_transaction->invoice_code, $order_item[0]['name'],  $order_item[0]['unit']);
                            } elseif ($get_transaction->service_type == 'massage') {
                                $this->send->index('paid9massage', $get_transaction->mobile_number, $get_transaction->full_name, $get_transaction->invoice_code, $order_item[0]['name'],  $order_item[0]['unit']);
                            }

                            $this->send_email_payment_success($order, $order_item, $user_email);
                            $this->send_email_payment_success_image($user_email);
                        }
                    }
                }
            }
        }

        update_cron($this->func_name);
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

    function insert_realtime_database($id_order, $status, $incoming_order = '')
    {
        $data = array(
            $id_order => $status
        );
        if (empty($data) || !isset($data)) {
            return FALSE;
        }

        if ($incoming_order == 'coming_order') {
            $key_firebase = 'coming_order';
        } else {
            $key_firebase = 'order';
        }

        foreach ($data as $key => $value) {
            $this->db->getReference()->getChild($key_firebase)->getChild($key)->set($value);
        }
        return TRUE;
    }

    function addOrderToMitra()
    {
        $getOrder = $this->conn['main']->query("select order_id from order_to_mitra where status_order = 'pending' group by order_id")->result();

        if ($getOrder) {
            foreach ($getOrder as $row) {
                $getOrderConfirm = $this->conn['main']->query("select order_id from order_to_mitra where order_id = '$row->order_id' and status_order = 'confirm'")->result();

                if (count($getOrderConfirm) < 1) {
                    $get_transaction   = $this->conn['main']->query("
                        SELECT a.*, b.product_data, c.payment_code, c.penyedia_jasa, c.tipe_customer, c.invoice_code, c.service_type, c.user_id, c.favorited, c.tunanetra
                        FROM `mall_transaction` a 
                        LEFT JOIN mall_transaction_item b on a.id = b.transaction_id
                        LEFT JOIN mall_order c on a.order_id = c.id
                        WHERE `order_id`  = '" . $row->order_id . "'
                    ")->row();

                    $product_data = json_decode($get_transaction->product_data);

                    $get_user = $this->conn['main']
                        ->select('*')
                        ->where('user_id', $get_transaction->user_id)
                        ->get('user_to_mitra')->row();

                    if ((!empty($get_user) && $get_user->mitra_id != '') || $get_transaction->favorited == '2') {
                    } else {
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
                        if (!empty($mitra_id)) {
                            $cond_query = " AND b.partner_id not in ($mitra_id)";
                        }

                        if ($get_transaction->payment_code == 'cod') {
                            // $cond_query .= " AND b.current_deposit >= " . $product_data->variant_price->harga * 30 / 100;
                            $cond_query .= " AND b.current_deposit >= -50000";
                        }

                        if ($get_transaction->penyedia_jasa == 'W') {
                            $cond_query .= " AND b.jenis_kelamin = 'P'";
                        } elseif ($get_transaction->penyedia_jasa == 'P') {
                            $cond_query .= " AND b.jenis_kelamin = 'L'";
                        }

                        if ($get_transaction->tipe_customer == 'W') {
                            $cond_query .= " AND b.tipe_customer in ('P','T')";
                        } elseif ($get_transaction->tipe_customer == 'P') {
                            $cond_query .= " AND b.tipe_customer in ('L','T')";
                        }

                        if ($get_transaction->service_type == 'massage') {
                            if ($get_transaction->tipe_customer == 'T') {
                                $cond_query .= " AND b.tipe_customer in ('L','T')";
                            }
                        }

                        if ($get_transaction->tunanetra == '1') {
                            $cond_query .= " AND b.tunanetra = '1'";
                        }

                        $cond_query .= " AND b.suspend = '0'";

                        $location = (json_decode($get_transaction->address_data));

                        $sql = "select a.partner_id, device_id, b.allowed_distance, (111.111
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
                            AND b.partner_id NOT IN (select merchant_id from mall_transaction where transaction_status_id = '10')
							AND FIND_IN_SET ('$id_jasa->id', c.jasa_id) > 0
							" . $cond_query . "
							HAVING distance <= b.allowed_distance
							ORDER BY distance ASC LIMIT 10";

                        $query = $this->conn['main']->query($sql)->result();

                        if ($query) {
                            foreach ($query as $value) {
                                $data = array(
                                    'order_id'  => $row->order_id,
                                    'mitra_id'  => $value->partner_id,
                                    'distance'  => round($value->distance, 1),
                                );

                                $mitra_id_encode = $this->order_model->encoded($value->partner_id);
                                $this->insert_realtime_database($mitra_id_encode, 'true', 'coming_order');

                                //send push notification order to mitra
                                $this->curl->push($value->partner_id, 'Orderan menunggumu', 'Ayo ambil orderanmu sekarang juga', 'order_pending');
                                $this->conn['main']->insert('order_to_mitra', $data);
                            }
                        }
                        // else {
                        //     // update mall_transaction expired
                        //     $this->conn['main']
                        //         ->set(array('transaction_status_id' => 5, 'note_cancel' => 'lokasi diluar jangkauan mitra'))
                        //         ->where('order_id', $get_transaction->order_id)
                        //         ->update('mall_transaction');

                        //     // update mall_order payment expired
                        //     $this->conn['main']
                        //         ->set(array('payment_status' => 'cancel'))
                        //         ->where('id', $get_transaction->order_id)
                        //         ->update('mall_order');

                        //     $this->curl->push($get_transaction->user_id, 'Orderan ' . $get_transaction->invoice_code . ' batal', 'Belum terdapat mitra pada lokasi anda', 'order_canceled', 'customer');

                        //     $order_id_encode = $this->order_model->encoded($get_transaction->order_id);
                        //     $this->insert_realtime_database($order_id_encode, 'Di luar jangkauan');

                        //     return false;
                        // }
                    }
                }
            }
        }

        update_cron($this->func_name);
    }

    function set_expired_order_pending_without_confirm()
    {
        $get_order_pending = $this->conn['main']
            ->select('a.*')
            ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) as encode_id")
            ->where('a.payment_status', 'pending')
            ->where('a.service_type !=', 'ecommerce')
            ->where_not_in('b.transaction_status_id', array(5, 6))
            ->where("NOT EXISTS (
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

            if ($menit > 180) { // set 3 menit = 180 (60 * 3)
                // update mall_order expired
                // $this->conn['main']
                //     ->set(array('payment_status' => 'expired'))
                //     ->where('id', $row->id)
                //     ->update('mall_order');

                $this->set_rating($row->id);

                // update mall_transaction expired
                $this->conn['main']
                    ->set(array('transaction_status_id' => 6))
                    ->where('order_id', $row->id)
                    ->update('mall_transaction');

                // update mall_order payment expired
                $this->conn['main']
                    ->set(array('payment_status' => 'expired'))
                    ->where('id', $row->id)
                    ->update('mall_order');

                // delete order to mitra
                $this->conn['main']
                    ->where("order_id", $row->id)
                    // ->where('status_order !=', 'canceled')
                    ->where('note_cancel is null', null, false)
                    ->delete('order_to_mitra');

                $this->curl->push($row->user_id, 'Orderan' . $row->invoice_code . ' batal', 'Orderan di batalkan karena tidak mendapatkan mitra', 'order_canceled', 'customer');
                $this->insert_realtime_database($row->encode_id, 'Tidak dapat mitra');
            }
        }

        update_cron($this->func_name);
    }

    function set_expired_order_pending_with_confirm()
    {
        $get_order_pending = $this->conn['main']
            ->select('a.*, b.merchant_id')
            ->where('a.payment_status', 'pending')
            ->where('a.service_type !=', 'ecommerce')
            ->where('a.payment_code !=', 'cod')
            ->where_not_in('b.transaction_status_id', array(5, 6))
            ->where("EXISTS (
                SELECT * FROM order_to_mitra om
                WHERE om.order_id = c.order_id AND om.status_order ='confirm'
            )")
            ->join('mall_transaction b', 'b.order_id = a.id', 'left')
            ->join('order_to_mitra c', 'c.order_id = a.id', 'left')
            ->group_by('a.id, b.merchant_id')
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

                $this->set_rating($row->id, 1);

                // update mall_transaction expired
                $this->conn['main']
                    ->set(array('transaction_status_id' => 6))
                    ->where('order_id', $row->id)
                    ->update('mall_transaction');

                // update mall_order payment expired
                $this->conn['main']
                    ->set(array('payment_status' => 'expired'))
                    ->where('id', $row->id)
                    ->update('mall_order');

                // delete order to mitra
                $this->conn['main']
                    ->where("order_id", $row->id)
                    // ->where('status_order !=', 'canceled')
                    ->where('note_cancel is null', null, false)
                    ->delete('order_to_mitra');

                $this->curl->push($row->merchant_id, 'Orderan ' . $row->invoice_code . ' batal', 'Orderan di batalkan karena pembayaran expired', 'order_canceled');
                $this->curl->push($row->user_id, 'Orderan ' . $row->invoice_code . ' batal', 'Orderan di batalkan karena pembayaran expired', 'order_canceled', 'customer');
            }
        }

        update_cron($this->func_name);
    }

    private function set_rating($id_order, $confirm = '')
    {
        $get_mitra_from_order = $this->conn['main']
            ->select('a.mitra_id, a.status_order, b.mitra_id as mitra_id_rating')
            ->where('a.mitra_id !=', 0)
            ->where('a.note_cancel is null', null, false)
            ->where('order_id', $id_order)
            ->join('rating_sistem b', 'a.mitra_id = b.mitra_id', 'left')
            ->get('order_to_mitra a')->result();

        foreach ($get_mitra_from_order as $row) {
            if (!empty($row->mitra_id_rating)) {
                // echo $confirm;die;
                if ($confirm === 1) {
                    // $status = array('confirm', 'canceled');
                    $status = array();
                } else {
                    $status = array('pending', 'canceled');
                }

                if (in_array($row->status_order, $status)) {
                    if ($row->status_order == 'confirm') {
                        $data = "confirm = confirm + 1";
                    } elseif ($row->status_order == 'pending') {
                        $data = "no_respon = no_respon + 1";
                    } elseif ($row->status_order == 'canceled') {
                        $data = "abaikan = abaikan + 1";
                    }
                    $sql = "update rating_sistem set $data where mitra_id = $row->mitra_id";
                    $this->conn['main']->query($sql);
                }
            } else {
                if ($confirm == 1) {
                    // $status = array('confirm', 'canceled');
                    $status = array();
                } else {
                    $status = array('pending', 'canceled');
                }
                if (in_array($row->status_order, $status)) {
                    if ($row->status_order == 'confirm') {
                        $data = array(
                            'mitra_id'  => $row->mitra_id,
                            'confirm' => 1
                        );
                    } elseif ($row->status_order == 'pending') {
                        $data = array(
                            'mitra_id'  => $row->mitra_id,
                            'no_respon' => 1
                        );
                    } elseif ($row->status_order == 'canceled') {
                        $data = array(
                            'mitra_id'  => $row->mitra_id,
                            'abaikan' => 1
                        );
                    }
                    $this->conn['main']->insert('rating_sistem', $data);
                }
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

    function cron_log()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);
        $data = array(
            'date' => date('Y-m-d'),
        );
        $insert_cron_log = $this->conn['main']->insert('cron_log', $data);
    }

    function set_complete_order_overtime()
    {
        $this->conn['main'] = $this->load->database('default', TRUE);
        $get_order_overtime = $this->conn['main']
            ->select("SHA1(CONCAT(a.id,'" . $this->config->item('encryption_key') . "')) as order_id, d.ecommerce_token")
            ->select("b.transaction_status_id, b.start_time, c.product_data")
            ->where('a.service_type !=', 'ecommerce')
            ->where('b.transaction_status_id', '10')
            ->join('mall_transaction b', 'b.order_id = a.id', 'left')
            ->join('mall_transaction_item c', 'c.transaction_id = b.id', 'left')
            ->join('user_partner d', 'b.merchant_id = d.partner_id', 'left')
            ->get('mall_order a')->result();

        foreach ($get_order_overtime as $row) {
            $product_data = json_decode($row->product_data, true);
            $now = strtotime(date('Y-m-d H:i:s'));
            $start = strtotime($row->start_time);
            $durasi = $product_data['variant_price']['durasi'] + 15; // 15 berdasarkan keputusan durasi layanan ditambah 15 menit

            $diff   = $now - $start;
            $menit  = floor($diff / 60);

            if ($menit > $durasi) {
                // proses update transaction complete
                $req_url = base_url() . 'v2/jasa/update_status/';
                $req_params = array(
                    'id_order' => $get_order_overtime[0]->order_id,
                    'status' => '4',
                    'alasan' => ''
                );
                $header = array(
                    "token: " . $get_order_overtime[0]->ecommerce_token . ""
                );


                $api_request = $this->curl->post($req_url, $req_params, $header, '', FALSE);
            }
        }
    }
}

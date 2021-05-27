<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Payment extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Connecting to database
        $this->conn['main'] = $this->load->database('default', TRUE);
        $this->conn['log']  = $this->load->database('log', TRUE);

        $this->data['api_duitku'] = $this->config->item('api_duitku');
        $this->data['api_duitku'] = $this->data['api_duitku'][$this->config->item('payment_env')];

        $this->data['api_midtrans'] = $this->config->item('api_midtrans');
        $this->data['api_midtrans'] = $this->data['api_midtrans'][$this->config->item('payment_env')];

        $this->provider = array('duitku');

        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();
    }

    public function index()
    {
        $this->load->model('payment_model');
        if ($this->method === 'GET') {
            $this->data['invoice_code'] = $this->request['body']['invoice_code'];

            if (in_array(substr($this->data['invoice_code'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
                $get_order = $this->conn['main']->select('id')
                    ->where('invoice_code', $this->data['invoice_code'])
                    ->where('payment_status', 'pending')
                    ->get('mall_order')->row();
            } else {
                $get_order = $this->conn['main']->select('id')
                    ->where('invoice_code', $this->data['invoice_code'])
                    ->where('payment_status', 'pending')
                    ->get('deposit_topup')->row();
            }

            if (!empty($get_order)) {
                $get_payment_channel = $this->payment_model->get_payment_channel(array(
                    'provider' => $this->provider,
                    'status_installment'  => 1
                ));

                if ($get_payment_channel) {
                    $this->data['payment_channel'] = $get_payment_channel;

                    $this->load->view('payment_channel', $this->data);
                } else {
                    $this->set_response('code', 404);
                    $this->print_output();
                }
            } else {
                $this->set_response('code', 404);
                $this->print_output();
            }
        } else {
            $this->set_response('code', 405);
            $this->print_output();
        }
    }

    public function duitku_inquiry()
    {
        $this->load->model('payment_model');
        if ($this->method === 'GET') {
            $request_data = $this->request['body'];

            $get_channel = $this->payment_model->get_payment_channel_id(array('id' => $request_data['channel_id']));

            if (in_array(substr($request_data['invoice_code'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
                $get_transaction = $this->conn['main']
                    ->select('a.*, b.id as transaction_id, b.shipping_cost, c.email, c.full_name, sum(d.price) as total_price, d.discount as total_discount')
                    ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                    ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                    ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                    ->where('invoice_code', $request_data['invoice_code'])
                    ->group_by('a.id, b.id')
                    ->get('mall_order a')->row();

                $amount = $get_transaction->total_price + $get_transaction->shipping_cost - $get_transaction->total_discount;
            } else {
                $get_transaction = $this->conn['main']
                    ->select('a.*, c.email, c.full_name')
                    ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                    ->where('invoice_code', $request_data['invoice_code'])
                    ->get('deposit_topup a')->row();

                $amount = $get_transaction->amount;
            }

            // Set Inquiry
            $req_url = $this->data['api_duitku']['url'] . 'v2/inquiry';
            $req_data = array();
            $req_data['merchantCode']     = $this->data['api_duitku']['code'];
            $req_data['paymentAmount']    = $amount;
            $req_data['merchantOrderId']  = $get_transaction->invoice_code;
            $req_data['productDetails']   = 'Pembayaran order sembilankita.com';
            $req_data['email']            = (!empty($get_transaction->email) ? $get_transaction->email : '');
            $req_data['merchantUserInfo'] = (!empty($get_transaction->full_name) ? ucwords($get_transaction->full_name) : $get_transaction->email);
            $req_data['paymentMethod']    = $get_channel[0]['code'];
            $req_data['returnUrl']        = base_url() . 'payment/duitku_return';
            $req_data['callbackUrl']      = base_url() . 'payment/duitku_callback';
            $req_data['signature']        = md5($req_data['merchantCode'] . $req_data['merchantOrderId'] . $req_data['paymentAmount'] . $this->data['api_duitku']['key']);

            $api_request = $this->curl->post($req_url, $req_data, '', FALSE);
            $api_request = json_decode($api_request, 1);

            if (!empty($api_request['reference']) && !empty($api_request['paymentUrl'])) {
                // Update booking invoice
                $data = array(
                    'payment_channel_id'    => $request_data['channel_id'],
                    'payment_data'          => json_encode($api_request),
                );

                if (in_array(substr($request_data['invoice_code'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
                    $update_order = $this->conn['main']->set($data)
                        ->where('invoice_code', $get_transaction->invoice_code)
                        ->update('mall_order');
                } else {
                    $update_order = $this->conn['main']->set($data)
                        ->where('invoice_code', $get_transaction->invoice_code)
                        ->update('deposit_topup');
                }

                redirect($api_request['paymentUrl']);
            } else {
                $this->set_response('message', $api_request['Message']);
            }
        } else {
            $this->set_response('code', 405);
        }
        $this->print_output();
    }

    public function duitku_callback()
    {
        $params_response = $this->input->post();

        // save to log_api
        $this->set_log_first($params_response);

        $apiKey = $this->data['api_duitku']['key'];
        $merchantCode = isset($params_response['merchantCode']) ? $params_response['merchantCode'] : null;
        $amount = isset($params_response['amount']) ? $params_response['amount'] : null;
        $merchantOrderId = isset($params_response['merchantOrderId']) ? $params_response['merchantOrderId'] : null;
        $productDetail = isset($params_response['productDetail']) ? $params_response['productDetail'] : null;
        $additionalParam = isset($params_response['additionalParam']) ? $params_response['additionalParam'] : null;
        $paymentMethod = isset($params_response['paymentCode']) ? $params_response['paymentCode'] : null;
        $resultCode = isset($params_response['resultCode']) ? $params_response['resultCode'] : null;
        $merchantUserId = isset($params_response['merchantUserId']) ? $params_response['merchantUserId'] : null;
        $reference = isset($params_response['reference']) ? $params_response['reference'] : null;
        $signature = isset($params_response['signature']) ? $params_response['signature'] : null;

        if (!empty($resultCode) && !empty($merchantCode) && !empty($amount) && !empty($merchantOrderId) && !empty($signature)) {
            $params = $merchantCode . $amount . $merchantOrderId . $apiKey;
            $calcSignature = md5($params);

            if ($signature == $calcSignature) {
                if ($resultCode == '00') {
                    $payment_status = 'paid';
                } else {
                    $payment_status = 'failed';
                }

                if (in_array(substr($merchantOrderId, 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
                    $get_transaction = $this->conn['main']
                        ->select('a.*, b.id as transaction_id, b.merchant_id, c.full_name, c.mobile_number, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
                        ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) AS `order_id`")
                        ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                        ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                        ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                        ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                        ->where('invoice_code', $merchantOrderId)
                        ->group_by('a.id, b.id')
                        ->get('mall_order a')->row();

                    if ($get_transaction->id != '') {
                        // invoice
                        // Update booking invoice
                        $data = array(
                            'payment_status'    => $payment_status,
                            'payment_data'      => json_encode($params_response),
                        );

                        $update_order = $this->conn['main']->set($data)
                            ->where('invoice_code', $get_transaction->invoice_code)
                            ->update('mall_order');

                        // related
                        if ($payment_status == 'paid') {
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
                            }

                            $user_email = $get_transaction->email;
                            $order = $get_transaction;
                            $order_item = $trans_item;

                            // send email
                            $this->send_email_payment_success($order, $order_item, $user_email);
                            $this->send_email_payment_success_image($user_email);

                            // send wa payment paid
                            if ($get_transaction->service_type == 'clean') {
                                $this->send->index('paid9clean', $get_transaction->mobile_number, $get_transaction->full_name, $get_transaction->invoice_code, $order_item[0]['name'],  $order_item[0]['unit']);
                            } elseif ($get_transaction->service_type == 'massage') {
                                $this->send->index('paid9massage', $get_transaction->mobile_number, $get_transaction->full_name, $get_transaction->invoice_code, $order_item[0]['name'],  $order_item[0]['unit']);
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
                                $this->curl->push($get_transaction->merchant_id, 'Orderan ' . $merchantOrderId . ' telah dibayar', 'Orderanmu siap di lanjutkan!', 'order_pending');
                                $this->curl->push($get_transaction->user_id, 'Pembayaran Order ' . $merchantOrderId . ' telah diterima', 'Selamat menikmati layanan kami', 'order_pending', 'customer');
                            }
                        }
                    }
                } else {
                    $get_transaction = $this->conn['main']
                        ->select('a.*, c.email, e.description')
                        ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) AS `id`")
                        ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                        ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                        ->where('invoice_code', $merchantOrderId)
                        ->get('deposit_topup a')->row();

                    if ($get_transaction->id != '') {
                        $data = array(
                            'payment_status'    => $payment_status,
                            'payment_data'      => json_encode($params_response),
                        );

                        $update_order = $this->conn['main']->set($data)
                            ->where('invoice_code', $get_transaction->invoice_code)
                            ->update('deposit_topup');

                        if ($payment_status == 'paid') {
                            $this->load->library('deposit');
                            $this->deposit->topup_deposit($get_transaction);
                        }
                    }
                }
            }
        }
    }

    public function duitku_return()
    {
        $this->load->model('order_model');

        $params_response = $this->input->get();

        if (!empty($params_response['merchantOrderId']) && !empty($params_response['resultCode'])) {

            switch ($params_response['resultCode']) {
                case '00':
                    $payment_status = 'paid';
                    break;
                case '01':
                    $payment_status = 'pending';
                    break;
                default:
                    $payment_status = 'failed';
                    break;
            }

            if (in_array(substr($params_response['merchantOrderId'], 0, 2), array('ST', 'SM', 'SC', 'SD', 'SI'))) {
                $get_transaction = $this->conn['main']
                    ->select('a.*, b.id as transaction_id, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
                    ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                    ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                    ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                    ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                    ->where('invoice_code', $params_response['merchantOrderId'])
                    ->group_by('a.id, b.id')
                    ->get('mall_order a')->row();

                if ($get_transaction->id != '') {
                    if ($params_response['resultCode'] == '00' || $params_response['resultCode'] == '01') {
                        $data = array(
                            // 'payment_status'    => $payment_status,
                            'payment_data'      => json_encode($params_response),
                        );
                        $update_order = $this->conn['main']->set($data)
                            ->where('invoice_code', $get_transaction->invoice_code)
                            ->update('mall_order');

                        $this->data['message'] = $this->language['payment_finish'];

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
                        }

                        $user_email = $get_transaction->email;
                        $order = $get_transaction;
                        $order_item = $trans_item;

                        // send email
                        // $this->send_email_payment_success($order, $order_item, $user_email);
                    } else {
                        $this->data['message'] = $this->language['payment_unfinish'];
                    }
                    $get_transaction->payment_status = $payment_status;

                    $this->data['order_detail'] = $get_transaction;
                    $this->data['data_redirect'] = 'merchantOrderId=' . $params_response['merchantOrderId'] . '&resultCode=' . $params_response['resultCode'] . '&reference=' . $params_response['reference'];

                    $this->load->view('payment_complete', $this->data);
                } else {
                    redirect(base_url() . 'console/page_error/' . 404);
                }
            } else {
                $get_transaction = $this->conn['main']
                    ->select('a.*, c.email, e.description')
                    ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) AS `id`")
                    ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                    ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                    ->where('invoice_code', $params_response['merchantOrderId'])
                    ->get('deposit_topup a')->row();

                if ($get_transaction->id != '') {
                    if ($params_response['resultCode'] == '00') {
                        $data = array(
                            // 'payment_status'    => $payment_status,
                            'payment_data'      => json_encode($params_response),
                        );

                        // hanya difungsikan pada saat callback
                        // if ($payment_status == 'paid') {
                        //     $this->load->library('deposit');
                        //     $this->deposit->topup_deposit($get_transaction);
                        // }

                        $update_order = $this->conn['main']->set($data)
                            ->where('invoice_code', $get_transaction->invoice_code)
                            ->update('deposit_topup');

                        $this->data['message'] = $this->language['payment_finish'];
                    } else {
                        $this->data['message'] = $this->language['payment_unfinish'];
                    }
                    $get_transaction->payment_status = $payment_status;

                    $get_transaction->total_price = $get_transaction->amount;
                    $get_transaction->shipping_cost = 0;
                    $get_transaction->flag_device = 0;

                    $this->data['order_detail'] = $get_transaction;
                    $this->data['data_redirect'] = 'merchantOrderId=' . $params_response['merchantOrderId'] . '&resultCode=' . $params_response['resultCode'] . '&reference=' . $params_response['reference'];

                    $this->load->view('payment_complete', $this->data);
                } else {
                    redirect(base_url() . 'console/page_error/' . 404);
                }
            }
        }
    }

    public function send_email_payment_success($order, $order_item, $user_email)
    {
        $this->load->library('email');

        $data['order'] = $order;
        $data['order_item'] = $order_item;
        // $this->load->view('email/payment_success', $data);

        $email_body = $this->load->view('email/payment_success', $data, TRUE);

        $get_email_sender = $this->common_model->get_global_setting(array(
            'group' => 'email',
            'name' => 'post-master'
        ));

        $this->email->from($get_email_sender['value'], '9tani');
        $this->email->to($user_email);
        $this->email->bcc('sembilantaniindonesia@gmail.com');

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

    public function transfer()
    {
        $this->load->model('payment_model');

        $request_data = $this->request['body'];

        if (in_array(substr($request_data['invoice_code'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
            $get_transaction = $this->conn['main']
                ->select('a.*, b.id as transaction_id, b.shipping_cost, c.email, sum(d.price) as total_price, d.discount as total_discount, e.description, c.mobile_number, c.full_name')
                ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                ->where('invoice_code', $request_data['invoice_code'])
                ->group_by('a.id, b.id')
                ->get('mall_order a')->row();
        } else {
            $get_transaction = $this->conn['main']->select('a.id, a.invoice_code, a.payment_status, a.amount as total_price, 0 as total_discount, 0 as shipping_cost, c.email, 0 as flag_device, "transfer" as description, c.mobile_number, c.full_name')
                ->where('invoice_code', $request_data['invoice_code'])
                ->where('payment_status', 'pending')
                ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                ->get('deposit_topup a')->row();
        }

        if ($this->method === 'GET') {
            // Load model

            if (!empty($get_transaction->id)) {
                // $uniq_code = ((strlen($get_transaction->id) > 3) ? substr($get_transaction->id, -3) : str_pad($get_transaction->id, 3, '0', STR_PAD_LEFT));
                // $uniq_code = $this->uniq_num();

                // Original Price
                $price = $get_transaction->total_price + $get_transaction->shipping_cost - $get_transaction->total_discount;

                // $this->data['amount']           = $price + $uniq_code;
                $this->data['amount']           = $price;
                $this->data['invoice_code']     = $request_data['invoice_code'];
                $this->data['channel_id']       = $request_data['channel_id'];

                $data_update = array(
                    'payment_channel_id'    => $request_data['channel_id'],
                    'payment_status'        => 'pending',
                );
                if (in_array(substr($request_data['invoice_code'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
                    $update_order = $this->conn['main']->set($data_update)
                        ->where('invoice_code', $request_data['invoice_code'])
                        ->update('mall_order');
                } else {
                    $update_order = $this->conn['main']->set($data_update)
                        ->where('invoice_code', $request_data['invoice_code'])
                        ->update('deposit_topup');
                }

                // set payment_transfer
                $get_payment_transfer = $this->payment_model->get_payment_transfer(array(
                    'transaction_type'    => 'booking',
                    'transaction_invoice' => $request_data['invoice_code'],
                ));

                if ($get_payment_transfer) {
                    $set_transfer = $this->payment_model->set_payment_transfer(array(
                        'id'                  => $get_payment_transfer[0]['id'],
                        'transaction_type'    => 'booking',
                        'transaction_invoice' => $request_data['invoice_code'],
                        'amount'              => $this->data['amount'],
                        'date'                => date('Y-m-d'),
                        // 'uniq_num'            => $uniq_code
                    ), array('id' => $get_payment_transfer[0]['id']));
                } else {
                    $set_transfer = $this->payment_model->set_payment_transfer(array(
                        'transaction_type'    => 'booking',
                        'transaction_invoice' => $request_data['invoice_code'],
                        'amount'              => $this->data['amount'],
                        'date'                => date('Y-m-d'),
                        // 'uniq_num'            => $uniq_code
                    ));
                }

                $this->send->index('banktransfer', $get_transaction->mobile_number, $get_transaction->full_name, '', '', '', $set_transfer);
                $this->send_email_payment_transfer($get_transaction, $set_transfer);

                $this->data['bank_account']     = $this->payment_model->get_bank_account(array('status' => 'on'));

                $this->load->view('payment_transfer', $this->data);
            } else {
                $this->set_response('code', 404);
            }
        } else {
            $request_data = $this->request['body'];

            // $uniq_code = ((strlen($get_transaction->id) > 3) ? substr($get_transaction->id, -3) : str_pad($get_transaction->id, 3, '0', STR_PAD_LEFT));
            // $uniq_code = $this->uniq_num();

            // $get_transaction->total_price = $get_transaction->total_price + $uniq_code - $get_transaction->total_discount;
            $get_transaction->total_price = $request_data['amount'];

            $this->data['order_detail'] = $get_transaction;
            $this->data['message'] = 'Dear customer, terima kasih atas pesanannya silakan lakukan pembayaran sesuai nominal yang tertera dibawah. ';

            $this->load->view('payment_complete', $this->data);
        }
    }

    private function send_email_payment_transfer($data_transaction, $amount)
    {
        $this->load->library('email');

        $data_bank_account = $this->payment_model->get_bank_account(array('status' => 'on'));

        // $uniq_code = ((strlen($data_transaction->id) > 3) ? substr($data_transaction->id, -3) : str_pad($data_transaction->id, 3, '0', STR_PAD_LEFT));
        // $price = $data_transaction->total_price + $data_transaction->shipping_cost - $data_transaction->total_discount;
        $price = $amount;

        $this->data['invoice_code'] = $data_transaction->invoice_code;
        $this->data['amount']       = $amount;
        $this->data['bank_account'] = $data_bank_account;


        $email_body = $this->load->view('email/payment_transfer', $this->data, TRUE);

        $get_email_sender = $this->common_model->get_global_setting(array(
            'group' => 'email',
            'name' => 'post-master'
        ));

        $this->email->from($get_email_sender['value'], '9tani');
        $this->email->to($data_transaction->email);
        $this->email->bcc('sembilantani.official@gmail.com');

        $this->email->subject('Silakan lakukan pembayaran nomor invoice ' . $data_transaction->invoice_code);
        $this->email->message($email_body);

        // if ($this->email->send()) {
        //     echo 'Sukses! email berhasil dikirim.';
        // } else {
        //     echo $this->email->print_debugger(array('headers'));
        // }
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

    function gopay()
    {
        $this->load->model('payment_model');
        $request_data = $this->request['body'];

        if (in_array(substr($request_data['invoice_code'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
            $get_transaction = $this->conn['main']
                ->select('a.*, b.id as transaction_id, b.shipping_cost, c.full_name, c.mobile_number, c.email, sum(d.price) as total_price, d.discount as total_discount, e.description')
                ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                ->where('invoice_code', $request_data['invoice_code'])
                ->group_by('a.id, b.id')
                ->get('mall_order a')->row();

            $description = 'Pembayaran transaksi ' . $get_transaction->invoice_code;
        } else {
            $get_transaction = $this->conn['main']->select('a.id, a.invoice_code, a.payment_status, a.amount as total_price, 0 as total_discount, 0 as shipping_cost, c.full_name, c.mobile_number, c.email, 0 as flag_device, "transfer" as description')
                ->where('invoice_code', $request_data['invoice_code'])
                ->where('payment_status', 'pending')
                ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                ->get('deposit_topup a')->row();
            $description = 'Pembayaran transaksi topup ' . $get_transaction->invoice_code;
        }

        $amount = $get_transaction->total_price - $get_transaction->total_discount;
        $server_key = $this->data['api_midtrans']['server'];

        // Set your Merchant Server Key
        \Midtrans\Config::$isProduction = (($this->config->item('payment_env') == 'prod') ? true : false);
        \Midtrans\Config::$serverKey = $server_key;

        $params = array(
            'transaction_details'   => array(
                'order_id'      => $get_transaction->invoice_code,
                'gross_amount'  => $amount
            ),
            'customer_details'  => array(
                'first_name'    => $get_transaction->full_name,
                'email'         => $get_transaction->email,
                'phone'         => $get_transaction->mobile_number,
            ),
            'payment_type'  => 'gopay',
            'gopay' => array(
                'enable_callback'   => true,
                'callback_url'  => base_url('payment/midtrans_return')    // nanti di update berdasarkan app nya
            )
        );

        $response = \Midtrans\CoreApi::charge($params);

        // update payment_data
        $this->payment_model->update_data(array('invoice_code' => $get_transaction->invoice_code), array('payment_data' => json_encode($response, JSON_UNESCAPED_SLASHES)), 'mall_order');

        if ($response->status_code == 201) {
            $this->set_response('code', 200);
            $this->set_response('data', $response);
        } else {
            $this->set_response('code', 404);
        }
        $this->print_output();
    }

    function midtrans_callback()
    {
        \Midtrans\Config::$isProduction = (($this->config->item('payment_env') == 'prod') ? true : false);
        \Midtrans\Config::$serverKey = $this->data['api_midtrans']['server'];
        $notif = new \Midtrans\Notification();

        // $notif = '
        // {
        //     "transaction_time": "2020-09-30 23:14:25",
        //     "gross_amount": "116000.00",
        //     "currency": "IDR",
        //     "order_id": "SM202009302886",
        //     "payment_type": "gopay",
        //     "signature_key": "a3d281c8d80d10ed25e1178986e2d7480a650f725db46702b63de4070f3228a9ca1def18040c9fe35ffe791529152c8724eabcab65dbc409bf40e7a354fb3b44",
        //     "status_code": "200",
        //     "transaction_id": "50fcfcd1-2b86-42e8-8097-75305d91f6e2",
        //     "transaction_status": "settlement",
        //     "fraud_status": "accept",
        //     "settlement_time": "2020-09-30 23:14:40",
        //     "status_message": "Success, transaction is found",
        //     "merchant_id": "G051329030",
        //     "payment_option_type": "GOPAY_WALLET"
        // }
        // ';
        // $notif = json_decode($notif);
        // print_r(json_decode($notif));die;

        $transaction = $notif->transaction_status;
        $type = $notif->payment_type;
        $order_id = $notif->order_id;
        $fraud = $notif->fraud_status;

        if ($transaction == 'capture') {
            // For credit card transaction, we need to check whether transaction is challenge by FDS or not
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    // TODO set payment status in merchant's database to 'Challenge by FDS'
                    // TODO merchant should decide whether this transaction is authorized or not in MAP
                    echo "Transaction order_id: " . $order_id . " is challenged by FDS";
                } else {
                    // TODO set payment status in merchant's database to 'Success'
                    echo "Transaction order_id: " . $order_id . " successfully captured using " . $type;
                }
            }
        } else if ($transaction == 'settlement') {
            // TODO set payment status in merchant's database to 'Settlement'
            // echo "Transaction order_id: " . $order_id . " successfully transfered using " . $type;
            $this->payment_success($order_id, $notif);
        } else if ($transaction == 'pending') {
            // TODO set payment status in merchant's database to 'Pending'
            echo "Waiting customer to finish transaction order_id: " . $order_id . " using " . $type;
        } else if ($transaction == 'deny') {
            // TODO set payment status in merchant's database to 'Denied'
            echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is denied.";
        } else if ($transaction == 'expire') {
            // TODO set payment status in merchant's database to 'expire'
            echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is expired.";
        } else if ($transaction == 'cancel') {
            // TODO set payment status in merchant's database to 'Denied'
            echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is canceled.";
        }
    }

    function payment_success($merchantOrderId, $notif)
    {
        if (in_array(substr($merchantOrderId, 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
            $get_transaction = $this->conn['main']
                ->select('a.*, b.id as transaction_id, b.merchant_id, c.full_name, c.mobile_number, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
                ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) AS `order_id`")
                ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                ->where('invoice_code', $merchantOrderId)
                ->group_by('a.id, b.id')
                ->get('mall_order a')->row();

            if ($get_transaction->id != '') {
                // invoice
                // Update booking invoice
                $data = array(
                    'payment_status'    => 'paid',
                    // 'payment_data'      => json_encode($notif),
                    'payment_data'      => Null,
                );

                $update_order = $this->conn['main']->set($data)
                    ->where('invoice_code', $get_transaction->invoice_code)
                    ->update('mall_order');

                // related
                if ($data['payment_status'] == 'paid') {
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
                    }

                    $user_email = $get_transaction->email;
                    $order = $get_transaction;
                    $order_item = $trans_item;

                    // send email
                    $this->send_email_payment_success($order, $order_item, $user_email);
                    $this->send_email_payment_success_image($user_email);

                    // send wa payment paid
                    if ($get_transaction->service_type == 'clean') {
                        $this->send->index('paid9clean', $get_transaction->mobile_number, $get_transaction->full_name, $get_transaction->invoice_code, $order_item[0]['name'],  $order_item[0]['unit']);
                    } elseif ($get_transaction->service_type == 'massage') {
                        $this->send->index('paid9massage', $get_transaction->mobile_number, $get_transaction->full_name, $get_transaction->invoice_code, $order_item[0]['name'],  $order_item[0]['unit']);
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
                        $this->curl->push($get_transaction->merchant_id, 'Orderan ' . $merchantOrderId . ' telah dibayar', 'Orderanmu siap di lanjutkan!', 'order_pending');
                        $this->curl->push($get_transaction->user_id, 'Pembayaran Order ' . $merchantOrderId . ' telah diterima', 'Selamat menikmati layanan kami', 'order_pending', 'customer');
                    }
                }
            }
        } else {
            $get_transaction = $this->conn['main']
                ->select('a.*, c.email, e.description')
                ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) AS `id`")
                ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                ->where('invoice_code', $merchantOrderId)
                ->get('deposit_topup a')->row();

            if ($get_transaction->id != '') {
                $data = array(
                    'payment_status'    => 'paid',
                    // 'payment_data'      => json_encode($notif),
                    'payment_data'      => Null,
                );

                $update_order = $this->conn['main']->set($data)
                    ->where('invoice_code', $get_transaction->invoice_code)
                    ->update('deposit_topup');

                if ($data['payment_status'] == 'paid') {
                    $this->load->library('deposit');
                    $this->deposit->topup_deposit($get_transaction);
                }
            }
        }
    }

    public function midtrans_return()
    {
        $this->load->model('order_model');

        $params_response = $this->input->get();

        if (!empty($params_response['order_id']) && !empty($params_response['result'])) {

            switch ($params_response['result']) {
                case 'success':
                    $payment_status = 'paid';
                    break;
                default:
                    $payment_status = 'failed';
                    break;
            }

            if (in_array(substr($params_response['order_id'], 0, 2), array('ST', 'SM', 'SC', 'SI'))) {
                $get_transaction = $this->conn['main']
                    ->select('a.*, b.id as transaction_id, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
                    ->join('mall_transaction b', 'a.id = b.order_id', 'left')
                    ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                    ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
                    ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                    ->where('invoice_code', $params_response['order_id'])
                    ->group_by('a.id, b.id')
                    ->get('mall_order a')->row();

                if ($get_transaction->id != '') {
                    if ($params_response['result'] == 'success') {
                        $data = array(
                            // 'payment_status'    => $payment_status,
                            // 'payment_data'      => json_encode($params_response),
                        );
                        // $update_order = $this->conn['main']->set($data)
                        //     ->where('invoice_code', $get_transaction->invoice_code)
                        //     ->update('mall_order');

                        $this->data['message'] = $this->language['payment_finish'];

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
                        }

                        $user_email = $get_transaction->email;
                        $order = $get_transaction;
                        $order_item = $trans_item;

                        // send email
                        // $this->send_email_payment_success($order, $order_item, $user_email);
                    } else {
                        $this->data['message'] = $this->language['payment_unfinish'];
                    }
                    $get_transaction->payment_status = $payment_status;

                    $this->data['order_detail'] = $get_transaction;
                    $this->data['data_redirect'] = 'merchantOrderId=' . $params_response['order_id'] . '&resultCode=' . $params_response['result'];

                    $this->load->view('payment_complete', $this->data);
                } else {
                    redirect(base_url() . 'console/page_error/' . 404);
                }
            } else {
                $get_transaction = $this->conn['main']
                    ->select('a.*, c.email, e.description')
                    ->select("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) AS `id`")
                    ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
                    ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
                    ->where('invoice_code', $params_response['order_id'])
                    ->get('deposit_topup a')->row();

                if ($get_transaction->id != '') {
                    if ($params_response['result'] == 'success') {
                        $data = array(
                            // 'payment_status'    => $payment_status,
                            // 'payment_data'      => json_encode($params_response),
                        );

                        // hanya difungsikan pada saat callback
                        // if ($payment_status == 'paid') {
                        //     $this->load->library('deposit');
                        //     $this->deposit->topup_deposit($get_transaction);
                        // }

                        // $update_order = $this->conn['main']->set($data)
                        //     ->where('invoice_code', $get_transaction->invoice_code)
                        //     ->update('deposit_topup');

                        $this->data['message'] = $this->language['payment_finish'];
                    } else {
                        $this->data['message'] = $this->language['payment_unfinish'];
                    }
                    $get_transaction->payment_status = $payment_status;

                    $get_transaction->total_price = $get_transaction->amount;
                    $get_transaction->shipping_cost = 0;
                    $get_transaction->flag_device = 0;

                    $this->data['order_detail'] = $get_transaction;
                    $this->data['data_redirect'] = 'merchantOrderId=' . $params_response['order_id'] . '&resultCode=' . $params_response['result'];

                    $this->load->view('payment_complete', $this->data);
                } else {
                    redirect(base_url() . 'console/page_error/' . 404);
                }
            }
        }
    }

    function uniq_num()
    {
        $this->load->model('payment_model');

        $uniq_code = rand(1, 999);
        $uniq_code = sprintf("%03d", $uniq_code);

        $cek_uniq = $this->payment_model->cek_uniq_num($uniq_code);
        if (!empty($cek_uniq)) {
            $this->uniq_num();
        } else {
            return $uniq_code;
        }
    }
}

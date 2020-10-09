<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Jasa extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model(array('jasa_model', 'user_model'));

        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();
    }

    // Get list
    public function index()
    {
        $this->_get_jasa();
    }

    public function category()
    {
        $get_data = $this->jasa_model->get_category();
        if ($get_data) {
            $this->response = $get_data;
        } else {
            $this->set_response('code', 400);
        }
        $this->print_output();
    }

    // Get popular list
    public function popular()
    {
        $this->_get_jasa('popular');
    }

    // Get viewed list
    public function viewed()
    {
        if (!empty($this->request['header']['token'])) {
            $this->_get_jasa('viewed');
        } else {
            $this->set_response('code', 400);
            $this->print_output();
        }
    }

    // Create, Read, Delete wishlist
    public function wishlist($action = '')
    {
        if (!empty($this->request['header']['token'])) {
            switch ($action) {
                case 'create':
                    $this->_create_jasa_wishlist();
                    break;
                case 'delete':
                    $this->_delete_jasa_wishlist();
                    break;

                default:
                    $this->_get_jasa('wishlist');
                    break;
            }
        } else {
            $this->set_response('code', 400);
            $this->print_output();
        }
    }

    public function review($action = '')
    {
        switch ($action) {
            case 'create':
                $this->_create_jasa_review();
                break;
            default:
                $this->_get_jasa_review();
                break;
        }
    }

    // Get detail
    public function detail($id)
    {
        if (!empty($id)) {
            $this->_get_jasa('detail', array('id' => $id));
        } else {
            $this->set_response('code', 400);
            $this->print_output();
        }
    }

    private function _create_jasa_wishlist()
    {
        if ($this->method == 'POST') {
            $req_params = array();
            $req_params['token'] = (!empty($this->request['header']['token']) ? $this->request['header']['token'] : '');
            $req_params['product_id'] = (!empty($this->request['body']['product_id']) ? $this->request['body']['product_id'] : '');

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($req_params);

            // BEGIN: Preparing rules
            $rules[] = array('token', 'trim|required');
            $rules[] = array('product_id', 'trim|required');
            // END: Preparing rules

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                if ($this->validate_token($this->request['header']['token'])) {
                    $params = array();
                    $params['token']      = $req_params['token'];
                    $params['product_id'] = $req_params['product_id'];

                    $set_data = $this->jasa_model->create_jasa_wishlist($params);

                    // RESPONSE
                    $this->response = $set_data;
                } else {
                    $this->set_response('code', 498);
                }
            } else {
                // Updating RESPONSE data
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    private function _delete_jasa_wishlist()
    {
        if ($this->method == 'DELETE') {
            $req_params = array();
            $req_params['token'] = (!empty($this->request['header']['token']) ? $this->request['header']['token'] : '');
            $req_params['product_id'] = (!empty($this->request['body']['product_id']) ? $this->request['body']['product_id'] : '');

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($req_params);

            // BEGIN: Preparing rules
            $rules[] = array('token', 'trim|required');
            $rules[] = array('product_id', 'trim|required');
            // END: Preparing rules

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                if ($this->validate_token($this->request['header']['token'])) {
                    $params = array();
                    $params['token']      = $req_params['token'];
                    $params['product_id'] = $req_params['product_id'];

                    $set_data = $this->jasa_model->delete_jasa_wishlist($params);

                    // RESPONSE
                    $this->response = $set_data;
                } else {
                    $this->set_response('code', 498);
                }
            } else {
                // Updating RESPONSE data
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    private function _get_jasa($action = '', $request_data = '')
    {
        if ($this->method == 'GET') {
            if (empty($request_data)) {
                $request_data = $this->request['body'];
            }

            // BEGIN: Preparing request parameters
            $params = array();
            $params['sort'][] = array(
                'sort_by'           => (!empty($request_data['sort_by']) ? $request_data['sort_by'] : 'created_at'),
                'sort_direction'    => (!empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'desc'),
            );
            $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
            $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);
            //   $params['status_active'] = TRUE;

            if (!empty($request_data['id']))
                $params['ENCRYPTED::id'] = $request_data['id'];

            if (!empty($request_data['merchant_id']))
                $params['ENCRYPTED::merchant_id'] = $request_data['merchant_id'];

            if (!empty($request_data['slug']))
                $params['slug'] = $request_data['slug'];

            if (!empty($request_data['category']))
                $params['layanan'] = $request_data['category'];

            if (!empty($request_data['name']))
                $params['name'] = 'LIKE::%' . $request_data['name'] . '%';

            if (!empty($request_data['merchant_id']))
                $params['ENCRYPTED::merchant_id'] = $request_data['merchant_id'];

            if (!empty($request_data['min_price']))
                $params['price_selling'] = 'GREATEQ::' . $request_data['min_price'];

            if (!empty($request_data['max_price']))
                $params['price_selling'] = 'LESSEQ::' . $request_data['max_price'];

            if (!empty($request_data['keyword']))
                $params['keyword'] = $request_data['keyword'];

            if (!empty($request_data['city_name']))
                $params['city_name'] = $request_data['city_name'];

            if (!empty($request_data['page_name']))
                $params['page_name'] = $request_data['page_name'];

            if (!empty($request_data['latitude']) && !empty($request_data['longitude'])) {
                // $params['latitude'] = $request_data['latitude'];
                // $params['longitude'] = $request_data['longitude'];
            }

            if (!empty($this->request['header']['token']))
                $params['token'] = $this->request['header']['token'];
            // END: Preparing request parameters

            $get_data = $this->jasa_model->read($params, $action);

            // RESPONSE
            $this->response = $get_data;
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    private function _create_jasa_review()
    {
        if ($this->method == 'POST') {
            $req_params = $this->request['body'];
            $req_params['token'] = (!empty($this->request['header']['token']) ? $this->request['header']['token'] : '');
            $req_params['product_id'] = (!empty($this->request['body']['product_id']) ? $this->request['body']['product_id'] : '');

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($req_params);

            // BEGIN: Preparing rules
            $rules[] = array('token', 'trim|required');
            $rules[] = array('product_id', 'trim|required');
            $rules[] = array('rate', 'trim|required');
            // END: Preparing rules

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                if ($this->validate_token($this->request['header']['token'])) {
                    $params = array();
                    $params['token']      = $req_params['token'];
                    $params['product_id'] = $req_params['product_id'];

                    if (!empty($req_params['comment']))
                        $params['comment'] = $req_params['comment'];

                    if (!empty($req_params['rate']))
                        $params['rate'] = $req_params['rate'];

                    $set_data = $this->jasa_model->create_review_jasa($params);

                    // RESPONSE
                    $this->response = $set_data;
                } else {
                    $this->set_response('code', 498);
                }
            } else {
                // Updating RESPONSE data
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    private function _get_jasa_review()
    {
        if ($this->method == 'GET') {
            $req_params = $this->request['body'];
            $req_params['id'] = (!empty($this->request['body']['id']) ? $this->request['body']['id'] : '');

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($req_params);

            $rules[] = array('id', 'trim|required');
            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                if (!empty($req_params['id']))
                    $params['ENCRYPTED::product_id'] = $req_params['id'];

                $get_data = $this->jasa_model->get_review_jasa($params);

                $this->response = $get_data;
            } else {
                // Updating RESPONSE data
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }
        $this->print_output();
    }

    public function cart($action = '')
    {
        if ($this->method != 'DELETE') {
            if (!empty($this->request['header']['token'])) {
                switch ($action) {
                    case 'create':
                        $this->_create_jasa_cart();
                        break;
                    default:
                        $this->_get_jasa('cart');
                        break;
                }
            } else {
                $this->set_response('code', 400);
                $this->print_output();
            }
        } else {
            $this->_delete_jasa_cart();
        }
    }

    private function _create_jasa_cart()
    {
        if ($this->method == 'POST') {
            $req_params = $this->request['body'];
            $req_params['token'] = (!empty($this->request['header']['token']) ? $this->request['header']['token'] : '');
            $req_params['product_id'] = (!empty($this->request['body']['product_id']) ? $this->request['body']['product_id'] : '');

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($req_params);

            // BEGIN: Preparing rules
            $rules[] = array('token', 'trim|required');
            $rules[] = array('product_id', 'trim|required');
            // END: Preparing rules

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                if ($this->validate_token($this->request['header']['token'])) {
                    $params = array();
                    $params['token']      = $req_params['token'];
                    $params['product_id'] = $req_params['product_id'];

                    $set_data = $this->jasa_model->create_jasa_cart($params);

                    // RESPONSE
                    $this->response = $set_data;
                } else {
                    $this->set_response('code', 498);
                }
            } else {
                // Updating RESPONSE data
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    private function _delete_jasa_cart()
    {
        if ($this->method == 'DELETE') {
            $req_params = array();
            $req_params['token'] = (!empty($this->request['header']['token']) ? $this->request['header']['token'] : '');
            $req_params['product_id'] = (!empty($this->request['body']['product_id']) ? $this->request['body']['product_id'] : '');

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($req_params);

            // BEGIN: Preparing rules
            $rules[] = array('token', 'trim|required');
            $rules[] = array('product_id[]', 'trim|required');
            // END: Preparing rules

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                if ($this->validate_token($this->request['header']['token'])) {
                    $params = array();
                    $params['token']      = $req_params['token'];
                    $params['product_id'] = $req_params['product_id'];

                    $set_data = $this->jasa_model->delete_jasa_cart($params);

                    // RESPONSE
                    $this->response = $set_data;
                } else {
                    $this->set_response('code', 498);
                }
            } else {
                // Updating RESPONSE data
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    public function checkout()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];
            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('product_id', 'trim|required|callback_validate_jasa_id');
            $rules[] = array('variant_id', 'trim|required|callback_validate_jasa_variant_id');
            $rules[] = array('penyedia_jasa', 'trim|required');
            // END: Preparing rules

            set_rules($rules);
            if (($this->form_validation->run() == TRUE)) {
                $this->load->model('jasa_model');

                $result = array();
                $get_product = $this->jasa_model->read(array('ENCRYPTED::id' => $request_data['product_id']));
                if (isset($get_product['code']) && ($get_product['code'] == 200)) {
                    $product_data = $get_product['response']['data'];

                    $product_variant = array();
                    if (!empty($request_data['variant_id']) && !empty($product_data['variant_price'])) {
                        foreach ($product_data['variant_price'] as $variant) {
                            if ($request_data['variant_id'] == $variant['id']) {
                                $product_variant['id']    = $variant['id'];
                                $product_variant['layanan']  = $variant['layanan'];
                                // $product_variant['description']  = $variant['description'];
                                // $product_variant['file']  = $variant['file'];
                                $product_variant['harga']  = $variant['harga'];
                                // $product_price = (!empty($product_data['price_discount']) ? $product_data['price_discount'] : $variant['harga']);
                                break;
                            }
                        }
                    }

                    $data_key = count($result);

                    $qty = 1;
                    $result[$data_key]['product'][] = array(
                        'product_id'    => $product_data['id'],
                        'name'          => $product_data['name'],
                        'total_amount'  => (float) ($product_variant['harga'] * $qty),
                        'variant_id'    => (!empty($product_variant['id']) ? $product_variant['id'] : ''),
                        'layanan'       => (!empty($product_variant['layanan']) ? $product_variant['layanan'] : ''),
                        // 'image'           => (!empty($product_data['image'][0]['file']) ? $product_data['image'][0]['file'] : ''),
                        // 'quantity'        => $qty,
                    );
                    if (!empty($result)) {
                        $this->set_response('code', 200);
                        $this->set_response('response', array('data' => $result));
                    } else {
                        $this->set_response('code', 404);
                    }
                } else {
                    $this->set_response('code', 400);
                    $this->set_response('message', sprintf($this->language['message_not_found'], 'product_id: ' . $request_data['product_id']));

                    $this->print_output();
                }
            } else {
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }
        $this->print_output();
    }

    public function order()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('product_id', 'trim|required|callback_validate_jasa_id');
            $rules[] = array('variant_id', 'trim|required|callback_validate_jasa_variant_id');
            $rules[] = array('penyedia_jasa', 'trim|required');
            // $rules[] = array('tipe_customer', 'trim|required');
            if (empty($request_data['mitra_code'])) {
                $rules[] = array('address_id', 'trim|required');
            } else {
                $rules[] = array('address_data', 'trim|required');
            }
            // END: Preparing rules

            set_rules($rules);
            if ($this->form_validation->run() == TRUE) {
                $transaction_data = array();
                $get_product = $this->jasa_model->read(array('ENCRYPTED::id' => $request_data['product_id']));

                if (isset($get_product['code']) && ($get_product['code'] == 200)) {
                    $product_data = $get_product['response']['data'];

                    if (!empty($request_data['variant_id']) && !empty($product_data['variant_price'])) {
                        foreach ($product_data['variant_price'] as $variant) {
                            if ($request_data['variant_id'] == $variant['id']) {
                                $variant_layanan    = $variant['layanan'];
                                $variant_desc       = $variant['description'];
                                $product_price      = $variant['harga'];
                                $variant_file       = $variant['file'];
                            }
                        }
                    }
                }

                if (empty($request_data['mitra_code']) || $request_data['mitra_code'] == '') {
                    $get_address = $this->curl->get(base_url() . 'address', array('id' => $request_data['address_id']), array('token:' .  $this->request['header']['token']), true);
                    if ($get_address->code == "200") {
                        $get_address = $get_address->response->data[0];
                    } else {
                        $this->set_response('code', 404);
                        $this->set_response('message', 'Address not found');
                        $this->print_output();
                    }
                } else {
                    $get_address = $request_data['address_data'];
                }

                $data_order['product'] = array(
                    'id'            => $request_data['product_id'],
                    'name'          => $product_data['name'],
                    'layanan'       => $product_data['layanan'],
                    'description'   => $product_data['description'],
                    'file'          => $product_data['file'],
                );

                $data_order['product']['variant_price'] = array(
                    'id'            => $request_data['variant_id'],
                    'layanan'       => $variant_layanan,
                    'harga'         => $product_price,
                    'description'   => $variant_desc,
                    'file'          => $variant_file,
                );
                $data_order['address'] = $get_address;

                $transaction_data[] = $data_order;

                if (!empty($transaction_data)) {
                    $params = array();
                    $params['token'] = $this->request['header']['token'];
                    $params['payment_code'] = '';
                    $params['service_type'] = $product_data['layanan'];

                    if (!empty($request_data['referral_code']))
                        $params['referral_code'] = $request_data['referral_code'];

                    if (!empty($request_data['send_at']))
                        $params['send_at'] = $request_data['send_at'];

                    if (!empty($request_data['shipping_date']))
                        $params['shipping_date'] = $request_data['shipping_date'];

                    if (!empty($request_data['penyedia_jasa']))
                        $params['penyedia_jasa'] = $request_data['penyedia_jasa'];

                    if (!empty($request_data['tipe_customer']))
                        $params['tipe_customer'] = $request_data['tipe_customer'];

                    if (!empty($request_data['note']))
                        $params['note'] = $request_data['note'];

                    if (!empty($request_data['cod'])) {

                        $filter['ecommerce_token'] = $params['token'];
                        $get_data = $this->user_model->read($filter);
                        if ($request_data['cod'] == 1) {
                            if ($get_data['response']['data'][0]['verified'] == 0) {
                                $this->set_response('code', 400);
                                $this->set_response('message', $this->language['user_not_verified'] . ' ' . $this->language['cod_payment']);
                                $this->print_output();
                            } else {
                                $params['cod'] = $request_data['cod'];
                            }
                        } else {
                            $params['cod'] = $request_data['cod'];
                        }
                    }

                    if (!empty($request_data['mitra_code'])) {
                        // $filter['ecommerce_token'] = $params['token'];
                        // $get_data = $this->user_model->read($filter);
                        // if ($get_data['response']['data'][0]['verified'] == 0) {
                        //     $this->set_response('code', 400);
                        //     $this->set_response('message', $this->language['user_not_verified'] . ' ' . $this->language['cod_payment']);
                        //     $this->print_output();
                        // } else {
                        $mitra_id = $this->user_model->getValue('partner_id', 'user_partner', array('referral_code' => $request_data['mitra_code']));
                        $request_data['mitra_code'] = $mitra_id;
                        $params['cod'] = 1;
                        // }
                    }

                    if (!empty($request_data['flag_device']))
                        $params['flag_device'] = $request_data['flag_device'];

                    if (!empty($request_data['favorited']))
                        $params['favorited'] = $request_data['favorited'];

                    // Set order
                    $this->load->model('order_model');
                    $set_order = $this->order_model->create($params);

                    if (!empty($set_order['code']) && ($set_order['code'] == 200)) {
                        // Set transaction
                        foreach ($transaction_data as $key => $value) {
                            $params = array();
                            $params['order_id'] = $set_order['response']['data']['id'];
                            $params['service_type'] = $set_order['response']['data']['service_type'];

                            // Load model
                            $this->load->model('transaction_model');
                            if (empty($request_data['mitra_code']) || $request_data['mitra_code'] == '') {
                                $set_transaction = $this->transaction_model->create(array_merge($params, $value));
                            } else {
                                $set_transaction = $this->transaction_model->create(array_merge($params, $value), $request_data['mitra_code']);
                            }

                            if (!empty($set_transaction['code']) && ($set_transaction['code'] == 200)) {
                                $set_transaction_success = TRUE;

                                if (empty($request_data['mitra_code']) || $request_data['mitra_code'] == '') {
                                    $sendOrderToMitra = $this->transaction_model->orderToMitra($set_transaction['response']['data']['id']);
                                    if ($sendOrderToMitra) {
                                        $this->insert_realtime_database($set_order['response']['data']['id'], 'Mencari mitra');
                                    } else {
                                        $this->insert_realtime_database($set_order['response']['data']['id'], 'Tidak dapat mitra');
                                    }
                                } else {
                                    $this->transaction_model->orderToMitra($set_transaction['response']['data']['id'], $request_data['mitra_code']);
                                    $this->insert_realtime_database($set_order['response']['data']['id'], 'Pesanan sudah dijadwalkan');
                                }
                            } else {
                                $set_transaction_success = FALSE;
                                $this->response = $set_transaction;

                                $this->print_output();
                            }
                        }

                        // SUCCESS
                        if (!empty($set_transaction_success)) {
                            $get_order = $this->order_model->read(array('ENCRYPTED::id' => $set_order['response']['data']['id']));

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' =>  $get_order['response']['data'][0]
                            ));

                            // Checking user data
                            $this->load->model('user_model');
                            $user_email = $this->user_model->get_user_email(array('ecommerce_token' => $this->request['header']['token']));
                            $order = $get_order['response']['data'][0];
                            $order_item = $transaction_data[0]['product'];

                            // send wa order
                            // $get_user = $this->user_model->get_user(array('ecommerce_token' => $this->request['header']['token']));
                            // $this->send->index('order', $get_user[0]['mobile_number'], $get_user[0]['full_name'], $set_order['response']['data']['invoice_code'], $transaction_data[0]['product']['name'],  $transaction_data[0]['product']['variant_price']['layanan']);

                            //send email
                            // $this->send_email_order_success($order, $order_item, $user_email);
                        }
                    } else {
                        $this->response = $set_order;
                    }
                }
            } else {
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }
        $this->print_output();
    }

    public function orderToMitra($id_transaction)
    {
        $this->load->model('transaction_model');
        $setOrderMitra = $this->transaction_model->orderToMitra($id_transaction);
    }

    public function get_order($active = '')
    {
        $this->load->model('order_model');

        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                $token = $this->request['header']['token'];

                $request_data = $this->request['body'];
                $params['page']     = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
                $params['length']   = (!empty($request_data['length']) ? (int) $request_data['length'] : 10);

                $get_order = $this->order_model->get_order($token, $active, $params);
                if (isset($get_order['code']) && ($get_order['code'] == 200)) {
                    $this->set_response('code', 200);
                    $this->set_response('response', array(
                        'data' => $get_order['response']['data'],
                        'summary' => $get_order['response']['summary']
                    ));
                } else {
                    $this->set_response('code', 404);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function take_order()
    {
        $this->load->model('order_model');

        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                $token = $this->request['header']['token'];
                $request_data = $this->request['body'];
                $request_data['token'] = $token;

                $take_order = $this->order_model->take_order($request_data);

                // update realtime database
                if ($take_order['code'] == 200) {
                    if ((!empty($take_order['type_payment']) && $take_order['type_payment'] == 'cod') || $take_order['payment_status'] == 'paid') {
                        $this->insert_realtime_database($request_data['order_id'], 'Pesanan sudah dijadwalkan');
                        if (!empty($take_order['type_payment']) && $take_order['type_payment'] == 'cod') {
                            unset($take_order['type_payment']);
                        } elseif (!empty($take_order['payment_status'])) {
                            unset($take_order['payment_status']);
                        }
                    } else {
                        $this->insert_realtime_database($request_data['order_id'], 'Menunggu Pembayaran');
                        if (!empty($take_order['payment_status'])) {
                            unset($take_order['payment_status']);
                        }
                    }
                }

                $this->response = $take_order;
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function update_status()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'PUT') {
                    $get_user = $this->user_model->get_user(array('ecommerce_token' => $this->request['header']['token']));

                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('id_order', 'required');
                    $rules[] = array('status', 'required');
                    // END: Preparing rules

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $request_data['user_type'] = $get_user[0]['user_type'];
                        $params = $request_data;

                        $this->load->model('order_model');

                        $get_order = $this->order_model->get_detail_order($params);

                        $params['mitra_id'] = $get_user[0]['partner_id'];
                        $set_data = $this->order_model->update($params);

                        if ($set_data['code'] == '200') {
                            switch ($params['status']) {
                                case '9':
                                    $status = "Dalam perjalanan";
                                    $this->curl->push($get_order->user_id, 'Status Order', 'Mitra sedang dalam perjalanan ke lokasi anda', 'on_the_way', 'customer');
                                    break;
                                case '10':
                                    $this->curl->push($get_order->user_id, 'Status Order', 'Mitra sedang melakukan pelayanan', 'doing_services', 'customer');
                                    $status = "Sedang melakukan pelayanan";
                                    break;
                                case '4':
                                    $this->curl->push($get_order->user_id, 'Status Order', 'Mitra sudah menyelesaikan pelayanan', 'order_completed', 'customer');
                                    $status = "Selesai";
                                    break;
                                case '5':
                                    if ($get_user[0]['user_type'] == 'mitra') {
                                        $partner_id = $this->user_model->getValueEncode('partner_id', 'user_partner', $get_user[0]['partner_id']);

                                        if ($get_order->merchant_id == $partner_id) {
                                            $this->curl->push($get_order->user_id, 'Status Order', 'Mitra membatalkan orderan', 'order_canceled', 'customer');
                                            $status = "Mencari mitra";
                                            $this->insert_realtime_database($params['id_order'], $status);
                                        }
                                    } else {
                                        $status = "Batal";
                                    }
                                    break;
                            }
                            if ($get_user[0]['user_type'] == 'mitra' && $params['status'] != 5) {
                                $this->insert_realtime_database($params['id_order'], $status);
                            }
                        }

                        // RESPONSE
                        $this->response = $set_data;
                    } else {
                        // Updating RESPONSE data
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                    }
                } else {
                    $this->set_response('code', 405);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
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

    function post_rating()
    {
        $this->load->model('rating_model');

        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {

                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('id_order', 'required');
                    $rules[] = array('rate', 'required');

                    if ($request_data['rate'] <= 3) {
                        $rules[] = array('comment', 'required');
                    }
                    // END: Preparing rules

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $params = $request_data;
                        $params['token'] = $this->request['header']['token'];

                        $set_data = $this->rating_model->create($params);
                        if (!empty($set_data['code']) && ($set_data['code'] == 200)) {
                        }
                        $this->response = $set_data;
                    } else {
                        // Updating RESPONSE data
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                    }
                } else {
                    $this->set_response('code', 405);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    function mitra_favorit()
    {

        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $req_params = $this->request['body'];
                    $req_params['token'] = $this->request['header']['token'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($req_params);

                    $rules[] = array('product_id', 'trim|required');
                    $rules[] = array('penyedia_jasa', 'trim|required');
                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $get_data = $this->jasa_model->get_mitra_favorit($req_params);

                        if ($get_data) {
                            foreach ($get_data['response']['data'] as $key => $value) {
                                unset($value->password);
                                unset($value->ecommerce_token);
                                unset($value->image_idcard);
                                unset($value->image_selfie);

                                if (!empty($value->img) && file_exists($this->config->item('storage_path') . 'user/' . $value->img)) {
                                    $get_data['response']['data'][$key]->img = $this->config->item('storage_url') . 'user/' . $value->img;
                                } else {
                                    $get_data['response']['data'][$key]->img = $this->config->item('storage_url') . 'user/no-image.png';
                                }

                                $get_data['response']['data'][$key]->rate = number_format((float)$get_data['response']['data'][$key]->rate, 2, '.', '');
                            }
                            $this->response = $get_data;
                        }
                    } else {
                        // Updating RESPONSE data
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                    }
                } else {
                    $this->set_response('code', 405);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }
        $this->print_output();
    }
}

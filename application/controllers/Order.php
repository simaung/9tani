<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Order extends Base_Controller
{

  public function __construct()
  {
    parent::__construct();

    // $this->data_login = $this->verify_request();

    if (!empty($this->request['header']['token'])) {
      if (!$this->validate_token($this->request['header']['token'])) {
        $this->set_response('code', 498);
        $this->print_output();
      }
    } else {
      $this->set_response('code', 498);
      $this->print_output();
    }

    // Load model
    $this->load->model('order_model');
  }

  // Get list
  public function index()
  {
    if ($this->method == 'GET') {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $params = array();
      $params['sort'][] = array(
        'sort_by'           => (!empty($request_data['sort_by']) ? $request_data['sort_by'] : 'created_at'),
        'sort_direction'    => (!empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'asc'),
      );
      $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
      $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);

      if (!empty($request_data['id']))
        $params['ENCRYPTED::id'] = $request_data['id'];

      if (!empty($request_data['invoice_code']))
        $params['invoice_code'] = $request_data['invoice_code'];

      if (!empty($request_data['payment_status']))
        $params['payment_status'] = $request_data['payment_status'];

      if (!empty($request_data['payment_code']))
        $params['payment_code'] = $request_data['payment_code'];

      if (!empty($request_data['created_at']))
        $params['created_at'] = 'LIKE::%' . date('Y-m-d' . strtotime($request_data['created_at'])) . '%';

      if (!empty($request_data['modified_at']))
        $params['modified_at'] = 'LIKE::%' . date('Y-m-d' . strtotime($request_data['modified_at'])) . '%';

      if (!empty($this->request['header']['token']))
        $params['token'] = $this->request['header']['token'];
      // END: Preparing request parameters

      // GET DATA
      $get_data = $this->order_model->read($params);

      // RESPONSE
      $this->response = $get_data;
    } else {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  // Set create
  public function create()
  {
    if ($this->method == 'POST') {
      $request_data = $this->request['body'];

      if (!empty($request_data['address']) &&  !empty($request_data['item'])) {
        $this->load->library(array('form_validation'));
        $this->form_validation->set_data($request_data);

        // BEGIN: Preparing rules
        // address
        $rules[] = array('address[address_id]', 'trim|required');
        // dropship
        $rules[] = array('dropship[name]', 'trim');
        $rules[] = array('dropship[phone]', 'trim');

        foreach ($request_data['item'] as $key => $item) {
          $rules[] = array('item[' . $key . '][merchant_id]', 'trim|required|callback_validate_merchant_id');

          // product
          foreach ($item['product'] as $key2 => $product) {
            $rules[] = array('item[' . $key . '][product][' . $key2 . '][product_id]', 'trim|required|callback_validate_product_id');
            $rules[] = array('item[' . $key . '][product][' . $key2 . '][variant_id]', 'trim|callback_validate_product_variant_id');
            $rules[] = array('item[' . $key . '][product][' . $key2 . '][quantity]', 'trim|required|numeric|is_natural_no_zero|max_length[10]');
            $rules[] = array('item[' . $key . '][product][' . $key2 . '][note]', 'trim|max_length[200]');
          }

          // shipping
          // $rules[] = array('item['.$key.'][shipping][code]', 'trim|required|callback_validate_shipping_code');
          // $rules[] = array('item['.$key.'][shipping][service]', 'trim|required');
        }
        // END: Preparing rules

        set_rules($rules);

        if (($this->form_validation->run() == TRUE)) {
          $transaction_data = array();

          // BEGIN: Checking data
          foreach ($request_data['item'] as $key => $item) {
            // 1. Check product and prepare data
            $total_weight = 0;

            foreach ($item['product'] as $key2 => $product) {
              $read_product = $this->product_model->read(array(
                'ENCRYPTED::id' => $product['product_id']
              ));

              if (isset($read_product['code']) && ($read_product['code'] == 200)) {
                $product_data = $read_product['response']['data'];

                // Check order minimum
                // if (!empty($product_data['order_minimum']) && ($product['quantity'] < $product_data['order_minimum'])) {
                //   $this->set_response('code', 400);
                //   $this->set_response('message', sprintf($this->language['message_less_than'], 'item[' . $key . '][product][' . $key2 . '][quantity]: ' . $product['quantity'], 'Minimum order ' . $product_data['order_minimum']));

                //   $this->print_output();
                // }

                // Check order maximum
                // if (!empty($product_data['order_maximum']) && ($product['quantity'] > $product_data['order_maximum'])) {
                //   $this->set_response('code', 400);
                //   $this->set_response('message', sprintf($this->language['message_greater_than'], 'item[' . $key . '][product][' . $key2 . '][quantity]: ' . $product['quantity'], 'Maximum order ' . $product_data['order_maximum']));

                //   $this->print_output();
                // }

                // Product Price
                $product_price = (!empty($product_data['price_discount']) ? $product_data['price_discount'] : $product_data['price_selling']);

                if (!empty($product['variant_id']) && !empty($product_data['product_variant'])) {
                  foreach ($product_data['product_variant'] as $variant) {
                    if (($product['variant_id'] == $variant['id'])) {
                      $product_price = (!empty($product_data['price_discount']) ? $product_data['price_discount'] : $variant['harga']);
                    }
                  }
                }
                // Product Discount
                $product_discount = 0;

                $item['product'][$key2]['price']      = $product_price * $product['quantity'];
                $item['product'][$key2]['discount']   = $product_discount;
                $item['product'][$key2]['product_data'] = $product_data;
                $item['product'][$key2]['note'] = (!empty($product['note']) ? $product['note'] : '');

                // Product Variant
                if (!empty($product['variant_id'])) {
                  $item['product'][$key2]['variant_id'] = $product['variant_id'];
                }

                $total_weight += (int) $product_data['shipping_weight'] * $product['quantity'];
              } else {
                $this->set_response('code', 404);
                $this->set_response('message', sprintf($this->language['message_not_found'], 'product_id: ' . $product['product_id']));

                $this->print_output();
              }
            }

            /*
            // 2. Check shipping cost
            $total_shipping_cost = 0;
            
            if (!empty($total_weight)) {
              $shipping_cost_params = array(
                'merchant_id'     => $item['merchant_id'],
                'shipping_code'   => $item['shipping']['code'],
                'province_id'     => $request_data['address']['province_id'],
                'city_id'         => $request_data['address']['city_id'],
                'subdistrict_id'  => $request_data['address']['subdistrict_id'],
                'weight'          => $total_weight,
              );

              
              $get_shipping_cost = $this->curl->post(base_url() . 'api/shipping/cost', $shipping_cost_params, '', FALSE);
              $get_shipping_cost = json_decode($get_shipping_cost, 1);

              if (!empty($get_shipping_cost['code']) && ($get_shipping_cost['code'] == 200)) {
                $shipping_data  = $get_shipping_cost['response']['data'][0];
                $shipping_costs = $get_shipping_cost['response']['data'][0]['costs'];
                $cost_found = false;
                foreach ($shipping_costs as $key2 => $cost) {
                  if ($item['shipping']['service'] == $cost['service']) {
                    $cost_found = true;

                    $total_shipping_cost += (float) $cost['cost'][0]['value'];
                    $shipping_data['costs'] = $cost;
                    $item['shipping']['shipping_data'] = $shipping_data;
                    $item['shipping']['shipping_cost'] = $total_shipping_cost;

                    break;
                  }
                }

                if (!$cost_found) {
                  $this->set_response('code', 404);
                  $this->set_response('message', sprintf($this->language['message_not_found'], 'Shipping Cost'));

                  $this->print_output();
                }
              } else {
                $this->set_response('code', 404);
                $this->set_response('message', sprintf($this->language['message_not_found'], 'Shipping Cost'));

                $this->print_output();
              }
            } else {
              $this->set_response('code', 404);
              $this->set_response('message', sprintf($this->language['message_not_found'], 'Total Weight'));
            }
            */

            // shipping cost dengan tarif flat
            $get_cost = $this->curl->get(base_url() . 'shipping/cost', '', array('token:' .  $this->request['header']['token']), true);
            $keterangan = $get_cost->response->data->keterangan;
            $total_shipping_cost = $get_cost->response->data->value;
            $item['shipping']['shipping_data'] = $keterangan;
            $item['shipping']['shipping_cost'] = $total_shipping_cost;

            // 3. Check address data
            // // address common
            $get_address = $this->curl->get(base_url() . 'address', array('id' => $request_data['address']['address_id']), array('token:' .  $this->request['header']['token']), true);
            if ($get_address->code == "200") {
              $get_address = $get_address->response->data[0];
              $item['address'] = $get_address;
            } else {
              $this->set_response('code', 404);
              $this->set_response('message', 'Address not found');
              $this->print_output();
            }

            // 4. Check dropship data
            if (!empty($request_data['dropship']['name']) && !empty($request_data['dropship']['phone'])) {
              $item['dropship']['name']  = $request_data['dropship']['name'];
              $item['dropship']['phone'] = $request_data['dropship']['phone'];
            }

            // Assign all params
            $transaction_data[] = $item;
          }
          // END: Checking data

          // BEGIN: Process data
          if (!empty($transaction_data)) {
            $params = array();
            $params['token'] = $this->request['header']['token'];
            $params['payment_code'] = '';

            if (!empty($request_data['referral_code']))
              $params['referral_code'] = $request_data['referral_code'];

            if (!empty($request_data['send_at']))
              $params['send_at'] = $request_data['send_at'];

            if (!empty($request_data['shipping_date']))
              $params['shipping_date'] = $request_data['shipping_date'];

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

            if (!empty($request_data['flag_device']))
              $params['flag_device'] = $request_data['flag_device'];

            // Set order
            $set_order = $this->order_model->create($params);

            if (!empty($set_order['code']) && ($set_order['code'] == 200)) {
              // Set transaction
              foreach ($transaction_data as $key => $value) {
                $params = array();
                $params['order_id'] = $set_order['response']['data']['id'];

                // Load model
                $this->load->model('transaction_model');
                $set_transaction = $this->transaction_model->create(array_merge($params, $value));

                if (!empty($set_transaction['code']) && ($set_transaction['code'] == 200)) {
                  $set_transaction_success = TRUE;
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
                $user_email = $this->user_model->get_user_email(array('ecommerce_token' => $this->request['header']['token']));
                $order = $get_order['response']['data'][0];
                $order_item = $transaction_data[0]['product'];

                //send email
                $this->send_email_order_success($order, $order_item, $user_email);
              }
            } else {
              $this->response = $set_order;
            }
          }
          // END: Process data
        } else {
          // Updating RESPONSE data
          $this->set_response('code', 400);
          $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
          $this->set_response('data', get_rules_error($rules));
        }
      } else {
        $this->set_response('code', 400);
      }
    } else {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }


  public function send_email_order_success($order, $order_item, $user_email)
  {
    $this->load->library('email');

    $data['order'] = $order;
    $data['order_item'] = $order_item;
    $this->load->view('email/order_success', $data);

    $email_body = $this->load->view('email/order_success', $data, TRUE);

    $get_email_sender = $this->common_model->get_global_setting(array(
      'group' => 'email',
      'name' => 'post-master'
    ));

    $this->email->from($get_email_sender['value'], '9tani');
    $this->email->to($user_email);

    if ($data['order']['payment_code'] == 'cod') {
      $this->email->subject('Terimakasih, anda sudah melakukan order dengan nomor invoice ' . $data['order']['invoice_code']);
    } else {
      $this->email->subject('Silakan lakukan pembayaran nomor invoice ' . $data['order']['invoice_code']);
    }
    $this->email->message($email_body);

    $this->email->send();
  }
}

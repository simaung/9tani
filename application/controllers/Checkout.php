<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Checkout extends Base_Controller {

  public function __construct()
  {
    parent::__construct();

    if ( ! empty($this->request['header']['token']))
		{
			if ( ! $this->validate_token($this->request['header']['token']))
      {
        $this->set_response('code', 498);
        $this->print_output();
      }
		}
    else
    {
      $this->set_response('code', 498);
      $this->print_output();
    }

    // Load model
    $this->load->model('order_model');
  }

  // Refactoring object
  public function index()
  {
    if ($this->method == 'POST')
    {
      $request_data = $this->request['body'];

      $this->load->library(array('form_validation'));
      $this->form_validation->set_data($request_data);

      // BEGIN: Preparing rules
      foreach ($request_data['item'] as $key => $item)
      {
        $rules[] = array('item['.$key.'][product_id]', 'trim|required|callback_validate_product_id');
        $rules[] = array('item['.$key.'][variant_id]', 'trim|callback_validate_product_variant_id');
        $rules[] = array('item['.$key.'][quantity]', 'trim|required|numeric|is_natural_no_zero|max_length[10]');
      }
      // END: Preparing rules

      set_rules($rules);

      if (($this->form_validation->run() == TRUE))
      {
        $this->load->model('product_model');

        $result = array();
        $merchant_group = array();
        foreach ($request_data['item'] as $key => $item)
        {
          $get_product = $this->product_model->read(array('ENCRYPTED::id' => $item['product_id']));

          if (isset($get_product['code']) && ($get_product['code'] == 200))
          {
            $product_data = $get_product['response']['data'];

            // Check order minimum
            if ( ! empty($product_data['order_minimum']) && ($item['quantity'] < $product_data['order_minimum']))
            {
              $this->set_response('code', 400);
              $this->set_response('message', sprintf($this->language['message_less_than'], 'item['.$key.'][quantity]: ' . $item['quantity'], 'Minimum order ' . $product_data['order_minimum']));

              $this->print_output();
            }

            // Check order maximum
            if ( ! empty($product_data['order_maximum']) && ($item['quantity'] > $product_data['order_maximum']))
            {
              $this->set_response('code', 400);
              $this->set_response('message', sprintf($this->language['message_greater_than'], 'item['.$key.'][quantity]: ' . $item['quantity'], 'Maximum order ' . $product_data['order_maximum']));

              $this->print_output();
            }

            /* 
            // Check quantity to stock
            if ( ! empty($item['variant_id']) && ! empty($product_data['product_variant']))
            {
              foreach ($product_data['product_variant'] as $variant)
              {
                if (($item['variant_id'] == $variant['id']) && ($item['quantity'] > $variant['stock']))
                {
                  $this->set_response('code', 400);
                  $this->set_response('message', sprintf($this->language['message_greater_than'], 'item['.$key.'][quantity]: ' . $item['quantity'], 'Variant Stock ' . $variant['stock']));

                  $this->print_output();
                }
              }
            }
            else
            {
              if ($item['quantity'] > $product_data['product_stock'])
              {
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['message_greater_than'], 'item['.$key.'][quantity]: ' . $item['quantity'], 'Stock ' . $product_data['product_stock']));

                $this->print_output();
              }
            }
            */
            // Preparing result data
            $product_price = ( ! empty($product_data['price_discount']) ? $product_data['price_discount'] : $product_data['price_selling']);

            $product_variant = array();
            if ( ! empty($item['variant_id']) && ! empty($product_data['product_variant']))
            {
              foreach ($product_data['product_variant'] as $variant)
              {
                if ($item['variant_id'] == $variant['id'])
                {
                  $product_variant['id']    = $variant['id'];
                  $product_variant['name']  = $variant['name'];

                  $product_price = ( ! empty($product_data['price_discount']) ? $product_data['price_discount'] : $variant['harga']);

                  break;
                }
              }
            }

            if (in_array($product_data['merchant_id'], $merchant_group))
            {
              $data_key = array_keys($merchant_group, $product_data['merchant_id']);
              $data_key = $data_key[0];

              $result[$data_key]['product'][] = array(
                'product_id'      => $product_data['id'],
                'name'            => $product_data['name'],
                'image'           => ( ! empty($product_data['image'][0]['file']) ? $product_data['image'][0]['file'] : ''),
                'total_amount'    => (float) ($product_price * $item['quantity']),
                'total_weight'    => (int) ($product_data['shipping_weight'] * $item['quantity']),
                'quantity'        => (int) $item['quantity'],
                'variant_id'      => ( ! empty($product_variant['id']) ? $product_variant['id'] : ''),
                'variant_name'    => ( ! empty($product_variant['name']) ? $product_variant['name'] : ''),
              );
            }
            else
            {
              $data_key = count($result);

              $result[$data_key]['merchant_id']    = $product_data['merchant_id'];
              $result[$data_key]['merchant_name']  = ( ! empty($product_data['merchant_detail']['name']) ? $product_data['merchant_detail']['name'] : '');

              $result[$data_key]['product'][] = array(
                'product_id'      => $product_data['id'],
                'name'            => $product_data['name'],
                'image'           => ( ! empty($product_data['image'][0]['file']) ? $product_data['image'][0]['file'] : ''),
                'total_amount'    => (float) ($product_price * $item['quantity']),
                'total_weight'    => (int) ($product_data['shipping_weight'] * $item['quantity']),
                'quantity'        => (int) $item['quantity'],
                'variant_id'      => ( ! empty($product_variant['id']) ? $product_variant['id'] : ''),
                'variant_name'    => ( ! empty($product_variant['name']) ? $product_variant['name'] : ''),
              );

              $merchant_group[] = $product_data['merchant_id'];
            }
          }
          else
          {
            $this->set_response('code', 400);
            $this->set_response('message', sprintf($this->language['message_not_found'], 'item['.$key.'][product_id]: ' . $item['product_id']));

            $this->print_output();
          }
        }

        if ( ! empty($result))
        {
          $this->set_response('code', 200);
          $this->set_response('response', array('data' => $result));
        }
        else
        {
          $this->set_response('code', 404);
        }
      }
      else
      {
        // Updating RESPONSE data
        $this->set_response('code', 400);
        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
        $this->set_response('data', get_rules_error($rules));
      }
    }
    else
    {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Shipping extends Base_Controller {
  private $api_url;
  private $api_key;

  public function __construct()
  {
    parent::__construct();

    $api_shipping = $this->config->item('api_shipping');

    $this->api_url = $api_shipping['url'];
    $this->api_key = $api_shipping['key'];
  }

  // Get Province list
  public function province()
  {
    if ($this->method == 'GET')
    {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $request = array();

      if ( ! empty($request_data['province_id']))
      {
        $request['id'] = $request_data['province_id'];
      }
      // END: Preparing request parameters

      $api_request = $this->curl->get($this->api_url . '/province', $request, array('key:' . $this->api_key), FALSE);
      $api_request = json_decode($api_request, 1);

      if (isset($api_request['rajaongkir']) && ($api_request['rajaongkir']['status']['code'] == 200))
      {
        $result = array();

        // Reconcile result
        if (empty($request['id']))
        {
          foreach ($api_request['rajaongkir']['results'] as $value)
          {
            $result[] = array(
              'id'	  => $value['province_id'],
              'name'	=> $value['province']
            );
          }
        }
        else
        {
          $result[] = array(
            'id'	  => $api_request['rajaongkir']['results']['province_id'],
            'name'	=> $api_request['rajaongkir']['results']['province']
          );
        }

        $this->set_response('code', 200);
        $this->set_response('response', array(
          'data' 		=> $result
        ));

      }
      else
      {
        $this->set_response('code', 404);
      }
    }
    else
    {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  // Get Regency / City list
  public function city()
  {
    if ($this->method == 'GET')
    {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $request = array();

      if ( ! empty($request_data['city_id']))
      {
        $request['id'] = $request_data['city_id'];
      }

      if ( ! empty($request_data['province_id']))
      {
        $request['province'] = $request_data['province_id'];
      }
      // END: Preparing request parameters

      // GET DATA
      $api_request = $this->curl->get($this->api_url . '/city', $request, array('key:' . $this->api_key), FALSE);
      $api_request = json_decode($api_request, 1);

      if (isset($api_request['rajaongkir']) && ($api_request['rajaongkir']['status']['code'] == 200))
      {
        $result = array();

        // Reconcile result
        if (empty($request['id']))
        {
          foreach ($api_request['rajaongkir']['results'] as $value)
          {
            $result[] = array(
              'id'	  => $value['city_id'],
              'type'	=> $value['type'],
              'name'	=> $value['city_name']
            );
          }
        }
        else
        {
          $result[] = array(
            'id'	  => $api_request['rajaongkir']['results']['city_id'],
            'type'	=> $api_request['rajaongkir']['results']['type'],
            'name'	=> $api_request['rajaongkir']['results']['city_name']
          );
        }

        $this->set_response('code', 200);
        $this->set_response('response', array(
          'data' => $result
        ));

      }
      else
      {
        $this->set_response('code', 404);
      }
    }
    else
    {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  // Get Subdistrict / Regency list
  public function subdistrict()
  {
    if ($this->method == 'GET')
    {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $request = array();

      if ( ! empty($request_data['subdistrict_id']))
      {
        $request['id'] = $request_data['subdistrict_id'];
      }

      if ( ! empty($request_data['city_id']))
      {
        $request['city'] = $request_data['city_id'];
      }
      // END: Preparing request parameters

      // GET DATA
      $api_request = $this->curl->get($this->api_url . '/subdistrict', $request, array('key:' . $this->api_key), FALSE);
      $api_request = json_decode($api_request, 1);

      if (isset($api_request['rajaongkir']) && ($api_request['rajaongkir']['status']['code'] == 200))
      {
        $result = array();

        // Reconcile result
        if (empty($request['id']))
        {
          foreach ($api_request['rajaongkir']['results'] as $value)
          {
            $result[] = array(
              'id'	  => $value['subdistrict_id'],
              'name'	=> $value['subdistrict_name']
            );
          }
        }
        else
        {
          $result[] = array(
            'id'	  => $api_request['rajaongkir']['results']['subdistrict_id'],
            'name'	=> $api_request['rajaongkir']['results']['subdistrict_name']
          );
        }

        $this->set_response('code', 200);
        $this->set_response('response', array(
          'data' 		=> $result
        ));

      }
      else
      {
        $this->set_response('code', 404);
      }
    }
    else
    {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  public function cost()
    {
        if (!empty($this->request['header']['Token'])) {
            if ($this->validate_token($this->request['header']['Token'])) {
              // Load model
              $this->load->model('common_model');
              $data = $this->common_model->get_global_setting(array('group' => "shipping",'name'=>"shipping-cost"));

                // RESPONSE
                $this->set_response('code', 200);
                $this->set_response('response', array(
                    'data' => $data
                ));
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
  }

  public function cost2($params = array())
  {
    $request_data = ( ! empty($params) ? $params : $this->request['body']);

    $this->load->library(array('form_validation'));
    $this->form_validation->set_data($request_data);

    $rules[] = array('merchant_id', 'trim|required|callback_validate_merchant_id');
    $rules[] = array('weight', 'trim|required|numeric|is_natural_no_zero');
    $rules[] = array('province_id', 'trim|required');
    $rules[] = array('city_id', 'trim|required');
    $rules[] = array('subdistrict_id', 'trim|required');

    set_rules($rules);

    if (($this->form_validation->run() == TRUE))
    {
      // Load model
      $this->load->model('merchant_model');

      $read_merchant = $this->merchant_model->read(array('ENCRYPTED::id' => $request_data['merchant_id']));

      if (isset($read_merchant['code']) && ($read_merchant['code'] == 200))
      {
        $merchant_data = $read_merchant['response']['data'][0];

        if ( ! empty($merchant_data['subdistrict_id']))
        {
          // BEGIN: Preparing request parameters
          $request = array();
          $request['origin']          = $merchant_data['subdistrict_id'];
          $request['originType']      = 'subdistrict';
          $request['destination']     = $request_data['subdistrict_id'];
          $request['destinationType'] = 'subdistrict';
          $request['weight']          = $request_data['weight'];
          if ( ! empty($request_data['shipping_code']))
          {
            $request['courier'] = $request_data['shipping_code'];
          }
          else
          {
            $request['courier'] = ( ! empty($merchant_data['courier']) ? implode(':', $merchant_data['courier']) : 'jne:pos:tiki');
          }
          // END: Preparing request parameters

          // GET DATA
          $api_request = $this->curl->post($this->api_url . '/cost', $request, array('key:' . $this->api_key), FALSE);
          $api_request = json_decode($api_request, 1);

          if (isset($api_request['rajaongkir']) && ($api_request['rajaongkir']['status']['code'] == 200))
          {
            $result = array();

            // Reconcile result
            foreach ($api_request['rajaongkir']['results'] as $value)
            {
              if ( ! empty($value['costs']))
              {
                $result[] = $value;
              }
            }

            if ( ! empty($result))
            {
              $this->set_response('code', 200);
              $this->set_response('response', array(
                'data' => $result
              ));
            }
            else
            {
              $this->set_response('code', 404);
            }
          }
          else
          {
            $this->set_response('code', 404);
          }
        }
        else
        {
          $this->set_response('code', 503);
        }
      }
      else
      {
        $this->set_response('code', 404);
        $this->set_response('message', sprintf($this->language['message_not_found'], 'merchant_id: ' . $request_data['merchant_id']));
      }
    }
    else
    {
      // Updating RESPONSE data
      $this->set_response('code', 400);
      $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
      $this->set_response('data', get_rules_error($rules));
    }

    $this->print_output();
  }

  public function history($params = array())
  {
    $request_data = ( ! empty($params) ? $params : $this->request['body']);

    $this->load->library(array('form_validation'));
    $this->form_validation->set_data($request_data);

    $rules[] = array('code', 'trim|required|callback_validate_shipping_code');
    $rules[] = array('number', 'trim|required');

    set_rules($rules);

    if (($this->form_validation->run() == TRUE))
    {
      // BEGIN: Preparing request parameters
      $request = array();
      $request['waybill']     = $request_data['number'];
      $request['courier']     = $request_data['code'];
      // END: Preparing request parameters

      // GET DATA
      $api_request = $this->curl->post($this->api_url . '/waybill', $request, array('key:' . $this->api_key), FALSE);
      $api_request = json_decode($api_request, 1);

      if (isset($api_request['rajaongkir']) && ($api_request['rajaongkir']['status']['code'] == 200))
      {
        $result = $api_request['rajaongkir']['result'];

        if ( ! empty($result))
        {
          $this->set_response('code', 200);
          $this->set_response('response', array(
            'data' => $result
          ));
        }
        else
        {
          $this->set_response('code', 404);
        }
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

    $this->print_output();
  }
}

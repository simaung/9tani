<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Address extends Base_Controller {

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
    $this->load->model('address_model');
  }

  // Get list
  public function index()
  {
    if ($this->method == 'GET')
    {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $params = array();
      $params['sort'][] = array(
        'sort_by'           => ( ! empty($request_data['sort_by']) ? $request_data['sort_by'] : 'created_at'),
        'sort_direction'    => ( ! empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'desc'),
      );
      $params['page']   = ( ! empty($request_data['page']) ? (int) $request_data['page'] : 1);
      $params['length'] = ( ! empty($request_data['length']) ? $request_data['length'] : 10);

      if (isset($request_data['status_primary']))
        $params['status_primary'] = ( ! empty($request_data['status_primary']) ? TRUE : FALSE);

      if ( ! empty($request_data['id']))
        $params['ENCRYPTED::id'] = $request_data['id'];

      if ( ! empty($this->request['header']['token']))
        $params['token'] = $this->request['header']['token'];
      // END: Preparing request parameters

      // GET DATA
      $get_data = $this->address_model->read($params);

      // RESPONSE
      $this->response = $get_data;
    }
    else
    {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  public function create()
  {
    if ($this->method == 'POST')
    {
      $request_data = $this->request['body'];

      $this->load->library(array('form_validation'));
      $this->form_validation->set_data($request_data);

      // BEGIN: Preparing rules
      $rules[] = array('label', 'trim|max_length[200]');
      $rules[] = array('address_maps', 'trim|required|min_length[3]|max_length[500]');
			$rules[] = array('address', 'trim|required|min_length[3]|max_length[500]');
			$rules[] = array('latitude', 'trim');
			$rules[] = array('longitude', 'trim');
			$rules[] = array('status_primary', 'trim|regex_match[(0|1)]');
      // END: Preparing rules

      set_rules($rules);

      if (($this->form_validation->run() == TRUE))
      {
        $params = $request_data;
        $params['token']      = $this->request['header']['token'];
        $set_data = $this->address_model->create($params);

        // RESPONSE
        $this->response = $set_data;
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

  public function update()
  {
    if ($this->method == 'PUT')
    {
      $request_data = $this->request['body'];

      $this->load->library(array('form_validation'));
      $this->form_validation->set_data($request_data);

      // BEGIN: Preparing rules
      $rules[] = array('label', 'trim|max_length[200]');
      $rules[] = array('address_maps', 'trim|required|min_length[3]|max_length[500]');
      $rules[] = array('address', 'trim|required|min_length[3]|max_length[500]');
      $rules[] = array('latitude', 'trim');
      $rules[] = array('longitude', 'trim');
      $rules[] = array('status_primary', 'trim|regex_match[(0|1)]');
      // END: Preparing rules

      set_rules($rules);

      if (($this->form_validation->run() == TRUE))
      {
        $params = $request_data;
        $params['token']      = $this->request['header']['token'];

        if ( ! empty($params['address_id']))
        {
          $address_id = $params['address_id'];
          unset($params['address_id']);
        }

        $set_data = $this->address_model->update($address_id, $params);

        // RESPONSE
        $this->response = $set_data;
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

  public function delete()
  {
    if ($this->method == 'POST')
    {
      $request_data = $this->request['body'];

      $this->load->library(array('form_validation'));
      $this->form_validation->set_data($request_data);

      // BEGIN: Preparing rules
      $rules[] = array('address_id', 'trim|required');
      // END: Preparing rules

      set_rules($rules);

      if (($this->form_validation->run() == TRUE))
      {
        $set_data = $this->address_model->delete($request_data['address_id']);

        // RESPONSE
        $this->response = $set_data;
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

  private function _get_province_name($id)
  {
    $get_province = $this->curl->get(base_url() . 'api/shipping/province', array('province_id' => $id), '', FALSE);
    $get_province = json_decode($get_province, 1);

    if ( ! empty($get_province['code']) && ($get_province['code'] == 200))
    {
      return $get_province['response']['data'][0]['name'];
    }
    else
    {
      $this->set_response('code', 404);
      $this->set_response('message', sprintf($this->language['message_not_found'], 'province_id'));

      $this->print_output();
    }
  }

  private function _get_city_name($id)
  {
    $get_city = $this->curl->get(base_url() . 'api/shipping/city', array('city_id' => $id), '', FALSE);
    $get_city = json_decode($get_city, 1);

    if ( ! empty($get_city['code']) && ($get_city['code'] == 200))
    {
      return $get_city['response']['data'][0]['type'] . ' ' . $get_city['response']['data'][0]['name'];
    }
    else
    {
      $this->set_response('code', 404);
      $this->set_response('message', sprintf($this->language['message_not_found'], 'city_id'));

      $this->print_output();
    }
  }

  private function _get_subdistrict_name($id)
  {
    $get_subdistrict = $this->curl->get(base_url() . 'api/shipping/subdistrict', array('subdistrict_id' => $id), '', FALSE);
    $get_subdistrict = json_decode($get_subdistrict, 1);

    if ( ! empty($get_subdistrict['code']) && ($get_subdistrict['code'] == 200))
    {
      return $get_subdistrict['response']['data'][0]['name'];
    }
    else
    {
      $this->set_response('code', 404);
      $this->set_response('message', sprintf($this->language['message_not_found'], 'subdistrict_id'));

      $this->print_output();
    }
  }
}

<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Transaction extends Base_Controller
{

  public function __construct()
  {
    parent::__construct();

    // Load model
    $this->load->model('transaction_model');
  }

  // Get list
  public function index()
  {
    if (!empty($this->request['header']['token'])) {
      if (!$this->validate_token($this->request['header']['token'])) {
        $this->set_response('code', 498);
        $this->print_output();
      }
    } else {
      $this->set_response('code', 498);
      $this->print_output();
    }

    if ($this->method == 'GET') {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $params = array();
      $params['sort'][] = array(
        'sort_by'           => (!empty($request_data['sort_by']) ? $request_data['sort_by'] : 'id'),
        'sort_direction'    => (!empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'desc'),
      );
      $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
      $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);

      if (!empty($request_data['id']))
        $params['ENCRYPTED::id'] = $request_data['id'];

      if (!empty($request_data['order_id']))
        $params['ENCRYPTED::order_id'] = $request_data['order_id'];

      if (!empty($request_data['merchant_id']))
        $params['ENCRYPTED::merchant_id'] = $request_data['merchant_id'];

      if (!empty($this->request['header']['token']))
        $params['token'] = $this->request['header']['token'];


      if (!empty($request_data['active']))
        $params['active'] = $request_data['active'];

      // END: Preparing request parameters

      // GET DATA
      $get_data = $this->transaction_model->read($params);

      // RESPONSE
      $this->response = $get_data;
    } else {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  public function review()
  {
    if ($this->method == 'POST') {
      $this->_create_transaction_review();
    } elseif ($this->method == 'GET') {
      $this->_get_transaction_review();
    }
  }

  private function _create_transaction_review()
  {
    if ($this->method == 'POST') {
      $req_params = $this->request['body'];
      $req_params['token'] = (!empty($this->request['header']['token']) ? $this->request['header']['token'] : '');
      $req_params['transaction_id'] = (!empty($this->request['body']['transaction_id']) ? $this->request['body']['transaction_id'] : '');

      $this->load->library(array('form_validation'));
      $this->form_validation->set_data($req_params);

      // BEGIN: Preparing rules
      $rules[] = array('token', 'trim|required');
      $rules[] = array('transaction_id', 'trim|required');
      $rules[] = array('rate', 'trim|required');
      // END: Preparing rules

      set_rules($rules);

      if (($this->form_validation->run() == TRUE)) {
        if ($this->validate_token($this->request['header']['token'])) {
          $params = array();
          $params['token']      = $req_params['token'];
          $params['transaction_id'] = $req_params['transaction_id'];

          if (!empty($req_params['comment']))
            $params['comment'] = $req_params['comment'];

          if (!empty($req_params['rate']))
            $params['rate'] = $req_params['rate'];

          $set_data = $this->transaction_model->create_review_transaction($params);

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

  private function _get_transaction_review()
  {
    if ($this->method == 'GET') {
      $req_params = $this->request['body'];

      $this->load->library(array('form_validation'));
      $this->form_validation->set_data($req_params);

      if (!empty($req_params['transaction_id']))
        $params['ENCRYPTED::transaction_id'] = $req_params['transaction_id'];

      $get_data = $this->transaction_model->get_review_transaction($req_params);

      $this->response = $get_data;
    } else {
      $this->set_response('code', 405);
    }
    $this->print_output();
  }
}

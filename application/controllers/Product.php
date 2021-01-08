<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product extends Base_Controller
{

  public function __construct()
  {
    parent::__construct();

    // Load model
    $this->load->model('product_model');
  }

  // Get list
  public function index()
  {
    $this->_get_product();
  }

  // Get popular list
  public function popular()
  {
    $this->_get_product('popular');
  }

  // Get viewed list
  public function viewed()
  {
    if (!empty($this->request['header']['token'])) {
      $this->_get_product('viewed');
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
          $this->_create_product_wishlist();
          break;
        case 'delete':
          $this->_delete_product_wishlist();
          break;

        default:
          $this->_get_product('wishlist');
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
        $this->_create_product_review();
        break;
      default:
        $this->_get_product_review();
        break;
    }
  }

  // Get detail
  public function detail($id)
  {
    if (!empty($id)) {
      $this->_get_product('detail', array('id' => $id));
    } else {
      $this->set_response('code', 400);
      $this->print_output();
    }
  }

  private function _create_product_wishlist()
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

          $set_data = $this->product_model->create_product_wishlist($params);

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

  private function _delete_product_wishlist()
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

          $set_data = $this->product_model->delete_product_wishlist($params);

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

  private function _get_product($action = '', $request_data = '')
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
      $params['status_active'] = TRUE;

      if (!empty($request_data['id']))
        $params['ENCRYPTED::id'] = $request_data['id'];

      if (!empty($request_data['merchant_id']))
        $params['ENCRYPTED::merchant_id'] = $request_data['merchant_id'];

      if (!empty($request_data['slug']))
        $params['slug'] = $request_data['slug'];

      if (!empty($request_data['name']))
        $params['name'] = 'LIKE::%' . $request_data['name'] . '%';

      if (!empty($request_data['merchant_id']))
        $params['ENCRYPTED::merchant_id'] = $request_data['merchant_id'];

      if (!empty($request_data['min_price']))
        $params['price_selling'] = 'GREATEQ::' . $request_data['min_price'];

      if (!empty($request_data['max_price']))
        $params['price_selling'] = 'LESSEQ::' . $request_data['max_price'];

      if (!empty($request_data['category_id'])) {
        $parameter_category['ENCRYPTED::id'] = $request_data['category_id'];
        $this->load->model('category_model');

        if ($request_data['category_id'] != 'all' && $request_data['category_id'] != 'promo') {
          $params['idxx'] = 'IN::' . $request_data['category_id'] . '[{product_id}{' . $this->product_model->tables['product_to_category'] . '}{ENCRYPTED--category_id}]';

          $get_data = $this->category_model->read($parameter_category);

          // if ($get_data['response']['data'][0]['name'] == 'Sembako') {
          //   $now = new DateTime();
          //   $begin = new DateTime('15:00');
          //   $end = new DateTime('21:00');

          //   if ($now >= $begin && $now <= $end) {
          //     $this->set_response('code', 480);
          //     $this->print_output();
          //   }
          // }
        }

        if ($request_data['category_id'] == 'promo') {
          $params['price_discount'] = 'GREAT::0';
        }
      }

      if (!empty($request_data['showcase_id']))
        $params['idxxx'] = 'IN::' . $request_data['showcase_id'] . '[{product_id}{' . $this->product_model->tables['product_to_showcase'] . '}{ENCRYPTED--showcase_id}]';

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

      $get_data = $this->product_model->read($params, $action);

      // RESPONSE
      $this->response = $get_data;
    } else {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }

  private function _create_product_review()
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

          $set_data = $this->product_model->create_review_product($params);

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

  private function _get_product_review()
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

        $get_data = $this->product_model->get_review_product($params);

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
            $this->_create_product_cart();
            break;
          default:
            $this->_get_product('cart');
            break;
        }
      } else {
        $this->set_response('code', 400);
        $this->print_output();
      }
    } else {
      $this->_delete_product_cart();
    }
  }

  private function _create_product_cart()
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

          $set_data = $this->product_model->create_product_cart($params);

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

  private function _delete_product_cart()
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

          $set_data = $this->product_model->delete_product_cart($params);

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
}

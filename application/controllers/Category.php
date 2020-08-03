<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Category extends Base_Controller
{

  public function __construct()
  {
    parent::__construct();

    // Load model
    $this->load->model('category_model');
  }

  // Get list
  public function index()
  {
    if ($this->method == 'GET') {
      $request_data = $this->request['body'];

      // BEGIN: Preparing request parameters
      $params = array();
      $params['sort'][] = array(
        'sort_by'           => (!empty($request_data['sort_by']) ? $request_data['sort_by'] : 'sort_order'),
        'sort_direction'    => (!empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'asc'),
      );
      $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
      $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);
      $params['status_active'] = TRUE;

      if (!empty($request_data['id']))
        $params['ENCRYPTED::id'] = $request_data['id'];

      if (!empty($request_data['slug']))
        $params['slug'] = $request_data['slug'];

      if (!empty($request_data['name']))
        $params['name'] = 'LIKE::%' . $request_data['name'] . '%';

      if (!empty($request_data['parent_id'])) {
        $params['ENCRYPTED::parent_id'] = $request_data['parent_id'];
      } else {
        $params['parent_id'] = 'NULL';
      }
      // END: Preparing request parameters

      // GET DATA
      $get_data = $this->category_model->read($params);
      $get_data['response']['data'][] = array(
        'id'    => "all",
        'slug'  => "all",
        'name'  => "All",
        'icon'  => $this->config->item('storage_url') . "category/all.png",
        'sort_order'  => "9",
        'created_at'  => "2018-06-01 06:07:24",
        'modified_at' => "2018-06-01 06:07:05",
        'status_active' => "1",
        'parent_id'   => "",
        'childs'  =>  array()
      );
      $get_data['response']['data'][] = array(
        'id'    => "promo",
        'slug'  => "promo",
        'name'  => "Promo",
        'icon'  => $this->config->item('storage_url') . "category/promo.png",
        'sort_order'  => "1",
        'created_at'  => "2018-06-01 06:07:24",
        'modified_at' => "2018-06-01 06:07:05",
        'status_active' => "1",
        'parent_id'   => "",
        'childs'  =>  array()
      );

      function cmp($a, $b)
      {
        return strcmp($a['sort_order'], $b['sort_order']);
      }

      usort($get_data['response']['data'], "cmp");

      // RESPONSE
      $this->response = $get_data;
    } else {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }
}

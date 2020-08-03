<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Slideshow extends Base_Controller {

  public function __construct()
  {
    parent::__construct();

    // Load model
    $this->load->model('slideshow_model');
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
        'sort_by'           => ( ! empty($request_data['sort_by']) ? $request_data['sort_by'] : 'sort_order'),
        'sort_direction'    => ( ! empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'asc'),
      );
      $params['page']   = ( ! empty($request_data['page']) ? (int) $request_data['page'] : 1);
      $params['length'] = ( ! empty($request_data['length']) ? $request_data['length'] : 10);
      $params['status_active'] = TRUE;

      if ( ! empty($request_data['id']))
        $params['ENCRYPTED::id'] = $request_data['id'];

      if ( ! empty($request_data['title']))
        $params['title'] = 'LIKE::%' . $request_data['title'] . '%';

      if ( ! empty($request_data['place']))
        $params['place'] = 'LIKE::%' . $request_data['place'] . '%';

      // END: Preparing request parameters

      // GET DATA
      $get_data = $this->slideshow_model->read($params);

      // RESPONSE
      $this->response = $get_data;
    }
    else
    {
      $this->set_response('code', 405);
    }

    $this->print_output();
  }
}

<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Voucher extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model(array('voucher_model'));
    }

    // Get list
    public function index()
    {
        $this->_get_voucher();
    }

    // Get detail
    public function detail($id)
    {
        if (!empty($id)) {
            $this->_get_voucher('detail', array('id' => $id));
        } else {
            $this->set_response('code', 400);
            $this->print_output();
        }
    }

    private function _get_voucher($action = '', $request_data = '')
    {
        if ($this->method == 'GET') {
            if (empty($request_data)) {
                $request_data = $this->request['body'];
            }

            $params = $request_data;

            $get_data = $this->voucher_model->read($params, $action);

            $this->response = $get_data;
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }
}

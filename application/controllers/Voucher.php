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
            // if (!empty($this->request['header']['token'])) {
            //     if ($this->validate_token($this->request['header']['token'])) {
            if (empty($request_data)) {
                $request_data = $this->request['body'];
            }

            $params = $request_data;

            $get_data = $this->voucher_model->read($params, $action);

            $this->response = $get_data;
            //     } else {
            //         $this->set_response('code', 498);
            //     }
            // } else {
            //     $this->set_response('code', 499);
            // }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    public function verification_voucher()
    {
        if ($this->method == 'POST') {
            if (!empty($this->request['header']['token'])) {
                if ($this->validate_token($this->request['header']['token'])) {
                    $this->load->library('voucher_lib');

                    $request_params = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_params);

                    $rules[] = array('product_id', 'trim|required|callback_validate_jasa_id');
                    $rules[] = array('variant_id', 'trim|required|callback_validate_jasa_variant_id');
                    $rules[] = array('voucher_code', 'trim|required');

                    set_rules($rules);
                    if (($this->form_validation->run() == TRUE)) {
                        $request_params['token'] = $this->request['header']['token'];
                        $get_user = $this->voucher_model->getWhere('user_partner', array('ecommerce_token' => $request_params['token']));

                        unset($request_params['token']);
                        $request_params['user_id'] = $get_user[0]->partner_id;
                        $request_params['type_product'] = 'kita';

                        $getVoucher = $this->voucher_lib->validation_voucher($request_params, 'kita');
                        if ($getVoucher['code'] == 200) {
                            $this->set_response('code', $getVoucher['code']);
                            $this->set_response('data', $getVoucher['data']);
                        } else {
                            $this->set_response('code', $getVoucher['code']);
                            $this->set_response('message', $getVoucher['message']);
                        }
                    } else {
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                    }
                } else {
                    $this->set_response('code', 498);
                }
            } else {
                $this->set_response('code', 499);
            }
        } else {
            $this->set_response('code', 405);
        }
        $this->print_output();
    }
}

<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Withdraw extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('withdraw_model');

        $this->data['api_bigflip'] = $this->config->item('api_bigflip');
        $this->data['api_bigflip'] = $this->data['api_bigflip'][$this->config->item('payment_env')];

        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();
    }

    function read()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $request_data = $this->request['body'];

                    $params = $request_data;
                    $set_data = $this->withdraw_model->read($params);
                } else {
                    $this->set_response('code', 405);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    function create()
    {
        $this->load->model('user_model');
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $request_data = $this->request['body'];

                    $get_user = $this->user_model->get_user_id_decode(array('ecommerce_token' => $this->request['header']['token']));
                    $request_data['withdraw'] = $get_user;

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('amount', 'trim|required|callback_compare_amount[' . $get_user . ']');
                    $rules[] = array('withdraw', 'callback_validate_data_current_day');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $params = $request_data;

                        $req_basic_auth = $this->data['api_bigflip']['server'] . ':';
                        $req_url = $this->data['api_bigflip']['url'] . '/disbursement';

                        $req_data = array(
                            'account_number'    => '0437051936',
                            'bank_code'         => 'bni',
                            'amount'            => $params['amount'],
                            'remark'            => 'testing'
                        );

                        $api_request = $this->curl->post($req_url, $req_data, '', FALSE, FALSE, $req_basic_auth);
                        if ($api_request) {
                            $set_data = $this->withdraw_model->create($params);
                            $withdraw_data = $set_data['response']['data'];

                            if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                                $this->set_response('code', $set_data['code']);
                                $this->set_response('response', array(
                                    'data' => $withdraw_data
                                ));
                            } else {
                                $this->set_response('code', 404);
                            }
                        }
                    } else {
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                    }
                } else {
                    $this->set_response('code', 405);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    function validate_data_current_day($partner_id)
    {
        if (!empty($partner_id)) {
            $where = array(
                'user_id' => $partner_id,
                'substr(created_at, 1, 10) =' => date('Y-m-d')
            );
            $rows = $this->withdraw_model->getWhere('withdraw_request', $where);

            if (count($rows) < 2) {
                return TRUE;
            } else {
                $this->form_validation->set_message('validate_data_current_day', 'permintaan withdraw hanya diperbolehkan dua kali sehari');

                return FALSE;
            }
        } else {
            return TRUE;
        }
    }

    function compare_amount($amount, $mitra_id)
    {
        $current_deposit = $this->withdraw_model->cek_saldo($mitra_id);

        if ($amount > $current_deposit->total_saldo) {
            $this->form_validation->set_message('compare_amount', 'Jumlah saldo kamu kurang!');
            return FALSE;
        } else {
            return TRUE;
        }
    }
}

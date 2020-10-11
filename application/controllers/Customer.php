<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Customer extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->load->model('user_model');
    }

    public function register()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            $credential = $request_data['credential'];
            $cek_credential = strpos($credential, '@');
            if ($cek_credential) {
                $rules[] = array('credential', 'trim|required|valid_email|max_length[100]|callback_validate_email_new', 'Email');
                $type = 'email';
            } else {
                $rules[] = array('credential', 'trim|required|numeric|max_length[15]|callback_validate_phone_new', 'Nomor telepon');
                $type = 'phone';
            }

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {

                if ($type == 'email') {
                    $data['email'] = $request_data['credential'];
                    $set_data = $this->user_model->read($data);
                } else {
                    $data['mobile_number'] = $request_data['credential'];
                    $set_data = $this->user_model->read($data);
                }

                if (isset($set_data['code']) && ($set_data['code'] == 404)) {
                    $set_data = $this->user_model->create($data);
                }

                if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                    $user_data = $set_data['response']['data'][0];

                    // BEGIN: Send Email
                    if ($type == 'email') {

                        $user_data['activated_code'] = rand(1000, 9999);;

                        $this->user_model->update_data(array('email' => $user_data['email']), array('date_added' => date('Y-m-d H:i:s'), 'activated_code' => $user_data['activated_code']), 'user_partner');

                        $get_email_sender = $this->common_model->get_global_setting(array(
                            'group' => 'email',
                            'name' => 'post-master'
                        ));

                        if (!empty($get_email_sender['value'])) {
                            $email_body = $this->load->view('email/activation_code', $user_data, TRUE);

                            $this->load->library('email');

                            $this->email->from($get_email_sender['value'], 'sembilankita');
                            $this->email->to($user_data['email']);

                            $this->email->subject('Aktivasi Akun Sembilankita');
                            $this->email->message($email_body);

                            $send_email = $this->email->send();
                        }
                        $this->set_response('code', 200);
                        $this->set_response('message', 'Kode verifikasi telah dikirimkan melalui email ke ' . $user_data['email']);
                    }
                } else {
                    $this->set_response('code', 404);
                }
            } else {
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    public function activate_account()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            $rules[] = array('credential', 'trim|required');
            $rules[] = array('activated_code', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {

                $time_now = date('Y-m-d H:i:s');
                $time_exp = date('Y-m-d H:i:s', strtotime('-30 minutes', strtotime($time_now)));

                $params = $request_data;
                $params['date_added'] = 'GREATEQ::' . $time_exp;

                $get_data = $this->user_model->read($params);

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {

                    $cek_credential = strpos($params['credential'], '@');
                    if ($cek_credential) {
                        $this->user_model->update_data(array('email' => $params['credential']), array('activated_code' => Null, 'customer_activated' => '1'), 'user_partner');
                    } else {
                        $this->user_model->update_data(array('mobile_number' => $params['credential']), array('activated_code' => Null, 'customer_activated' => '1'), 'user_partner');
                    }

                    $this->set_response('code', 200);
                    $this->set_response('message', 'Selamat akun anda berhasil di aktivasi');
                } else {
                    $this->set_response('code', 404);
                    $this->set_response('message', 'Kode tidak valid');
                }
            } else {
                $this->set_response('code', 400);
                $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                $this->set_response('data', get_rules_error($rules));
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    public function validate_email_current($email)
    {
        // Connection
        $this->load->model('common_model');
        $conn = $this->common_model->get_connection('main');

        $data_found = $conn->select('email')
            ->from("user_partner")
            ->where(array(
                'email' => $email,
                'ecommerce_token !=' => $this->request['header']['token']
            ))
            ->count_all_results();

        if ($data_found > 0) {
            $this->form_validation->set_message('validate_email_current', $this->language['message_email_already_taken']);

            return FALSE;
        } else {
            return TRUE;
        }
    }
}

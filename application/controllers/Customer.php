<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Customer extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->load->model('user_model');
    }

    public function login()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            $credential = $request_data['credential'];
            $cek_credential = strpos($credential, '@');
            if ($cek_credential) {
                $rules[] = array('credential', 'trim|required|valid_email|max_length[100]', 'Email');
                $type = 'email';
            } else {
                $rules[] = array('credential', 'trim|required|numeric|max_length[15]', 'Nomor telepon');
                $type = 'phone';
            }

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {

                if ($type == 'email') {
                    $data['email'] = $request_data['credential'];
                    $data['customer_activated'] = '1';
                    $get_data = $this->user_model->read($data);
                } else {
                    $data['mobile_number'] = $request_data['credential'];
                    $data['customer_activated'] = '1';
                    $get_data = $this->user_model->read($data);
                }

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                    $user_data = $get_data['response']['data'][0];

                    $user_data['type'] = 'login';
                    $user_data['activated_code'] = rand(100000, 999999);
                    $this->user_model->update_data(array('email' => $user_data['email']), array('created_at_code' => date('Y-m-d H:i:s'), 'activated_code' => $user_data['activated_code']), 'user_partner');

                    // BEGIN: Send Email
                    if ($type == 'email') {
                        $get_email_sender = $this->common_model->get_global_setting(array(
                            'group' => 'email',
                            'name' => 'post-master'
                        ));

                        if (!empty($get_email_sender['value'])) {
                            $email_body = $this->load->view('email/activation_code', $user_data, TRUE);

                            $this->load->library('email');

                            $this->email->from($get_email_sender['value'], 'sembilankita');
                            $this->email->to($user_data['email']);

                            $this->email->subject('Kode Login Akun Sembilankita');
                            $this->email->message($email_body);

                            $send_email = $this->email->send();
                        }
                        $this->set_response('code', 200);
                        $this->set_response('message', 'Kode OTP telah dikirimkan melalui email ke ' . $user_data['email']);
                    } else {
                        $this->set_response('code', 200);
                        $this->set_response('message', 'Silakan pilih metode pengiriman OTP');
                    }
                } else {
                    $this->set_response('code', 404);
                    $this->set_response('message', 'Nomor telepon / Email belum terdaftar');
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

                        $user_data['type'] = 'register';
                        $user_data['activated_code'] = rand(100000, 999999);

                        $this->user_model->update_data(array('email' => $user_data['email']), array('created_at_code' => date('Y-m-d H:i:s'), 'activated_code' => $user_data['activated_code']), 'user_partner');

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
                    } else {
                        $this->set_response('code', 200);
                        $this->set_response('message', 'Silakan pilih metode pengiriman OTP');
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

    public function send_otp()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            $rules[] = array('credential', 'trim|required');
            $rules[] = array('method', 'trim|required');
            $rules[] = array('type', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {

                $user_data['activated_code'] = rand(100000, 999999);
                $this->user_model->update_data(array('mobile_number' => $request_data['credential']), array('created_at_code' => date('Y-m-d H:i:s'), 'activated_code' => $user_data['activated_code']), 'user_partner');

                $this->send->index('sendOtp', $request_data['credential'], $user_data['activated_code'], $request_data['type']);

                if ($request_data['type'] == 'register') {
                    $ket = 'OTP proses daftar anda';
                } elseif ($request_data['type'] == 'login') {
                    $ket = 'OTP proses login anda';
                } else {
                    $ket = 'OTP proses verifikasi nomor telepon anda';
                }
                $this->set_response('code', 200);
                $this->set_response('message', 'Kode ' . $ket . ' telah dikirimkan melalui whatsapp ke nomor ' . $request_data['credential']);
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


    public function verifikasi_code()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            $rules[] = array('credential', 'trim|required');
            $rules[] = array('activated_code', 'trim|required');
            $rules[] = array('type', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $type = $request_data['type'];
                unset($request_data['type']);

                $time_now = date('Y-m-d H:i:s');
                $time_exp = date('Y-m-d H:i:s', strtotime('-30 minutes', strtotime($time_now)));

                $params = $request_data;
                $params['created_at_code'] = 'GREATEQ::' . $time_exp;

                $get_data = $this->user_model->read($params);

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {

                    $cek_credential = strpos($params['credential'], '@');
                    if ($type == 'register') {
                        if ($cek_credential) {
                            $this->user_model->update_data(array('email' => $params['credential']), array('activated_code' => Null, 'customer_activated' => '1', 'phone_verified' => '1'), 'user_partner');
                        } else {
                            $this->user_model->update_data(array('mobile_number' => $params['credential']), array('activated_code' => Null, 'customer_activated' => '1', 'phone_verified' => '1'), 'user_partner');
                        }

                        $token = hash('sha1', time() . $this->config->item('encryption_key'));
                        $get_data['response']['data'][0]['ecommerce_token'] = $token;
                        $user_data = $get_data['response']['data'][0];
                        $this->user_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token, 'activated_code' => Null));

                        $this->set_response('code', 200);
                        $this->set_response('message', 'Selamat akun anda telah berhasil di verifikasi');
                        $this->set_response('response', array(
                            'data' => $user_data
                        ));
                    } elseif ($type == 'login') {
                        if ($get_data['response']['data'][0]['user_type'] == 'user') {
                            $token = hash('sha1', time() . $this->config->item('encryption_key'));
                            $get_data['response']['data'][0]['ecommerce_token'] = $token;
                            $get_data['response']['data'][0]['activated_code'] = Null;
                            $user_data = $get_data['response']['data'][0];

                            if ($get_data['response']['data'][0]['phone_verified'] == '0'  && !$cek_credential) {
                                $this->user_model->update_data(array('mobile_number' => $params['credential']), array('phone_verified' => '1'), 'user_partner');
                                $user_data['phone_verified'] = '1';
                            }
                            $this->user_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token, 'activated_code' => Null));

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        } else {
                            $this->set_response('code', 403);
                        }
                    } elseif ($type == 'verifikasi') {
                        $user_data = $get_data['response']['data'][0];
                        $this->user_model->update_data(array('mobile_number' => $params['credential']), array('phone_verified' => '1'), 'user_partner');
                        $this->set_response('code', 200);
                        $this->set_response('message', 'Selamat nomor telepon anda telah berhasil di verifikasi');
                    }
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

    function verifikasi_code_sms()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            $rules[] = array('credential', 'trim|required');
            $rules[] = array('type', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $type = $request_data['type'];
                unset($request_data['type']);
                $params = $request_data;

                $cek_credential = strpos($params['credential'], '@');
                if (!$cek_credential) {
                    $params['credential']   = preg_replace('/^(\+62|62|0)?/', "0", $params['credential']);
                }

                $get_data = $this->user_model->read($params);
                if ($type == 'register') {
                    if ($cek_credential) {
                        $this->user_model->update_data(array('email' => $params['credential']), array('activated_code' => Null, 'customer_activated' => '1', 'phone_verified' => '1'), 'user_partner');
                    } else {
                        $this->user_model->update_data(array('mobile_number' => $params['credential']), array('activated_code' => Null, 'customer_activated' => '1', 'phone_verified' => '1'), 'user_partner');
                    }
                    $token = hash('sha1', time() . $this->config->item('encryption_key'));
                    $get_data['response']['data'][0]['ecommerce_token'] = $token;
                    $user_data = $get_data['response']['data'][0];
                    $this->user_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token, 'activated_code' => Null));

                    $this->set_response('code', 200);
                    $this->set_response('message', 'Selamat akun anda telah berhasil di verifikasi');
                    $this->set_response('response', array(
                        'data' => $user_data
                    ));
                } elseif ($type == 'login') {
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        if ($get_data['response']['data'][0]['user_type'] == 'user') {
                            $token = hash('sha1', time() . $this->config->item('encryption_key'));
                            $get_data['response']['data'][0]['ecommerce_token'] = $token;
                            $get_data['response']['data'][0]['activated_code'] = Null;

                            $user_data = $get_data['response']['data'][0];

                            if ($user_data['phone_verified'] == '0' && !$cek_credential) {
                                $this->user_model->update_data(array('mobile_number' => $params['credential']), array('phone_verified' => '1'), 'user_partner');
                                $user_data['phone_verified'] = '1';
                            }
                            $this->user_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token, 'activated_code' => Null));

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        } else {
                            $this->set_response('code', 403);
                        }
                    }
                } elseif ($type == 'verifikasi') {
                    $user_data = $get_data['response']['data'][0];
                    $this->user_model->update_data(array('mobile_number' => $params['credential']), array('phone_verified' => '1'), 'user_partner');
                    $this->set_response('code', 200);
                    $this->set_response('message', 'Selamat nomor telepon anda telah berhasil di verifikasi');
                    $this->set_response('response', array(
                        'data' => $user_data
                    ));
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

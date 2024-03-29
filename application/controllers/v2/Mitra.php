<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mitra extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->load->model('v2/mitra_model');
        $this->load->model('deposit_model');
    }

    public function profile()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $get_data = $this->mitra_model->read(array('ecommerce_token' => $this->request['header']['token']));
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        if ($get_data['response']['data'][0]['user_type'] == 'mitra') {
                            $user_data = $get_data['response']['data'][0];
                            unset($user_data['ecommerce_token']);

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        } else {
                            $this->set_response('code', 403);
                        }
                    } else {
                        $this->set_response('code', 404);
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

    function get_mitra_profile()
    {
        $request_data = $this->request['body'];
        $get_data = $this->mitra_model->get_user(array('referral_code' => $request_data['mitra_code']));
        if ($get_data) {
            $get_data = $get_data[0];
            unset($get_data['ecommerce_token']);
            unset($get_data['password']);
            unset($get_data['current_deposit']);

            if (!empty($get_data['img']) && file_exists($this->config->item('storage_path') . 'user/' . $get_data['img'])) {
                $get_data['img'] = $this->config->item('storage_url') . 'user/' . $get_data['img'];
            } else {
                $get_data['img'] = $this->config->item('storage_url') . 'user/no-image.png';
            }
            $this->set_response('code', 200);
            $this->set_response('response', array(
                'data' => $get_data
            ));
        } else {
            $this->set_response('code', 404);
        }
        $this->print_output();
    }

    public function agent()
    {
        if ($this->method == 'GET') {
            $request_data = $this->request['body'];

            // BEGIN: Preparing request parameters
            $params = array();
            // Default params
            $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
            $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);

            $params['(' . $this->tables['user'] . '.user_type = "agent" or ' . $this->tables['user'] . '.user_type = "reseller")'] = null;
            $params[$this->tables['user'] . '.status_active'] = '1';

            if (!empty($request_data['latitude']) && !empty($request_data['longitude'])) {
                $params['latitude']   = $request_data['latitude'];
                $params['longitude']  = $request_data['longitude'];
            } else {
                $params['sort'][] = array(
                    'sort_by'           => (!empty($request_data['sort_by']) ? $request_data['sort_by'] : 'RAND()'),
                    'sort_direction'    => (!empty($request_data['sort_direction']) ? $request_data['sort_direction'] : 'asc'),
                );
            }

            // Additional Params
            if (!empty($request_data['id'])) {
                $params[$this->mitra_model->encrypted_column($this->tables['user'] . '.id', '=')] = $request_data['id'];
            }

            if (!empty($request_data['referral_code'])) {
                $params[$this->mitra_model->lowered_column($this->tables['user'] . '.referral_code', '=')] = $request_data['referral_code'];
            }

            if (!empty($request_data['name'])) {
                $params[$this->mitra_model->lowered_column($this->tables['user'] . '.name', 'like')] = '%' . strtolower($request_data['name']) . '%';
            }

            if (!empty($request_data['email'])) {
                $params[$this->mitra_model->lowered_column($this->tables['user'] . '.email', '=')] = strtolower($request_data['name']);
            }
            // END: Preparing request parameters

            $get_data = $this->mitra_model->read($params);

            // RESPONSE
            $this->response = $get_data;
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    public function referral_list()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                $referral_id = $this->mitra_model->get_user_id(array('token' => $this->request['header']['token']));

                $request_data = $this->request['body'];

                // BEGIN: Preparing request parameters
                $params = array();
                // Default params
                $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
                $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);
                $params[$this->tables['user'] . '.referral_id'] = $referral_id;
                $params[$this->tables['user'] . '.status_active'] = '1';
                // END: Preparing request parameters

                $get_data = $this->mitra_model->read($params);

                // RESPONSE
                $this->response = $get_data;
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    // Login
    public function login()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('email', 'trim|required|valid_email');
            $rules[] = array('password', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $get_data = $this->mitra_model->read(array(
                    'email'         => $request_data['email'],
                    'password'      => hash('sha1', $request_data['password'] . $this->config->item('encryption_key'))
                ));

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                    if ($get_data['response']['data'][0]['user_type'] == 'mitra') {
                        // $token = AUTHORIZATION::generateToken(['partner_id' => $get_data['response']['data'][0]['partner_id']]);
                        $token = hash('sha1', time() . $this->config->item('encryption_key'));
                        $get_data['response']['data'][0]['ecommerce_token'] = $token;

                        $user_data = $get_data['response']['data'][0];

                        // BEGIN: Update Token
                        $this->mitra_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token));
                        // END: Update Token

                        $this->set_response('code', 200);
                        if ($get_data['response']['data'][0]['suspend'] == '1') {
                            $this->set_response('message', 'Maaf, akun anda telah di suspend, mohon segera menghubungi BP (Business Partner) atau Teknikal Support Sembilan Kita.');
                        }
                        $this->set_response('response', array(
                            'data' => $user_data
                        ));
                        // }
                    } else {
                        $this->set_response('code', 403);
                    }
                } else {
                    $this->set_response('code', 404);
                    $this->set_response('message', $this->language['invalid_user_password']);
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

    // Login by Auth
    public function auth()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('email', 'trim|required|valid_email');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $get_data = $this->mitra_model->read(array(
                    'email' => $request_data['email']
                ));

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                    // $token = AUTHORIZATION::generateToken(['partner_id' => $get_data['response']['data'][0]['partner_id']]);
                    $token = hash('sha1', time() . $this->config->item('encryption_key'));
                    $get_data['response']['data'][0]['ecommerce_token'] = $token;

                    // BEGIN: Update Token
                    $this->mitra_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token));
                    // END: Update Token
                    $user_data = $get_data['response']['data'][0];

                    $this->set_response('code', 200);
                    $this->set_response('response', array(
                        'data' => $user_data
                    ));
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

    // Register
    public function register()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('name', 'trim|required|min_length[2]|max_length[100]');
            $rules[] = array('email', 'trim|required|valid_email|max_length[100]|callback_validate_email_new');
            $rules[] = array('phone', 'trim|required|numeric|max_length[15]');
            $rules[] = array('password', 'trim|min_length[6]|required');
            $rules[] = array('password_confirm', 'trim|min_length[6]|matches[password]');
            //$rules[] = array('user_type', 'trim|regex_match[(mitra|agent|reseller|customer)]');
            $rules[] = array('auth_type', 'trim|regex_match[(facebook|google|twitter)]');
            $rules[] = array('auth_id', 'trim');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $user_type = (empty($request_data['user_type']) ? 'customer' : $request_data['user_type']);
                $auth_type = (!empty($request_data['auth_type']) ? $request_data['auth_type'] : null);
                $auth_id = (!empty($request_data['auth_id']) ? $request_data['auth_id'] : null);

                $data = array(
                    'full_name'     => $request_data['name'],
                    'email'         => $request_data['email'],
                    'mobile_number' => $request_data['phone'],
                    'password'      => hash('sha1', $request_data['password'] . $this->config->item('encryption_key')),
                    // 'token'      => hash('sha1', time() . $this->config->item('encryption_key')),
                    // 'token'      => AUTHORIZATION::generateToken('simaungproject'),
                    // 'user_type'     => $user_type,
                    // 'auth_type'     => $auth_type,
                    // 'auth_id'       => $auth_id,
                    // 'status_active' => (($user_type == 'agent') ? 0 : 1),
                );

                $set_data = $this->mitra_model->create($data);

                if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                    $user_data = $set_data['response']['data'];

                    // BEGIN: Send Email
                    $get_email_sender = $this->common_model->get_global_setting(array(
                        'group' => 'email',
                        'name' => 'post-master'
                    ));

                    if (!empty($get_email_sender['value'])) {
                        $email_body = $this->load->view('email/user_registration', $user_data, TRUE);

                        $this->load->library('email');

                        $this->email->from($get_email_sender['value'], '9tani');
                        $this->email->to($user_data['email']);

                        $this->email->subject(lang('subject_user_registration'));
                        $this->email->message($email_body);

                        $send_email = $this->email->send();
                    }
                    // END: Send Email

                    $this->set_response('code', 200);
                    $this->set_response('response', array(
                        'data' => $user_data
                    ));
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

    // Update
    public function update()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('name', 'trim|required|min_length[2]|max_length[100]');
                    $rules[] = array('email', 'trim|required|valid_email|max_length[100]|callback_validate_email_current');
                    $rules[] = array('phone', 'trim|required|numeric|max_length[15]');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $data = array(
                            'full_name'     => $request_data['name'],
                            'email'         => $request_data['email'],
                            'mobile_number' => $request_data['phone'],
                        );

                        $user_id = $this->mitra_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $set_data = $this->mitra_model->update($user_id, $data);

                        if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                            $user_data = $set_data['response']['data'];

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
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
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    // Update Password
    public function update_password()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('old_password', 'trim|required');
                    $rules[] = array('password', 'trim|min_length[6]|required');
                    $rules[] = array('password_confirm', 'trim|min_length[6]|matches[password]');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $data = array(
                            'password' => hash('sha1', $request_data['password'] . $this->config->item('encryption_key')),
                        );

                        $cek_password = $this->mitra_model->get_user_password(array('password' => hash('sha1', $request_data['old_password'] . $this->config->item('encryption_key'))));

                        if ($cek_password) {
                            $user_id = $this->mitra_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                            $set_data = $this->mitra_model->update($user_id, $data);

                            if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                                $user_data = $set_data['response']['data'];

                                $this->set_response('code', 200);
                                $this->set_response('response', array(
                                    'data' => $user_data
                                ));
                            } else {
                                $this->set_response('code', 404);
                            }
                        } else {
                            $this->set_response('code', 496);
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

    // Update Photo
    public function update_photo()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $get_user = $this->mitra_model->get_user(array('ecommerce_token' => $this->request['header']['token']));

                    // Photo
                    if (!empty($_FILES['photo']['tmp_name'])) {
                        $data['img'] = $this->upload_photo_file('photo');

                        if ($data['img']) {
                            $temp_path = $this->config->item('storage_path') . 'user/';
                            unlink($temp_path . $get_user[0]['img']);
                        }

                        // Move photo file
                        $this->reconcile_photo_file($data['img']);

                        $user_id = $this->mitra_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $set_data = $this->mitra_model->update($user_id, $data);

                        if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                            $user_data = $set_data['response']['data'];

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        } else {
                            $this->set_response('code', 404);
                        }
                    } else {
                        $this->set_response('code', 400);
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

    public function verified()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    // Photo
                    if (!empty($_FILES['image_idcard']['tmp_name']) && !empty($_FILES['image_selfie']['tmp_name'])) {
                        $data['image_idcard'] = $this->upload_photo_file('image_idcard');
                        $this->reconcile_photo_file($data['image_idcard']);

                        $data['image_selfie'] = $this->upload_photo_file('image_selfie');
                        $this->reconcile_photo_file($data['image_selfie']);

                        // $data['verified'] = '1';

                        $user_id = $this->mitra_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $set_data = $this->mitra_model->update($user_id, $data);

                        if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                            $user_data = $set_data['response']['data'];

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        } else {
                            $this->set_response('code', 404);
                        }
                    } else {
                        $this->set_response('code', 400);
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

    // Reset Password
    public function reset_password()
    {
        switch ($this->method) {
            case 'GET':
                $request_data = $this->request['body'];

                if (!empty($request_data['reset_token'])) {
                    $get_user = $this->mitra_model->get_user(array('reset_token' => $request_data['reset_token']));

                    if ($get_user) {
                        $this->load->view('reset_password', $this->data);
                    } else {
                        redirect(base_url() . 'console/page_error/' . 404);
                    }
                } else {
                    redirect(base_url() . 'console/page_error/' . 404);
                }
                break;
            case 'POST':
                $reset_token = $this->input->get('reset_token');
                $get_user = $this->mitra_model->get_user(array('reset_token' => $reset_token));

                if ($get_user) {
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('password', 'trim|required');
                    $rules[] = array('password_confirm', 'trim|matches[password]');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $set_data = $this->mitra_model->update($get_user[0]['partner_id'], array(
                            'password'    => hash('sha1', $request_data['password'] . $this->config->item('encryption_key')),
                            'reset_token' => null,
                        ));

                        if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                            $user_data = $set_data['response']['data'];

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                            $this->print_output();
                        } else {
                            $this->set_response('code', 404);
                            $this->print_output();
                        }
                    } else {
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                        $this->print_output();
                    }
                } else {
                    $this->set_response('code', 405);
                    $this->print_output();
                }
                break;

            default:
                $this->set_response('code', 405);
                $this->print_output();
                break;
        }
    }

    // Reset Password
    public function reset_token()
    {
        if ($this->method == 'POST') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('email', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $get_user = $this->mitra_model->get_user(array('email' => $request_data['email']));
                $get_email_sender = $this->common_model->get_global_setting(array('group' => 'email', 'name' => 'post-master'));

                if ($get_user && !empty($get_user[0]['email']) && !empty($get_email_sender['value'])) {
                    $reset_token = hash('sha1', time() . $this->config->item('encryption_key'));

                    $email_data['url'] = base_url() . 'user/reset_password?reset_token=' . $reset_token;

                    $email_body = $this->load->view('email/reset_password', $email_data, TRUE);

                    $set_data = $this->mitra_model->update($get_user[0]['partner_id'], array('reset_token' => $reset_token));

                    // Send email reset
                    $this->load->library('email');

                    $this->email->from($get_email_sender['value'], '9 Tani');
                    $this->email->to($get_user[0]['email']);

                    $this->email->subject(lang('subject_email_reset_password'));
                    $this->email->message($email_body);

                    $send_email = $this->email->send();

                    $this->set_response('code', 200);
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

    // Get Payment URL
    public function get_payment_url()
    {
        if ($this->method == 'GET') {
            $request_data = $this->request['body'];

            $this->load->library(array('form_validation'));
            $this->form_validation->set_data($request_data);

            // BEGIN: Preparing rules
            $rules[] = array('email', 'trim|required');

            set_rules($rules);

            if (($this->form_validation->run() == TRUE)) {
                $get_user = $this->mitra_model->get_user(array(
                    'email'         => $request_data['email'],
                    'user_type !='  => 'customer',
                    'status_active' => '0'
                ));

                if ($get_user) {
                    $get_pricing = $this->common_model->get_global_setting(array(
                        'group' => 'pricing',
                        'name'  => 'user_' . $get_user[0]['user_type']
                    ));

                    if ($get_pricing) {
                        $get_user_invoice = $this->mitra_model->get_user_invoice(array(
                            'user_id' => $get_user[0]['id']
                        ));

                        if ($get_user_invoice) {
                            switch ($get_user_invoice[0]['payment_status']) {
                                case 'pending':
                                    $this->set_response('response', array(
                                        'data' => array(
                                            'payment_url' => base_url() . 'transaction/payment?type=account&invoice_number=' . $get_user_invoice[0]['invoice_number']
                                        )
                                    ));
                                    break;

                                case 'failed':
                                    // Delete existing invoice
                                    $this->mitra_model->unset_user_invoice(array(
                                        'id' => $get_user_invoice[0]['id']
                                    ));

                                    // Create new invoice
                                    $set_user_invoice = $this->mitra_model->set_user_invoice(
                                        array(
                                            'user_id'         => $get_user[0]['id'],
                                            'invoice_date'    => date('Y-m-d'),
                                            'payment_amount'  => (float) $get_pricing['value'],
                                            'status_active'   => 1,
                                        ),
                                        array(),
                                        true // response as data
                                    );

                                    $this->set_response('response', array(
                                        'data' => array(
                                            'payment_url' => base_url() . 'transaction/payment?type=account&invoice_number=' . $set_user_invoice[0]['invoice_number']
                                        )
                                    ));
                                    break;

                                default:
                                    $this->set_response('code', 404);
                                    break;
                            }
                        } else {
                            // Create new invoice
                            $set_user_invoice = $this->mitra_model->set_user_invoice(
                                array(
                                    'user_id'         => $get_user[0]['id'],
                                    'invoice_date'    => date('Y-m-d'),
                                    'payment_amount'  => (float) $get_pricing['value'],
                                    'status_active'   => 1,
                                ),
                                array(),
                                true // response as data
                            );

                            $this->set_response('response', array(
                                'data' => array(
                                    'payment_url' => base_url() . 'transaction/payment?type=account&invoice_number=' . $set_user_invoice[0]['invoice_number']
                                )
                            ));
                        }
                    } else {
                        $this->set_response('code', 404);
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

    private function upload_photo_file($type)
    {
        // $temp_path = 'assets/img/user/';
        $temp_path = $this->config->item('storage_path') . 'user/';

        $config['upload_path']      = $temp_path;
        $config['allowed_types']  = 'jpg|jpeg|png';
        $config['max_size']       = 4096; // 4 MB
        $config['file_name'] = md5(time() . uniqid());

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if ($this->upload->do_upload($type)) {
            if ($type == 'photo') {
                $this->resizeImage($this->upload->data('file_name'), 100);

                // convert to webp
                $name = $this->upload->data('file_name');
                // $newName = (explode('.', $name));
                // $newName = $newName[0] . '.webp';
                $newName = $name . '.webp';

                if ($this->upload->data('file_ext') == '.jpg' || $this->upload->data('file_ext') == '.jpeg') {
                    $img = imagecreatefromjpeg($temp_path . $name);
                } elseif ($this->upload->data('file_ext') == '.png') {
                    $img = imagecreatefrompng($temp_path . $name);
                }

                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                imagewebp($img, $temp_path . $newName, 100);
                imagedestroy($img);

                unlink($temp_path . $name);
                return $newName;
            } else {
                $this->resizeImage($this->upload->data('file_name'), 600);
                $name = $this->upload->data('file_name');
                return $name;
            }
        } else {
            $this->set_response('code', 300);
            $this->set_response('message', $this->upload->display_errors('', ''));
            $this->print_output();
        }
    }

    private function resizeImage($filename, $ukuran)
    {
        // $source_path = 'assets/img/user/' . $filename;
        $source_path = $this->config->item('storage_path') . 'user/' . $filename;
        $target_path = $source_path;

        $config_manip = array(
            'image_library' => 'gd2',
            'source_image' => $source_path,
            'new_image' => $target_path,
            'maintain_ratio' => TRUE,
            // 'create_thumb' => TRUE,
            // 'thumb_marker' => '_thumb',
            'width' => $ukuran,
            'height' => $ukuran
        );

        $this->load->library('image_lib', $config_manip);
        if (!$this->image_lib->resize()) {
            $this->set_response('code', 400);
            $this->set_response('message', $this->image_lib->display_errors());
            $this->print_output();
        }

        $this->image_lib->clear();
    }

    private function reconcile_photo_file($photo)
    {
        // Move images
        $temp_path = $this->config->item('storage_path') . 'temp/';
        $upload_path = $this->config->item('storage_path') . 'user/';

        if (!is_dir(rtrim($upload_path, '/'))) mkdir(rtrim($upload_path), 0775, TRUE);

        if (!empty($photo) && file_exists($temp_path . $photo)) {
            rename($temp_path . $photo, $upload_path . $photo);
        }
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

    public function validasi_referal()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                $get_data = $this->mitra_model->read(array('referral_code' => $this->request['body']['referral_code'], 'user_type' => "mitra"));

                // RESPONSE
                if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                    $user_data = $get_data['response']['data'][0];

                    $this->set_response('code', 200);
                    $this->set_response('response', array(
                        'data' => array('name' => $user_data['full_name'])
                    ));
                } else {
                    $this->set_response('code', 404);
                }
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function get_partner_id()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                $partner_id = $this->mitra_model->get_partner_id(array('token' => $this->request['header']['token']));

                // RESPONSE
                $this->set_response('code', 200);
                $this->set_response('response', array(
                    'data' => $partner_id
                ));
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function add_device()
    {
        if ($this->method == 'POST') {
            if (!empty($this->request['header']['token'])) {
                if ($this->validate_token($this->request['header']['token'])) {

                    $token = $this->request['header']['token'];
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('device_id', 'trim|required');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {

                        $data = array(
                            'token'         => $token,
                            'device_id'     => $request_data['device_id'],
                        );

                        $set_data = $this->mitra_model->set_device($data);

                        if ($set_data) {
                            $this->set_response('code', 200);
                        } else {
                            $this->set_response('code', 404);
                        }
                    } else {
                        $this->set_response('code', 400);
                        $this->set_response('message', sprintf($this->language['error_response'], $this->language['response'][400]['title'], validation_errors()));
                        $this->set_response('data', get_rules_error($rules));
                    }
                } else {
                    $this->set_response('code', 498);
                    $this->print_output();
                }
            } else {
                $this->set_response('code', 499);
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }

    public function update_status_active()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $token = $this->request['header']['token'];
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('status_active', 'trim|required');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {

                        $data = array(
                            'token'                 => $token,
                            'status_active'         => $request_data['status_active'],
                        );

                        $set_data = $this->mitra_model->update_profile($data);

                        if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                            $user_data = $set_data['response']['data'][0];
                            unset($user_data['ecommerce_token']);

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
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
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function update_status_profile()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $token = $this->request['header']['token'];
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('status', 'trim');
                    $rules[] = array('tipe_customer', 'trim');
                    $rules[] = array('allowed_distance', 'trim');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {

                        $data = array(
                            'token' => $token,
                        );

                        if (!empty($request_data['status']))
                            $data['status_profile'] = $request_data['status'];

                        if (!empty($request_data['tipe_customer']))
                            $data['tipe_customer'] = $request_data['tipe_customer'];

                        if (!empty($request_data['allowed_distance']))
                            $data['allowed_distance'] = $request_data['allowed_distance'];

                        if (!empty($data)) {

                            $set_data = $this->mitra_model->update_profile($data);

                            if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                                $user_data = $set_data['response']['data'][0];
                                unset($user_data['ecommerce_token']);

                                $this->set_response('code', 200);
                                $this->set_response('response', array(
                                    'data' => $user_data
                                ));
                            } else {
                                $this->set_response('code', 404);
                            }
                        }

                        // if (!empty($request_data['status'])) {
                        //     $data = array(
                        //         'token'         => $token,
                        //         'field'         => 'status_profile',
                        //         'value'         => $request_data['status'],
                        //     );
                        // } elseif (!empty($request_data['tipe_customer'])) {
                        //     $data = array(
                        //         'token'         => $token,
                        //         'field'         => 'tipe_customer',
                        //         'value'         => $request_data['tipe_customer'],
                        //     );
                        // }

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

    public function update_current_location()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $token = $this->request['header']['token'];
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('latitude', 'trim|required');
                    $rules[] = array('longitude', 'trim|required');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {

                        $data = array(
                            'token'         => $token,
                            'latitude'      => $request_data['latitude'],
                            'longitude'     => $request_data['longitude'],
                        );

                        $set_data = $this->mitra_model->update_current_location($data);

                        if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                            $user_data = $set_data['response']['data'][0];

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
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
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function order_stat()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $token = $this->request['header']['token'];

                    $get_data = $this->mitra_model->get_order_stat($token);

                    $this->set_response('code', 200);
                    $this->set_response('response', array(
                        'data' => $get_data['response']['data']
                    ));
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

    public function get_deposit()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $token = $this->request['header']['token'];

                    $request_data = $this->request['body'];
                    $params['page']     = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
                    $params['length']   = (!empty($request_data['length']) ? (int) $request_data['length'] : 10);

                    $get_data = $this->mitra_model->get_deposit_history($token, $params);
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        $data = array();
                        foreach ($get_data['response']['data'] as $key => $row) {
                            unset($get_data['response']['data'][$key]['dtd_id']);
                            unset($get_data['response']['data'][$key]['partner_id']);
                        }
                        // $deposit_data = $get_data['response']['data'][0];

                        $this->set_response('code', 200);
                        $this->set_response('response', array(
                            'data'      => $get_data['response']['data'],
                            'summary'   => $get_data['response']['summary']
                        ));
                    } else {
                        $this->set_response('code', 404);
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

    function topup()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    $rules[] = array('amount', 'trim|required|callback_minimum_amount');
                    set_rules($rules);
                    if ($this->form_validation->run() == TRUE) {
                        $params = array(
                            'token'     => $this->request['header']['token'],
                            'amount'    => $request_data['amount']
                        );
                        $set_topup = $this->deposit_model->create($params);
                        if (!empty($set_topup['code']) && ($set_topup['code'] == 200)) {
                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' =>  $set_topup['response']['data']
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
            } else {
                $this->set_response('code', 498);
            }
        } else {
            $this->set_response('code', 499);
        }

        $this->print_output();
    }

    public function get_topup()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $token = $this->request['header']['token'];

                    $request_data = $this->request['body'];
                    $params['page']     = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
                    $params['length']   = (!empty($request_data['length']) ? (int) $request_data['length'] : 10);

                    $get_data = $this->mitra_model->get_data_topup($token, $params);
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        $data = array();
                        foreach ($get_data['response']['data'] as $key => $row) {
                            unset($get_data['response']['data'][$key]['dtd_id']);
                            unset($get_data['response']['data'][$key]['partner_id']);
                        }
                        // $deposit_data = $get_data['response']['data'][0];

                        $this->set_response('code', 200);
                        $this->set_response('response', array(
                            'data'      => $get_data['response']['data'],
                            'summary'   => $get_data['response']['summary']
                        ));
                    } else {
                        $this->set_response('code', 404);
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

    public function check_form()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $get_data = $this->mitra_model->read(array('ecommerce_token' => $this->request['header']['token']));
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        $partner_id = $this->mitra_model->getValueEncode('partner_id', 'user_partner', $get_data['response']['data'][0]['partner_id']);

                        $where = array(
                            'partner_id'    => $partner_id,
                            'created_at'    => date('Y-m-d')
                        );
                        $get_form = $this->mitra_model->getWhere('tbl_answering', $where);
                        if (empty($get_form)) {
                            $this->set_response('code', 400);
                            $this->set_response('message', 'Anda belum mengisi formulir hari ini');
                            $this->set_response('url', 'https://sembilankita.com/form/kesehatan?header=no&partner_id=' . $get_data['response']['data'][0]['partner_id']);
                        } else {
                            $this->set_response('code', 200);
                        }
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

    function get_qrcode()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $get_data = $this->mitra_model->read(array('ecommerce_token' => $this->request['header']['token']));
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        $path_img = $this->config->item('storage_path') . 'qrcode/';
                        $image_name = $get_data['response']['data'][0]['referral_code'] . '.jpg'; //buat name dari qr code sesuai
                        if (file_exists($path_img . $image_name)) {
                            $this->set_response('code', 200);
                            $this->set_response('image_url', $this->config->item('storage_url') . 'qrcode/' . $image_name);
                        } else {
                            $this->load->library('ciqrcode'); //pemanggilan library QR CODE

                            $config['cacheable']    = true; //boolean, the default is true
                            $config['imagedir']     = $path_img; //direktori penyimpanan qr code
                            $config['quality']      = true; //boolean, the default is true
                            $config['size']         = '1024'; //interger, the default is 1024
                            $config['black']        = array(224, 255, 255); // array, default is array(255,255,255)
                            $config['white']        = array(70, 130, 180); // array, default is array(0,0,0)
                            $this->ciqrcode->initialize($config);
                            // print_r($config);die;

                            $params['level'] = 'H'; //H=High
                            $params['size'] = 10;

                            $params['data'] = $get_data['response']['data'][0]['referral_code']; //data yang akan di jadikan QR CODE
                            $params['savename'] = $config['imagedir'] . $image_name; //simpan image QR CODE ke folder static/img/qrcode
                            // print_r($params);die;
                            $generate_qrcode = $this->ciqrcode->generate($params); // fungsi untuk generate QR CODE
                            if (!empty($generate_qrcode)) {
                                $this->set_response('code', 200);
                                $this->set_response('image_url', $this->config->item('storage_url') . 'qrcode/' . $image_name);
                            }
                        }
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

    function get_review()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $get_data = $this->mitra_model->getWhere('user_partner', array('ecommerce_token' => $this->request['header']['token']));
                    if ($get_data) {
                        $get_review = $this->mitra_model->getWhere('mitra_rating', array('partner_id' => $get_data[0]->partner_id), '', 'created_at', 'desc');
                        if (count($get_review) > 0) {
                            foreach ($get_review as $key => $value) {
                                unset($value->partner_id);
                                unset($value->id_order);
                                unset($value->tepat_waktu);
                                unset($value->kesopanan);
                                unset($value->seragam);
                                unset($value->kualitas_pijat);
                                unset($value->teknik_pijat);
                                unset($value->durasi_pengerjaan);
                                unset($value->kualitas_hasil_kerja);
                            }
                            $this->set_response('code', 200);
                            $this->set_response('resonse', array(
                                'data'      => $get_review,
                            ));
                        } else {
                            $this->set_response('code', 404);
                        }
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

    function minimum_amount($num)
    {
        if ($num < 10000) {
            $this->form_validation->set_message(
                'minimum_amount',
                'Jumlah topup minimal Rp. 10.000'
            );
            return FALSE;
        } else {
            return TRUE;
        }
    }
}

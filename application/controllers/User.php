<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->load->model('user_model');
    }

    public function index()
    {
        $data = $this->verify_request();
        print_r($data);
    }

    public function profile()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $get_data = $this->user_model->read(array('ecommerce_token' => $this->request['header']['token']));
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        $user_data = $get_data['response']['data'][0];
                        unset($user_data['ecommerce_token']);

                        $this->set_response('code', 200);
                        $this->set_response('response', array(
                            'data' => $user_data
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

    public function point()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $get_data = $this->user_model->getValue('point', 'user_partner', array('ecommerce_token' => $this->request['header']['token']));
                    $this->set_response('code', 200);
                    $this->set_response('response', array(
                        'data' => ['point' => $get_data],
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
                $params[$this->user_model->encrypted_column($this->tables['user'] . '.id', '=')] = $request_data['id'];
            }

            if (!empty($request_data['referral_code'])) {
                $params[$this->user_model->lowered_column($this->tables['user'] . '.referral_code', '=')] = $request_data['referral_code'];
            }

            if (!empty($request_data['name'])) {
                $params[$this->user_model->lowered_column($this->tables['user'] . '.name', 'like')] = '%' . strtolower($request_data['name']) . '%';
            }

            if (!empty($request_data['email'])) {
                $params[$this->user_model->lowered_column($this->tables['user'] . '.email', '=')] = strtolower($request_data['name']);
            }
            // END: Preparing request parameters

            $get_data = $this->user_model->read($params);

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
                $referral_id = $this->user_model->get_user_id(array('token' => $this->request['header']['token']));

                $request_data = $this->request['body'];

                // BEGIN: Preparing request parameters
                $params = array();
                // Default params
                $params['page']   = (!empty($request_data['page']) ? (int) $request_data['page'] : 1);
                $params['length'] = (!empty($request_data['length']) ? $request_data['length'] : 10);
                $params[$this->tables['user'] . '.referral_id'] = $referral_id;
                $params[$this->tables['user'] . '.status_active'] = '1';
                // END: Preparing request parameters

                $get_data = $this->user_model->read($params);

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
                $get_data = $this->user_model->read(array(
                    'email'         => $request_data['email'],
                    'password'      => hash('sha1', $request_data['password'] . $this->config->item('encryption_key'))
                ));

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                    if ($get_data['response']['data'][0]['user_type'] == 'user') {
                        if ($get_data['response']['data'][0]['suspend'] == '1') {
                            $this->set_response('code', 403);
                            $this->set_response('message', 'Akun anda telah di suspend.');
                        } else {
                            // $token = AUTHORIZATION::generateToken(['partner_id' => $get_data['response']['data'][0]['partner_id']]);
                            $token = hash('sha1', time() . $this->config->item('encryption_key'));
                            $get_data['response']['data'][0]['ecommerce_token'] = $token;

                            $user_data = $get_data['response']['data'][0];

                            // BEGIN: Update Token
                            $this->user_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token));
                            // END: Update Token

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        }
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
                $get_data = $this->user_model->read(array(
                    'email' => $request_data['email']
                ));

                if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                    if ($get_data['response']['data'][0]['user_type'] == 'user') {
                        if ($get_data['response']['data'][0]['suspend'] == '1') {
                            $this->set_response('code', 403);
                            $this->set_response('message', 'Akun anda telah di suspend.');
                        } else {
                            // $token = AUTHORIZATION::generateToken(['partner_id' => $get_data['response']['data'][0]['partner_id']]);
                            $token = hash('sha1', time() . $this->config->item('encryption_key'));
                            $get_data['response']['data'][0]['ecommerce_token'] = $token;

                            // BEGIN: Update Token
                            $this->user_model->update($get_data['response']['data'][0]['partner_id'], array('ecommerce_token' => $token));
                            // END: Update Token
                            $user_data = $get_data['response']['data'][0];

                            $this->set_response('code', 200);
                            $this->set_response('response', array(
                                'data' => $user_data
                            ));
                        }
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
                    'reference_code' => $request_data['reference_code'],
                    // 'token'      => hash('sha1', time() . $this->config->item('encryption_key')),
                    // 'token'      => AUTHORIZATION::generateToken('simaungproject'),
                    // 'user_type'     => $user_type,
                    // 'auth_type'     => $auth_type,
                    // 'auth_id'       => $auth_id,
                    // 'status_active' => (($user_type == 'agent') ? 0 : 1),
                );

                $set_data = $this->user_model->create($data);

                if (isset($set_data['code']) && ($set_data['code'] == 200)) {
                    $user_data = $set_data['response']['data'];

                    if ($request_data['reference_code'] != '') {
                        $data = [
                            'code' => $request_data['reference_code'],
                            'type' => 'register',
                        ];

                        $this->load->library('point');
                        $this->point->add_point($data);
                    }

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
                    $rules[] = array('name', 'trim|min_length[2]|max_length[100]');
                    $rules[] = array('email', 'trim|valid_email|max_length[100]');
                    $rules[] = array('phone', 'trim|numeric|max_length[15]');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {

                        $user_data = $this->user_model->getWhere('user_partner', array('ecommerce_token' => $this->request['header']['token']));

                        if (!empty($request_data['phone'])) {
                            $request_data['phone']   = preg_replace('/^(\+62|62|0)?/', "0", $request_data['phone']);
                            $cek_exist_phone = $this->user_model->getWhere('user_partner', array('partner_id !=' => $user_data[0]->partner_id, 'mobile_number' => $request_data['phone']));
                            $data['mobile_number'] = $request_data['phone'];

                            if (!empty($cek_exist_phone)) {
                                // remove ecommerce_token
                                $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                                $data = array(
                                    'ecommerce_token' => '',
                                );
                                $set_data = $this->user_model->update($user_id, $data);

                                $this->set_response('code', 400);
                                $this->set_response('message', $this->language['message_phone_already_taken']);
                                $this->print_output();
                            }
                            if ($user_data[0]->mobile_number <> $request_data['phone']) {
                                $data['phone_verified'] = '0';
                            }
                        }

                        if (!empty($request_data['name'])) {
                            $data['full_name'] = $request_data['name'];
                        }

                        if (!empty($request_data['email'])) {
                            $cek_exist_email = $this->user_model->getWhere('user_partner', array('partner_id !=' => $user_data[0]->partner_id, 'email' => $request_data['email']));
                            if (!empty($cek_exist_email)) {
                                $this->set_response('code', 400);
                                $this->set_response('message', $this->language['message_email_already_taken']);
                                $this->print_output();
                            }
                            $data['email'] = $request_data['email'];
                        }

                        $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));

                        $set_data = $this->user_model->update($user_id, $data);

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

                        $cek_password = $this->user_model->get_user_password(array('password' => hash('sha1', $request_data['old_password'] . $this->config->item('encryption_key'))));

                        if ($cek_password) {
                            $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                            $set_data = $this->user_model->update($user_id, $data);

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
                    $get_user = $this->user_model->get_user(array('ecommerce_token' => $this->request['header']['token']));

                    // Photo
                    if (!empty($_FILES['photo']['tmp_name'])) {
                        $data['img'] = $this->upload_photo_file('photo');

                        if ($data['img']) {
                            $temp_path = $this->config->item('storage_path') . 'user/';
                            unlink($temp_path . $get_user[0]['img']);
                        }

                        // Move photo file
                        $this->reconcile_photo_file($data['img']);

                        $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $set_data = $this->user_model->update($user_id, $data);

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

                        $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $set_data = $this->user_model->update($user_id, $data);

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
                    $get_user = $this->user_model->get_user(array('reset_token' => $request_data['reset_token']));

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
                $get_user = $this->user_model->get_user(array('reset_token' => $reset_token));

                if ($get_user) {
                    $request_data = $this->request['body'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    // BEGIN: Preparing rules
                    $rules[] = array('password', 'trim|required');
                    $rules[] = array('password_confirm', 'trim|matches[password]');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $set_data = $this->user_model->update($get_user[0]['partner_id'], array(
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
                $get_user = $this->user_model->get_user(array('email' => $request_data['email']));
                $get_email_sender = $this->common_model->get_global_setting(array('group' => 'email', 'name' => 'post-master'));

                if ($get_user && !empty($get_user[0]['email']) && !empty($get_email_sender['value'])) {
                    $reset_token = hash('sha1', time() . $this->config->item('encryption_key'));

                    $email_data['url'] = base_url() . 'user/reset_password?reset_token=' . $reset_token;

                    $email_body = $this->load->view('email/reset_password', $email_data, TRUE);

                    $set_data = $this->user_model->update($get_user[0]['partner_id'], array('reset_token' => $reset_token));

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
                $get_user = $this->user_model->get_user(array(
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
                        $get_user_invoice = $this->user_model->get_user_invoice(array(
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
                                    $this->user_model->unset_user_invoice(array(
                                        'id' => $get_user_invoice[0]['id']
                                    ));

                                    // Create new invoice
                                    $set_user_invoice = $this->user_model->set_user_invoice(
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
                            $set_user_invoice = $this->user_model->set_user_invoice(
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
                $get_data = $this->user_model->read(array('referral_code' => $this->request['body']['referral_code'], 'user_type' => "mitra"));

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
                $partner_id = $this->user_model->get_partner_id(array('token' => $this->request['header']['token']));

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

                        $set_data = $this->user_model->set_device($data);

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

    function add_mitra_favorit()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $request_data = $this->request['body'];
                    $token = $this->request['header']['token'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    $rules[] = array('mitra_id', 'trim|required');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $user_id = $this->user_model->getValue('partner_id', 'user_partner', array('ecommerce_token' => $token));
                        $mitra_id = $this->user_model->getValueEncode('partner_id', 'user_partner', $request_data['mitra_id']);
                        $data = array(
                            'user_id'   => $user_id,
                            'mitra_id'   => $mitra_id,
                        );

                        $set_mitra = $this->user_model->save_mitra_favorit($data);

                        $this->set_response('code', $set_mitra['code']);
                        $this->set_response('message', $set_mitra['message']);
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

    function get_mitra_favorit()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'GET') {
                    $token = $this->request['header']['token'];

                    $user_id = $this->user_model->getValue('partner_id', 'user_partner', array('ecommerce_token' => $token));

                    $get_mitra = $this->user_model->list_mitra_favorit(array('user_id' => $user_id));
                    if ($get_mitra) {
                        foreach ($get_mitra as $key => $value) {
                            unset($value->password);
                            unset($value->ecommerce_token);
                            unset($value->image_idcard);
                            unset($value->image_selfie);

                            if (!empty($value->img) && file_exists($this->config->item('storage_path') . 'user/' . $value->img)) {
                                $get_mitra[$key]->img = $this->config->item('storage_url') . 'user/' . $value->img;
                            } else {
                                $get_mitra[$key]->img = $this->config->item('storage_url') . 'user/no-image.png';
                            }

                            $get_mitra[$key]->rate = number_format((float)$get_mitra[$key]->rate, 2, '.', '');
                        }
                        $this->set_response('code', 200);
                        $this->set_response('data', $get_mitra);
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

    function unfavorited()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $request_data = $this->request['body'];
                    $token = $this->request['header']['token'];

                    $this->load->library(array('form_validation'));
                    $this->form_validation->set_data($request_data);

                    $rules[] = array('mitra_id', 'trim|required');

                    set_rules($rules);

                    if (($this->form_validation->run() == TRUE)) {
                        $user_id = $this->user_model->getValue('partner_id', 'user_partner', array('ecommerce_token' => $token));
                        $mitra_id = $this->user_model->getValueEncode('partner_id', 'user_partner', $request_data['mitra_id']);
                        $where = array(
                            'user_id'   => $user_id,
                            'mitra_id'   => $mitra_id,
                        );

                        $set_mitra = $this->user_model->delete_mitra_favorit($where);

                        $this->set_response('code', $set_mitra['code']);
                        $this->set_response('message', $set_mitra['message']);
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
    public function update_vaksin()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'POST') {
                    $get_user = $this->user_model->get_user(array('ecommerce_token' => $this->request['header']['token']));

                    // Photo
                    if (!empty($_FILES['photo']['tmp_name'])) {
                        $data['vaksin_verified'] = 'pending';
                        $data['vaksin'] = $this->upload_vaksin_file('photo');

                        if ($get_user[0]['vaksin'] != null) {
                            $temp_path = $this->config->item('storage_path') . 'vaksin/';
                            unlink($temp_path . $get_user[0]['vaksin']);
                        }

                        // Move photo file
                        $this->reconcile_vaksin_file($data['vaksin']);

                        $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $set_data = $this->user_model->update($user_id, $data);

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

    private function upload_vaksin_file($type)
    {
        // $temp_path = 'assets/img/user/';
        $temp_path = $this->config->item('storage_path') . 'vaksin/';

        $config['upload_path']      = $temp_path;
        $config['allowed_types']  = 'jpg|jpeg|png';
        $config['max_size']       = 4096; // 4 MB
        $config['file_name'] = md5(time() . uniqid());

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if ($this->upload->do_upload($type)) {
            if ($type == 'photo') {
                $this->resizeImageVaksin($this->upload->data('file_name'), 800);

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
                $this->resizeImageVaksin($this->upload->data('file_name'), 800);
                $name = $this->upload->data('file_name');
                return $name;
            }
        } else {
            $this->set_response('code', 300);
            $this->set_response('message', $this->upload->display_errors('', ''));
            $this->print_output();
        }
    }

    private function reconcile_vaksin_file($photo)
    {
        // Move images
        $temp_path = $this->config->item('storage_path') . 'temp/';
        $upload_path = $this->config->item('storage_path') . 'vaksin/';

        if (!is_dir(rtrim($upload_path, '/'))) mkdir(rtrim($upload_path), 0775, TRUE);

        if (!empty($photo) && file_exists($temp_path . $photo)) {
            rename($temp_path . $photo, $upload_path . $photo);
        }
    }

    private function resizeImageVaksin($filename, $ukuran)
    {
        // $source_path = 'assets/img/user/' . $filename;
        $source_path = $this->config->item('storage_path') . 'vaksin/' . $filename;
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

    public function delete_account()
    {
        if (!empty($this->request['header']['token'])) {
            if ($this->validate_token($this->request['header']['token'])) {
                if ($this->method == 'DELETE') {
                    $get_data = $this->user_model->read(array('ecommerce_token' => $this->request['header']['token']));
                    if (isset($get_data['code']) && ($get_data['code'] == 200)) {
                        $user_id = $this->user_model->get_user_id(array('ecommerce_token' => $this->request['header']['token']));
                        $data = array(
                            'ecommerce_token' => '',
                            'status_active' => '0',
                            'verified' => '0',
                            'phone_verified' => '0',
                        );
                        $this->user_model->update($user_id, $data);

                        $this->set_response('code', 200);
                        $this->set_response('message', 'Account berhasil di hapus');
                        $this->print_output();
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
}

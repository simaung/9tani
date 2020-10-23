<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Callback extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Connecting to database
        $this->conn['main'] = $this->load->database('default', TRUE);
        $this->conn['log']  = $this->load->database('log', TRUE);

        $this->data['api_bigflip'] = $this->config->item('api_bigflip');
        $this->data['api_bigflip'] = $this->data['api_bigflip'][$this->config->item('payment_env')];

        $firebase = $this->firebase->init();
        $this->db = $firebase->getDatabase();
    }

    function flip()
    {
        $data = isset($_POST['data']) ? $_POST['data'] : null;
        $token = isset($_POST['token']) ? $_POST['token'] : null;

        if ($token === $this->data['api_bigflip']['token']) {
            $data_payment = array(
                'action'    => 'payment',
                'post'      => $data,
                'result'    => ''
            );
            $this->conn['log']->insert('log_payment', $data_payment);

            $decoded_data = json_decode($data);

            if ($decoded_data->status == "DONE") {
                $update_data = $this->conn['main']
                    ->set(array('payment_status' => 'paid', 'payment_data' => $data))
                    ->where('payment_status', 'pending')
                    ->where('id_vendor', $decoded_data->id)
                    ->update('withdraw_request');

                if ($update_data) {
                    $this->conn['main']->query("update deposit_history set payment_status = 'ok' where payment_referensi = (select invoice_code from withdraw_request where id_vendor = " . $decoded_data->id . ")");

                    $sql = "select mobile_number, full_name, bank_name,	bank_account_holder FROM user_partner a
	                        left join withdraw_request b on b.user_id = a.partner_id
	                        left join user_bank c on c.id = b.bank_id
                            WHERE id_vendor = " . $decoded_data->id;
                    $get_data_user = $this->conn['main']->query($sql)->row();

                    $this->send->index('withdrawsuccess', $get_data_user->mobile_number, $get_data_user->full_name, $decoded_data->amount, $get_data_user->bank_name, $get_data_user->bank_account_holder);
                    $this->send->send_file('callback_flip', $get_data_user->mobile_number, $get_data_user->full_name, $decoded_data->receipt);
                }
            } elseif ($decoded_data->status == "CANCELLED") {
                $update_data = $this->conn['main']
                    ->set(array('payment_status' => 'cancel', 'payment_data' => $data))
                    ->where('payment_status', 'pending')
                    ->where('id_vendor', $decoded_data->id)
                    ->update('withdraw_request');

                $this->conn['main']->query("update deposit_history set payment_status = 'cancel' where payment_referensi = (select invoice_code from withdraw_request where id_vendor = " . $decoded_data->id . ")");

                $data_withdraw = $this->conn['main']
                    ->select('*')
                    ->where('id_vendor', $decoded_data->id)
                    ->get('withdraw_request')->row();

                // insert to deposit history
                $this->load->library('deposit');
                $this->deposit->back_deposit_withdraw($data_withdraw);
            }
        }
    }

    function flip_inquiry()
    {
        $data = isset($_POST['data']) ? $_POST['data'] : null;
        $token = isset($_POST['token']) ? $_POST['token'] : null;

        if ($token === $this->data['api_bigflip']['token']) {
            $data_payment = array(
                'action'    => 'inquiry',
                'post'      => $data,
                'result'    => ''
            );
            $this->conn['log']->insert('log_payment', $data_payment);

            $decoded_data = json_decode($data);

            if ($decoded_data->status == "SUCCESS") {
            } elseif ($decoded_data->status == "INVALID_ACCOUNT_NUMBER") {
                $get_data_user = $this->conn['main']
                    ->select('mobile_number, full_name, bank_name, bank_account_no')
                    ->where('bank_code', $decoded_data->bank_code)
                    ->where('bank_account_no', $decoded_data->account_number)
                    ->join('user_partner a', 'a.partner_id = b.partner_id', 'left')
                    ->get('user_bank b')->row();

                $this->conn['main']
                    ->where("bank_code", $decoded_data->bank_code)
                    ->where('bank_account_no', $decoded_data->account_number)
                    ->delete('user_bank');

                $this->send->index('invalid_account', $get_data_user->mobile_number, $get_data_user->full_name, $get_data_user->bank_name, $get_data_user->bank_account_no, $get_data_user->bank_account_holder);
            }
        }
    }
}

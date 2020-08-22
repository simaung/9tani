<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Deposit_model extends Base_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function create($params = array())
    {
        if (!empty($params['token'])) {
            // SET reconciliation parameters
            $token = $params['token'];

            // Set request params
            $request = $params;
            unset($request['token']);

            // SET query data preparation
            $field_to_set = $this->build_field($this->conn['main'], 'deposit_topup', $request);

            // SET reconciliation field
            $field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')";

            $field_to_set = ltrim($field_to_set, ", ");

            // QUERY process
            $sql = "INSERT INTO `deposit_topup` SET " . $field_to_set;
            $query = $this->conn['main']->simple_query($sql);

            // CONDITION for QUERY result
            if ($query) {
                $insert_id = $this->conn['main']->insert_id();
                $id = $insert_id;

                // INVOICE
                $this->conn['main']->query("UPDATE `deposit_topup` SET `invoice_code` = 'TD" . date('Ymd') . str_pad($id, 3, '0', STR_PAD_LEFT) . "' WHERE `id` = '{$id}'");

                // GET data result for RESPONSE
                $read_data = $this->read(array('id' => $id));

                // SET RESPONSE data
                $this->set_response('code', 200);
                $this->set_response('response', array(
                    'data' => $read_data['response']['data'][0]
                ));
            } else {
                $this->set_response('', $this->conn['main']->error());
            }
        } else {
            $this->set_response('code', 400);
        }

        return $this->get_response();
    }

    public function read($params = array())
    {
        if (!empty($params['token'])) {
            $token = $this->sanitize($this->conn['main'], $params['token']);
            unset($params['token']);
        }

        $cond_query     = $this->build_condition($this->conn['main'], $params, 'deposit_topup');
        $order_query    = $this->build_order($this->conn['main'], $params, 'deposit_topup');
        $limit_query    = $this->build_limit($this->conn['main'], $params);

        if (!empty($token)) {
            $cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`deposit_topup`.`user_id` = (SELECT `" . $this->tables['user'] . "`.`partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%{$token}'))";
        }

        // SET the QUERY
        $this->conn['main']->query("SET group_concat_max_len = 1024*1024");
        $sql = "SELECT
					`deposit_topup`.*,
					SHA1(CONCAT(`deposit_topup`.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
					SHA1(CONCAT(`deposit_topup`.`user_id`, '" . $this->config->item('encryption_key') . "')) AS `user_id`
					FROM `deposit_topup`" . $cond_query . $order_query . $limit_query;

        // QUERY process
        $query = $this->conn['main']->query($sql)->result_array();

        // CONDITION for QUERY result
        if ($query) {
            // SET reconciliation data result for RESPONSE
            $data = array();
            foreach ($query as $row) {
                // Assign row to data
                $data[] = $row;
            }

            // GET summary data for RESPONSE
            $total_filter = $this->count_rows($this->conn['main'], $this->tables['order'], $params);

            $summary['total_show']      = count($data);
            $summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
            $summary['total_data']      = (float) $this->conn['main']->count_all($this->tables['order']);

            // SET RESPONSE data
            $this->set_response('code', 200);
            $this->set_response('response', array(
                'data'         => $data,
                'summary' => $summary
            ));
        } else {
            // SET RESPONSE data
            $this->set_response('code', 404);
        }

        return $this->get_response();
    }

    public function get_request_expired()
    {
        $query = $this->conn['main']
            ->select('*')
            ->where('substr(created_at, 1, 10) <', date('Y-m-d'))
            ->where('payment_status', 'pending')
            ->get('deposit_topup')->result_array();

        return $query;
    }

    public function set_topup_expired($id)
    {
        $update_order = $this->conn['main']->set(array('payment_status' => 'expired'))
            ->where('id', $id)
            ->update('deposit_topup');
    }

    public function set_payment_transfer_expired($invoice_code)
    {
        $update_order = $this->conn['main']->set(array('status' => 'expired'))
            ->where('transaction_invoice', $invoice_code)
            ->update('payment_transfer');
    }
}

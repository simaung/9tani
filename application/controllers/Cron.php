<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cron extends Base_Controller
{

  public function __construct()
  {
    parent::__construct();

    // Load model
    $this->load->model('order_model');

    $firebase = $this->firebase->init();
    $this->db = $firebase->getDatabase();
  }

  public function expired()
  {
    $get_data = $this->order_model->get_order_expired();
    foreach ($get_data as $row) {
      $this->order_model->set_order_expired($row['id']);
    }
  }

  public function cek_mutasi()
  {
    $this->conn['main'] = $this->load->database('default', TRUE);

    $this->load->model('payment_model');
    $link_url = 'https://bank.appsku.net/api/getMutasi/NGNKbFhSRnZNNmF1L2tHdmhJckNVUT09/';

    $get_payment_transfer = $this->payment_model->get_payment_transfer(array('status' => 'pending'));
    if ($get_payment_transfer) {
      foreach ($get_payment_transfer as $row) {
        $amount = $row['amount'];
        $date = $row['date'];
        $transaction_invoice = $row['transaction_invoice'];

        $get_mutasi = $this->curl->get($link_url . $amount . '/' . $date, '', '', 'true');
        if ($get_mutasi->rest_no == 0) {
          $this->order_model->set_order_paid($transaction_invoice, json_encode($get_mutasi->mutasi_data));

          $get_transaction = $this->conn['main']
            ->select('a.*, b.id as transaction_id, b.merchant_id, c.email, sum(d.price) as total_price, b.shipping_cost, e.description')
            ->join('mall_transaction b', 'a.id = b.order_id', 'left')
            ->join('user_partner c', 'a.user_id = c.partner_id', 'left')
            ->join('mall_transaction_item d', 'b.id = d.transaction_id', 'left')
            ->join('payment_channel e', 'a.payment_channel_id = e.id', 'left')
            ->where('invoice_code', $transaction_invoice)
            ->get('mall_order a')->row();

          $product = $this->conn['main']
            ->select('a.*')
            ->select("SHA1(CONCAT(variant_id, '" . $this->config->item('encryption_key') . "')) as variant_id")
            ->where('transaction_id', $get_transaction->transaction_id)
            ->get('mall_transaction_item a')->result_array();

          foreach ($product as $product_row) {
            $item = json_decode($product_row['product_data'], true);

            if ($product_row['variant_id'] != '') {
              if (!empty($item['product_variant'])) {
                foreach ($item['product_variant'] as $variant) {
                  if ($product_row['variant_id'] == $variant['id']) {
                    $trans_item[] = array(
                      'name'      => $item['name'],
                      'unit'      => $variant['name'],
                      'price'     => $variant['harga'],
                      'qty'       => $product_row['quantity'],
                    );
                  }
                }
              }
            } else {
              $trans_item[] = array(
                'name'      => $item['name'],
                'unit'      => $item['price_unit'],
                'price'     => $item['price_selling'],
                'qty'       => $product_row['quantity'],
              );
            }

            // update realtime database
            if ($get_transaction->service_type != 'ecommerce') {
              $this->insert_realtime_database($get_transaction->order_id, 'Pesanan sudah dijadwalkan');
            }

            $update_status_order = $this->conn['main']
              ->set(array('status_order' => 'confirm'))
              ->where('order_id', $get_transaction->id)
              ->where('mitra_id', $get_transaction->merchant_id)
              ->update('order_to_mitra');

            // update merchant_id di mall_order
            $update_merchant_id = $this->conn['main']
              ->set(array('merchant_id' => $get_transaction->merchant_id, 'transaction_status_id' => 8))
              ->where('order_id', $get_transaction->id)
              ->update('mall_transaction');

            //send push notification order to mitra
            $this->curl->push($get_transaction->merchant_id, 'Orderan ' . $transaction_invoice . ' telah dibayar', 'Orderanmu siap di lanjutkan!', 'order_pending');


            $user_email = $get_transaction->email;
            $order = $get_transaction;
            $order_item = $trans_item;

            $this->send_email_payment_success($order, $order_item, $user_email);
            $this->send_email_payment_success_image($user_email);
          }
        }
      }
    }
  }

  public function send_email_payment_success($order, $order_item, $user_email)
  {
    $this->load->library('email');

    $data['order'] = $order;
    $data['order_item'] = $order_item;

    $email_body = $this->load->view('email/payment_success', $data, TRUE);

    $get_email_sender = $this->common_model->get_global_setting(array(
      'group' => 'email',
      'name' => 'post-master'
    ));

    $this->email->from($get_email_sender['value'], '9tani');
    $this->email->to($user_email);
    $this->email->cc('sembilantaniindonesia@gmail.com');

    $this->email->subject('Pembayaran nomor invoice ' . $data['order']->invoice_code . ' berhasil');
    $this->email->message($email_body);

    $this->email->send();
  }

  public function send_email_payment_success_image($user_email)
  {
    $this->load->library('email');

    $email_body = $this->load->view('email/payment_success_image', '', TRUE);

    $get_email_sender = $this->common_model->get_global_setting(array(
      'group' => 'email',
      'name' => 'post-master'
    ));

    $this->email->from($get_email_sender['value'], '9tani');
    $this->email->to($user_email);
    $this->email->bcc('sembilantaniindonesia@gmail.com');

    $this->email->subject('Ucapan terimakasih dari SEMBILAN TANI');
    $this->email->message($email_body);

    $this->email->send();
  }

  function insert_realtime_database($id_order, $status)
  {
    $data = array(
      $id_order => $status
    );
    if (empty($data) || !isset($data)) {
      return FALSE;
    }

    foreach ($data as $key => $value) {
      $this->db->getReference()->getChild('order')->getChild($key)->set($value);
    }
    return TRUE;
  }
}

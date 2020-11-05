<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Rating_model extends Base_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function create($params = array())
    {
        $get_order = $this->conn['main']
            ->select('a.order_id, merchant_id as mitra_id')
            ->where("SHA1(CONCAT(a.order_id, '" . $this->config->item('encryption_key') . "')) = ", $params['id_order'])
            ->get('mall_transaction a')->row();

        $criteria = array_map("intval", explode(",", $params['criteria']));

        $array_criteria = [];
        foreach ($criteria as $value) {
            switch ($value) {
                case '1':
                    $array_criteria['tepat_waktu'] = '1';
                    break;
                case '2':
                    $array_criteria['kesopanan'] = '1';
                    break;
                case '3':
                    $array_criteria['seragam'] = '1';
                    break;
                case '4':
                    $array_criteria['kualitas_pijat'] = '1';
                    break;
                case '5':
                    $array_criteria['teknik_pijat'] = '1';
                    break;
                case '6':
                    $array_criteria['durasi_pengerjaan'] = '1';
                    break;
                case '7':
                    $array_criteria['kualitas_hasil_kerja'] = '1';
                    break;
            }
        }

        $data = array(
            'partner_id'    => $get_order->mitra_id,
            'id_order'      => $get_order->order_id,
            'rate'          => $params['rate'],
            'comment'       => $params['comment']
        );

        $data_rating = (array_merge($data, $array_criteria));

        $query = $this->conn['main']->insert('mitra_rating', $data_rating);
        if ($query) {
            $this->conn['main']
                ->set('status_review', '1')
                ->where('order_id', $get_order->order_id)
                ->update('mall_transaction');

            if ($params['rate'] == 1 || $params['rate'] == 2) {
                $this->conn['main']
                    ->set('suspend', '1')
                    ->set('ecommerce_token', null)
                    ->where('partner_id', $get_order->mitra_id)
                    ->update('user_partner');
            }

            $this->set_response('code', 200);
            $this->set_response('message', 'Ulasan berhasil di kirim');
        } else {
            $this->set_response('', $this->conn['main']->error());
        }
        return $this->get_response();
    }
}

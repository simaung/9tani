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
        // $get_user = $this->conn['main']->query("select partner_id from user_partner where ecommerce_token = '" . $params['token'] . "'")->row();
        $get_order = $this->conn['main']
            ->select('a.id, merchant_id as mitra_id')
            ->where("SHA1(CONCAT(a.id, '" . $this->config->item('encryption_key') . "')) = ", $params['id_order'])
            ->join('mall_transaction b', 'a.id = b.order_id', 'left')
            ->get('mall_order a')->row();

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
            'id_order'      => $get_order->id,
            'rate'          => $params['rate'],
            'comment'       => $params['comment']
        );

        $data_rating = (array_merge($data, $array_criteria));

        $query = $this->conn['main']->insert('mitra_rating', $data_rating);
        if ($query) {
            $this->set_response('code', 200);
            $this->set_response('message', 'Ulasan berhasil di kirim');
        } else {
            $this->set_response('', $this->conn['main']->error());
        }
        return $this->get_response();
    }
}

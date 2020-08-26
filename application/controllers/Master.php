<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Master extends Base_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model(array('base_model'));
    }

    public function ket_batal()
    {
        $get_ket = $this->base_model->getWhere('mstr_keterangan_batal', array());
        if ($get_ket) {
            foreach ($get_ket as $row) {
                unset($row->id);
                $this->set_response('code', 200);
                $this->set_response('response', $get_ket);
            }
        } else {
            $this->set_response('code', 404);
        }
        $this->print_output();
    }

}

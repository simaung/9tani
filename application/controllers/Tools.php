<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Tools extends Base_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model(array('base_model'));
    }

    public function get_versi()
    {
        $get_versi = $this->base_model->getWhere('versi_app', array());
        if ($get_versi) {
            foreach ($get_versi as $row) {
                unset($row->id);
                $this->set_response('code', 200);
                $this->set_response('response', $get_versi);
            }
        } else {
            $this->set_response('code', 404);
        }
        $this->print_output();
    }
}

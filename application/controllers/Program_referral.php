<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Program_referral extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load model
        $this->load->model(array('base_model'));
    }

    // Get list
    public function index()
    {
        if ($this->method == 'GET') {
            // GET DATA
            $get_data = $this->base_model->getWhere('mstr_referral', array('status_active' => '1', 'end_date >= ' => date('Y-m-d')));

            if ($get_data) {
                foreach ($get_data as $row) {
                    unset($row->id);
                    $row->file = $this->config->item('storage_url') . 'slideshow/' . $row->file;
                    $this->set_response('code', 200);
                    $this->set_response('response', $get_data);
                }
            } else {
                $this->set_response('code', 404);
            }
        } else {
            $this->set_response('code', 405);
        }

        $this->print_output();
    }
}

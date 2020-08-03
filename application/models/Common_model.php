<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Common_model extends Base_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function get_connection($conn = 'main')
    {
        return $this->conn[$conn];
    }

    public function get_global_setting($params = array())
    {
        $query = $this->conn['main']
            ->select('*')
            ->from($this->tables['global_setting'])
            ->where($params)
            ->get()->result_array();

        if ($query) {
            return $query[0];
        } else {
            return false;
        }
    }
}

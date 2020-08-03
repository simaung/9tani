<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Log_model extends Base_Model {

  public function __construct()
  {
    parent::__construct();
  }

  public function create($params = array())
  {
    // SET reconciliation parameters
    $request = $params;

    // SET query data preparation
    $field_to_set = $this->build_field($this->conn['log'], $this->tables['log'], $request);

    $field_to_set = ltrim($field_to_set, ", ");

    // QUERY process
    $sql = "INSERT INTO `" . $this->tables['log'] . "` SET " . $field_to_set;
    $query = $this->conn['log']->simple_query($sql);

    // CONDITION for QUERY result
    if ($query)
    {
      $insert_id = $this->conn['log']->insert_id();
      $id = $insert_id;

      // GET data result for RESPONSE
      $read_data = $this->read(array('id' => $id));

      // SET RESPONSE data
      $this->set_response('code', 200);
      $this->set_response('response', array(
        'data' => $read_data['response']['data'][0]
      ));
    }
    else
    {
      // SET RESPONSE data
      $this->set_response('', $this->conn['log']->error());
    }

    return $this->get_response();
  }

  public function read($params = array())
	{
		$cond_query     = $this->build_condition($this->conn['log'], $params, $this->tables['log']);
    $order_query    = $this->build_order($this->conn['log'], $params, $this->tables['log']);
    $limit_query    = $this->build_limit($this->conn['log'], $params);

		// SET the QUERY
		$sql = "SELECT
					`".$this->tables['log']."`.*
          FROM `".$this->tables['log']."`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['log']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query)
		{
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row)
			{
        $row['request']	  = json_decode(preg_replace("!\r?\n!", "", $row['request']), 1);
        $row['response']  = json_decode(preg_replace("!\r?\n!", "", $row['response']), 1);

				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['log'], $this->tables['log'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['log']->count_all($this->tables['log']);

			// SET RESPONSE data
			$this->set_response('code', 200);
			$this->set_response('response', array(
					'data' 		=> $data,
          'summary' => $summary
				));
		}
		else
		{
			// SET RESPONSE data
			$this->set_response('code', 404);
		}

		return $this->get_response();
	}

	public function total($params = array())
  {
		return $this->count_rows($this->conn['log'], $this->tables['log'], $params);
  }
}

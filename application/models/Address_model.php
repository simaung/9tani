<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Address_model extends Base_Model {

  public function __construct()
  {
    parent::__construct();
  }

	public function create($params = array())
	{
		if ( ! empty($params['token']))
		{
			// SET reconciliation parameters
			$token = $params['token'];

			// Set request params
			$request = $params;
			unset($request['token']);

			if (isset($request['id'])) unset($request['id']);

			$request['created_at'] = $request['modified_at'] = date('Y-m-d H:i:s');

			// SET query data preparation
			$field_to_set = $this->build_field($this->conn['main'], $this->tables['address'], $request);

			// SET reconciliation field
      $field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `".$this->tables['user']."` WHERE `".$this->tables['user']."`.`ecommerce_token` LIKE '%" . $token . "')";

			$field_to_set = ltrim($field_to_set, ", ");

			// QUERY process
			$sql = "INSERT INTO `" . $this->tables['address'] . "` SET " . $field_to_set;
			$query = $this->conn['main']->simple_query($sql);

			// CONDITION for QUERY result
			if ($query)
			{
				$insert_id = $this->conn['main']->insert_id();
				$id = $insert_id;

        // STATUS PRIMARY
        if ( ! $this->total("WHERE `user_id` = (SELECT `partner_id` FROM `".$this->tables['user']."` WHERE `".$this->tables['user']."`.`ecommerce_token` LIKE '%" . $token . "') AND `status_primary` = 1"))
        {
          $this->conn['main']->query("UPDATE `" . $this->tables['address'] . "` SET `status_primary` = '1' WHERE `id` = '{$id}'");
        }
        else
        {
          if ( ! empty($request['status_primary']))
          {
            $this->conn['main']->query("UPDATE `" . $this->tables['address'] . "` SET `status_primary` = '0' WHERE `user_id` = (SELECT `partner_id` FROM `".$this->tables['user']."` WHERE `".$this->tables['user']."`.`ecommerce_token` LIKE '%" . $token . "') AND `status_primary` = 1");

            $this->conn['main']->query("UPDATE `" . $this->tables['address'] . "` SET `status_primary` = '1' WHERE `id` = '{$id}'");
          }
        }

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
				$this->set_response('', $this->conn['main']->error());
			}
		}
		else
		{
			$this->set_response('code', 400);
		}

		return $this->get_response();
	}

  public function read($params = array())
	{
    if ( ! empty($params['token']))
    {
      $token = $this->sanitize($this->conn['main'], $params['token']);
      unset($params['token']);
		}

		$cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['address']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['address']);
		$limit_query    = $this->build_limit($this->conn['main'], $params);

		if ( ! empty($token))
		{
			$cond_query .= ( ! empty($cond_query) ? " AND " : " WHERE ") . "(`".$this->tables['address']."`.`user_id` = (SELECT `".$this->tables['user']."`.`partner_id` FROM `".$this->tables['user']."` WHERE `".$this->tables['user']."`.`ecommerce_token` LIKE '%{$token}'))";
		}

		// SET the QUERY
		$this->conn['main']->query("SET group_concat_max_len = 1024*1024");
		$sql = "SELECT
					`".$this->tables['address']."`.*,
					SHA1(CONCAT(`".$this->tables['address']."`.`id`, '".$this->config->item('encryption_key')."')) AS `id`,
					SHA1(CONCAT(`".$this->tables['address']."`.`user_id`, '".$this->config->item('encryption_key')."')) AS `user_id`
					FROM `".$this->tables['address']."`" . $cond_query . $order_query . $limit_query;

		// QUERY process
		$query = $this->conn['main']->query($sql)->result_array();

		// CONDITION for QUERY result
		if ($query)
		{
			// SET reconciliation data result for RESPONSE
			$data = array();
			foreach ($query as $row)
			{
				// Assign row to data
				$data[] = $row;
			}

			// GET summary data for RESPONSE
			$total_filter = $this->count_rows($this->conn['main'], $this->tables['address'], $params);

			$summary['total_show'] 	 = count($data);
			$summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
			$summary['total_data'] 	 = (float) $this->conn['main']->count_all($this->tables['address']);

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

	public function update($id = '', $params = array())
	{
		if ( ! empty($id) &&  ! empty($params['token']))
		{
      $read_data = $this->read(array('ENCRYPTED::id' => $id));

			if ($read_data['code'] == 200)
			{
        // SET reconciliation parameters
        $token = $params['token'];

        // Set request params
        $request = $params;
        unset($request['token']);

        if (isset($request['id'])) unset($request['id']);

        if (isset($request['status_primary']) && ($request['status_primary'] == '0'))
        {
          if ( $this->total("WHERE SHA1(CONCAT(`".$this->tables['address']."`.`id`,'" . $this->config->item('encryption_key') . "')) = '{$id}' AND `status_primary` = 1"))
          {
            unset($request['status_primary']);
          }
        }

        $request['modified_at'] = date('Y-m-d H:i:s');

        // SET query data preparation
        $field_to_set = $this->build_field($this->conn['main'], $this->tables['address'], $request);

        // SET reconciliation field
        $field_to_set = ltrim($field_to_set, ", ");

        // QUERY process
        $sql = "UPDATE `" . $this->tables['address'] . "` SET " . $field_to_set . " WHERE SHA1(CONCAT(`".$this->tables['address']."`.`id`,'" . $this->config->item('encryption_key') . "')) = '{$id}'";
				$query = $this->conn['main']->simple_query($sql);

        // CONDITION for QUERY result
        if ($query)
        {
					// GET id
					$id = $this->conn['main']->query("SELECT `id` FROM `" . $this->tables['address'] . "` WHERE SHA1(CONCAT(`".$this->tables['address']."`.`id`,'" . $this->config->item('encryption_key') . "')) = '{$id}'")->result_array();
					$id = $id[0]['id'];

          // STATUS PRIMARY
          if ( ! empty($request['status_primary']))
          {
            $this->conn['main']->query("UPDATE `" . $this->tables['address'] . "` SET `status_primary` = '0' WHERE `user_id` = (SELECT `partner_id` FROM `".$this->tables['user']."` WHERE `".$this->tables['user']."`.`ecommerce_token` LIKE '%" . $token . "') AND `status_primary` = 1");

            $this->conn['main']->query("UPDATE `" . $this->tables['address'] . "` SET `status_primary` = '1' WHERE `id` = '{$id}'");
          }

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
          $this->set_response('', $this->conn['main']->error());
        }
      }
			else
			{
				$this->set_response('code', 404);
			}
		}
		else
		{
			$this->set_response('code', 400);
		}

		return $this->get_response();
	}

	public function delete($id = '')
	{
		if ( ! empty($id))
		{
			$read_data = $this->read(array('ENCRYPTED::id' => $id));

			if ($read_data['code'] == 200)
			{
				// QUERY process
				$sql = "DELETE FROM `".$this->tables['address']."` WHERE SHA1(CONCAT(".$this->tables['address'].".`id`,'" . $this->config->item('encryption_key') . "')) = '{$id}'";
				$query = $this->conn['main']->simple_query($sql);

				// CONDITION for QUERY result
				if ($query)
				{
					$affected[][$this->tables['address']] = $this->conn['main']->affected_rows();

					// SET RESPONSE data
					$this->set_response('code', 200);
					$this->set_response('response', array(
						'data' => array('affected' => $affected)
					));
				}
				else
				{
					$this->set_response('', $this->conn['main']->error());
				}
			}
			else
			{
				$this->set_response('code', 404);
			}
		}
		else
		{
			$this->set_response('code', 400);
		}

		return $this->get_response();
	}

	public function total($params = array())
  {
		return $this->count_rows($this->conn['main'], $this->tables['address'], $params);
  }
}

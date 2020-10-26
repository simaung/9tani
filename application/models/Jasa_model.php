<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Jasa_model extends Base_Model
{

  public function __construct()
  {
    parent::__construct();
  }

  public function read($params = array(), $action = '')
  {
    if (!empty($params['ENCRYPTED::id'])) {
      $action = 'detail';
    }

    if (!empty($params['token'])) {
      $token = $this->sanitize($this->conn['main'], $params['token']);
      unset($params['token']);
    }

    if (!empty($params['keyword'])) {
      $keyword = $this->sanitize($this->conn['main'], $params['keyword']);
      unset($params['keyword']);
    }

    if (!empty($params['city_name'])) {
      $city_name = $this->sanitize($this->conn['main'], $params['city_name']);
      unset($params['city_name']);
    }

    if (!empty($params['latitude']) && !empty($params['longitude'])) {
      $latitude = $this->sanitize($this->conn['main'], $params['latitude']);
      $longitude = $this->sanitize($this->conn['main'], $params['longitude']);

      unset($params['latitude']);
      unset($params['longitude']);
    }

    $cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['jasa']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['jasa']);
    $limit_query    = $this->build_limit($this->conn['main'], $params);

    // Rebuild conditions
    if (!empty($keyword)) {
      $cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['jasa'] . "`.`name` LIKE '%{$keyword}%' OR `" . $this->tables['jasa'] . "`.`description` LIKE '%{$keyword}%')";
    }

    if (!empty($city_name) && !in_array($action, array('viewed', 'wishlist'))) {
      $cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['merchant'] . "`.`city_name` LIKE '%{$city_name}%')";
    }

    // SET the QUERY
    $this->conn['main']->query("SET group_concat_max_len = 1024*1024");
    $sql = "SELECT
          `" . $this->tables['jasa'] . "`.*,
          SHA1(CONCAT(`" . $this->tables['jasa'] . "`.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
          (SELECT (COALESCE(COUNT(`" . $this->tables['jasa_wishlist'] . "`.`mitra_id`), 0)) FROM `" . $this->tables['jasa_wishlist'] . "` WHERE `" . $this->tables['jasa_wishlist'] . "`.`mitra_id` = `" . $this->tables['jasa'] . "`.`id`) AS `total_wishlist`";

    switch ($action) {
      case 'popular':
        if (!empty($latitude) && !empty($longitude)) {
          $sql .= ", (111.111
              * DEGREES(ACOS(COS(RADIANS(`latitude`))
              * COS(RADIANS(" . $latitude . "))
              * COS(RADIANS(`longitude` - " . $longitude . ")) + SIN(RADIANS(`latitude`))
              * SIN(RADIANS(" . $latitude . "))))) AS `distance`";

          $order_query = " HAVING `distance` < 20 ORDER BY `total_viewed` DESC, `distance` ASC";
        } else {
          $order_query = " ORDER BY `total_viewed` DESC";
        }

        $selection_query = " FROM `" . $this->tables['jasa'] . "` INNER JOIN `" . $this->tables['merchant'] . "` ON `" . $this->tables['jasa'] . "`.`merchant_id` = `" . $this->tables['merchant'] . "`.`id`";

        break;
      case 'viewed':
        if (!empty($token)) {
          $cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['product_viewed'] . "`.`user_id` = (SELECT `" . $this->tables['user'] . "`.`partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%{$token}'))";
        }

        $order_query = " ORDER BY `" . $this->tables['product_viewed'] . "`.`created_at` DESC";

        $selection_query = " FROM `" . $this->tables['jasa'] . "` INNER JOIN `" . $this->tables['product_viewed'] . "` ON `" . $this->tables['jasa'] . "`.`id` = `" . $this->tables['product_viewed'] . "`.`product_id`";

        break;

      case 'wishlist':
        if (!empty($token)) {
          $cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['product_wishlist'] . "`.`user_id` = (SELECT `" . $this->tables['user'] . "`.`partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%{$token}'))";
        }

        $order_query = " ORDER BY `" . $this->tables['product_wishlist'] . "`.`created_at` DESC";

        $selection_query = " FROM `" . $this->tables['jasa'] . "` INNER JOIN `" . $this->tables['product_wishlist'] . "` ON `" . $this->tables['jasa'] . "`.`id` = `" . $this->tables['product_wishlist'] . "`.`product_id`";

        break;

      case 'cart':
        if (!empty($token)) {
          $cond_query .= (!empty($cond_query) ? " AND " : " WHERE ") . "(`" . $this->tables['product_cart'] . "`.`user_id` = (SELECT `" . $this->tables['user'] . "`.`partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%{$token}'))";
        }

        $sql .= ", " . $this->tables['product_cart'] . ".qty as qty_cart";

        $order_query = " ORDER BY `" . $this->tables['product_cart'] . "`.`created_at` DESC";

        $selection_query = " FROM `" . $this->tables['jasa'] . "` INNER JOIN `" . $this->tables['product_cart'] . "` ON `" . $this->tables['jasa'] . "`.`id` = `" . $this->tables['product_cart'] . "`.`product_id`";

        break;


      case 'detail':
        $sql .= ", (SELECT GROUP_CONCAT(pv.`id` SEPARATOR ',') FROM `" . $this->tables['jasa_price'] . "` pv WHERE pv.`id` != 0 AND pv.`id` AND pv.`produk_jasa_id` = `" . $this->tables['jasa'] . "`.`id`) AS `variant_price`";
        $selection_query = " FROM `" . $this->tables['jasa'] . "`";
        break;
      default:
        if (!empty($latitude) && !empty($longitude)) {
          $sql .= ", (111.111
              * DEGREES(ACOS(COS(RADIANS(`latitude`))
              * COS(RADIANS(" . $latitude . "))
              * COS(RADIANS(`longitude` - " . $longitude . ")) + SIN(RADIANS(`latitude`))
              * SIN(RADIANS(" . $latitude . "))))) AS `distance`";

          $order_query = " HAVING `distance` < 20 ORDER BY `distance` ASC";
        }

        $selection_query = " FROM `" . $this->tables['jasa'] . "`";
        break;
    }

    // QUERY process
    $query = $this->conn['main']->query($sql . $selection_query . $cond_query . $order_query . $limit_query)->result_array();

    // CONDITION for QUERY result
    if ($query) {
      // SET reconciliation data result for RESPONSE
      $data = array();
      foreach ($query as $row) {
        // IMAGE
        if (!empty($row['file'])) {
          $row['file'] = $this->config->item('storage_url') . "product/" . $row['file'];
        }

        // VARIANT
        if (!empty($row['variant_price'])) {
          $row['variant_price'] = $this->conn['main']->query("SELECT
              SHA1(CONCAT(pv.`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
              pv.`layanan`,
              pv.`harga`,
              pv.`description`,
              pv.`file`
            FROM `" . $this->tables['jasa_price'] . "` pv WHERE pv.`id` != 0 AND pv.`id` IN (" . $row['variant_price'] . ")")->result_array();

          $get_diskon = $this->getValue('value', 'global_setting', array('group' => 'price', 'name' => 'diskon-order'));

          if (!empty($get_diskon)) {
            foreach ($row['variant_price'] as $key => $value) {
              $row['variant_price'][$key]['harga_hasil_diskon'] = $value['harga'] - ($value['harga'] * $get_diskon / 100);
              $row['variant_price'][$key]['besar_diskon'] = $get_diskon . '%';
            }
          } else {
            foreach ($row['variant_price'] as $key => $value) {
              $row['variant_price'][$key]['harga_hasil_diskon'] = 0;
              $row['variant_price'][$key]['besar_diskon'] = '';
            }
          }
        } else {
          $row['variant_price'] = array();
        }

        // Assign row to data
        $data[] = $row;
      }

      // Return object detail
      if ($action == 'detail') {
        $data = $data[0];

        // if (!empty($params['ENCRYPTED::id']) && !empty($token)) {
        //   $this->set_product_viewed($this->sanitize($this->conn['main'], $params['ENCRYPTED::id']), $token);
        // }
      }

      // GET summary data for RESPONSE
      $total_filter = $this->conn['main']->query($sql . $selection_query . $cond_query)->num_rows();

      $summary['total_show']    = count($data);
      $summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
      $summary['total_data']    = (float) $this->conn['main']->count_all($this->tables['jasa']);

      // SET RESPONSE data
      $this->set_response('code', 200);
      $this->set_response('response', array(
        'data'     => $data,
        'summary' => $summary
      ));
    } else {
      // SET RESPONSE data
      $this->set_response('code', 404);
    }

    return $this->get_response();
  }

  public function create_product_wishlist($params = array())
  {
    if (!empty($params['token']) && !empty($params['product_id'])) {
      // SET reconciliation parameters
      $token      = $params['token'];
      $product_id = $params['product_id'];

      if (!$this->count_rows($this->conn['main'], $this->tables['product_wishlist'], " WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "') AND `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')")) {
        // Set request params
        $request = $params;
        unset($request['token']);
        unset($request['product_id']);

        $request['created_at'] = date('Y-m-d H:i:s');

        // SET query data preparation
        $field_to_set = $this->build_field($this->conn['main'], $this->tables['product_wishlist'], $request);

        // SET reconciliation field
        $field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')";
        $field_to_set .= ", `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')";

        $field_to_set = ltrim($field_to_set, ", ");

        // QUERY process
        $sql = "INSERT INTO `" . $this->tables['product_wishlist'] . "` SET " . $field_to_set;
        $query = $this->conn['main']->simple_query($sql);

        // CONDITION for QUERY result
        if ($query) {
          $insert_id = $this->conn['main']->insert_id();
          $id = $insert_id;

          // SET RESPONSE data
          $this->set_response('code', 200);
        } else {
          $this->set_response('', $this->conn['main']->error());
        }
      } else {
        $this->set_response('code', 200);
      }
    } else {
      $this->set_response('code', 400);
    }

    return $this->get_response();
  }

  public function delete_product_wishlist($params = array())
  {
    if (!empty($params['token']) && !empty($params['product_id'])) {
      // SET reconciliation parameters
      $token      = $params['token'];
      $product_id = $params['product_id'];

      if ($this->count_rows($this->conn['main'], $this->tables['product_wishlist'], " WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "') AND `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')")) {
        // QUERY process
        $sql = "DELETE FROM `" . $this->tables['product_wishlist'] . "` WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "') AND `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')";
        $query = $this->conn['main']->simple_query($sql);

        // CONDITION for QUERY result
        if ($query) {
          // SET RESPONSE data
          $this->set_response('code', 200);
        } else {
          $this->set_response('', $this->conn['main']->error());
        }
      } else {
        $this->set_response('code', 404);
      }
    } else {
      $this->set_response('code', 400);
    }

    return $this->get_response();
  }

  public function create_product_cart($params = array())
  {
    if (!empty($params['token']) && !empty($params['product_id'])) {
      // SET reconciliation parameters
      $token      = $params['token'];
      $product_id = $params['product_id'];

      if (!$this->count_rows($this->conn['main'], $this->tables['product_cart'], " WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "') 
      AND `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')
      ")) {
        // Set request params
        $request = $params;
        unset($request['token']);
        unset($request['product_id']);

        $request['created_at'] = date('Y-m-d H:i:s');

        // SET query data preparation
        $field_to_set = $this->build_field($this->conn['main'], $this->tables['product_cart'], $request);

        // SET reconciliation field
        $field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')";
        $field_to_set .= ", `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')";

        $field_to_set = ltrim($field_to_set, ", ");

        // QUERY process
        $sql = "INSERT INTO `" . $this->tables['product_cart'] . "` SET " . $field_to_set;
        $query = $this->conn['main']->simple_query($sql);

        // CONDITION for QUERY result
        if ($query) {
          $insert_id = $this->conn['main']->insert_id();
          $id = $insert_id;

          // SET RESPONSE data
          $this->set_response('code', 200);
        } else {
          $this->set_response('', $this->conn['main']->error());
        }
      } else {
        $this->conn['main']->query("UPDATE `" . $this->tables['product_cart'] . "` SET `qty` = `qty` + 1 WHERE
              `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "') AND
              `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `ecommerce_token` LIKE '%" . $token . "')");

        $this->set_response('code', 200);
      }
    } else {
      $this->set_response('code', 400);
    }

    return $this->get_response();
  }

  public function set_product_viewed($product_id = '', $token = '')
  {
    if ($this->count_rows($this->conn['main'], $this->tables['user'], array('ecommerce_token' => 'LIKE::%' . $token))) {
      if ((!empty($product_id)) && (!empty($token))) {
        $check_query = $this->conn['main']->query("SELECT `product_id`, `user_id` FROM `" . $this->tables['product_viewed'] . "` WHERE
            `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "') AND
            `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `ecommerce_token` LIKE '%" . $token . "')")->result_array();

        if (!empty($check_query)) {
          $this->conn['main']->query("UPDATE `" . $this->tables['product_viewed'] . "` SET `created_at` = NOW() WHERE
              `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "') AND
              `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `ecommerce_token` LIKE '%" . $token . "')");
        } else {
          $count_query = $this->conn['main']->query("SELECT `user_id` FROM `" . $this->tables['product_viewed'] . "` WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `ecommerce_token` LIKE '%" . $token . "')")->num_rows();

          if (!empty($count_query) && ($count_query > 9)) {
            $this->conn['main']->query("DELETE FROM `" . $this->tables['product_viewed'] . "` WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `ecommerce_token` LIKE '%" . $token . "') ORDER BY `created_at` ASC LIMIT 1");
          }

          $this->conn['main']->query("INSERT INTO `" . $this->tables['product_viewed'] . "` SET `created_at` = NOW(),
              `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "'),
              `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `ecommerce_token` LIKE '%" . $token . "')");
        }
      }
    }
  }

  public function delete_product_cart($params = array())
  {
    if (!empty($params['token']) && !empty($params['product_id'])) {
      // SET reconciliation parameters
      $token      = $params['token'];
      $product_id = $params['product_id'];

      foreach ($product_id as $row) {
        if ($this->count_rows($this->conn['main'], $this->tables['product_cart'], " WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "') AND `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $row . "')")) {
          // QUERY process
          $sql = "DELETE FROM `" . $this->tables['product_cart'] . "` WHERE `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "') AND `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $row . "')";
          $query = $this->conn['main']->simple_query($sql);

          // CONDITION for QUERY result
          if ($query) {
            // SET RESPONSE data
            $this->set_response('code', 200);
          } else {
            $this->set_response('', $this->conn['main']->error());
          }
        } else {
          $this->set_response('code', 404);
        }
      }
    } else {
      $this->set_response('code', 400);
    }

    return $this->get_response();
  }

  public function get_review_product($params = array())
  {
    $cond_query     = $this->build_condition($this->conn['main'], $params, $this->tables['product_review']);
    $order_query    = $this->build_order($this->conn['main'], $params, $this->tables['product_review']);
    $limit_query    = $this->build_limit($this->conn['main'], $params);

    $sql = "
    SELECT 
    SHA1(CONCAT(" . $this->tables['product_review'] . ".`id`, '" . $this->config->item('encryption_key') . "')) AS `id`,
    SHA1(CONCAT(u.`partner_id`, '" . $this->config->item('encryption_key') . "')) AS `user_id`,
    u.full_name, u.img as img_user,
    " . $this->tables['product_review'] . ".comment, " . $this->tables['product_review'] . ".rate,
    (SELECT GROUP_CONCAT(ir.`id` SEPARATOR ',') FROM `" . $this->tables['product_review_image'] . "` ir WHERE ir.`id` != 0 AND ir.`id` AND ir.`review_id` = `" . $this->tables['product_review'] . "`.`id`) AS `image_review`
    ";
    $selection_query = " FROM " . $this->tables['product_review'] . " LEFT JOIN " . $this->tables['user'] . " u on " . $this->tables['product_review'] . ".user_id = u.partner_id";

    $query = $this->conn['main']->query($sql . $selection_query . $cond_query . $order_query . $limit_query)->result_array();

    if ($query) {
      $data = array();

      foreach ($query as $key => $row) {
        if (!empty($row['img_user']) && file_exists($this->config->item('storage_path') . 'user/' . $row['img_user'])) {
          $query[$key]['img_user'] = $this->config->item('storage_url') . 'user/' . $row['img_user'];
        } else {
          $query[$key]['img_user'] = $this->config->item('storage_url') . 'user/no-image.png';
        }

        if (!empty($row['image_review'])) {
          $query[$key]['image_review'] = $this->conn['main']->query("SELECT pv.`img` FROM `" . $this->tables['product_review_image'] . "` pv WHERE pv.`id` != 0 AND pv.`id` IN (" . $row['image_review'] . ")")->result_array();
        } else {
          $query[$key]['image_review'] = array();
        }

        foreach ($query[$key]['image_review'] as $key2 => $row2) {
          if (!empty($row2['img']) && file_exists($this->config->item('storage_path') . 'review/' . $row2['img'])) {
            $query[$key]['image_review'][$key2]['img'] = $this->config->item('storage_url') . 'review/' . $row2['img'];
          } else {
            $query[$key]['image_review'][$key2]['img'] = $this->config->item('storage_url') . 'review/no-image.png';
          }
        }
      }
      $data = $query;

      $total_filter = $this->conn['main']->query($sql . $selection_query . $cond_query)->num_rows();

      $summary['total_show']    = count($data);
      $summary['total_filter'] = (float) (($total_filter) ? $total_filter : 0);
      $summary['total_data']    = (float) $this->conn['main']->count_all($this->tables['product_review']);

      // SET RESPONSE data
      $this->set_response('code', 200);
      $this->set_response('response', array(
        'data'     => $data,
        'summary' => $summary
      ));
    } else {
      $this->set_response('code', 404);
    }
    return $this->get_response();
  }

  public function create_review_product($params = array())
  {
    if (!empty($params['token']) && !empty($params['product_id'])) {
      // SET reconciliation parameters
      $token      = $params['token'];
      $product_id = $params['product_id'];

      // Set request params
      $request = $params;
      unset($request['token']);
      unset($request['product_id']);

      $request['created_at'] = date('Y-m-d H:i:s');

      // SET query data preparation
      $field_to_set = $this->build_field($this->conn['main'], $this->tables['product_review'], $request);

      // SET reconciliation field
      $field_to_set .= ", `user_id` = (SELECT `partner_id` FROM `" . $this->tables['user'] . "` WHERE `" . $this->tables['user'] . "`.`ecommerce_token` LIKE '%" . $token . "')";
      $field_to_set .= ", `product_id` = (SELECT `id` FROM `" . $this->tables['jasa'] . "` WHERE SHA1(CONCAT(`id`, '" . $this->config->item('encryption_key') . "')) = '" . $product_id . "')";

      $field_to_set = ltrim($field_to_set, ", ");

      // QUERY process
      $sql = "INSERT INTO `" . $this->tables['product_review'] . "` SET " . $field_to_set;
      $query = $this->conn['main']->simple_query($sql);

      // CONDITION for QUERY result
      if ($query) {
        $insert_id = $this->conn['main']->insert_id();
        $id = $insert_id;

        $temp_path = $this->config->item('storage_path') . 'review/';

        $config['upload_path']      = $temp_path;
        $config['allowed_types']  = 'jpg|jpeg|png';
        $config['max_size']       = 4096; // 4 MB
        $config['file_name'] = md5(time() . uniqid());

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        $jumlah_img = count($_FILES['img']['name']);
        for ($i = 0; $i < $jumlah_img; $i++) {
          if (!empty($_FILES['img']['name'][$i])) {

            $_FILES['file']['name'] = $_FILES['img']['name'][$i];
            $_FILES['file']['type'] = $_FILES['img']['type'][$i];
            $_FILES['file']['tmp_name'] = $_FILES['img']['tmp_name'][$i];
            $_FILES['file']['error'] = $_FILES['img']['error'][$i];
            $_FILES['file']['size'] = $_FILES['img']['size'][$i];

            if ($this->upload->do_upload('file')) {
              $uploadData = $this->upload->data();
              $data['review_id'] = $id;
              $data['img'] = $uploadData['file_name'];

              $query = $this->conn['main']->insert($this->tables['product_review_image'], $data);
            } else {
              $this->set_response('code', 400);
              $this->set_response('message', $this->upload->display_errors('', ''));
              return $this->get_response();
            }
          }
        }
        // SET RESPONSE data
        $this->set_response('code', 200);
      } else {
        $this->set_response('', $this->conn['main']->error());
      }
    } else {
      $this->set_response('code', 400);
    }

    return $this->get_response();
  }

  public function total($params = array())
  {
    return $this->count_rows($this->conn['main'], $this->tables['jasa'], $params);
  }

  public function total_product_variant($params = array())
  {
    return $this->count_rows($this->conn['main'], $this->tables['jasa_price'], $params);
  }

  public function get_category()
  {
    $get_data = $this->conn['main']
      ->select('layanan as name')
      ->group_by('layanan')
      ->get($this->tables['jasa'])->result();

    if ($get_data) {
      $this->set_response('code', 200);
      $this->set_response('response', array(
        'data'     => $get_data
      ));
    } else {
      // SET RESPONSE data
      $this->set_response('code', 404);
    }

    return $this->get_response();
  }

  public function get_mitra_favorit($params)
  {
    $user = $this->getWhere('user_partner', array('ecommerce_token' => $params['token']));
    $jasa_id = $this->getValueEncode('id', 'product_jasa', $params['product_id']);

    $this->conn['main']->select('b.*');
    $this->conn['main']->select('(select sum(rate) / count(rate) from mitra_rating where mitra_rating.partner_id = a.mitra_id) as rate');

    $where = array(
      'a.user_id'       => $user[0]->partner_id,
      'b.status_active' => '1'
    );

    if ($params['penyedia_jasa'] == 'P') {
      $where['b.jenis_kelamin'] = 'L';
      $this->conn['main']->where_in('tipe_customer', array('L', 'T'));
    } elseif ($params['penyedia_jasa'] == 'W') {
      $where['b.jenis_kelamin'] = 'P';
      $this->conn['main']->where_in('tipe_customer', array('P', 'T'));
    }
    $this->conn['main']->where($where);
    $this->conn['main']->where("FIND_IN_SET ('$jasa_id', c.jasa_id) > 0", null);

    $this->conn['main']->join('user_partner b', 'a.mitra_id = b.partner_id', 'left');
    $this->conn['main']->join('mitra_jasa c', 'a.mitra_id = c.partner_id', 'left');
    $get_mitra_favorit = $this->conn['main']->get('mitra_favorit a')->result();

    if (count($get_mitra_favorit) > 0) {
      $this->set_response('code', 200);
      $this->set_response('response', array(
        'data'     => $get_mitra_favorit
      ));
    } else {
      $this->set_response('code', 404);
    }

    return $this->get_response();
  }

  public function get_total_cancel($where)
  {
    $this->conn['main']->select('count(a.id) as total_cancel');
    $this->conn['main']->where($where);
    $this->conn['main']->where('a.payment_code', 'cod');
    $this->conn['main']->where('b.note_cancel is not null', null);
    $this->conn['main']->where('b.note_cancel !=', 'Belum Menemukan Mitra');
    $this->conn['main']->where('substr(created_at, 1, 10) =', date('Y-m-d'));
    $this->conn['main']->join('mall_order a', 'a.id = b.order_id');
    $query = $this->conn['main']->get('mall_transaction b')->row();

    if ($query) {
      return $query->total_cancel;
    } else {
      return false;
    }
  }
}

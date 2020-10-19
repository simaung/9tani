<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Base Model
 * @author Asep Fajar Nugraha <delve_brain@hotmail.com>
 */
class Base_Model extends CI_Model
{
  protected $conn;
  protected $language;
  public $tables;
  private $response;

  public function __construct()
  {
    parent::__construct();

    $this->language = $this->lang->language;

    // Connecting to database
    $this->conn['main'] = $this->load->database('default', TRUE);
    $this->conn['log']  = $this->load->database('log', TRUE);

    $prefix = 'mall_';
    $suffix = '_mall';
    $this->tables = array(
      'log'                 => 'log' . $suffix,
      'address'             => $prefix . 'address',
      'category'            => $prefix . 'category',
      'product'             => $prefix . 'product',
      'product_image'       => $prefix . 'product_image',
      'product_review'      => $prefix . 'product_review',
      'product_review_image' => $prefix . 'product_review_image',
      'product_to_category' => $prefix . 'product_to_category',
      'product_to_showcase' => $prefix . 'product_to_showcase',
      'product_variant'     => $prefix . 'product_variant',
      'product_viewed'      => $prefix . 'product_viewed',
      'product_wishlist'    => $prefix . 'product_wishlist',
      'product_cart'        => $prefix . 'product_cart',
      'showcase'            => $prefix . 'showcase',
      'slideshow'           => $prefix . 'slideshow',
      'order'               => $prefix . 'order',
      'transaction'         => $prefix . 'transaction',
      'transaction_item'    => $prefix . 'transaction_item',
      'transaction_status'  => $prefix . 'transaction_status',
      'transaction_review'  => $prefix . 'transaction_review',
      'transaction_review_image' => $prefix . 'transaction_review_image',
      'merchant'            => 'merchant',
      'merchant_to_shipping_gateway' => 'merchant_to_shipping_gateway',
      'user'                => 'user_partner',
      'user_device'         => 'user_partner_device',
      'payment_gateway'     => 'payment_gateway',
      'payment_channel'     => 'payment_channel',
      'payment_transfer'    => 'payment_transfer',
      'shipping_gateway'    => 'shipping_gateway',
      'global_setting'      => 'global_setting',
      'bank_account'        => 'bank_account',
      'jasa'                => 'product_jasa',
      'jasa_price'          => 'product_jasa_price',
      'mitra_jasa'          => 'mitra_jasa',
      'jasa_wishlist'       => 'product_jasa_wishlist',
      'bank_account'        => 'bank_account',
    );
  }

  protected function sanitize($conn, $data = array())
  {
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        unset($data[$key]);

        $data[$this->sanitize($conn, $key)] = $this->sanitize($conn, $value);
      }
    } else {
      if (!is_object($data)) {
        $data = trim($conn->escape_str(htmlspecialchars($data, ENT_QUOTES, 'UTF-8')));
      }
    }

    return $data;
  }

  protected function build_condition($conn, $args = array(), $table)
  {
    $conditions = array();

    $args = $this->sanitize($conn, $args);
    foreach ($args as $key => $value) {
      if (in_array($key, array('sort', 'page', 'length')) === FALSE) {
        /**
         * Hasil: $condition_key (column) $condition_operator (=) $condition_value (nilai)
         * Contoh: column = nilai
         *
         **/

        // Normalize
        $condition_key      = '';
        $condition_operator = '';
        $condition_value    = '';
        $selector = $operator = '';

        // Preparing condition_key
        $selector = explode('::', $key);
        if (isset($selector[1])) {
          $key = explode('.', $selector[1]);
          switch ($selector[0]) {
            case 'ENCRYPTED':
              $condition_key = "SHA1(CONCAT(" . (isset($key[1]) ? "`" . $key[0] . "`.`" . $key[1] . "`" : "`" . $table . "`.`" . $key[0] . "`") . ", '" . $this->config->item('encryption_key') . "'))";
              break;
            default:
              $condition_key = (isset($key[1]) ? "`" . $key[0] . "`.`" . $key[1] . "`" : "`" . $table . "`.`" . $key[0] . "`");
              break;
          }
        } else {
          $key = explode('.', $key);
          if ((isset($key[1]) && (strpos($key[1], 'idxx') !== FALSE)) or (empty($key[1]) && (strpos($key[0], 'idxx') !== FALSE))) {
            $condition_key = (isset($key[1]) ? "`" . $key[0] . "`.`id`" : "`" . $table . "`.`id`");
          } else {
            $condition_key = (isset($key[1]) ? "`" . $key[0] . "`.`" . $key[1] . "`" : "`" . $table . "`.`" . $key[0] . "`");
          }
        }

        // Preparing condition_operator
        if (is_array($value) === false) {
          $operator = explode('::', $value);

          if (count($operator) > 1) {
            $valid_operator = array("EQUAL", "NOTEQ", "LESS", "GREAT", "LESSEQ", "GREATEQ", "IN", "NOTIN", "LIKE", "NOTLIKE", "BETWEEN");

            if (in_array($operator[0], $valid_operator)) {
              switch ($operator[0]) {
                case 'EQUAL':
                  $condition_operator = "=";
                  break;
                case 'NOTEQ':
                  $condition_operator = "!=";
                  break;
                case 'LESS':
                  $condition_operator = "<";
                  break;
                case 'GREAT':
                  $condition_operator = ">";
                  break;
                case 'LESSEQ':
                  $condition_operator = "<=";
                  break;
                case 'GREATEQ':
                  $condition_operator = ">=";
                  break;
                case 'IN':
                  $condition_operator = "IN";
                  break;
                case 'NOTIN':
                  $condition_operator = "NOT IN";
                  break;
                case 'LIKE':
                  $condition_operator = "LIKE";
                  break;
                case 'NOTLIKE':
                  $condition_operator = "NOT LIKE";
                  break;
                case 'BETWEEN':
                  $condition_operator = "BETWEEN";
                  break;
                default:
                  $condition_operator = "=";
                  break;
              }
            }

            $value = $operator[1];
          } else {
            $condition_operator = "=";
          }
        }

        if (!empty($condition_operator)) {
          if (preg_match('/(.*?)\[\{(.*)\}\{(.*)\}\{(.*)\}\]/', $value, $match)) {
            $value = explode(',', $match[1]);
            $selector2 = explode('--', $match[4]);
            if (isset($selector2[1]) && ($selector2[0] == 'ENCRYPTED')) {
              $condition_value = "(SELECT `" . $match[2] . "` FROM `" . $match[3] . "` WHERE SHA1(CONCAT(`" . $selector2[1] . "`, '" . $this->config->item('encryption_key') . "')) IN ('" . implode("', '", $value) . "'))";
            } else {
              $condition_value = "(SELECT `" . $match[2] . "` FROM `" . $match[3] . "` WHERE `" . $match[4] . "` IN ('" . implode("', '", $value) . "'))";
            }
          } else {
            if ($condition_operator == 'BETWEEN') {
              if (preg_match('/\[\{(.*)\}\{(.*)\}\]/', $value, $match)) {
                $condition_value = "'" . $match[1] . "' AND '" . $match[2] . "'";
              }
            } else {
              $value = explode('??', $value);

              if ((count($value) > 1) && ($value[1] == 'subquery')) {
                $condition_value = $value[0];
              } else {
                if (in_array($value[0], array('NULL', 'NOT NULL'))) {
                  $condition_value = $value[0];
                } else {
                  $condition_value = "'" . $value[0] . "'";
                }
              }
            }
          }
        }

        if (!empty($condition_key) && !empty($condition_operator) && !empty($condition_value)) {
          if (in_array($value[0], array('NULL', 'NOT NULL'))) {
            $conditions[] = "(" . $condition_key . " IS " . $condition_value . ")";
          } else {
            $conditions[] = "(" . $condition_key . " " . $condition_operator . " " . $condition_value . ")";
          }
        }
      }
    }

    $result = (($conditions) ? " WHERE " : "");

    foreach ($conditions as $key => $cond) {
      $result .= $cond . (($key < (count($conditions) - 1)) ? " AND " : "");
    }

    return $result;
  }

  protected function prepare_data($data = array())
  {
    $result = array();
    foreach ($data as $key => $value) {
      $result[$key] = (((is_array($value)) or (is_object($value))) ? json_encode($value) : $value);
    }

    return $result;
  }

  protected function build_order($conn, $args = array(), $table = '')
  {
    $args = $this->sanitize($conn, $args);

    $result = "";
    if (!empty($args['sort'])) {
      $index = 0;
      foreach ($args['sort'] as $key => $sort) {
        if (!empty($sort['sort_by']) && !empty($sort['sort_direction'])) {
          if ($sort['sort_direction'] != 'desc') $sort['sort_direction'] = 'asc';

          $key = explode('.', $sort['sort_by']);

          if ($sort['sort_by'] == 'random_sort') {
            $result .= (($index == 0) ? " ORDER BY" : "");
            $result .= (($index > 0) ? "," : "");
            $result .= " RAND()";
          } else {
            if (!empty($table)) {
              $columns = $this->describe_table($conn, $table);

              $valid_order = (isset($key[1]) ? in_array($key[1], $columns) : in_array($key[0], $columns));

              if ($valid_order) {
                $result .= (($index == 0) ? " ORDER BY" : "");
                $result .= (($index > 0) ? "," : "");
                $result .=
                  " " . (isset($key[1]) ? "`" . $key[0] . "`.`" . $key[1] . "`" : "`" . $table . "`.`" . $key[0] . "`") .
                  " " . strtoupper($sort['sort_direction']);
              }
            } else {
              $result .= (($index == 0) ? " ORDER BY" : "");
              $result .= (($index > 0) ? "," : "");
              $result .=
                " " . (isset($key[1]) ? "`" . $key[0] . "``" . $key[1] . "`" : "`" . $key[0] . "`") .
                " " . strtoupper($sort['sort_direction']);
            }
          }

          $index++;
        }
      }
    }

    return $result;
  }

  protected function build_limit($conn, $args = array())
  {
    $result = "";
    $default_length = 10;

    $page   = (isset($args['page']) ? (int) $args['page'] : '');
    $length = (isset($args['length']) ? $args['length'] : 'all');

    if ((!empty($page)) or (!empty($length))) {
      if ((!empty($page)) && (!empty($length))) {
        if ($length !== 'all') {
          $start = (((int) $page > 1) ? ((int) $page - 1) * (int) $length : 0);
          $result = " LIMIT " . $start . "," . (int) $length;
        }
      } elseif ((!empty($page)) && (empty($length))) {
        $start = (((int) $page > 1) ? ((int) $page - 1) * $default_length : 0);
        $result = " LIMIT " . $start . "," . $default_length;
      } elseif ((empty($page)) && (!empty($length))) {
        if ($length !== 'all') $result = " LIMIT " . (int) $length;
      }
    }

    return $result;
  }

  protected function build_field($conn, $table = '', $values = '')
  {
    $result = "";
    $columns = $this->describe_table($conn, $table);

    $i = 1;
    foreach ($values as $key => $value) {
      if (in_array($key, $columns)) {
        $result .= (($i != 1) ? ', ' : '') . "`" . $key . "` = '" . (((is_array($value)) or (is_object($value))) ? json_encode($this->sanitize($conn, $value)) : $this->sanitize($conn, $value)) . "'";

        $i++;
      }
    }

    return $result;
  }

  protected function describe_table($conn, $table, $show_full = FALSE)
  {
    $query = $conn->query("DESCRIBE `" . $table . "`")->result();

    $result = array();
    foreach ($query as $row) {
      if ($show_full) {
        $result[] = array($row->Field, $row->Type, $row->Null, $row->Key, $row->Default, $row->Extra);
      } else {
        $result[] = $row->Field;
      }
    }

    return $result;
  }

  protected function count_all($conn, $table)
  {
    $query = $conn->query("SELECT COUNT(*) AS `total_rows` FROM `" . $table . "`");

    if ($query->num_rows() === 0) {
      return 0;
    }

    $query = $query->fetch();
    return (int) $query['total_rows'];
  }

  protected function count_rows($conn = '', $table = '', $params = array())
  {
    $conn = (!empty($conn) ? $conn : $this->conn['main']);

    if (!empty($table) && !empty($params)) {
      if (is_array($params)) {
        $cond_query = $this->build_condition($conn, $params, $table);
      } else {
        $cond_query = $params;
      }

      $query = $conn->query("SELECT COUNT(*) AS `rows` FROM `" . $table . "` " . $cond_query)->result_array();

      if ($query) {
        return $query[0]['rows'];
      } else {
        return 0;
      }
    }
  }

  protected function set_response($key = '', $value = '')
  {
    if (!empty($key)) {
      if ($key == 'message') {
        $this->response['message'] = trim(preg_replace('/\s\s+/', ' ', $value));
      } else {
        $this->response[$key] = $value;

        if ($key == 'code') {
          if (!empty($this->response['response'])) unset($this->response['response']);

          $this->response['message'] = sprintf(
            $this->language['error_response'],
            $this->language['response'][$value]['title'],
            $this->language['response'][$value]['description']
          );
        }
      }
    } else {
      $this->response = $value;
    }
  }

  protected function get_response($key = '')
  {
    if (!empty($key)) {
      if (isset($this->response[$key])) {
        return $this->response[$key];
      } else {
        return FALSE;
      }
    } else {
      return $this->response;
    }
  }

  function getWhere($table, $where, $limit = '', $order_by = '', $order_type = '')
  {

    $this->conn['main']->select('*');
    $this->conn['main']->where($where);
    if ($limit)
      $this->conn['main']->limit($limit);
    if ($order_by) {
      $this->conn['main']->order_by($order_by, $order_type);
    }
    $data = $this->conn['main']->get($table)->result();

    return $data;
  }

  function getValue($field, $table, $where)
  {
    $data = $this->conn['main']
      ->select($field)
      ->where($where)
      ->get($table)->row();

    return $data->$field;
  }

  function getAllEncode($field, $table, $value_where)
  {
    $data = $this->conn['main']
      ->select('*')
      ->where("SHA1(CONCAT($field, '" . $this->config->item('encryption_key') . "')) = ", $value_where)
      ->get($table)->row();

    return $data;
  }

  function getValueEncode($field, $table, $value_where)
  {
    $data = $this->conn['main']
      ->select($field)
      ->where("SHA1(CONCAT($field, '" . $this->config->item('encryption_key') . "')) = ", $value_where)
      ->get($table)->row();

    return $data->$field;
  }

  function save($data, $table)
  {
    $this->conn['main']->insert($table, $data);
    $insert_id = $this->conn['main']->insert_id();

    return  $insert_id;
  }

  function update_data($where, $data, $tabel)
  {
    $this->conn['main']->where($where);
    return $this->conn['main']->update($tabel, $data);
  }

  function cek_uniq_num($uniq_num)
  {
    $data = $this->conn['main']
      ->select('*')
      ->where('uniq_num', $uniq_num)
      ->where('status', 'pending')
      ->where('date', date('Y-m-d'))
      ->get('payment_transfer')->row();

    return $data;
  }

  function encoded($code)
  {
    $sql = "select SHA1(CONCAT($code, '" . $this->config->item('encryption_key') . "')) as code";
    $get = $this->conn['main']->query($sql)->row();

    return $get->code;
  }
}

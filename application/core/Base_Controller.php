<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Base Controller
 * @author Asep Fajar Nugraha <delve_brain@hotmail.com>
 */
class Base_Controller extends CI_Controller
{
  protected $method;
  protected $request;
  protected $response;
  protected $data = array();
  protected $language = array();

  public function __construct()
  {
    parent::__construct();

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, token");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

    // INIT Application
    $this->init_application();
  }

  protected function init_application()
  {
    $this->method = strtoupper($this->input->server('REQUEST_METHOD'));

    if (in_array($this->method, array('GET', 'DELETE', 'POST', 'PUT'))) {
      // BEGIN: SET LANGUAGE
      $lang_available = array('EN', 'ID');
      $lang = $this->input->get('lang');
      $lang = ((!empty($lang) && (in_array($lang, $lang_available))) ? $lang : $this->config->item('language'));

      $this->lang->load(array('common', 'response', 'calendar'), $lang);
      $this->language = $this->lang->language;
      // END: SET LANGUAGE

      // BEGIN: SET REQUEST
      $this->request['header'] = $this->input->request_headers();

      switch ($this->method) {
        case 'GET':
          $this->request['body'] = $this->input->get();
          break;
        case 'POST':
          $this->request['body'] = $this->input->post();
          break;
        case 'PUT':
        case 'DELETE':
          parse_str(file_get_contents("php://input"), $this->request['body']);
          break;
      }
      // END: SET REQUEST

      // BEGIN: COMMON VARIABLES
      $this->data['assets_url']   = $this->config->item('base_url') . 'assets/';
      $this->data['storage_url']  = $this->config->item('storage_url');
    } else {
      $this->set_response('code', 405);
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

  protected function print_output($data = array(), $log = TRUE)
  {
    $output = $this->input->get('output');
    $response_type = (!empty($output) ? $output : 'json');
    $response_data = (!empty($data) ? $data : $this->get_response());

    switch ($response_type) {
      default:
      case 'json':
        $response = json_encode($response_data);
        $this->output
          ->set_content_type('application/json', $this->config->item('charset'))
          ->set_output($response)
          ->_display();

        if ($log) $this->set_log();

        exit;
        break;

      case 'xml':
        $xml_data = new SimpleXMLElement('<?xml version="1.0"?><response></response>');
        $this->array_to_xml($response_data, $xml_data);

        $response = $xml_data->asXML();

        header('Content-type: text/xml');
        echo $response;

        if ($log) $this->set_log();

        exit;
        break;

      case 'plain':
        $response = json_encode($response_data);
        return json_decode($response, 1);

        if ($log) $this->set_log();

        exit;
        break;
    }
  }

  // Convert array into xml
  private function array_to_xml($data, &$xml_data)
  {
    foreach ($data as $key => $value) {
      if (is_numeric($key)) {
        $key = 'item' . $key;
      }

      if (is_array($value)) {
        $subnode = $xml_data->addChild($key);
        $this->array_to_xml($value, $subnode);
      } else {
        $xml_data->addChild("$key", htmlspecialchars("$value"));
      }
    }
  }

  private function set_log()
  {
    // Load model
    $this->load->model('log_model');

    $params = array(
      'url'         => $this->router->fetch_directory() . $this->router->fetch_class() . '/' . $this->router->fetch_method(),
      'method'      => $this->method,
      'request'     => $this->request,
      'response'    => $this->get_response(),
      'ip_address'  => $this->input->ip_address()
    );

    // Insert to log
    $this->log_model->create($params);
  }

  public function validate_email_new($email)
  {
    // Load model
    $this->load->model('user_model');
    $rows = $this->user_model->total(array('email' => 'LIKE::%' . $email));
    if ($rows < 1) {
      return TRUE;
    } else {
      $this->form_validation->set_message('validate_email_new', $this->language['message_email_already_taken']);
      return FALSE;
    }
  }

  public function validate_token($token)
  {
    // Load model
    $this->load->model('user_model');
    $rows = $this->user_model->total(array('ecommerce_token' => 'LIKE::%' . $token));

    if ($rows) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  public function verify_request()
  {
    // Get all the headers
    $headers = $this->input->request_headers();
    // Extract the token
    if (array_key_exists('Authorization', $headers)) {

      $token = $headers['Authorization'];
      // Use try-catch
      // JWT library throws exception if the token is not valid
      try {
        // Validate the token
        // Successfull validation will return the decoded user data else returns false
        $data = AUTHORIZATION::validateToken($token);
        if ($data === false) {
          $this->set_response('code', 498);
          $this->print_output();
          exit();
        } else {
          return $data;
        }
      } catch (Exception $e) {
        // Token is invalid
        // Send the unathorized access message
        $this->set_response('code', 498);
        $this->print_output();
      }
    } else {
      $this->set_response('code', 401);
      $this->print_output();
    }
  }

  // BEGIN: form_validation custom method
  public function validate_merchant_id($merchant_id)
  {
    // Load model
    $this->load->model('merchant_model');

    $rows = $this->merchant_model->total(array('ENCRYPTED::id' => $merchant_id));

    if ($rows) {
      return TRUE;
    } else {
      $this->form_validation->set_message('validate_merchant_id', $this->language['message_not_found']);

      return FALSE;
    }
  }

  public function validate_product_id($product_id)
  {
    // Load model
    $this->load->model('product_model');

    $rows = $this->product_model->total(array('ENCRYPTED::id' => $product_id));

    if ($rows) {
      return TRUE;
    } else {
      $this->form_validation->set_message('validate_product_id', $this->language['message_not_found']);

      return FALSE;
    }
  }

  public function validate_product_variant_id($variant_id = '')
  {
    if (!empty($variant_id)) {
      // Load model
      $this->load->model('product_model');

      $rows = $this->product_model->total_product_variant(array('ENCRYPTED::id' => $variant_id));

      if ($rows) {
        return TRUE;
      } else {
        $this->form_validation->set_message('validate_product_variant_id', $this->language['message_not_found']);

        return FALSE;
      }
    } else {
      return TRUE;
    }
  }

  public function validate_payment_code($payment_code)
  {
    // Load model
    $this->load->model('payment_gateway_model');

    $rows = $this->payment_gateway_model->total(array('payment_code' => $payment_code));

    if ($rows) {
      return TRUE;
    } else {
      $this->form_validation->set_message('validate_payment_code', $this->language['message_not_found']);

      return FALSE;
    }
  }

  public function validate_shipping_code($shipping_code)
  {
    // Load model
    $this->load->model('shipping_gateway_model');

    $rows = $this->shipping_gateway_model->total(array('shipping_code' => $shipping_code));

    if ($rows) {
      return TRUE;
    } else {
      $this->form_validation->set_message('validate_shipping_code', $this->language['message_not_found']);

      return FALSE;
    }
  }

  public function validate_jasa_id($product_id)
  {
    // Load model
    $this->load->model('jasa_model');

    $rows = $this->jasa_model->total(array('ENCRYPTED::id' => $product_id));

    if ($rows) {
      return TRUE;
    } else {
      $this->form_validation->set_message('validate_jasa_id', $this->language['message_not_found']);

      return FALSE;
    }
  }

  public function validate_jasa_variant_id($variant_id = '')
  {
    if (!empty($variant_id)) {
      // Load model
      $this->load->model('jasa_model');

      $rows = $this->jasa_model->total_product_variant(array('ENCRYPTED::id' => $variant_id));

      if ($rows) {
        return TRUE;
      } else {
        $this->form_validation->set_message('validate_jasa_variant_id', $this->language['message_not_found']);

        return FALSE;
      }
    } else {
      return TRUE;
    }
  }

  // END: form_validation custom method

  protected function set_log_first($request)
  {
    // Load model
    $this->load->model('log_model');

    $params = array(
      'path'        => $this->uri->uri_string(),
      'method'      => $this->method,
      'request'     => $this->request,
      'response'    => $this->get_response(),
      'ip_address'  => $this->input->ip_address()
    );

    // Insert to log
    $this->log_model->create($params);
  }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Console extends Base_Controller {

  public function error($code = '404')
  {
    $this->set_response('code', $code);
    $this->print_output();
  }

  public function page_error($code = '404')
  {
    $this->data['message'] = sprintf(
      $this->language['error_response'],
      $this->language['response'][$code]['title'],
      $this->language['response'][$code]['description']
    );

    $this->load->view('error', $this->data);
  }

  public function encode($data = '', $encoding = '')
  {
    if ( ! empty($encoding) && ($encoding == 'sha1'))
    {
      echo hash('sha1', $data . $this->config->item('encryption_key'));
    }
    else if ( ! empty($encoding) && ($encoding == 'md5'))
    {
      echo md5($data);
    }
    else
    {
      echo encode($data);
    }
  }

  public function decode($data = '')
  {
    echo decode($data);
  }

  public function booking_expired()
  {
    $this->load->model('common_model');
    $conn = $this->common_model->get_connection('main');

    $conn->query("DELETE FROM `".$this->tables['booking']."` WHERE UNIX_TIMESTAMP(created) < UNIX_TIMESTAMP()");
  }
}

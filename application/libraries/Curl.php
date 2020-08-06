<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Library Curl
 * Digunakan untuk menggunakan fungsi PHP curl.
 *
 * @author Fajar <delve_brain@hotmail.com>
 */
class Curl
{

  private $CI;

  public function __construct()
  {
    $this->CI = &get_instance();

    set_time_limit(700);
    ini_set('max_execution_time', 0); // 0 = NOLIMIT
  }

  public function post($req_url = '', $req_params, $req_header = array(), $auto_decode = TRUE, $debug_mode = FALSE)
  {
    if ($req_url) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $req_url);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_POSTFIELDS, $req_params);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req_params));
      curl_setopt($ch, CURLOPT_REFERER, base_url());
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 700);
      curl_setopt($ch, CURLOPT_TIMEOUT, 700);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

      if (!empty($req_header)) {
        if (!is_array($req_header)) {
          $req_header = array($req_header);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $req_header);
      }

      $result = curl_exec($ch);

      if ($debug_mode) {
        curl_close($ch);
        echo '<pre>' . print_r($result, 1) . '</pre>';
        exit();
      } else {
        if ($result === false) {
          return curl_error($ch);
        } else {
          if ($auto_decode) {
            return json_decode($result);
          } else {
            return $result;
          }
        }
      }

      curl_close($ch);
      exit();
    } else {
      $response['code']      = 400;
      $response['message']   = lang('code_' . $this->res['code']);

      return json_encode($response);
      exit();
    }
  }

  public function post_file($req_url = '', $req_params, $req_header = array(), $auto_decode = TRUE, $debug_mode = FALSE)
  {
    if ($req_url) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $req_url);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $req_params);
      // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req_params));
      curl_setopt($ch, CURLOPT_REFERER, base_url());
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 700);
      curl_setopt($ch, CURLOPT_TIMEOUT, 700);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

      if (!empty($req_header)) {
        if (!is_array($req_header)) {
          $req_header = array($req_header);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $req_header);
      }

      $result = curl_exec($ch);

      if ($debug_mode) {
        curl_close($ch);
        echo '<pre>' . print_r($result, 1) . '</pre>';
        exit();
      } else {
        if ($result === false) {
          return curl_error($ch);
        } else {
          if ($auto_decode) {
            return json_decode($result);
          } else {
            return $result;
          }
        }
      }

      curl_close($ch);
      exit();
    } else {
      $response['code']      = 400;
      $response['message']   = lang('code_' . $this->res['code']);

      return json_encode($response);
      exit();
    }
  }

  public function get($req_url = '', $req_params, $req_header = array(), $auto_decode = TRUE, $debug_mode = FALSE)
  {
    if ($req_url) {
      $ch = curl_init();

      if (!empty($req_params)) {
        $req_url = $req_url . '?' . http_build_query($req_params);
      }

      curl_setopt($ch, CURLOPT_URL, $req_url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_ENCODING, "");
      curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 700);
      curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

      if (!empty($req_header)) {
        if (!is_array($req_header)) {
          $req_header = array($req_header);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $req_header);
      }

      $result = curl_exec($ch);

      if ($debug_mode) {
        curl_close($ch);
        echo '<pre>' . print_r($result, 1) . '</pre>';
        exit();
      } else {
        if ($result === false) {
          return curl_error($ch);
        } else {
          if ($auto_decode) {
            return json_decode($result);
          } else {
            return $result;
          }
        }
      }

      curl_close($ch);
      exit();
    } else {
      $response['code']      = 400;
      $response['message']   = lang('code_' . $this->res['code']);

      return json_encode($response);
      exit();
    }
  }

  public function push($mitra_id, $title, $msg, $menu, $type = 'mitra')
  {
    $ci = &get_instance();

    $url = 'https://fcm.googleapis.com/fcm/send';

    $ci->load->model('mitra_model');
    $get_device = $ci->mitra_model->get_deviceID($mitra_id)->row();

    if ($type == 'mitra') {
      $server_key = 'AAAAaARMcOw:APA91bEVd_uSdJ0-p5zkmr-H7Vsam7IZlYY9yqjwVsHU8ym0toQCna2OJGpyyavFmyeOVdGnfAr-UnXAH2L_SKWeDVg3IP8eH5ch_AJsDmf-Wd424riAuYSMOUa7nQY6a_f2h2ux4IQN';
    } else {
      $server_key = 'AAAACdkCJJE:APA91bEkZ4luCS8nU5B8mrvz7R6MghViKgnFL5GV24OnsprpIo_N_0K_bBoOzb1BG8e1feZrKLI1DZqIziUowjCdODbn9Magyawi-VvVpDkw07SAPaL44AMaeOJiNXRAUlKQOsZkc76B';
    }

    if (!empty($get_device)) :
      $DeviceId = $get_device->device_id;
      $data = array(
        'priority'  => 10,
        'data' => array('msg_id' => date('Ymd'), 'message'  => $msg, 'menu'   => $menu),
        'notification' => array('title' => $title, 'body' => $msg),
        'to'  => $DeviceId
      );

      $headers = array(
        'Content-Type: application/json',
        'Authorization:key=' . $server_key
      );
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
      $result = curl_exec($ch);

      if ($result === FALSE) :
        $msg = 'Curl failed: ' . curl_error($ch);
        $respon = array(
          'err_no'  => "1",
          'message' => $msg
        );
      else :
        $resultDecode = json_decode($result);
        if ($resultDecode->success == "1") :
          $respon = array(
            'err_no'  => "0",
            'message_id' => $resultDecode->results[0]->message_id
          );
        else :
          $respon = array(
            'err_no'  => "1",
            'message' => $resultDecode->results[0]->error
          );
        endif;
      endif;

      curl_close($ch);
      return $respon;
    endif;
  }
}

/*
$error_codes = array(
[1] => 'CURLE_UNSUPPORTED_PROTOCOL',
[2] => 'CURLE_FAILED_INIT',
[3] => 'CURLE_URL_MALFORMAT',
[4] => 'CURLE_URL_MALFORMAT_USER',
[5] => 'CURLE_COULDNT_RESOLVE_PROXY',
[6] => 'CURLE_COULDNT_RESOLVE_HOST',
[7] => 'CURLE_COULDNT_CONNECT',
[8] => 'CURLE_FTP_WEIRD_SERVER_REPLY',
[9] => 'CURLE_REMOTE_ACCESS_DENIED',
[11] => 'CURLE_FTP_WEIRD_PASS_REPLY',
[13] => 'CURLE_FTP_WEIRD_PASV_REPLY',
[14]=>'CURLE_FTP_WEIRD_227_FORMAT',
[15] => 'CURLE_FTP_CANT_GET_HOST',
[17] => 'CURLE_FTP_COULDNT_SET_TYPE',
[18] => 'CURLE_PARTIAL_FILE',
[19] => 'CURLE_FTP_COULDNT_RETR_FILE',
[21] => 'CURLE_QUOTE_ERROR',
[22] => 'CURLE_HTTP_RETURNED_ERROR',
[23] => 'CURLE_WRITE_ERROR',
[25] => 'CURLE_UPLOAD_FAILED',
[26] => 'CURLE_READ_ERROR',
[27] => 'CURLE_OUT_OF_MEMORY',
[28] => 'CURLE_OPERATION_TIMEDOUT',
[30] => 'CURLE_FTP_PORT_FAILED',
[31] => 'CURLE_FTP_COULDNT_USE_REST',
[33] => 'CURLE_RANGE_ERROR',
[34] => 'CURLE_HTTP_POST_ERROR',
[35] => 'CURLE_SSL_CONNECT_ERROR',
[36] => 'CURLE_BAD_DOWNLOAD_RESUME',
[37] => 'CURLE_FILE_COULDNT_READ_FILE',
[38] => 'CURLE_LDAP_CANNOT_BIND',
[39] => 'CURLE_LDAP_SEARCH_FAILED',
[41] => 'CURLE_FUNCTION_NOT_FOUND',
[42] => 'CURLE_ABORTED_BY_CALLBACK',
[43] => 'CURLE_BAD_FUNCTION_ARGUMENT',
[45] => 'CURLE_INTERFACE_FAILED',
[47] => 'CURLE_TOO_MANY_REDIRECTS',
[48] => 'CURLE_UNKNOWN_TELNET_OPTION',
[49] => 'CURLE_TELNET_OPTION_SYNTAX',
[51] => 'CURLE_PEER_FAILED_VERIFICATION',
[52] => 'CURLE_GOT_NOTHING',
[53] => 'CURLE_SSL_ENGINE_NOTFOUND',
[54] => 'CURLE_SSL_ENGINE_SETFAILED',
[55] => 'CURLE_SEND_ERROR',
[56] => 'CURLE_RECV_ERROR',
[58] => 'CURLE_SSL_CERTPROBLEM',
[59] => 'CURLE_SSL_CIPHER',
[60] => 'CURLE_SSL_CACERT',
[61] => 'CURLE_BAD_CONTENT_ENCODING',
[62] => 'CURLE_LDAP_INVALID_URL',
[63] => 'CURLE_FILESIZE_EXCEEDED',
[64] => 'CURLE_USE_SSL_FAILED',
[65] => 'CURLE_SEND_FAIL_REWIND',
[66] => 'CURLE_SSL_ENGINE_INITFAILED',
[67] => 'CURLE_LOGIN_DENIED',
[68] => 'CURLE_TFTP_NOTFOUND',
[69] => 'CURLE_TFTP_PERM',
[70] => 'CURLE_REMOTE_DISK_FULL',
[71] => 'CURLE_TFTP_ILLEGAL',
[72] => 'CURLE_TFTP_UNKNOWNID',
[73] => 'CURLE_REMOTE_FILE_EXISTS',
[74] => 'CURLE_TFTP_NOSUCHUSER',
[75] => 'CURLE_CONV_FAILED',
[76] => 'CURLE_CONV_REQD',
[77] => 'CURLE_SSL_CACERT_BADFILE',
[78] => 'CURLE_REMOTE_FILE_NOT_FOUND',
[79] => 'CURLE_SSH',
[80] => 'CURLE_SSL_SHUTDOWN_FAILED',
[81] => 'CURLE_AGAIN',
[82] => 'CURLE_SSL_CRL_BADFILE',
[83] => 'CURLE_SSL_ISSUER_ERROR',
[84] => 'CURLE_FTP_PRET_FAILED',
[84] => 'CURLE_FTP_PRET_FAILED',
[85] => 'CURLE_RTSP_CSEQ_ERROR',
[86] => 'CURLE_RTSP_SESSION_ERROR',
[87] => 'CURLE_FTP_BAD_FILE_LIST',
[88] => 'CURLE_CHUNK_FAILED');
*/

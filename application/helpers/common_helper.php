<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Common Helper
 * Helper merupakan fungsi tambahan dalam aplikasi
 * @author Asep Fajar Nugraha <delve_brain@hotmail.com>
 */

// FORM VALIDATION
if (!function_exists('set_rules')) {
    function set_rules($rules = array(), $prefix = '', $suffix = '')
    {
        $CI = &get_instance();

        $CI->form_validation->set_error_delimiters($prefix, $suffix);

        foreach ($rules as $rule) {
            if (!empty($rule[0])) {
                $CI->form_validation->set_rules($rule[0], ((!empty($rule[2])) ? $rule[2] : ''), ((!empty($rule[1])) ? $rule[1] : ''));
            }
        }

        unset($CI);
    }
}

if (!function_exists('get_rules_error')) {
    function get_rules_error($rules = array())
    {
        $CI = &get_instance();

        $errors['message']  = validation_errors();
        $errors['items']    = array();
        foreach ($rules as $rule) {
            if ((!empty($rule[0])) && form_error($rule[0])) {
                $errors['items'][$rule[0]] = form_error($rule[0]);
            }
        }

        return array('error' => $errors);
    }
}

// URL
if (!function_exists('create_query_url')) {
    function create_query_url($url, $params = array())
    {
        $CI = &get_instance();

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $current_params);

        // merging
        $params = array_replace_recursive($current_params, $params);

        foreach ($params as $key => $value) {
            if (empty($value)) {
                unset($params[$key]);
            }
        }

        // build query string
        $query = http_build_query($params);

        // build url
        return explode('?', $url)[0] . '?' . $query;
    }
}

if (!function_exists('create_sort_url')) {
    function create_sort_url($name = '')
    {
        $CI = &get_instance();

        $get = $CI->input->get();

        if ((!empty($get['sort_by']) && !empty($get['sort_direction'])) && ($get['sort_by'] == $name)) {
            if ($get['sort_direction'] == 'asc') {
                $url    = create_query_url(current_url(), array('sort_by' => $name, 'sort_direction' => 'desc'));
                $icon   = '<i class="icon5-sort-asc"></i>';
            } else {
                $url    = create_query_url(current_url(), array('sort_by' => '', 'sort_direction' => ''));
                $icon   = '<i class="icon5-sort-desc"></i>';
            }
        } else {
            $url    = create_query_url(current_url(), array('sort_by' => $name, 'sort_direction' => 'asc'));
            $icon   = '<i class="icon5-sort"></i>';
        }

        return '&nbsp;&nbsp;<a href="' . $url . '">' . $icon . '</a>';
    }
}

// COMMON


if (!function_exists('set_number_format')) {
    function set_number_format($number, $decimal = 0, $decimal_symbol = '', $thousands_symbol = '', $tag = '', $shorten = '')
    {
        $CI = &get_instance();
        $CI->lang->load('common', $CI->config->item('language'));

        if ((float)$number > 1000) {
            if ($shorten == 'k') {
                $number = round(((float)$number / 1000));
                $shorten_status = TRUE;
            }
        }

        $decimal_symbol = (!empty($decimal_symbol) ? $decimal_symbol : $CI->lang->line('decimal_symbol'));
        $thousands_symbol = (!empty($thousands_symbol) ? $thousands_symbol : $CI->lang->line('thousands_symbol'));

        $formatted_number = number_format($number, $decimal, $decimal_symbol, $thousands_symbol);
        if ($tag) {
            $number_temp = explode($thousands_symbol, $formatted_number);

            $tag = strtolower($tag);
            $formatted_number = '';

            if (count($number_temp) > 1) {
                foreach ($number_temp as $key => $t) {
                    if ($key != (count($number_temp) - 1)) {
                        $formatted_number .= '<' . $tag . '>' . $t . '</' . $tag . '>' . $thousands_symbol;
                    } else {
                        $formatted_number .= $t;
                    }
                }
            } else {
                $formatted_number .= '<' . $tag . '>' . $number_temp[0] . '</' . $tag . '>';
            }
        }

        if (!empty($shorten_status)) {
            $formatted_number = $formatted_number . strtoupper($shorten);
        }

        return $formatted_number;
    }
}

if (!function_exists('set_date_format')) {
    function set_date_format($date_data, $format = 'l, d F Y')
    {
        $CI = &get_instance();
        $CI->lang->load('calendar', $CI->config->item('language'));

        switch ($format) {
            case 'l, d F Y':
                if ($CI->lang->line('lang_code') != 'en') {
                    $day = $CI->lang->line('cal_' . strtolower(date('l', strtotime($date_data))));
                    $month = $CI->lang->line('cal_' . strtolower(date('F', strtotime($date_data))));

                    $date = date('d', strtotime($date_data));
                    $year = date('Y', strtotime($date_data));

                    return "{$day}, {$date} {$month} {$year}";
                } else {
                    return date($format, strtotime($date_data));
                }
                break;
            case 'd F Y':
                if ($CI->lang->line('lang_code') != 'en') {
                    $month = $CI->lang->line('cal_' . strtolower(date('F', strtotime($date_data))));

                    $date = date('d', strtotime($date_data));
                    $year = date('Y', strtotime($date_data));

                    return "{$date} {$month} {$year}";
                } else {
                    return date($format, strtotime($date_data));
                }
                break;
            default:
                return date('d-m-Y', strtotime($date_data));
                break;
        }
    }
}

if (!function_exists('pretty_print')) {
    function pretty_print($data = array(), $exit = FALSE)
    {
        echo '<pre>' . print_r($data, 1) . '</pre>';

        if ($exit == TRUE) {
            exit();
        }
    }
}

if (!function_exists('check_permission')) {
    function check_permission($item = '', $type = 'admin')
    {
        $CI = &get_instance();
        $login = $CI->session->userdata('login-' . $CI->config->item('encryption_key'));
        $login = $login[$type];

        if (!empty($login) && !empty($item)) {
            if (in_array('granted', $login['permission'])) {
                return TRUE;
            } else {
                if (in_array($item, $login['permission'])) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            }
        } else {
            return FALSE;
        }
    }
}

if (!function_exists('generate_paging_config')) {
    function generate_paging_config()
    {
        $config['full_tag_open']    = '<ul class="pagination">';
        $config['full_tag_close']   = '</ul>';
        $config['first_tag_open']   = '<li>';
        $config['first_tag_close']  = '</li>';
        $config['last_tag_open']    = '<li>';
        $config['last_tag_close']   = '</li>';
        $config['cur_tag_open']     = '<li class="active"><a href="#">';
        $config['cur_tag_close']    = '</a></li>';
        $config['next_tag_open']    = '<li>';
        $config['next_tag_close']   = '</li>';
        $config['prev_tag_open']    = '<li>';
        $config['prev_tag_close']   = '</li>';
        $config['num_tag_open']     = '<li>';
        $config['num_tag_close']    = '</li>';
        $config['first_link']       = '<i class="icon4-skip-back"></i>';
        $config['prev_link']        = '<i class="icon4-rewind"></i>';
        $config['next_link']        = '<i class="icon4-fast-forward"></i>';
        $config['last_link']        = '<i class="icon4-skip-forward"></i>';
        $config['use_page_numbers'] = TRUE;
        $config['num_links']        = 1;

        return $config;
    }
}

if (!function_exists('update_cron')) {
    function update_cron($func_name)
    {
        $CI = &get_instance();

        $CI->conn['main'] = $CI->load->database('default', TRUE);

        $data = array('update_at' => date('Y-m-d H:i:s'));
        $CI->conn['main']
            ->set('last_update', 'update_at', false)
            ->where('cron_name', $func_name)
            ->update('cron_log', $data);

        unset($CI);
    }
}

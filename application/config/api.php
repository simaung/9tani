<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Payment Gateway DUITKU
$config['api_duitku']['dev']['code'] = 'D6162';
$config['api_duitku']['dev']['key'] = '5a6be57216d054b9db5b530150e0f88e';
$config['api_duitku']['dev']['url'] = 'https://sandbox.duitku.com/webapi/api/merchant/';

$config['api_duitku']['prod']['code'] = 'D2708';
$config['api_duitku']['prod']['key'] = 'ed28ddd23a32a851ff149eefcf42ccc8';
$config['api_duitku']['prod']['url'] = 'https://passport.duitku.com/webapi/api/merchant/';

// Shipping API
$config['api_shipping']['url'] = 'https://pro.rajaongkir.com/api/';
$config['api_shipping']['key'] = 'd9ad2f0889d6523f4e3fcfa36e1bb1a5';

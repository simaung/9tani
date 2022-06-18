<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Payment Gateway DUITKU
$config['api_duitku']['dev']['code'] = 'D6522';
$config['api_duitku']['dev']['key'] = '1fb6d42dc329218a8befbf8e1625e6c0';
$config['api_duitku']['dev']['url'] = 'https://sandbox.duitku.com/webapi/api/merchant/';

$config['api_duitku']['prod']['code'] = 'D4302';
$config['api_duitku']['prod']['key'] = 'a66333bac0422744517a5930e18b6775';
$config['api_duitku']['prod']['url'] = 'https://passport.duitku.com/webapi/api/merchant/';

$config['api_midtrans']['dev']['id'] = 'G051329030';
$config['api_midtrans']['dev']['client'] = 'SB-Mid-client-Dupvl-PooCuqYPgd';
$config['api_midtrans']['dev']['server'] = 'SB-Mid-server-PT94kelDw1MHFiBV_ziQyzuu';
$config['api_midtrans']['dev']['url'] = 'https://api.sandbox.midtrans.com/v2/charge';

$config['api_midtrans']['prod']['id'] = 'G051329030';
$config['api_midtrans']['prod']['client'] = 'Mid-client-khzxOABQ63XKssmv';
$config['api_midtrans']['prod']['server'] = 'Mid-server-ZiSY4x1nAMmd18ki5SgoYgj3';
$config['api_midtrans']['prod']['url'] = 'https://api.midtrans.com/v2/charge';

// Payment Gateway Ipaymu
$config['api_ipaymu']['dev']['va']  = '1179008218464240';
$config['api_ipaymu']['dev']['key'] = '154B66DE-8182-4AF0-AC14-5895C109FE03';
$config['api_ipaymu']['dev']['url'] = 'https://sandbox.ipaymu.com/api/';

$config['api_ipaymu']['prod']['va']  = '1179003576365593';
$config['api_ipaymu']['prod']['key'] = 'F3E5774C-74B1-4C54-A944-3B9D2A2589EA';
$config['api_ipaymu']['prod']['url'] = 'https://my.ipaymu.com/';

// Payment Gateway BigFlip
$config['api_bigflip']['prod']['token'] = '$2y$13$oRQ71dKFupLwlLJF6KwgcuvodgjRUVFQ18QnW491o28yULNtbp5iO';
$config['api_bigflip']['prod']['server'] = 'JDJ5JDEzJGlmd1hGbGVOTXNXU0N4bENGSmt4Q3VMWUx4L0puL0VsMnptQTBOeEV1RVFXYmxEeXczTVdP';
$config['api_bigflip']['prod']['url'] = 'https://big.flip.id/api/v2';

$config['api_bigflip']['dev']['token'] = '$2y$13$TTECOYM/zUkD.9/2/QI6fuCRuF9/ipbdhgadOcGjqjtKKhkEzFKQC';
$config['api_bigflip']['dev']['server'] = 'JDJ5JDEzJGw4WXhxaW9kR1lnUVUvcTdjcjhGR09RMEZOWlFQYm1DcW1SREtoS0J0Rks4TVhDSEtQWVg2';
$config['api_bigflip']['dev']['url'] = 'https://sandbox.flip.id/api/v2';

// Shipping API
$config['api_shipping']['url'] = 'https://pro.rajaongkir.com/api/';
$config['api_shipping']['key'] = 'd9ad2f0889d6523f4e3fcfa36e1bb1a5';

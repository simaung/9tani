<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// HTTP Response
// 1XX Informational
$lang['response'][100]['title'] 		  = 'Continue';
$lang['response'][100]['description'] = 'Melanjutkan proses.';

// 2XX Success
$lang['response'][200]['title'] 		  = 'OK';
$lang['response'][200]['description'] = 'Proses berhasil.';

// 3XX Redirection
$lang['response'][300]['title'] 		  = 'Multiple Choices';
$lang['response'][300]['description'] = 'Terdapat beberapa pilihan dari hasil.';

// 4XX Client Error
$lang['response'][400]['title'] 		  = 'Maaf';
$lang['response'][400]['description'] = 'Parameter permintaan tidak valid.';
$lang['response'][401]['title'] 		  = 'Unauthorized';
$lang['response'][401]['description'] = 'Dibutuhkan otorisasi terlebih dahulu.';
$lang['response'][403]['title'] 		  = 'Forbidden';
$lang['response'][403]['description'] = 'Dibutuhkan izin untuk melakukan ini.';
$lang['response'][404]['title'] 		  = 'Not Found';
$lang['response'][404]['description'] = 'Permintaan tidak ditemukan.';
$lang['response'][405]['title'] 		  = 'Method Not Allowed';
$lang['response'][405]['description'] = 'Metode permintaan tidak diizinkan.';
$lang['response'][480]['title'] 		  = 'Produk tidak tersedia';
$lang['response'][480]['description'] = 'Produk sembako hanya bisa di order sampai batas jam 3 sore.';
$lang['response'][498]['title'] 		  = 'Invalid Token';
$lang['response'][498]['description'] = 'Token kadaluarsa atau tidak valid.';
$lang['response'][499]['title'] 		  = 'Bad Request';
$lang['response'][499]['description'] = 'Token diperlukan.';
$lang['response'][496]['title'] 		  = 'Invalid old password';
$lang['response'][496]['description'] = 'Password lama salah.';
$lang['response'][497]['title'] 		  = 'Invalid referral';
$lang['response'][497]['description'] = 'Kode referral salah.';


// 5XX Server Error
$lang['response'][500]['title'] 		  = 'Internal Server Error';
$lang['response'][500]['description'] = 'Terdapat kesalahan dalam sistem.';
$lang['response'][503]['title'] 		  = 'Service Unavailable';
$lang['response'][503]['description'] = 'Layanan sementara tidak tersedia.';


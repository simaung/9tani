<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// HTTP Response
// 1XX Informational
$lang['response'][100]['title'] 		  = 'Continue';
$lang['response'][100]['description'] = 'Continue proccess.';

// 2XX Success
$lang['response'][200]['title'] 		  = 'OK';
$lang['response'][200]['description'] = 'Success.';

// 3XX Redirection
$lang['response'][300]['title'] 		  = 'Multiple Choices';
$lang['response'][300]['description'] = 'Multiple choices as results.';

// 4XX Client Error
$lang['response'][400]['title'] 		  = 'Bad Request';
$lang['response'][400]['description'] = 'Parameter not valid.';
$lang['response'][401]['title'] 		  = 'Unauthorized';
$lang['response'][401]['description'] = 'Need authorized first.';
$lang['response'][403]['title'] 		  = 'Forbidden';
$lang['response'][403]['description'] = 'Forbidden access.';
$lang['response'][404]['title'] 		  = 'Not Found';
$lang['response'][404]['description'] = 'Request not found.';
$lang['response'][405]['title'] 		  = 'Method Not Allowed';
$lang['response'][405]['description'] = 'Request method no allowed.';
$lang['response'][498]['title'] 		  = 'Invalid Token';
$lang['response'][498]['description'] = 'Token has expired or invalid value.';
$lang['response'][499]['title'] 		  = 'Bad request';
$lang['response'][499]['description'] = 'Parameter not valid.';
$lang['response'][496]['title'] 		  = 'Invalid old password';
$lang['response'][496]['description'] = 'Wrong old password.';
$lang['response'][497]['title'] 		  = 'Invalid referral';
$lang['response'][497]['description'] = 'wrong referral code.';

// 5XX Server Error
$lang['response'][500]['title'] 		  = 'Internal Server Error';
$lang['response'][500]['description'] = 'Error internal system.';
$lang['response'][503]['title'] 		  = 'Service Unavailable';
$lang['response'][503]['description'] = 'Service temporarily unvailable.';

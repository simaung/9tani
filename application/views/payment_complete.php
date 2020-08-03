<!DOCTYPE html>
<html lang="en">

<head>
  <?php
  $link = 'https://sembilantani.com/payment?';
  if ($order_detail->flag_device == '1') { ?>
    <meta http-equiv="refresh" content="0; URL='<?php echo $link.$data_redirect; ?>'" />
  <?php } ?>
  <meta charset="UTF-8">
  <title>Sembilantani Payment</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="HandheldFriendly" content="true">
  <link rel="shortcut icon" href="<?php echo $assets_url; ?>img/icon.png" type="image/x-icon">
  <link href="<?php echo $assets_url; ?>css/reset.css" rel="stylesheet">
  <link href="<?php echo $assets_url; ?>css/base.css" rel="stylesheet">
  <link href="<?php echo $assets_url; ?>css/payment.css" rel="stylesheet">
</head>

<body>
  <?php if ($order_detail->flag_device != '1') { ?>
    <div class="container">
      <img class="logo" src="<?php echo $assets_url; ?>img/logo.png" alt="" />
      <hr>
      <div>
        <p><?php echo $message; ?></p>
        <table>
          <tr>
            <td>Invoice Number</td>
            <td>
              <h3 style="color: #1A9A62;"><?php echo $order_detail->invoice_code; ?></h3>
            </td>
          </tr>
          <tr>
            <td>Payment Method</td>
            <td><?php echo $order_detail->description; ?></td>
          </tr>
          <tr>
            <td>Payment Amount</td>
            <td><?php echo number_format($order_detail->total_price + $order_detail->shipping_cost); ?></td>
          </tr>
          <tr>
            <td>Payment Status</td>
            <td><?php echo $order_detail->payment_status; ?></td>
          </tr>
        </table>
      </div>
    </div>
  <?php } ?>
</body>

</html>
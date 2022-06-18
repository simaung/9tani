<!DOCTYPE html>
<html lang="en">

<head>
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
  <div class="loader-wrapper">
    <div class="loader">
      <div class="lds-dual-ring"></div>
    </div>
  </div>
  <div class="container">
    <h3 class="title">Select Payment Method</h3>
    <ul class="channel-list">
      <?php foreach ($payment_channel as $channel) { ?>
        <li>
          <?php
          if ($channel['code'] == 'bank_transfer') {
            $url = base_url() . 'payment/transfer/?&invoice_code=' . $invoice_code . '&channel_id=' . $channel['id'];
          } else {
            if ($channel['provider'] == 'duitku') {
              $url = base_url() . 'payment/duitku_inquiry/?invoice_code=' . $invoice_code . '&channel_id=' . $channel['id'];
            } elseif ($channel['provider'] == 'ipaymu') {
              $url = base_url() . 'payment/ipaymu_inquiry/?invoice_code=' . $invoice_code . '&channel_id=' . $channel['id'];
            }
          }
          ?>
          <a href="<?php echo $url; ?>" onclick="showLoader();">
            <?php if (!empty($channel['icon'])) { ?>
              <img src="<?php echo $storage_url . 'payment/' . $channel['icon']; ?>" alt="">
            <?php } ?>
            <div class="label">
              <h4><?php echo $channel['name']; ?></h4>
              <p><?php echo $channel['description']; ?></p>
            </div>
          </a>
        </li>
      <?php } ?>
    </ul>
  </div>
  <script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script type="text/javascript">
    var showLoader = function() {
      $('.loader-wrapper').show();
    }
  </script>
</body>

</html>
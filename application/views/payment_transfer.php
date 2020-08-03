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
    <div class="loader"><div class="lds-dual-ring"></div></div>
  </div>
  <div class="container">
    <h3 class="title">Bank Transfer</h3>
    <div class="transfer-description">
      <p><?php echo lang('message_bank_transfer'); ?></p>
      <div>Silakan lakukan pembayaran disertai kode unik sesuai dengan nominal yang tertera ke akun dibawah ini.</div>
      <h4>Rp <?php echo number_format($amount, 0, ',', '.'); ?></h4>
    </div>
    <ul class="account-list">
      <?php foreach ($bank_account as $account) { ?>
        <li>
          <h4><?php echo $account['bank_name']; ?> <?php echo $account['account_number']; ?> </h4>
          <p><?php echo $account['account_name']; ?></p>
        </li>
      <?php } ?>
    </ul>
    <form style="margin-top: 20px;" method="POST" action="<?php echo current_url(); ?>" onsubmit="showLoader();">
        <input type="hidden" name="invoice_code" value="<?php echo $invoice_code; ?>">
        <input type="hidden" name="channel_id" value="<?php echo $channel_id; ?>">
        <input type="hidden" name="amount" value="<?php echo $amount; ?>">
      <button type="submit" class="button">Selesai</button>
      <div style="margin-top: 16px;"><a class="link" href="<?php echo base_url().'payment?invoice_code='.$invoice_code; ?>">Kembali</a></div>
    </form>
  </div>
  <script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script type="text/javascript">
    var showLoader = function()
    {
      $('.loader-wrapper').show();
    }
  </script>
</body>
</html>

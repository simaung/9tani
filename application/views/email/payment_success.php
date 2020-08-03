<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Sembilantani</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 0 30px 0;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border: 1px solid #cccccc;font-size:14px">
                    <tr>
                        <td style="padding: 10px 30px 10px 20px;">
                            <img src="<?php echo base_url(); ?>assets/img/logo.png" alt="sembilantani.com" width="80" style="display: block;" />
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 30px 20px 30px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="line-height: 32px;color: #666;font-size: 20px;" colspan="2"><b>Pembayaran Berhasil</b></td>
                                </tr>
                                <tr>
                                    <?php
                                    $time = explode(' ', $order->created_at);
                                    ?>
                                    <td style="color: #999;line-height: 1.6;" colspan="2">Pembayaran transaksi berhasil pada <?php echo set_date_format(date('Y-m-d')) . ', ' . date('H:i'); ?> WIB</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 18px;color: #666;" colspan="2"><b>Ringkasan Pembayaran</b></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="color: #999;line-height: 1.6;">Total Harga (<?php echo count($order_item); ?> Barang)</td>
                                    <td style="color: #999;line-height: 1.6;">Rp. <?php echo number_format($order->total_price, 0, ',', '.'); ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #999;line-height: 1.6;">Total Ongkos Kirim</td>
                                    <td style="color: #999;line-height: 1.6;">Rp. <?php echo number_format($order->shipping_cost, 0, ',', '.'); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <hr>
                                    </td>
                                    <td>
                                        <hr>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #666;"><b>Total Pembayaran</b></td>
                                    <td style="color: #666;"><b>Rp. <?php echo number_format($order->shipping_cost + $order->total_price, 0, ',', '.'); ?></b></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 18px;color: #666;" colspan="2"><b>Rincian Pesanan</b></td>
                                </tr>
                                <tr>
                                    <td>&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="color:#81dd76"><b><?php echo $order->invoice_code; ?></b></td>
                                </tr>
                                <?php foreach ($order_item as $row) { ?>
                                    <tr>
                                        <td style="color: #999;line-height: 1.8;"><?php echo $row['name'] . ' / ' . $row['unit']; ?></td>
                                        <td style="color: #999;line-height: 1.8;">Rp. <?php echo number_format($row['qty'] * $row['price'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #999;line-height: 1.8;"><?php echo $row['qty'] . ' x Rp.' . number_format($row['price'], 0, ',', '.'); ?></td>
                                    </tr>

                                    <tr style="line-height:0.2;font-size:14px">
                                        <td>&nbsp;</td>
                                    </tr>
                                <?php } ?>
                                <tr>
                                    <td style="color: #999;line-height: 1.8;">Ongkos Kirim</td>
                                    <td style="color: #999;line-height: 1.8;">Rp. <?php echo number_format($order->shipping_cost, 0, ',', '.'); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <hr>
                                    </td>
                                    <td>
                                        <hr>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #666;"><b>Total Pembayaran</b></td>
                                    <td style="color: #666;"><b>Rp. <?php echo number_format($order->shipping_cost + $order->total_price, 0, ',', '.'); ?></b></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse;">
                    <tr>
                        <td bgcolor="#3e1466" align="center" style="padding: 30px 30px 30px 30px;color: #81dd76; font-family: Arial, sans-serif; font-size: 14px;">
                            © <?php echo date('Y'); ?> sembilantani All Rights Reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
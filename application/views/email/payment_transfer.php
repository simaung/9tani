<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>sembilantani.com</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<body style="margin: 0; padding: 20px 20px 20px 20px;  background-color: #e6eaed">

    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: white">
        <tr>
            <td style="padding: 40px 30px 0 20px;" align="center">
                <img src="<?php echo base_url(); ?>assets/img/logo.png" alt="sembilantani.com" width="80" style="display: block;" />
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 30px 40px 30px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="color: #153643; font-family: Arial, sans-serif; font-size: 14px; line-height: 20px;">
                    <tr>
                        <td style="color:#a2a2a2">
                            <b>Dear, Customer</b>
                        </td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center">
                            Silakan lakukan pembayaran disertai kode unik sesuai dengan nominal yang tertera ke akun dibawah ini.
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:10px">
                            <div style="font-weight:bold;font-size:20px;background-color:#ffd69e;padding:15px 15px;width:120px;border-radius:10px">Rp <?php echo number_format($amount, 0, ',','.'); ?><div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            &nbsp;
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table border="1" cellpadding="10" cellspacing="0" width="100%">
                                <tr bgcolor="#3e1466" style="font-weight:bold;color:#ffffff" align="center">
                                    <td>Bank</td>
                                    <td>Nomor Rekening</td>
                                    <td>Nama Rekening</td>
                                </tr>
                                <?php foreach ($bank_account as $row) { ?>
                                    <tr>
                                        <td><b><?php echo $row['bank_name']; ?></b></td>
                                        <td><b><?php echo $row['account_number']; ?></b></td>
                                        <td><b><?php echo $row['account_name']; ?></b></td>
                                    </tr>
                                <?php } ?>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#3e1466" align="center" style="padding: 30px 30px 30px 30px;color: #ffffff; font-family: Arial, sans-serif; font-size: 14px;">
                © <?php echo date('Y'); ?> sembilantani All Rights Reserved.
            </td>
        </tr>
    </table>
</body>

</html>
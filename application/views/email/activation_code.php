<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Sembilan Tani</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="HandheldFriendly" content="true">
    <style>
        .container {
            position: relative;
            border-radius: 6px;
            width: calc(100% - 40px);
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            overflow: hidden;
            text-align: center;
            -webkit-box-shadow: 4px 4px 12px 0 #ddd;
            -moz-box-shadow: 4px 4px 12px 0 #ddd;
            -ms-box-shadow: 4px 4px 12px 0 #ddd;
            -o-box-shadow: 4px 4px 12px 0 #ddd;
            box-shadow: 4px 4px 12px 0 #ddd
        }

        .container>img.logo {
            height: 100px
        }

        hr {
            margin: 20px 0;
            padding: 0;
            border: none;
            border-top: 1px solid #eee
        }

        p {
            font-size: 14px;
            color: #333
        }

        p {
            margin-bottom: 20px
        }

        .button {
            border: none;
            outline: 0;
            display: inline-block;
            width: 300px;
            height: 44px;
            line-height: 44px;
            text-align: center;
            background: #3dc04c;
            color: #fff !important;
            border-radius: 22px;
            font-size: 14px;
            letter-spacing: 1px
        }
    </style>
</head>

<body>
    <div class="container">
        <img class="logo" src="https://admin.sembilankita.com/public/assets/img/sembilankita.svg" alt="" style="width: 200px;" />
        <hr>
        <?php if ($type == 'register') { ?>
            <p style="font-weight: bold;font-size: 20px;">Yuk, mulai aktivasi akun Sembilankita kamu.</p>
            <p>Sedikit lagi akunmu akan aktif. Cukup masukkan kode verifikasi di bawah untuk mengaktifkan akunmu.</p>
        <?php } else { ?>
            <p style="font-weight: bold;font-size: 20px;">Yuk, masuk ke akun Sembilankita kamu.</p>
            <p>Masukkan kode di bawah untuk masuk ke dalam akun sembilankita kamu.</p>
        <?php } ?>
        <div style="border: 1px solid #e5e7e9;padding: 14px 48px;display: inline-block;font-size: 24px;font-weight: bold;color: rgba(49,53,59,0.96);margin-bottom: 16px;border-radius: 8px;"><?php echo $activated_code; ?></div>
        <p>Kode di atas hanya berlaku 30 menit. Mohon jangan sebarkan kode ini ke siapapun, termasuk pihak yang mengatasnamakan Sembilankita.</p>
    </div>
</body>

</html>
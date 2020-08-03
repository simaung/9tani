<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Real Travel Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="HandheldFriendly" content="true">
    <link rel="shortcut icon" href="<?php echo $assets_url; ?>img/icon.png" type="image/x-icon">
    <link href="<?php echo $assets_url; ?>css/reset.css" rel="stylesheet">
    <link href="<?php echo $assets_url; ?>css/base.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <img src="<?php echo base_url(); ?>assets/img/logo.png" alt="sembilantani.com" width="80" style="display: block;margin-left: auto;margin-right: auto;" />
        <hr>
        <h3 class="title">Reset Password</h3>
        <p class="success" style="margin: 10px 0;display: none;"></p>
        <form id="reset-password" style="margin-top: 20px;" method="POST" action="<?php echo current_url(); ?>">
            <p class="error" style="margin: 10px 0;"></p>
            <div><input class="input" type="password" name="password" placeholder="Password"></div>
            <div style="margin-top: 10px;"><input class="input" type="password" name="password_confirm" placeholder="Password Confirmation"></div>
            <div style="margin-top: 20px;"><button type="submit" class="button">Reset Password</button></div>
        </form>
    </div>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script type="text/javascript">
        $(function() {
            $('#reset-password').submit(function(e) {
                e.preventDefault();

                submitData(this);
            });
        });


        var submitData = function(form) {
            var ajax = $.ajax({
                url: $(form).attr('action'),
                type: 'post',
                dataType: 'json',
                data: $(form).serialize(),
                beforeSend: function() {
                    $('.container p').html('');
                    $('#' + $(form).attr('id')).find(':submit').prop('disabled', true);
                },
                success: function(response) {
                    $('.container p').html(response.message);

                    if (response.code == 200) {
                        $('.container form').remove();
                        $('.container p.success').show();
                    }

                    $('#' + $(form).attr('id')).find(':submit').prop('disabled', false);
                },
                error: function(x, t, m) {
                    m = getXHRMessage(x, t, m);
                    $('.container p').html(m);
                    $('#' + $(form).attr('id')).find(':submit').prop('disabled', false);
                }
            });

            ajax = null;
            delete ajax;
        }

        var getXHRMessage = function(x, t, m) {
            m = x.statusText;
            if ((x.status == 200) && (t == 'parsererror')) m = 'Error';

            return m;
        }
    </script>
</body>

</html>
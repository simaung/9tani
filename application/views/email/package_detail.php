<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Real Travel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="HandheldFriendly" content="true">
	<style>
    a,abbr,acronym,address,applet,article,aside,audio,b,big,blockquote,body,canvas,caption,center,cite,code,dd,del,details,dfn,dialog,div,dl,dt,em,embed,fieldset,figcaption,figure,font,footer,form,h1,h2,h3,h4,h5,h6,header,hgroup,hr,html,i,iframe,img,ins,kbd,label,legend,li,main,mark,menu,meter,nav,object,ol,output,p,pre,progress,q,rp,rt,ruby,s,samp,section,small,span,strike,strong,sub,summary,sup,table,tbody,td,tfoot,th,thead,time,tr,tt,u,ul,var,video,xmp{border:0;margin:0;padding:0;font-size:100%}body,html{height:100%}article,aside,details,figcaption,figure,footer,header,hgroup,main,menu,nav,section{display:block}b,strong{font-weight:700}img{color:transparent;font-size:0;vertical-align:middle;-ms-interpolation-mode:bicubic}ol,ul{list-style:none}li{display:list-item}table{border-collapse:collapse;border-spacing:0}caption,td,th{font-weight:400;vertical-align:top;text-align:left}q{quotes:none}q:after,q:before{content:"";content:none}small,sub,sup{font-size:75%}sub,sup{line-height:0;position:relative;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}svg{overflow:hidden}

    body,html{margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background:#fff;color:#333}body{font-size:14px;line-height:1.6;color:#333}*{box-sizing:border-box}hr{margin:20px 0;padding:0;border:none;border-top:1px solid #eee}div.container{width:600px;margin:20px auto;border:1px solid #eee;border-radius:6px;overflow:hidden}div.logo-wrapper{padding:20px 0;text-align:center;border-bottom:1px solid #eee}img.logo{height:100px}li.item{padding:12px;border-top:1px solid #eee}li.item-no-border{padding:12px}h3.title{font-size:12px;font-weight:600;text-transform:uppercase;color:#1a9a62}img.airline-logo{display:inline-block;width:40px;height:40px;object-fit:cover}span.label{display:inline-block;padding:4px 12px;background:#eee;border-radius:6px;font-size:12px;font-weight:600}div.text-small{font-size:12px}div.text-gray{color:#999}span.green{color:#1a9a62}.html-content{margin-top:12px}.html-content *{margin-top:12px;font-size:12px!important}table#table_itinerary{margin-top:4px;margin-bottom:20px}table#table_itinerary *{font-size:12px!important}
  </style>
</head>
<body>
  <div class="container">
    <div class="logo-wrapper">
      <img class="logo" src="https://api.realtravel.co.id/assets/img/logo.png" alt="" />
    </div>
    <ul class="main-list">
      <li class="item-no-border">
        <h3 class="title">Nama Paket</h3>
        <h2><?php echo $package['name']; ?></h2>
      </li>
      <li class="item">
        <h3 class="title">Deskripsi</h3>
        <p><?php echo $package['description']; ?></p>
      </li>
      <li class="item">
        <h3 class="title">Harga</h3>
        <h4>Rp. <?php echo number_format($package['date'][0]['total_price'], 0, ',', '.'); ?>*</h4>
        <div style="margin-top: 6px;" class="text-small text-gray">*Acuan harga termurah paket terkait.</div>
      </li>
      <li class="item">
        <h3 class="title">Tanggal Keberangkatan</h3>
        <ul>
          <?php foreach ($package['date'] as $key => $date) { ?>
            <li><?php echo set_date_format($date['date_depart'], 'd F Y'); ?> - <?php echo set_date_format($date['date_return'], 'd F Y'); ?></li>
          <?php } ?>
        </ul>
      </li>
      <?php if ( ! empty($package['date'][0]['bundling']['airline'])) { ?>
        <?php foreach ($package['date'][0]['bundling']['airline'] as $key => $airline) { ?>
          <li class="item">
            <h3 class="title">Maskapai</h3>
            <div class="airline">
              <h4 class="name" style="margin-bottom: 12px;"><img src="<?php echo $airline['detail']['airline_logo']; ?>" class="airline-logo"> <?php echo $airline['detail']['airline_name']; ?></h4>
              <span class="label">Rute Keberangkatan</span>
              <div class="text-small" style="margin: 8px 0;">
                <?php echo $airline['detail']['depart_from_name']; ?> <span class="green">&#8594;</span>
                <?php if ( ! empty($airline['detail']['transit_depart_airport'])) { ?>
                <?php echo $airline['detail']['transit_depart_airport']; ?> <span class="green">&#8594;</span>
                <?php } ?>
                <?php echo $airline['detail']['depart_to_name']; ?>
              </div>
              <span class="label">Rute Kepulangan</span>
              <div class="text-small" style="margin-top: 8px;">
                <?php echo $airline['detail']['return_from_name']; ?> <span class="green">&#8594;</span>
                <?php if ( ! empty($airline['detail']['transit_return_airport'])) { ?>
                <?php echo $airline['detail']['transit_return_airport']; ?> <span class="green">&#8594;</span>
                <?php } ?>
                <?php echo $airline['detail']['return_to_name']; ?>
              </div>
            </div>
          </li>
        <?php } ?>
      <?php } ?>
      <?php if ( ! empty($package['date'][0]['bundling']['visa'])) { ?>
        <?php $visa = $package['date'][0]['bundling']['visa']; ?>
        <li class="item">
          <h3 class="title">Visa</h3>
          <p><?php echo $visa['detail']['name']; ?></p>
        </li>
      <?php } ?>
      <?php if ( ! empty($package['date'][0]['bundling']['bus'])) { ?>
        <?php $bus = $package['date'][0]['bundling']['bus']; ?>
        <li class="item">
          <h3 class="title">Transportasi</h3>
          <p><?php echo $bus['detail']['name']; ?> - Kapasitas <?php echo $bus['detail']['seat']; ?> kursi</p>
        </li>
      <?php } ?>
      <?php if ( ! empty($package['date'][0]['bundling']['hotel'])) { ?>
        <?php $hotel = $package['date'][0]['bundling']['hotel']; ?>
        <li class="item">
          <h3 class="title">Akomodasi</h3>
          <ul>
          <?php foreach ($hotel as $key => $value) { ?>
            <li>
              <h4 style="<?php echo (($key > 0) ? 'margin-top: 8px;' : ''); ?>">Hotel <?php echo $value['detail']['hotel_name']; ?> (<?php echo ucwords($value['detail']['hotel_location']); ?>)</h4>
              <div class="text-small" style="margin-top: 6px;"><?php echo $value['detail']['hotel_description']; ?></div>
            </li>
          <?php } ?>
          </ul>
        </li>
      <?php } ?>
      <?php if ( ! empty($package['date'][0]['bundling']['catering'])) { ?>
        <li class="item">
          <h3 class="title">Katering</h3>
          <ol style="list-style-type: decimal; margin-top: 12px; margin-left: 16px;">
            <?php foreach ($package['date'][0]['bundling']['catering'] as $key => $value) { ?>
              <li>
                <?php echo $value['detail']['name']; ?>
                (<?php echo ucwords($value['detail']['location']); ?>)
              </li>
            <?php } ?>
          </ol>
        </li>
      <?php } ?>
      <?php if ( ! empty($package['date'][0]['bundling']['instrument'])) { ?>
        <li class="item">
          <h3 class="title">Perlengkapan</h3>
          <ol style="list-style-type: decimal; margin-top: 12px; margin-left: 16px;">
            <?php foreach ($package['date'][0]['bundling']['instrument'] as $key => $value) { ?>
              <li><?php echo $value['detail']['name']; ?></li>
            <?php } ?>
          </ol>
        </li>
      <?php } ?>
      <?php if ( ! empty($package['date'][0]['bundling']['handling'])) { ?>
        <li class="item">
          <h3 class="title">Handling</h3>
          <ol style="list-style-type: decimal; margin-top: 12px; margin-left: 16px;">
            <?php foreach ($package['date'][0]['bundling']['handling'] as $key => $value) { ?>
              <li><?php echo $value['detail']['name']; ?></li>
            <?php } ?>
          </ol>
        </li>
      <?php } ?>
      <li class="item">
        <h3 class="title">Itinerary</h3>
        <div class="html-content">
          <?php echo html_entity_decode($package['itinerary'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </li>
    </ul>
  </div>
</body>
</html>

<!doctype html>
<html lang='<?=$lang?>'>
<head>
<meta charset='utf-8' name="viewport" content="width=device-width, initial-scale=1"/>
<title><?=get_title($title)?></title>
<?php if(isset($favicon)): ?><link rel='shortcut icon' href='<?=$favicon?>'/><?php endif; ?>
<?php foreach($stylesheets as $val): ?>
<link rel='stylesheet' type='text/css' href='<?=$val?>'/>
<?php endforeach; ?>
<?php if (isset($inlinestyle)): ?>
<style><?= $inlinestyle ?></style>
<?php endif; ?>
<!-- Bootstrap: Latest compiled and minified CSS -->
<link rel="stylesheet" href="css/bootstrap.min.css">
<!-- Bootstrap: Optional theme -->
<link rel="stylesheet" href="css/bootstrap-theme.min.css">
</head>
<body>
  <div id='wrapper'>
    <div id='header'><?= $header ?></div>
    <div id='main'>
		<?= generate_menu($menu, 'nav nav-tabs'); ?>
		<?= generate_breadcrumbs($breadcrumbs); ?>
		<?= $main ?>
	</div>
    <div id='footer'><?= $footer ?></div>
  </div>
<!-- jQuery -->
<script src="js/jquery-1.11.2.min.js"></script>
<!-- Bootstrap: Latest compiled and minified JavaScript -->
<script src="js/bootstrap.min.js"></script>
</body>
</html>


<?php

/**
 * Set the error reporting.
 *
 */
error_reporting(-1);              // Report all type of errors
ini_set('display_errors', 1);     // Display all errors 
ini_set('output_buffering', 0);   // Do not buffer outputs, write directly

/**
 * Define Yaf paths.
 *
 */
define('YAF_INSTALL_PATH', __DIR__ . '/..');
define('YAF_THEME_PATH', YAF_INSTALL_PATH . '/theme/render.php');

/**
 * Include bootstrapping functions.
 *
 */
include(YAF_INSTALL_PATH . '/src/bootstrap.php');
 
/**
 * Start the session.
 *
 */
session_name(preg_replace('/[^a-z\d]/i', '', __DIR__));
session_start();
 
/**
 * Create the Yaf variable.
 *
 */
$yaf = array();

/**
 * Site wide settings.
 *
 */
$yaf['lang']         = 'sv';
//$yaf['title_append'] = ' | Yaf en webbtemplate';

define('DB_USER', 'username');
define('DB_PASSWORD', 'password');

$db_host = '127.0.0.1';
$mysql_bin = '/Applications/XAMPP/bin/mysql';

$host = gethostname();
if (!strpos($host, "."))
	$host = $_SERVER['HTTP_HOST'];
$hostconfig = __DIR__ . '/config-' . $host . '.php';
if (file_exists($hostconfig)) {
    /** @noinspection PhpIncludeInspection */
    include($hostconfig);
}

$db_name = 'dbname';
 
$dbconfig = [
	'mysql_bin' => $mysql_bin,
	'host' => $db_host,
	'dbname' => $db_name,
	'dsn' => 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';',
	'username' => DB_USER,
	'password' => DB_PASSWORD,
	// 'prefix' => '',
	'driver_options' => [
		PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
	],
];
$yaf['database'] = $dbconfig;

/**
 * Theme related settings.
 *
 */
//$yaf['stylesheets'] = array('css/style.css');
//$yaf['favicon']    = 'favicon.ico';

$title_value = null;
$title_placeholder = null;
$_SESSION['title'] = null;
if (isset($_GET['title']))
	$_SESSION['title'] = $_GET['title'];
if (isset($_SESSION['title'])) {
	$title_value = 'value="' . $_SESSION['title'] . '"';
} else {
	$title_placeholder = 'placeholder="Titel"';
}

/**
 * Default header
 */
$yaf['header'] = <<<EOD
<span class='sitetitle'>YAF</span>
<span class='siteslogan'>Yet Another Framework</span>
EOD;

$yaf['breadcrumbs'] = array();
$yaf['breadcrumbs'][] = [
	'Hem' => 'index.php',
];

/**
 * Default footer
 */
$footers = [
	"Copyright (c) RM Rental Movies"
];

/**
 * Navigation settings.
 */
$yaf['menu'] = array(
	'items' => array(
		'index'     => array('text' => 'Hem',    'url' => 'index.php',       'class' => null),
	),
);

if (CUser::is_authenticated()) {
	$footers[] = '<a href="http://validator.w3.org/unicorn/check?ucn_uri=referer&amp;ucn_task=conformance">Unicorn </a>';
}

$footerstring = implode(" | ", $footers);

$yaf['footer'] = <<<EOD
<footer>
  <span class='sitefooter'>$footerstring</span>
</footer>
EOD;


<?php

include(__DIR__ . '/config.php');

$yaf['title'] = 'Visa Källkod';

$yaf['stylesheets'][] = 'css/source.css';

$source = new CSource(array('secure_dir' => '..', 'base_dir' => '..'));

$yaf['main'] = <<<EOD
<h1>Visa Källkod</h1>
{$source->View()}
EOD;

/** @noinspection PhpIncludeInspection */
include(YAF_THEME_PATH);


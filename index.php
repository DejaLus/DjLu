<?php

// load framework
$f3 = require('lib/base.php');
$f3->config('config.ini');
date_default_timezone_set($f3->get("TIMEZONE"));
setlocale(LC_NUMERIC, 'en_US');

$f3->run();

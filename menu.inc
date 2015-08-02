<?php

use diversen\conf;
use diversen\lang;

$allow = conf::getModuleIni('gittobook_allow');
$_MODULE_MENU[] = array(
    'title' => lang::translate('Your repos'),
    'url' => '/gittobook/repos',
    'allow' => $allow,
    'auth' => 'user'
);

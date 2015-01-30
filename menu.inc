<?php

$allow = config::getModuleIni('gitbook_allow');
$_MODULE_MENU[] = array(
    'title' => lang::translate('Your repos'),
    'url' => '/gitbook/repos',
    'allow' => $allow
);

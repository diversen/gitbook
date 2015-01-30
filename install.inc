<?php

$_INSTALL['VERSION'] = 2.71; 

$title = lang::translate('Content', null, array ('no_translate' => true)); 
$url = '/gitbook/index';

$_INSTALL['MAIN_MENU_ITEM'] = array (
    'title' => $title,
    'url' => $url,
    'module_name' => 'gitbook',
    'parent' => 0,
    'weight' => 2,
);

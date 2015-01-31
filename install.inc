<?php

$_INSTALL['VERSION'] = 3.01; 

$title = lang::translate('Books', null, array ('no_translate' => true)); 
$url = '/gitbook/index';

$_INSTALL['MAIN_MENU_ITEM'] = array (
    'title' => $title,
    'url' => $url,
    'module_name' => 'gitbook',
    'parent' => 0,
    'weight' => 2,
);

$_INSTALL['ROUTES'][] = array ('#/read/[0-9]#'=>
    array ('method' => 'gitbook::read',
           'module' => 'gitbook',
           'controller' => '/gitbook/read')
);


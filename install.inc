<?php

use diversen\lang;

$title = lang::translate('Books', null, array ('no_translate' => true)); 
$url = '/gittobook/index';

$_INSTALL['MAIN_MENU_ITEM'] = array (
    'title' => $title,
    'url' => $url,
    'module_name' => 'gittobook',
    'parent' => 0,
    'weight' => 2,
);

$_INSTALL['ROUTES'][] = array ('#/books/[0-9]#'=>
    array ('method' => 'gittobook::booksAction'),

);

$_INSTALL['ROUTES'][] = array ('#/downloads/[0-9]#'=>
    array ('method' => 'gittobook::downloadAction',
           'module' => 'gittobook',
           'controller' => '/gittobook/downloads'),

);
$_INSTALL['PUBLIC_CLONE_URL'] = "git://github.com/diversen/gittobook.git";
$_INSTALL['PRIVATE_CLONE_URL'] = 'git@github.com:diversen/gittobook.git';

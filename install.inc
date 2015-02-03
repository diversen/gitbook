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

$_INSTALL['ROUTES'][] = array ('#/books/[0-9]#'=>
    array ('method' => 'gitbook::booksAction',
           'module' => 'gitbook',
           'controller' => '/gitbook/books')
);

$_INSTALL['PUBLIC_CLONE_URL'] = "git://github.com/diversen/gitbook.git";
$_INSTALL['PRIVATE_CLONE_URL'] = 'git@github.com:diversen/gitbook.git';

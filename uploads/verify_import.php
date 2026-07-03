<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_id('importtest');
session_start();
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_FILES['csv_file'] = [
    'name' => 'test_import.csv',
    'type' => 'text/csv',
    'tmp_name' => __DIR__ . '/test_import.csv',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize(__DIR__ . '/test_import.csv')
];
$_POST = [];

include __DIR__ . '/../tools/import_products.php';

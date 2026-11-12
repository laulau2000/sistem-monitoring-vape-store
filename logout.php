<?php
require_once __DIR__ . '/includes/functions.php';

$_SESSION = [];
session_destroy();
session_start();
flash_set('info', 'Anda telah keluar dari sistem.');
redirect('login.php');

<?php

require_once __DIR__ . '/session_boot.php';
admin_secure_session_start();

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;

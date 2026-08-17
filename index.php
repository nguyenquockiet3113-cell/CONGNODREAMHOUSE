<?php
require_once __DIR__ . '/config/config.php';
redirect(!empty($_SESSION['user']) ? '/dashboard.php' : '/login.php');

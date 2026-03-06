<?php
require_once 'auth.php';

auth_logout();
header('Location: login.php');
exit;


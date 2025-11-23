<?php
// logout.php
require __DIR__ . '/config.php';

session_unset();
session_destroy();

header('Location: index.php');
exit;

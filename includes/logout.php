<?php
session_start();
session_unset();
session_destroy();
header('Location: ' . dirname(dirname($_SERVER['PHP_SELF'])) . '/index.php');
exit;


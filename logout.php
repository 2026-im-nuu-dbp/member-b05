<?php

require 'auth.php';

//銷毀伺服器端的 Session 記憶
session_destroy();


header('Location: login.php');
exit;
?>

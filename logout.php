<?php
session_start();
session_destroy();
header("Content-type: application/json");
echo json_encode(["success" => true]);
exit;
?>
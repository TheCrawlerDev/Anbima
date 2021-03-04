<?php

error_reporting(0);

require_once('Anbima.php');

header('Content-Type: application/json');

$anbima = new Anbima($_GET['cpf'],$_GET['nome']);

echo json_encode($anbima->certificacoes);

?>

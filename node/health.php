<?php
	include ("../connect.php");
	include ("healthlib.php");
	$database = Connection();

	$id = isset($_GET['id']) ? $_GET['id'] : false;
	$layout = empty($_GET['layout']) ? 'table' : $_GET['layout'];

	health($id, $layout);

?>

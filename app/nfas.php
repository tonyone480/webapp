<?php
require __DIR__ . '/../webapp_for_sapi.php';
new class extends webapp
{
	function __construct()
	{
		parent::__construct(new sapi);
	}
	// function get_f(string $hash)
	// {
	// 	$this->print($hash);
	// }
};
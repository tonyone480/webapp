<?php
require 'webapp.php';
final class sapi implements webapp_sapi
{
	function request_ip():string
	{
		return '127.0.0.1';
	}
	function request_header(string $name):?string
	{
		return $_SERVER[$name] ?? NULL;
	}
	function request_method():string
	{
		return $_SERVER['argv'][1] ?? 'GET';
	}
	function request_query():string
	{
		return substr($_SERVER['argv'][2] ?? '?', 1);
	}
	function request_cookie(string $name):?string
	{
		return $_COOKIE[$name] ?? NULL;
	}
	function request_content():string
	{
		return file_get_contents('php://input');
	}
	function request_formdata(bool $uploadedfile):array
	{
		return [];
	}
	function response_sent():bool
	{
		return headers_sent();
	}
	function response_status(int $code):void
	{
		http_response_code($code);
	}
	function response_header(string $value):void
	{
		header($value);
	}
	function response_cookie(string ...$values):void
	{
		setcookie(...$values);
	}
	function response_content(string $data):void
	{
		file_put_contents('php://output', $data);
	}
	function response_sendfile(string $filename):bool
	{
		return !$this->response_header("X-Sendfile: {$filename}");
	}
}
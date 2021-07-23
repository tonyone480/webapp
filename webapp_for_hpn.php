<?php
require 'webapp.php';
abstract class sapi extends hpn_http_server implements webapp_sapi
{
	function request_ip():string
	{
	}
	function request_header(string $name):?string
	{
	}
	function request_method():string
	{
	}
	function request_query():string
	{
	}
	function request_cookie(string $name):?string
	{
	}
	function request_content():string
	{
	}
	function request_formdata(bool $uploadedfile):array
	{
	}
	function response_sent():bool
	{
	}
	function response_status(int $code):void
	{
	}
	function response_header(string $value):void
	{
	}
	function response_cookie(string ...$values):void
	{
	}
	function response_content(string $data):void
	{
	}
	function response_sendfile(string $filename):bool
	{
	}
}
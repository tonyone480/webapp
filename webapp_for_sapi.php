<?php
require 'webapp.php';
final class sapi implements webapp_sapi
{
	function request_ip():string
	{
		return $_SERVER['REMOTE_ADDR'];
	}
	function request_header(string $name):?string
	{
		return match($key = strtr(strtoupper($name), '-', '_'))
		{
			'CONTENT_TYPE',
			'CONTENT_LENGTH' => $_SERVER[$key] ?? NULL,
			default => $_SERVER["HTTP_{$key}"] ?? NULL
		};
	}
	function request_method():string
	{
		return $_SERVER['REQUEST_METHOD'];
	}
	function request_query():string
	{
		return $_SERVER['QUERY_STRING'] ?? '';
	}
	function request_cookie(string $name):?string
	{
		return $_COOKIE[$name] ?? NULL;
	}
	function request_content():string
	{
		return file_get_contents('php://input');
	}
	function request_formdata(?array &$uploadedfiles):array
	{
		$uploadedfiles = [];
		foreach ($_FILES as $name => $info)
		{
			$files = [];
			foreach ($info as $type => $values)
			{
				if (is_array($values))
				{
					foreach ($values as $index => $value)
					{
						$files[$index][$type] = $value;
					}
					continue;
				}
				$files[0][$type] = $values;
			}
			$uploadedfiles[$name] = [];
			foreach ($files as $file)
			{
				if ($file['error'] === UPLOAD_ERR_OK)
				{
					$uploadedfiles[$name][] = $file;
				}
			}
		}
		return $_POST;
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
}
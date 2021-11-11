<?php
require 'webapp.php';
final class io implements webapp_io
{
	function request_ip():string
	{
		return $_SERVER['REMOTE_ADDR'];
	}
	function request_header(string $name):?string
	{
		return match($key = strtr(strtoupper($name), '-', '_'))
		{
			'AUTHORIZATION' => $_SERVER['PHP_AUTH_DIGEST'] ?? NULL,
			'CONTENT_TYPE', 'CONTENT_LENGTH' => $_SERVER[$key] ?? NULL,
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
	function request_formdata():array
	{
		return $_POST;
	}
	function request_uploadedfile():array
	{
		$uploadedfiles = [];
		foreach ($_FILES as $name => $file)
		{
			$files = [];
			foreach ($file as $type => $info)
			{
				if (is_array($info))
				{
					foreach ($info as $index => $value)
					{
						$files[$index][$type] = $value;
					}
					continue;
				}
				$files[0][$type] = $info;
			}
			$uploadedfiles[$name] = [];
			foreach ($files as $file)
			{
				if ($file['error'] === UPLOAD_ERR_OK)
				{
					$uploadedfiles[$name][] = [
						'file' => $file['tmp_name'],
						'size' => $file['size'],
						'mime' => $file['type'],
						'name' => $file['name'],
						'type' => preg_match('/\.(\w{1,256})$/i', $file['name'], $suffix) ? strtolower($suffix[1]) : 'unknown'
					];
				}
			}
		}
		return $uploadedfiles;
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
	function response_content(string $data):bool
	{
		return file_put_contents('php://output', $data) !== FALSE;
	}
	function response_sendfile(string $filename):bool
	{
		return PHP_SAPI === 'apache2handler'
			&& in_array('mod_xsendfile', apache_get_modules(), TRUE)
			? $this->response_header("X-Sendfile: {$filename}") === NULL
			: copy($filename, 'php://output');
	}
}
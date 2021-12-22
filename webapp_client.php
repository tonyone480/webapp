<?php
declare(strict_types=1);
class webapp_client implements Stringable, Countable
{
	public array $errors = [];
	private $buffer, $filter, $client, $context;
	function __construct(public readonly string $socket)
	{
		$this->buffer = fopen('php://memory', 'r+');
		$this->context = stream_context_create(['ssl' => [
			'verify_peer' => FALSE,
			'verify_peer_name' => FALSE,
			'allow_self_signed' => TRUE]]);
		$this->reconnect();
	}
	function __destruct()
	{
		fclose($this->buffer);
	}
	// function __get(string $name):mixed
	// {
	// 	return match ($name)
	// 	{
	// 		'metadata' =>		stream_get_meta_data($this->stream),
	// 		'remote_name' =>	stream_socket_get_name($this->stream, TRUE),
	// 		'local_name' =>		stream_socket_get_name($this->stream, FALSE),
	// 		'is_lockable' =>	stream_supports_lock($this->stream),
	// 		'is_local' =>		stream_is_local($this->stream),
	// 		'is_tty' =>			stream_isatty($this->stream),
	// 		default =>			NULL
	// 	};
	// }
	//缓冲区内容
	function __toString():string
	{
		return stream_get_contents($this->buffer, ($length = count($this)) && rewind($this->buffer) ? $length : 0);
	}
	//缓冲区大小
	function count():int
	{
		return ftell($this->buffer);
	}
	//调试
	function debug(int $filter = STREAM_FILTER_WRITE/* STREAM_FILTER_ALL */):void
	{
		if (in_array('webapp_client_debug', stream_get_filters(), TRUE) === FALSE)
		{
			stream_filter_register('webapp_client_debug', 'webapp_client_debug');
			stream_filter_append($this->client, 'webapp_client_debug', $filter);
		}
	}
	//重连
	function reconnect():bool
	{
		if ($this->client = @stream_socket_client($this->socket, $erron, $error, 4, context: $this->context))
		{
			return TRUE;
		}
		$this->errors[] = "{$erron}: {$error}";
		return FALSE;
	}
	//关闭
	function shutdown(int $mode = STREAM_SHUT_WR):bool
	{
		return stream_socket_shutdown($this->client, $mode);
	}
	//模式（请使用默认阻塞模式，别问为什么，除非你知道在做什么）
	function blocking(bool $mode):bool
	{
		return stream_set_blocking($this->client, $mode);
	}
	//超时
	function timeout(int $seconds):bool
	{
		return stream_set_timeout($this->client, $seconds);
	}
	//缓冲区过滤
	function filter(...$params):bool
	{
		return $this->remove() && is_resource($this->filter = stream_filter_append($this->buffer, ...$params));
	}
	//缓冲区过滤移除
	function remove():bool
	{
		if (is_resource($this->filter))
		{
			if (stream_filter_remove($this->filter) === FALSE)
			{
				return FALSE;
			}
			$this->filter = NULL;
		}
		return TRUE;
	}
	//缓冲区清空
	function clear():bool
	{
		return $this->remove() && rewind($this->buffer);
	}
	//缓冲区输入
	function echo(string $data):bool
	{
		return fwrite($this->buffer, $data) === strlen($data);
	}
	//缓冲区格式化输入
	function printf(string $format, mixed ...$values):bool
	{
		return $this->echo(sprintf($format, ...$values));
	}
	//缓冲区从
	function from($stream, int $length = NULL):bool
	{
		return is_int($copied = stream_copy_to_stream(
			is_resource($stream) ? $stream : fopen($stream, 'r'),
			$this->buffer, $length)) && ($length === NULL || $copied === $length);
	}
	//缓冲区拉取
	function pull(int $length = NULL):bool
	{
		return $this->from($this->client, $length);
	}
	//缓冲区到
	function to($stream):bool
	{
		$length = count($this);
		return stream_copy_to_stream($this->buffer,
			is_resource($stream) ? $stream : fopen($stream, 'w'),
			rewind($this->buffer) ? $length : 0) === $length;
	}
	//缓冲区推送
	function push():bool
	{
		return $this->to($this->client);
	}



	//窥视数据
	function peek(?string &$output, int $length):bool
	{
		return is_string($output = @stream_socket_recvfrom($this->client, $length, STREAM_PEEK)) && strlen($output) === $length;
	}
	//读取
	function read(?string &$output, int $length = NULL):int
	{
		return is_string($output = @stream_get_contents($this->client, $length)) ? strlen($output) : 0;
	}
	//读取一行
	function readline(?string &$output = NULL, int $length = 65535, string $ending = "\r\n"):bool
	{
		return is_string($output = @stream_get_line($this->client, $length, $ending));
	}
	//读取至流
	function readinto($stream, int $length = NULL):int
	{
		return (int)@stream_copy_to_stream($this->client, $stream, $length);
	}








	//读取剩余内容
	function readfull(int $length = -1):string
	{
		return stream_get_contents($this->stream, $length);
	}


	//发送
	function send(string $data):bool
	{
		return @fwrite($this->client, $data) === strlen($data);
	}
}
class webapp_client_debug extends php_user_filter
{
	//注意：过滤流在内部读取时只能过滤一个队列，这是一个BUG？
	function filter($in, $out, &$consumed, $closing):int
	{
		echo "\r\n", $consumed === NULL
			? '>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>'
			: '<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<',
			"\r\n";
		while ($bucket = stream_bucket_make_writeable($in))
		{
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
			echo quoted_printable_encode($bucket->data);
		}
		return PSFS_PASS_ON;
	}
}
// class webapp_client_smtp extends webapp_client
// {
// 	function __construct(string $host)
// 	{
// 		$parse = static::parseurl($host, 25);
// 		parent::__construct($parse[0]);
// 		//$this->debug(STREAM_FILTER_ALL);
// 		if ($this->readstat() === 220)
// 		{
// 			if (count($parse) > 3)
// 			{
// 				$this->send("EHLO {$parse[1]}\r\n");
// 				if ($this->readstat($status) && strpos(join($status), 'AUTH LOGIN'))
// 				{
// 					$this->send("AUTH LOGIN\r\n");
// 					$this->readstat($status);
// 					$this->send(base64_encode($parse[3]) . "\r\n");
// 					$this->readstat($status);
// 					$this->send(base64_encode($parse[4]) . "\r\n");
// 					$this->readstat($status);
// 					print_r($status);
// 				}
// 			}
// 			else
// 			{
// 				$this->send("HELO {$parse[1]}\r\n");
// 			}
// 			// if ($this->readstat() === 220) 
// 			// {

// 			// }
// 		}
		
// 	}
// 	private function readstat(?array &$status = []):int
// 	{
// 		for (;$this->readline($content); $status[] = $content);
// 		return intval(end($status));
// 	}
// 	function mailto():bool
// 	{
// 	}
// }
class webapp_client_http extends webapp_client implements ArrayAccess
{
	public string $path;
	public int $reconnect = 0;
	private array $headers = [
		'Host' => '*',
		'Connection' => 'keep-alive',
		'User-Agent' => 'WebApp/Client',
		'Accept' => 'application/json,application/xml,text/html;q=0.9, */*;q=0.8',
		'Accept-Encoding' => 'gzip, deflate',
		'Accept-Language' => 'zh-CN, zh;q=0.9, en;q=0.8'
	], $cookies = [], $responses = [];
	function __construct(public readonly string $url, private array &$referers = [])
	{
		[$socket, $this->headers['Host'], $this->path] = $parse = static::parseurl($url);
		$this->referers[$socket] = $this;
		if (count($parse) > 3)
		{
			$this->headers['Authorization'] = 'Basic ' . base64_encode(join(':', array_slice($parse, 3)));
		}
		parent::__construct($socket);
	}
	function offsetExists(mixed $offset):bool
	{
		return array_key_exists($offset, $this->responses);
	}
	function offsetGet(mixed $offset):mixed
	{
		return $this->responses[$offset] ?? NULL;
	}
	function offsetSet(mixed $offset, mixed $value):void
	{
		$this->headers[$offset] = $value;
	}
	function offsetUnset(mixed $offset):void
	{
		unset($this->headers[$offset]);
	}
	function headers(array $replace):static
	{
		foreach ($replace as $name => $value)
		{
			$this->headers[$name] = $value;
		}
		return $this;
	}
	function cookies(string|array $replace):static
	{
		foreach (is_string($replace) && preg_match_all('/(\w+)\=([^;]+)/', $replace, $cookies, PREG_SET_ORDER)
			? array_map('urldecode', array_column($cookies, 2, 1)) : $replace as $name => $value) {
			$this->cookies[$name] = $value;
		}
		return $this;
	}
	private function form($data, string $name, string $contents, string $filename):bool
	{
		if (is_array($data))
		{
			foreach ($data as $key => $value)
			{
				if ($this->form($value, $key, $contents, $filename) === FALSE)
				{
					return FALSE;
				}
			}
			return TRUE;
		}
		return match (TRUE)
		{
			is_null($data), is_scalar($data) => $this->printf($contents, $name) && $this->echo((string)$data),
			is_resource($data) => $this->printf($filename, $name, basename(stream_get_meta_data($data)['uri'])) && $this->from($data),
			//$data instanceof self => $this->from($data),
			default => FALSE
		} && $this->echo("\r\n");
	}
	function request(string $method, string $path, $data = NULL, string $type = NULL):bool
	{
		$this->responses = [];
		$request = ["{$method} {$path} HTTP/1.1"];
		foreach ($this->headers as $name => $value)
		{
			$request[] = "{$name}: {$value}";
		}
		if ($this->cookies)
		{
			$request[] = 'Cookie: '. http_build_query($this->cookies, arg_separator: '; ');
		}
		do
		{
			if ($this->clear() === FALSE)
			{
				break;
			}
			if ($data)
			{
				if ((is_string($data) ? $this->echo($data) : match ($type ??= 'application/x-www-form-urlencoded') {
					'application/x-www-form-urlencoded' => $this->echo(http_build_query($data)),
					'multipart/form-data' => $this->form($data, '',
						$contents = '--' . join("\r\n", [
							$boundary = uniqid('----WebAppFormBoundarys'),
							'Content-Disposition: form-data; name="%s"', "\r\n"]),
						substr($contents, 0, -4) . "; filename=\"%s\"\r\nContent-Type: application/octet-stream\r\n\r\n",
						$type .= "; boundary={$boundary}")
						&& $this->echo("--{$boundary}--"),
					'application/json' => $this->echo(json_encode($data, JSON_UNESCAPED_UNICODE)),
					'application/xml' => $this->echo($data instanceof DOMDocument ? $data->saveXML() : (string)$data),
					'application/octet-stream' => $this->from($data),
					default => FALSE}) === FALSE
					|| is_resource($buffer = fopen('php://memory', 'r+')) === FALSE
					|| $this->to($buffer) === FALSE) {
					break;
				}
				$request[] = "Content-Type: {$type}";
				$request[] = 'Content-Length: ' . ftell($buffer);
			}
			$reconnect = $this->reconnect;
			$request = join($request[] = "\r\n", $request);
			$buffer ??= NULL;
			do
			{
				do
				{
					if ($this->send($request) === FALSE
						|| (count($this) === 0 || $this->push()) === FALSE
						|| $this->readline($status) === FALSE) {
						continue;
					}
					$responses = [$status];


					break 2;
				} while (--$reconnect > -1 && $this->reconnect());
				break 2;
			} while (0);
			

			if ($this->send(join($request[] = "\r\n", $request)) === FALSE
				|| (count($this) === 0 || $this->push()) === FALSE
				|| $this->readline($status) === FALSE) {
				break;
			}
			$responses = [$status];
			do
			{
				if ($this->readline($header) === FALSE)
				{
					break 2;
				}
				if ($offset = strpos($header, ': '))
				{
					$key = ucwords(substr($header, 0, $offset), '-');
					$value = substr($header, $offset + 2);
					if ($key !== 'Set-Cookie')
					{
						$responses[$key] = $value;
						continue;
					}
					if (preg_match('/^([^=]+)=([^;]+)(?:; expires=([^;]+))?/', $value, $cookies))
					{
						if (array_key_exists(3, $cookies) && strtotime($cookies[3]) < time())
						{
							unset($this->cookies[$cookies[1]]);
							continue;
						}
						$this->cookies[$cookies[1]] = $cookies[2];
					}
				}
			} while ($header);
			if ($this->clear() === FALSE)
			{
				break;
			}
			if (array_key_exists('Content-Encoding', $responses))
			{
				if (match ($responses['Content-Encoding']) {
					'gzip' => $this->filter('zlib.inflate', STREAM_FILTER_WRITE, ['window' => 31]),
					'deflate' => $this->filter('zlib.inflate', STREAM_FILTER_WRITE),
					default => FALSE} === FALSE) {
					break;
				};
			}
			if (array_key_exists('Content-Length', $responses))
			{
				if ($this->pull(intval($responses['Content-Length'])) === FALSE)
				{
					break;
				}
			}
			else
			{
				if (array_key_exists('Transfer-Encoding', $responses) && $responses['Transfer-Encoding'] === 'chunked')
				{
					
					do
					{
						if ($this->readline($size, 8) === FALSE)
						{
							break 2;
						}
						if ($length = hexdec($size))
						{
							if ($this->pull($length) === FALSE)
							{
								break 2;
							}
						}
						if ($this->readline($line, 2) === FALSE)
						{
							break 2;
						}
					} while ($length);
				}
			}
			if ($this->remove() === FALSE)
			{
				break;
			}
			$this->responses = $responses;
			return TRUE;
		} while (0);
		$this->clear();
		return FALSE;
	}
	function type():string
	{
		return is_string($type = $this['Content-Type'])
			? strtolower(is_int($offset = strpos($type, ';')) ? substr($type, 0, $offset) : $type)
			: 'application/octet-stream';
	}
	function goto(string|array $entry, Closure $success, ...$params)
	{

		// if (is_string($entry))
		// {
		// 	$entry = ['method' => 'GET', 'url' => $entry];
		// }
		$url = is_string($entry) ? $entry : $entry['url'] ??= $this->path;

		
		do
		{
			if (preg_match('/^https?\:\/\//i', $url) === 0)
			{
				$client = $this;
				$client->path = $url;
				break;
			}
			[$socket,, $path] = static::parseurl($url);
			if (array_key_exists($socket, $this->referers))
			{
				$client = $this->referers[$socket];
				$client->path = $path;
				break;
			}
			$client = new static($url, $this->referers);
			$client['User-Agent'] = $this->headers['User-Agent'];
		} while (0);
		$client['Referer'] = $this->url;
		if ($client->request('GET', $client->path))
		{
			$success->call($client, ...$params);
		}
	}
	function content():string|array|webapp_xml
	{
		return match ($this->type())
		{
			'application/json' => json_decode((string)$this, TRUE),
			'application/xml' => new webapp_xml((string)$this),
			//'html' => webapp_document::html($this->bufferdata()),
			default => (string)$this
		};
	}
	function download(string $filename):bool
	{
		//$this->to()
		return FALSE;
	}

// 	// function http(string $method, string $url, /*Closure|int*/$detect = 4, $data = NULL, bool $multipart = FALSE)
// 	// {
// 	// 	do
// 	// 	{
// 	// 		if (preg_match('/^https?\:\/\//i', $url) === 0)
// 	// 		{
// 	// 			$host = $this;
// 	// 			$host->path = $url;
// 	// 			break;
// 	// 		}
// 	// 		$urlinfo = static::parseurl($url);
// 	// 		if (array_key_exists($urlinfo[1], $this->referers))
// 	// 		{
// 	// 			$host = $this->referers[$urlinfo[1]];
// 	// 			$host->path = $urlinfo[2];
// 	// 			break;
// 	// 		}
// 	// 		$host = new static($url, $this->referers);
// 	// 		$host->headers(['Referer' => $this->url]);
// 	// 	} while (0);
// 	// 	if (is_callable($detect))
// 	// 	{
// 	// 		for ($count = 0;;)
// 	// 		{
// 	// 			$host->responses = $host->request($method, $host->path, $data, $multipart);
// 	// 			if (($retval = $detect->call($host, ++$count)) === TRUE)
// 	// 			{
// 	// 				$host->reconnect();
// 	// 				continue;
// 	// 			}
// 	// 			return $retval;
// 	// 		}
// 	// 	}
// 	// 	while (empty($host->responses = $host->request($method, $host->path, $data, $multipart)))
// 	// 	{
// 	// 		if (--$detect < 1)
// 	// 		{
// 	// 			file_put_contents('php://stderr', "Disconnected({$url})\n");
// 	// 			break;
// 	// 		}
// 	// 		file_put_contents('php://stderr', "Reconnecting({$url})\n");
// 	// 		$host->reconnect();
// 	// 	}
// 	// 	return $host;
// 	// }
	static function parseurl(string $url):array
	{
		$port = 0;
		if (is_array($parse = parse_url($url)) && array_key_exists('scheme', $parse) && array_key_exists('host', $parse))
		{
			switch (strtolower($parse['scheme']))
			{
			 	case 'https':
					$port = 443;
				case 'wss':
					$parse['scheme'] = 'ssl';
					break;
				case 'http':
					$port = 80;
				case 'ws':
					$parse['scheme'] = 'tcp';
					break;
			}
			$host = array_key_exists('port', $parse) ? $parse['host'] .= ":{$parse['port']}" : "{$parse['host']}:{$port}";
			$result = ["{$parse['scheme']}://{$host}", $parse['host'], $parse['path'] ?? '/'];
			if (array_key_exists('query', $parse))
			{
				$result[2] .= "?{$parse['query']}";
			}
			if (array_key_exists('user', $parse))
			{
				$result[] = $parse['user'];
				if (array_key_exists('pass', $parse))
				{
					$result[] = $parse['pass'];
				}
			}
			return $result;
		}
		return ["tcp://127.0.0.1:{$port}", '127.0.0.1', '/'];
	}
}
// class webapp_client_websocket extends webapp_client_http
// {
// 	/*
// 	WebSocket
// 	Frame format:
// 	0                   1                   2                   3
// 	0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
// 	+-+-+-+-+-------+-+-------------+-------------------------------+
// 	|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
// 	|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
// 	|N|V|V|V|       |S|             |   (if payload len==126/127)   |
// 	| |1|2|3|       |K|             |                               |
// 	+-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
// 	|     Extended payload length continued, if payload len == 127  |
// 	+ - - - - - - - - - - - - - - - +-------------------------------+
// 	|                               |Masking-key, if MASK set to 1  |
// 	+-------------------------------+-------------------------------+
// 	| Masking-key (continued)       |          Payload Data         |
// 	+-------------------------------- - - - - - - - - - - - - - - - +
// 	:                     Payload Data continued ...                :
// 	+ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
// 	|                     Payload Data continued ...                |
// 	+---------------------------------------------------------------+
// 	*/
// 	function __construct(string $url)
// 	{
// 		parent::__construct($url);
// 		($responses = $this->headers([
// 			//测试地址 ws://82.157.123.54:9010/ajaxchattest, ws://121.40.165.18:8800
// 			'Origin' => 'http://coolaf.com',
// 			'Upgrade' => 'websocket',
// 			'Connection' => 'Upgrade',
// 			'Sec-WebSocket-Version' => 13,
// 			'Sec-WebSocket-Key' => base64_encode(random_bytes(16))
// 		])->request('GET', $this->path))
// 		&& $responses[0] === 'HTTP/1.1 101 Switching Protocols'
// 		&& array_key_exists('Sec-WebSocket-Accept', $responses)
// 		&& base64_encode(sha1("{$this->headers['Sec-WebSocket-Key']}258EAFA5-E914-47DA-95CA-C5AB0DC85B11", TRUE)) === $responses['Sec-WebSocket-Accept'];
// 		print_r($responses);
// 	}
// 	function readfhi():array
// 	{
// 		if (strlen($data = $this->readfull(2)) === 2)
// 		{
// 			extract(unpack('Cb0/Cb1', $data));
// 			$hi = [
// 				'fin' => $b0 >> 7,
// 				'rsv' => $b0 >> 4 & 0x07,
// 				'opcode' => $b0 & 0x0f,
// 				'mask' => [],
// 				'length' => $b1 & 0x7f
// 			];
// 			if ($hi['length'] > 125)
// 			{
// 				$hi['length'] = hexdec(bin2hex($this->readfull($hi['length'] === 126 ? 2 : 8)));
// 			}
// 			if ($b1 >> 7)
// 			{
// 				$hi['mask'] = array_values(unpack('Cb0/Cb1/Cb2/Cb3', $this->readfull(4)));
// 			}
// 			return $hi;
// 		}
// 		return [];
// 	}
// 	function packfhi(int $length, int $opcode = 1, bool $fin = TRUE, int $rsv = 0, string $mask = NULL):string
// 	{
// 		$format = 'CC';
// 		$values = [$fin << 7 | ($rsv & 0x07 << 4) | ($opcode & 0x0f)];
// 		if ($length < 126)
// 		{
// 			$values[] = $length;
// 		}
// 		else
// 		{
// 			if ($length < 65536)
// 			{
// 				$format .= 'n';
// 				$values[] = 126;
// 			}
// 			else
// 			{
// 				$format .= 'J';
// 				$values[] = 127;
// 			}
// 			$values[] = $length;
// 		}
// 		if (strlen($mask) > 3)
// 		{
// 			$format .= 'a4';
// 			$values[] = $mask;
// 		}
// 		return pack($format, ...$values);
// 	}
// 	function readframe(?array &$hi = NULL):?string
// 	{
// 		if (count($hi = $this->readfhi()) && strlen($contents = $this->readfull($hi['length'])) === $hi['length'])
// 		{
// 			if ($mask = $hi['mask'])
// 			{
// 				$length = strlen($contents);
// 				for ($i = 0; $i < $length; ++$i)
// 				{
// 					$contents[$i] = chr(ord($contents[$i]) ^ $mask[$i % 4]);
// 				}
// 			}
// 			return $contents;
// 		}
// 		return NULL;
// 	}
// 	function sendframe(string $content, int $opcode = 1, bool $fin = TRUE, int $rsv = 0, string $mask = NULL):bool
// 	{
// 		return $this->send($this->packfhi(strlen($content), $opcode, $fin, $rsv, $mask)) && $this->send($content);
// 	}
// }
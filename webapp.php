<?php
declare(strict_types=1);
require 'webapp_client.php';
require 'webapp_dom.php';
require 'webapp_echo.php';
require 'webapp_image.php';
require 'webapp_mysql.php';
interface webapp_io
{
	function request_ip():string;
	function request_header(string $name):?string;
	function request_method():string;
	function request_query():string;
	function request_cookie(string $name):?string;
	function request_content():string;
	function request_formdata():array;
	function request_uploadedfile():array;
	function response_sent():bool;
	function response_status(int $code):void;
	function response_header(string $value):void;
	function response_cookie(string ...$values):void;
	function response_content(string $data):bool;
	function response_sendfile(string $filename):bool;
}
abstract class webapp implements ArrayAccess, Stringable
{
	const version = '4.7a', key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
	private array $errors = [], $headers = [], $cookies = [], $configs, $uploadedfiles;
	protected static array $interfaces = [];
	static function __callStatic(string $name, array $arguments):mixed
	{
		return (self::$interfaces[$name] ??= require __DIR__ . "/lib/{$name}/interface.php")(...$arguments);
	}
	static function res(string $filename = NULL):string
	{
		return "/webapp/res/{$filename}";
	}
	static function random(int $length):string
	{
		return random_bytes($length);
	}
	static function time(int $offset = 0):int
	{
		return time() + $offset;
	}
	static function time33(string $data):int
	{
		for ($hash = 5381, $i = strlen($data); $i;)
		{
			$hash = (($hash << 5) + $hash) + ord($data[--$i]) & 0x0fffffffffffffff;
		}
		return $hash;
	}
	static function hash(string $data, bool $care = FALSE):string
	{
		for ($code = static::time33($data), $hash = '', [$i, $n, $b] = $care ? [10, 6, 63] : [12, 5, 31]; $i;)
		{
			$hash .= self::key[$code >> --$i * $n & $b];
		}
		return $hash;
	}
	static function iphex(string $ip):string
	{
		return str_pad(bin2hex(inet_pton($ip)), 32, '0', STR_PAD_LEFT);
	}
	static function hexip(string $hex):string
	{
		return inet_ntop(hex2bin($hex));
	}
	static function url64_encode(string $data):string
	{
		for ($i = 0, $length = strlen($data), $buffer = ''; $i < $length;)
		{
			$value = ord($data[$i++]) << 16;
			$buffer .= self::key[$value >> 18 & 63];
			if ($i < $length)
			{
				$value |= ord($data[$i++]) << 8;
				$buffer .= self::key[$value >> 12 & 63];
				if ($i < $length)
				{
					$value |= ord($data[$i++]);
					$buffer .= self::key[$value >> 6 & 63];
					$buffer .= self::key[$value & 63];
					continue;
				}
				$buffer .= self::key[$value >> 6 & 63];
				break;
			}
			$buffer .= self::key[$value >> 12 & 63];
			break;
		}
		return $buffer;
	}
	static function url64_decode(string $data):?string
	{
		do
		{
			if (rtrim($data, self::key))
			{
				break;
			}
			for ($i = 0, $length = strlen($data), $buffer = ''; $i < $length;)
			{
				$value = strpos(self::key, $data[$i++]) << 18;
				if ($i < $length)
				{
					$value |= strpos(self::key, $data[$i++]) << 12;
					$buffer .= chr($value >> 16 & 255);
					if ($i < $length)
					{
						$value |= strpos(self::key, $data[$i++]) << 6;
						$buffer .= chr($value >> 8 & 255);
						if ($i < $length)
						{
							$buffer .= chr($value | strpos(self::key, $data[$i++]) & 255);
						}
					}
					continue;
				}
				break 2;
			}
			return $buffer;
		} while (0);
		return NULL;
	}
	static function encrypt(?string $data):?string
	{
		return is_string($data) && is_string($binary = openssl_encrypt($data, 'aes-128-gcm', static::key, OPENSSL_RAW_DATA, md5(static::key, TRUE), $tag)) ? static::url64_encode($tag . $binary) : NULL;
	}
	static function decrypt(?string $data):?string
	{
		return is_string($data) && strlen($data) > 20
			&& is_string($binary = static::url64_decode($data))
			&& is_string($result = openssl_decrypt(substr($binary, 16), 'aes-128-gcm', static::key, OPENSSL_RAW_DATA, md5(static::key, TRUE), substr($binary, 0, 16))) ? $result : NULL;
	}
	static function signature(string $username, string $password, string $additional = NULL):?string
	{
		return static::encrypt(pack('VCCa*', static::time(), strlen($username), strlen($password), $username . $password . $additional));
	}
	static function authorize(?string $signature, callable $authenticate):bool
	{
		return is_string($data = static::decrypt($signature))
			&& strlen($data) > 5
			&& extract(unpack('Vsigntime/C2length', $data)) === 3
			&& strlen($data) > 5 + $length1 + $length2
			&& is_array($acc = unpack("a{$length1}uid/a{$length2}pwd/a*add", $data, 6))
			&& $authenticate($acc['uid'], $acc['pwd'], $signtime, $acc['add']);
	}
	static function captcha_random(int $length, int $expire):?string
	{
		$random = static::random($length * 3);
		for ($i = 0; $i < $length; ++$i)
		{
			$random[$i] = chr((ord($random[$i]) % 26) + 65);
		}
		return static::encrypt(pack('VCa*', static::time($expire), $length, $random));
	}
	static function captcha_result(?string $random):?array
	{
		if (is_string($binary = static::decrypt($random))
			&& strlen($binary) > 4
			&& extract(unpack('Vexpire/Clength', $binary)) === 2
			&& strlen($binary) > 4 + $length * 3
			&& is_array($values = unpack("a{$length}code/c{$length}size/c{$length}angle", $binary, 5))) {
			for ($result = [$expire, '', [], []], $i = 0; $i < $length;)
			{
				$result[1] .= $values['code'][$i++];
				$result[2][] = $values['size' . $i];
				$result[3][] = $values['angle' . $i];
			}
			return $result;
		}
		return NULL;
	}
	static function captcha_verify(string $random, string $answer):bool
	{
		return is_array($result = static::captcha_result($random)) && $result[0] > static::time() && $result[1] === strtoupper($answer);
	}




	// static function debugtime(?float &$time = 0):float
	// {
	// 	return $time = microtime(TRUE) - $time;
	// }
	// static function splitchar(string $content):array
	// {
	// 	return preg_match_all('/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/', $content, $pattern) === FALSE ? [] : $pattern[0];
	// }
	function __construct(private webapp_io $io, array $config = [])
	{
		$this->webapp = $this;
		$this->configs = $config + [
			//Request
			'request_method'	=> in_array($method = strtolower($io->request_method()), ['get', 'post', 'put', 'delete'], TRUE) ? $method : 'get',
			'request_query'		=> $io->request_query(),
			//Application
			//'app_rootdir'		=> __DIR__,
			'app_resroot'		=> '/webapp/res',
			'app_charset'		=> 'utf-8',
			'app_mapping'		=> 'webapp_mapping_',
			'app_index'			=> 'home',
			'app_entry'			=> 'index',
			//Admin
			'admin_username'	=> 'admin',
			'admin_password'	=> 'nimda',
			'admin_cookie'		=> 'webapp',
			'admin_expire'		=> 604800,
			//MySQL
			'mysql_host'		=> 'p:127.0.0.1:3306',
			'mysql_user'		=> 'root',
			'mysql_password'	=> '',
			'mysql_database'	=> 'webapp',
			'mysql_maptable'	=> 'webapp_maptable_',
			'mysql_charset'		=> 'utf8mb4',//latin1
			//Captcha
			'captcha_echo'		=> TRUE,
			'captcha_unit'		=> 4,
			'captcha_expire'	=> 99,
			'captcha_params'	=> [210, 86, __DIR__ . '/res/fonts/ArchitectsDaughter_R.ttf', 28],
			//QRCode
			'qrcode_echo'		=> TRUE,
			'qrcode_ecc'		=> 0,
			'qrcode_size'		=> 4,
			'qrcode_maxdata'	=> 256,
			//Misc
			'smtp_host'			=> 'tcp://user:pass@localhost',
			'copy_webapp'		=> 'Web Application v' . self::version,
			'gzip_level'		=> -1
		];
		if (preg_match('/^\w+(?=\/([\-\w]*))?/', $this['request_query'], $entry))
		{
			[$this['app_index'], $this['app_entry']] = [...$entry, $this['app_entry']];
		}
		[$this['app_mapping'], $this['app_index'], $this['app_entry']]
			= method_exists($this, $index = "{$this['request_method']}_{$this['app_index']}")
			? [$this, $index, array_slice($entry, 1)]
			: [$this['app_mapping'] . $this['app_index'], strtr("{$this['request_method']}_{$this['app_entry']}", '-', '_'), []];
	}
	function __destruct()
	{
		do
		{
			if (method_exists($this['app_mapping'], $this['app_index']))
			{
				$method = new ReflectionMethod($this['app_mapping'], $this['app_index']);
				do
				{
					if (preg_match_all('/\,(\w+)(?:\:([\%\+\-\.\/\=\w]+))?/', $this['request_query'], $params, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL))
					{
						$parameters = array_column($params, 2, 1);
						foreach (array_slice($method->getParameters(), intval($this['app_mapping'] === $this)) as $parameter)
						{
							if (array_key_exists($parameter->name, $parameters))
							{
								$this['app_entry'][$parameter->name] = match ((string)$parameter->getType())
								{
									'int' => intval($parameters[$parameter->name]),
									'float' => floatval($parameters[$parameter->name]),
									//'string' => urldecode($parameters[$parameter->name]),
									default => $parameters[$parameter->name]
								};
								continue;
							}
							if ($parameter->isOptional() === FALSE)
							{
								break 2;
							}
						}
					}
					if ($method->isPublic()
						&& ($method->isUserDefined() || $this['app_mapping'] instanceof Closure)
						&& $method->getNumberOfRequiredParameters() <= count($this['app_entry'])) {
						$status = $method->invoke($reflex = $this->app(), ...$this['app_entry']);
						$object = property_exists($this, 'app') ? $this->app : $reflex;
						if ($object !== $this && method_exists($object, '__toString'))
						{
							$this->print((string)$object);
						}
						break 2;
					}
				} while (0);
			}
			$status = 404;
		} while (0);
		if ($this->io->response_sent() === FALSE)
		{
			if (is_int($status))
			{
				$this->io->response_status($status);
			}
			foreach ($this->cookies as $values)
			{
				$this->io->response_cookie(...$values);
			}
			foreach ($this->headers as $name => $value)
			{
				$this->io->response_header("{$name}: {$value}");
			}
			if (property_exists($this, 'buffer'))
			{
				if ($this['gzip_level']
					&& stripos($this->request_header('Accept-Encoding'), 'gzip') !== FALSE
					&& stream_filter_append($this->buffer, 'zlib.deflate', STREAM_FILTER_READ, ['level' => $this['gzip_level'], 'window' => 31, 'memory' => 9])) {
					$this->io->response_header('Content-Encoding: gzip');
				}
				$this->io->response_content((string)$this);
				unset($this->buffer);
			}
		}
	}
	function __toString():string
	{
		return rewind($this->buffer) ? stream_get_contents($this->buffer) : join(PHP_EOL, $this->errors);
	}
	// function __debugInfo():array
	// {
	// 	return $this->errors;
	// }
	// function __call(string $name, array $params):mixed
	// {

	// 	var_dump(method_exists($this->app, $name));
	// 	//return method_exists($this->app, $name) ? $this->app->{$name}(...$params) : throw new error;
	// }
	function __get(string $name):mixed
	{
		if ($this->offsetExists($name))
		{
			return $this[$name];
		}
		if (method_exists($this, $name))
		{
			$method = new ReflectionMethod($this, $name);
			if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0)
			{
				return $this->{$name} = $method->invoke($this);
			}
		}
		return property_exists($this->app, $name) ? $this->app->{$name} : throw new error;
	}
	final function __invoke(object $object, string $errors = 'errors'):object
	{
		$object->webapp = $this;
		if ($object instanceof ArrayAccess)
		{
			$object[$errors] = &$this->errors;
		}
		else
		{
			$object->{$errors} = &$this->errors;
		}
		return $object;
	}
	final function offsetExists(mixed $key):bool
	{
		return array_key_exists($key, $this->configs);
	}
	final function &offsetGet(mixed $key):mixed
	{
		return $this->configs[$key];
	}
	final function offsetSet(mixed $key, mixed $value):void
	{
		$this->configs[$key] = $value;
	}
	final function offsetUnset(mixed $key):void
	{
		unset($this->configs[$key]);
	}
	final function buffer():mixed
	{
		return fopen('php://memory', 'r+');
	}
	function print(string $data):int
	{
		return fwrite($this->buffer, $data);
	}
	function printf(string $format, string ...$params):int
	{
		return fprintf($this->buffer, $format, ...$params);
	}
	function println(string $data):int
	{
		return $this->printf("%s\n", $data);
	}
	function putcsv(array $values, string $delimiter = ',', string $enclosure = '"'):int
	{
		return fputcsv($this->buffer, $values, $delimiter, $enclosure);
	}
	function app(string $classname = NULL, mixed ...$params):object
	{
		return is_string($classname)
			? $this->app = new $classname($this, ...$params)
			: (is_object($this['app_mapping'])
				? $this['app_mapping']
				: $this['app_mapping'] = new $this['app_mapping']($this, ...$params));
	}


	function admin(?string $signature = NULL):bool
	{
		return static::authorize(func_num_args() ? $signature : $this->request_cookie($this['admin_cookie']),
			fn(string $username, string $password, int $signtime):bool =>
				$signtime > static::time(-$this['admin_expire'])
				&& $username === $this['admin_username']
				&& $password === $this['admin_password']);
	}
	function authorization(Closure $authenticate = NULL):bool
	{
		return $authenticate
			? static::authorize($this->request_header('Authorization'), $authenticate)
			: $this->admin($this->request_header('Authorization'));
	}


	//---------------------



	//----------------
	function http(string $url, int $timeout = 4):webapp_client_http
	{
		return $this(new webapp_client_http($url, $timeout))->headers([
			'Authorization' => 'Digest ' . $this->signature($this['admin_username'], $this['admin_password']),
			'User-Agent' => 'WebApp/' . self::version
		]);
		$client = new webapp_client_http($url);
		if ($client->errors)
		{
			array_push($this->errors, ...$client->errors);
		}
		return $this($client->headers(['User-Agent' => 'WebApp/' . self::version]));
	}
	function xml(mixed ...$params):webapp_xml
	{
		if ($params)
		{
			try
			{
				libxml_use_internal_errors(TRUE);
				return new webapp_xml(...$params);
			}
			catch (Throwable $error)
			{
				$this->errors[] = $error->getMessage();
				libxml_use_internal_errors(FALSE);
			}
		}
		return new webapp_xml("<?xml version='1.0' encoding='{$this['app_charset']}'?><webapp/>");
	}
	// function formdata(array|webapp_html $node = NULL, string $action = NULL):array|webapp_html_form
	// {
	// 	if (is_array($node))
	// 	{
	// 		$form = new webapp_html_form($this);
	// 		foreach ($node as $name => $attr)
	// 		{
	// 			$form->field($name, ...is_array($attr) ? [$attr['type'], $attr] : [$attr]);
	// 		}
	// 		return $form->fetch() ?? [];
	// 	}
	// 	return new webapp_html_form($this, $node, $action);
	// }

	function mysql():webapp_mysql
	{
		$mysql = new webapp_mysql($this['mysql_host'], $this['mysql_user'], $this['mysql_password'], $this['mysql_database'], $this['mysql_maptable']);
		if ($mysql->connect_errno)
		{
			$this->errors[] = $mysql->connect_error;
		}
		else
		{
			$mysql->set_charset($this['mysql_charset']);
		}
		return $this($mysql);
	}
	function sqlite():webapp_sqlite{}
	function redis():webapp_redis{}

	function break(callable $invoke, mixed ...$params):void
	{
		$this['app_mapping'] = Closure::fromCallable($invoke)->bindTo($this);
		$this['app_index'] = '__invoke';
		$this['app_entry'] = $params;
	}
	//request
	function request_ip():string
	{
		return $this->io->request_ip();
	}
	function request_query(string $name):?string
	{
		return preg_match('/^\w+$/', $name) && preg_match('/\,' . $name . '\:([\%\+\-\.\/\=\w]+)/', $this['request_query'], $query) ? $query[1] : NULL;
	}
	function request_cond(string $name = 'cond'):array
	{
		$cond = [];
		preg_match_all('/(\w+\.(?:eq|ne|gt|ge|lt|le|lk|nl|in|ni))(?:\.([^\/]*))?/', $this->request_query($name), $values, PREG_SET_ORDER);
		foreach ($values as $value)
		{
			$cond[$value[1]] = array_key_exists(2, $value) ? urldecode($value[2]) : NULL;
		}
		return $cond;
	}
	function request_replace(string $name, bool $append = FALSE):string
	{
		return '?'. (preg_match('/^\w+$/', $name) ? preg_replace('/\,'. $name .'\:(?:[\%\+\-\.\/\=\w]+)/', '', $this['request_query']) : $this['request_query']) . ($append ? ",{$name}:" : '');
	}
	function request_cookie(string $name):?string
	{
		return $this->io->request_cookie($name);
	}
	function request_cookie_decrypt(string $name):?string
	{
		return static::decrypt($this->request_cookie($name));
	}
	function request_header(string $name):?string
	{
		return $this->io->request_header($name);
	}
	function request_device():string
	{
		return $this->request_header('User-Agent') ?? 'Unknown';
	}
	function request_referer():?string
	{
		return $this->request_header('Referer');
	}
	function request_content(string $format = NULL):array|string|webapp_xml
	{
		return match($format ?? strtolower(strpos($type = $this->request_header('Content-Type'), ';') === FALSE ? $type : strstr($type, ';', TRUE)))
		{
			'multipart/form-data',
			'application/x-www-form-urlencoded' => $this->io->request_formdata(),
			'application/json' => json_decode($this->io->request_content(), TRUE),
			'application/xml' => $this->xml($this->io->request_content()),
			default => $this->io->request_content()
		};
	}
	function request_uploadedfile(string $name, int $maximum = 1):ArrayObject
	{
		if (array_key_exists($name, $this->uploadedfiles ??= $this->io->request_uploadedfile()) === FALSE || is_array($this->uploadedfiles[$name]))
		{
			$uploadedfiles = [];
			if (array_key_exists($name, $this->uploadedfiles))
			{
				foreach ($this->uploadedfiles[$name] as $uploadedfile)
				{
					$uploadedfiles[$this->hash_time33(hash_file('haval160,4', $uploadedfile['file'], TRUE))] = $uploadedfile;
					if (count($uploadedfiles) === $maximum)
					{
						break;
					}
				}
			}
			$this->uploadedfiles[$name] = new class($uploadedfiles) extends ArrayObject implements Stringable
			{
				function __toString():string
				{
					return join(',', $this->column('file'));
				}
				function column(string $key):array
				{
					return array_column($this->getArrayCopy(), $key);
				}
				function size():int
				{
					return array_sum($this->column('size'));
				}
				// function open(string $hash = NULL):mixed
				// {
				// 	return fopen($hash === NULL ? $this : $this[$hash]['file'], 'r');
				// }
				// function content(string $hash = NULL):string
				// {
				// 	return file_get_contents($hash === NULL ? $this : $this[$hash]['file']);
				// }
				function moveto(string $filename):array
				{
					$success = [];
					$date = array_combine(['date', 'year', 'month', 'day', 'week', 'yday', 'time', 'hours', 'minutes', 'seconds'], explode(' ', date('Ymd Y m d w z His H i s')));
					foreach ($this as $hash => $info)
					{
						if ((is_dir($rootdir = dirname($file = preg_replace_callback('/\{([a-z]+)(?:\,(-?\d+)(?:\,(-?\d+))?)?\}/i', fn(array $format):string => match($format[1])
						{
							'hash' => count($format) > 2 ? substr($hash, ...array_slice($format, 2)) : $hash,
							'name', 'type' => $info[$format[1]],
							default => $date[$format[1]] ?? $format[0]
						}, $filename))) || mkdir($rootdir, recursive: TRUE)) && move_uploaded_file($this[$hash]['file'], $file)) {
							$this[$hash]['file'] = $file;
							$success[$hash] = $this[$hash];
						}
					}
					return $success;
				}
				// function detect(string $mime):bool
				// {
				// 	foreach ($this as $files)
				// 	{
				// 		//感觉在不久的将来这里需要改
				// 		if (preg_match('/^(' . str_replace(['/', '*', ','], ['\\/', '.*', '|'], $mime) . ')$/', $files['type']) === 0)
				// 		{
				// 			return FALSE;
				// 		}
				// 	}
				// 	return TRUE;
				// }
			};
		}
		return $this->uploadedfiles[$name];
	}
	//response
	function response_status(int $code):void
	{
		$this->break(fn():int => $code);
	}
	function response_cookie(string $name, ?string $value = NULL, int $expire = 0, string $path = '', string $domain = '', bool $secure = FALSE, bool $httponly = FALSE):void
	{
		$cookie = func_get_args();
		$cookie[1] ??= '';
		$this->cookies[] = $cookie;
	}
	function response_cookie_encrypt(string $name, ?string $value = NULL, int $expire = 0, string $path = '', string $domain = '', bool $secure = FALSE, bool $httponly = FALSE):void
	{
		$cookie = func_get_args();
		$cookie[1] = static::encrypt($cookie[1] ?? NULL) ?? '';
		$this->cookies[] = $cookie;
	}
	function response_header(string $name, string $value):void
	{
		//$this->headers[ucwords($name, '-')] = $value;
		$this->headers[$name] = $value;
	}
	function response_location(string $url):void
	{
		$this->response_header('Location', $url);
	}
	function response_refresh(int $second = 0, string $url = NULL):void
	{
		$this->response_header('Refresh', $url === NULL ? (string)$second : "{$second}; url={$url}");
	}
	function response_cache_control(string $command):void
	{
		$this->response_header('Cache-Control', $command);
	}
	function response_content_type(string $mime):void
	{
		$this->response_header('Content-Type', $mime);
	}
	function response_content_download(string $basename):void
	{
		$this->response_content_type('application/force-download');
		$this->response_header('Content-Disposition', 'attachment; filename=' . urlencode($basename));
	}
	function response_sendfile(string $filename):bool
	{
		return $this->io->response_sendfile($filename);
	}
	//append function
	final function init_admin(webapp_io $io, array $config = []):bool
	{
		self::__construct($io, $config);
		if ($this['app_mapping'] === $this && in_array($this['app_index'], ['get_captcha', 'get_qrcode', 'get_scss'], TRUE)) return TRUE;
		if ($this->admin) return FALSE;
		if ($this['request_method'] === 'post')
		{
			$this->app('webapp_echo_json', ['errors' => &$this->errors, 'signature' => NULL]);
			if ($input = webapp_echo_html::form_sign_in($this))
			{
				if ($this->admin($signature = $this->signature($input['username'], $input['password'])))
				{
					$this->response_refresh(0);
					$this->response_cookie($this['admin_cookie'], $this->app['signature'] = $signature);
				}
				else
				{
					$this->app['errors'][] = 'Sign in failed';
				}
			}
		}
		else
		{
			webapp_echo_html::form_sign_in($this->app('webapp_echo_html')->xml->body->article->section);
			$this->app->title('Sign In Admin');
		}
		$this->response_status(200);
		return TRUE;
	}
	function get_captcha(string $random = NULL)
	{
		if ($this['captcha_echo'])
		{
			if ($result = static::captcha_result($random))
			{
				$this->response_content_type('image/jpeg');
				webapp_image::captcha($result, ...$this['captcha_params'])->jpeg($this->buffer);
				return;
			}
			if ($random = static::captcha_random($this['captcha_unit'], $this['captcha_expire']))
			{
				$this->response_content_type("text/plain; charset={$this['app_charset']}");
				$this->print($random);
				return;
			}
			return 500;
		}
		return 404;
	}
	function get_qrcode(string $encode)
	{
		if ($this['qrcode_echo'] && is_string($decode = $this->url64_decode($encode)) && strlen($decode) < $this['qrcode_maxdata'])
		{
			$this->response_content_type('image/png');
			webapp_image::qrcode(static::qrcode($decode, $this['qrcode_ecc']), $this['qrcode_size'])->png($this->buffer);
			return;
		}
		return 404;
	}
	//这个函数在不久的将来会被移除
	function get_home()
	{
		$this->app('webapp_echo_html')->header['style'] = 'font-size:2rem';
		$this->app->header->text('Welcome in WebApp Framework');
	}
	function get_scss(string $filename)
	{
		if (file_exists($input = __DIR__ . "/res/ps/{$filename}.scss"))
		{
			$this->response_content_type('text/css');
			$this->response_cache_control('no-cache');
			if (filemtime($input) > filemtime($output = __DIR__ . "/res/ps/{$filename}.css"))
			{
				$scss = static::scss();
				$scss->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
				file_put_contents($output, $scss->compile(file_get_contents($input)));
			}
			$this->response_sendfile($output);
			return;
		}
		return 404;
	}
}
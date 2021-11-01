<?php
require 'webapp_connect.php';
require 'webapp_dom.php';
require 'webapp_echo.php';
require 'webapp_html.php';
require 'webapp_image.php';
require 'webapp_mysql.php';
interface webapp_sapi
{
	function request_ip():string;
	function request_header(string $name):?string;
	function request_method():string;
	function request_query():string;
	function request_cookie(string $name):?string;
	function request_content():string;
	function request_formdata(bool $uploadedfile):array;
	function response_sent():bool;
	function response_status(int $code):void;
	function response_header(string $value):void;
	function response_cookie(string ...$values):void;
	function response_content(string $data):void;
	function response_sendfile(string $filename):bool;
}
abstract class webapp implements ArrayAccess, Stringable
{
	const version = '4.4a', key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
	private array $errors = [], $headers = [], $cookies = [], $configs, $uploadedfiles;
	function __construct(private webapp_sapi $sapi, array $config = [])
	{
		$this->webapp = $this;
		$this->configs = $config + [
			//Request
			'request_method'	=> in_array($method = strtolower($sapi->request_method()), ['get', 'post', 'put', 'delete'], TRUE) ? $method : 'get',
			'request_query'		=> $sapi->request_query(),
			//Application
			// 'app_rootdir'		=> __DIR__,
			// 'app_locales'		=> __DIR__ . '/lib/local/en.php',
			'app_library'		=> __DIR__ . '/lib',
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
			'captcha_params'	=> [210, 86, __DIR__ . '/files/fonts/ArchitectsDaughter_R.ttf', 28],
			'captcha_expire'	=> 99,
			//QRCode
			'qrcode_echo'		=> TRUE,
			'qrcode_ecc'		=> 0,
			'qrcode_size'		=> 4,
			'qrcode_maxdata'	=> 256,
			//Misc
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
		return $this;
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
						foreach (array_slice($method->getParameters(), $this['app_mapping'] === $this) as $parameter)
						{
							if (array_key_exists($parameter->name, $parameters))
							{
								$this['app_entry'][$parameter->name] = match ((string)$parameter->getType())
								{
									'int' => intval($parameters[$parameter->name]),
									'float' => floatval($parameters[$parameter->name]),
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
					if ($method->isPublic() && $method->isUserDefined() && $method->getNumberOfRequiredParameters() <= count($this['app_entry']))
					{
						$status = $method->invoke($reflex = $this->app(), ...$this['app_entry']);
						$object = property_exists($this, 'app') ? $this->app : $reflex;
						if ($object !== $this && method_exists($object, '__toString'))
						{
							$this->print($object);
						}
						break 2;
					}
				} while (0);
			}
			$status = 404;
		} while (0);
		if ($this->sapi->response_sent() === FALSE)
		{
			if (is_int($status))
			{
				$this->sapi->response_status($status);
			}
			foreach ($this->cookies as $values)
			{
				$this->sapi->response_cookie(...$values);
			}
			foreach ($this->headers as $key => $value)
			{
				$this->sapi->response_header("{$key}: {$value}");
			}
			if (property_exists($this, 'io'))
			{
				if ($this['gzip_level']
					&& stripos($this->request_header('Accept-Encoding'), 'gzip') !== FALSE
					&& stream_filter_append($this->io, 'zlib.deflate', STREAM_FILTER_READ, ['level' => $this['gzip_level'], 'window' => 31, 'memory' => 9])) {
					$this->sapi->response_header('Content-Encoding: gzip');
				}
				$this->sapi->response_content($this);
				unset($this->io);
			}
		}
	}
	function __toString():string
	{
		return rewind($this->io) ? stream_get_contents($this->io) : join(PHP_EOL, $this->errors);
	}
	// function __debugInfo():array
	// {
	// 	return $this->errors;
	// }
	function __call(string $name, array $params):mixed
	{
		return method_exists($this->app, $name) ? $this->app->{$name}(...$params) : throw new error;
	}
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
	final function __invoke(object $object):object
	{
		$object->webapp = $this;
		if ($object instanceof ArrayAccess)
		{
			$object['errors'] = &$this->errors;
		}
		else
		{
			$object->errors = &$this->errors;
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
	function app(string $classname = NULL, mixed ...$params):object
	{
		// return $this(is_string($classname)
		// 	? $this->app = new $classname($this, ...$params)
		// 	: is_object($this['app_mapping']) ? $this['app_mapping'] : $this['app_mapping'] = new $this['app_mapping']($this));

		if (is_string($classname))
		{
			return $this->app = new $classname($this, ...$params);
		}
		return is_object($this['app_mapping']) ? $this['app_mapping'] : $this['app_mapping'] = new $this['app_mapping']($this);
	}
	function io():mixed
	{
		return fopen('php://memory', 'r+');
	}
	function print(string $data):int
	{
		return fwrite($this->io, $data);
	}
	function printf(string $format, string ...$params):int
	{
		return fprintf($this->io, $format, ...$params);
	}
	function println(string $data):int
	{
		return $this->printf("%s\n", $data);
	}
	function putcsv(array $values, string $delimiter = ',', string $enclosure = '"'):int
	{
		return fputcsv($this->io, $values, $delimiter, $enclosure);
	}
	function hash_time33(string $data, bool $care = FALSE):string
	{
		for ($hash = 5381, $i = strlen($data); $i; $hash = (($hash << 5) + $hash) + ord($data[--$i]) & 0x0fffffffffffffff);
		if ($care) while ($i < 10) $code[] = self::key[$hash >> $i++ * 6 & 63];
		else while ($i < 12) $code[] = self::key[$hash >> $i++ * 5 & 31];
		return join($code);
	}
	function url64_encode(string $data):string
	{
		for ($buffer = [], $length = strlen($data), $i = 0; $i < $length;)
		{
			$value = ord($data[$i++]) << 16 | ord($data[$i++] ?? NULL) << 8 | ord($data[$i++] ?? NULL);
			$buffer[] = self::key[$value >> 18 & 63];
			$buffer[] = self::key[$value >> 12 & 63];
			$buffer[] = self::key[$value >> 6 & 63];
			$buffer[] = self::key[$value & 63];
		}
		if ($modulo = $length % 3)
		{
			array_splice($buffer, -(3 - $modulo));
		}
		return join($buffer);
	}
	function url64_decode(string $data):?string
	{
		do
		{
			if (rtrim($data, self::key))
			{
				break;
			}
			for ($buffer = [], $length = strlen($data), $i = 0; $i < $length;)
			{
				$value = strpos(self::key, $data[$i++]) << 18;
				if ($i < $length)
				{
					$value |= strpos(self::key, $data[$i++]) << 12;
					$buffer[] = chr($value >> 16 & 255);
					if ($i < $length)
					{
						$value |= strpos(self::key, $data[$i++]) << 6;
						$buffer[] = chr($value >> 8 & 255);
						if ($i < $length)
						{
							$buffer[] = chr($value | strpos(self::key, $data[$i++]) & 255);
						}
					}
					continue;
				}
				break 2;
			}
			return join($buffer);
		} while (0);
		return NULL;
	}
	function encrypt(string $data):?string
	{
		return is_string($binary = openssl_encrypt($data, 'aes-128-gcm', static::key, OPENSSL_RAW_DATA, md5(static::key, TRUE), $tag)) ? $this->url64_encode($tag . $binary) : NULL;
	}
	function decrypt(string $data):?string
	{
		return strlen($data) > 20
			&& is_string($binary = $this->url64_decode($data))
			&& is_string($result = openssl_decrypt(substr($binary, 16), 'aes-128-gcm', static::key, OPENSSL_RAW_DATA, md5(static::key, TRUE), substr($binary, 0, 16))) ? $result : NULL;
	}
	function signature(string $username, string $password, string $additional = NULL):?string
	{
		return $this->encrypt(pack('VCCa*', time(), strlen($username), strlen($password), $username . $password . $additional));
	}
	function authorize(closure $authenticate, string $signature = NULL):bool
	{
		if (is_string($signature) && strlen($data = $this->decrypt($signature)) > 5)
		{
			$hi = unpack('Vst/Cul/Cpl', $data);
			$acc = unpack("a{$hi['ul']}uid/a{$hi['pl']}pwd/a*add", $data, 6);
			return $authenticate->call($this, $acc['uid'], $acc['pwd'], $hi['st'], $acc['add']);
		}
		return FALSE;
	}
	function admin(string $signature = NULL):bool
	{
		return $this->authorize(fn(string $username, string $password, int $signtime):bool =>
			$signtime + $this['admin_expire'] > time()
			&& $username === $this['admin_username']
			&& $password === $this['admin_password']
		, func_num_args() ? $signature : $this->request_cookie($this['admin_cookie']));
	}
	function random(int $length):string
	{
		return random_bytes($length);
	}
	function iphex(string $ip):string
	{
		return str_pad(bin2hex(inet_pton($ip)), 32, 0, STR_PAD_LEFT);
	}
	function hexip(string $hex):string
	{
		return inet_ntop(hex2bin($hex));
	}
	//---------------------
	function library(string $function):mixed
	{
		return include_once "{$this['app_library']}/{$function}/interface.php";
	}
	function resroot(string $filename = NULL):string
	{
		return "{$this['app_resroot']}/{$filename}";
	}
	//----------------
	function connect(string $url):webapp_connect
	{
		$connect = new webapp_connect($url);
		if ($connect->errors)
		{
			array_push($this->errors, ...$connect->errors);
		}
		$connect->headers(['User-Agent' => 'WebApp/' . self::version]);
		return $this->errors($connect);
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
	function formdata(array|webapp_html_xml $node = NULL, string $action = NULL):array|webapp_html_form
	{
		if (is_array($node))
		{
			$form = new webapp_html_form($this);
			foreach ($node as $name => $attr)
			{
				$form->field($name, ...is_array($attr) ? [$attr['type'], $attr] : [$attr]);
			}
			return $form->fetch() ?? [];
		}
		return new webapp_html_form($this, $node, $action);
	}
	function image(int $width, int $height):webapp_image
	{
		return new webapp_image($width, $height);
	}
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
	function debugtime(?float &$time = 0):float
	{
		return $time = microtime(TRUE) - $time;
	}
	function charsplit(string $content):array
	{
		return preg_match_all('/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/', $content, $pattern) === FALSE ? [] : $pattern[0];
	}
	function cond(string $name = 'cond'):array
	{
		$cond = [];
		preg_match_all('/(\w+\.(?:eq|ne|gt|ge|lt|le|lk|nl|in|ni))(?:\.([^\/]*))?/', $this->request_query($name), $values, PREG_SET_ORDER);
		foreach ($values as $value)
		{
			$cond[$value[1]] = array_key_exists(2, $value) ? urldecode($value[2]) : NULL;
		}
		return $cond;
	}
	function callback(closure $callable, mixed ...$params):void
	{
		$this['app_mapping'] = new class($callable)
		{
			function __construct(private closure $callable)
			{
				//毫无卵用
			}
			function __invoke(mixed ...$params):mixed
			{
				return ($this->callable)(...$params);
			}
		};
		$this['app_index'] = '__invoke';
		$this['app_entry'] = $params;
	}
	//request
	function request_ip():string
	{
		return $this->sapi->request_ip();
	}
	function request_header(string $name):?string
	{
		return $this->sapi->request_header($name);
	}
	function request_cookie(string $name):?string
	{
		return $this->sapi->request_cookie($name);
	}
	function request_cookie_decrypt(string $name):?string
	{
		return ($cookie = $this->request_cookie($name)) === NULL || ($content = $this->decrypt($cookie)) === NULL ? NULL : $content;
	}
	function request_query(string $name):?string
	{
		return preg_match('/^\w+$/', $name) && preg_match('/\,' . $name . '\:([\%\+\-\.\/\=\w]+)/', $this['request_query'], $query) ? $query[1] : NULL;
	}
	function request_query_remove(string $name, bool $append = FALSE):string
	{
		return '?'. (preg_match('/^\w+$/', $name) ? preg_replace('/\,'. $name .'\:(?:[\%\+\-\.\/\=\w]+)/', '', $this['request_query']) : $this['request_query']) . ($append ? ",{$name}:" : '');
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
			'application/x-www-form-urlencoded' => $this->sapi->request_formdata(FALSE),
			'application/json' => json_decode($this->sapi->request_content(), TRUE),
			'application/xml' => $this->xml($this->sapi->request_content()),
			default => $this->sapi->request_content()
		};
	}
	function request_uploadedfile(string $name, int $maximum = 1):ArrayObject
	{
		if (array_key_exists($name, $this->uploadedfiles ??= $this->sapi->request_formdata(TRUE)) === FALSE || is_array($this->uploadedfiles[$name]))
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
	function response_sendfile(string $filename):bool
	{
		return $this->sapi->response_sendfile($filename);
	}
	function response_status(int $code, string $data = NULL):void
	{
		$this->callback(fn():int => [$code, $data === NULL || $this->print($data)][0]);
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
		$this->response_header('Refresh', strlen($url) ? "{$second}; url={$url}" : $second);
	}
	function response_cookie(string $name, string $value = NULL, int $expire = 0, string $path = NULL, string $domain = NULL, bool $secure = FALSE, bool $httponly = FALSE):void
	{
		$this->cookies[] = func_get_args();
	}
	function response_cookie_encrypt(string $name, string $value = NULL, mixed ...$params):void
	{
		$this->response_cookie($name, $value === NULL ? NULL : $this->encrypt($value), ...$params);
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
	//append function
	final function no_sign_in_admin(webapp_sapi $sapi, array $config = []):bool
	{
		self::__construct($sapi, $config);
		if ($this['app_mapping'] === $this && in_array($this['app_index'], ['get_captcha', 'get_qrcode', 'get_scss'], TRUE)) return TRUE;
		if ($this->admin) return FALSE;
		if ($this['request_method'] === 'post')
		{
			$this->app('webapp_echo_json', ['errors' => &$this->errors, 'signature' => NULL]);
			if ($input = webapp_html::form_sign_in($this))
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
			webapp_html::form_sign_in($this->app('webapp_html')->xml->body->article->section);
			$this->title('Sign In Admin');
		}
		$this->response_status(200);
		return TRUE;
	}
	function captcha_random():?string
	{
		$random = $this->random($this['captcha_unit'] * 3);
		for ($i = 0; $i < $this['captcha_unit']; ++$i)
		{
			$random[$i] = chr((ord($random[$i]) % 26) + 65);
		}
		return $this->encrypt(pack('Va*', time(), $random));
	}
	function captcha_format(string $random):array
	{
		if (strlen($binary = $this->decrypt($random)) === $this['captcha_unit'] * 3 + 4)
		{
			$format = unpack('Vtime/a4code/c4rotate/c4size', $binary);
			$result = [$format['time']];
			for ($i = 0; $i < $this['captcha_unit'];)
			{
				$result[1][] = [$format['code'][$i++], $format['rotate' . $i], $format['size' . $i]];
			}
			return $result;
		}
		return [];
	}
	function captcha_verify(string $random, string $answer):bool
	{
		return ($format = $this->captcha_format($random))
			&& $format[0] + $this['captcha_expire'] > time()
			&& join(array_column($format[1], 0)) === strtoupper($answer);
	}
	function get_home()
	{
		$this->app('webapp_html')->header['style'] = 'font-size:2rem';
		$this->app->header->text('Welcome in WebApp Framework');
		//$this->app('webapp_html')->header->append('h1', ['Welcome use WebApp Framework', 'style' => 'padding:0.6rem']);
	}
	function get_captcha(string $random = NULL)
	{
		if ($this['captcha_echo'])
		{
			if (is_string($random) && ($format = $this->captcha_format($random)))
			{
				$this->response_content_type('image/jpeg');
				webapp_image::captcha($format[1], ...$this['captcha_params'])->jpeg($this->io);
				return;
			}
			if ($random = $this->captcha_random())
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
			webapp_image::qrcode($decode, $this['qrcode_ecc'], $this['qrcode_size'])->png($this->io);
			return;
		}
		return 404;
	}
	//这个函数在不久的将来会被移除
	function get_scss(string $filename = NULL)
	{
		$this->response_content_type('text/css');
		$this->response_cache_control('no-cache');
		if (file_exists($scss = __DIR__ . "/res/ps/{$filename}.scss"))
		{
			if (filemtime($scss) > filemtime($css = __DIR__ . "/res/ps/{$filename}.css"))
			{
				$app = $this->library("scss");
			// 	// include 'lib/scss/scss.php';
			// 	// $a = new Leafo\ScssPhp\Compiler;
				$app->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
				file_put_contents($css, $app->compile(file_get_contents($scss)));
			}
			$this->response_sendfile($css);
			return;
		}
	}
}
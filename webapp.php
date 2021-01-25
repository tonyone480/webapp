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
	function request_formdata(?array &$uploadedfiles):array;
	function response_sent():bool;
	function response_status(int $code):void;
	function response_header(string $value):void;
	function response_cookie(string ...$values):void;
	function response_content(string $data):void;
}
abstract class webapp implements ArrayAccess, Stringable
{
	const version = '4.0a', key = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-';
	private $errors = [], $headers = [], $cookies = [], $configs, $uploadedfiles;
	function __construct(private webapp_sapi $sapi, array $config = [])
	{
		$this->webapp = $this;
		$this->configs = $config + [
			//Request
			'request_method'	=> in_array($method = strtolower($sapi->request_method()), ['get', 'post', 'put', 'delete']) ? $method : 'get',
			'request_query'		=> $sapi->request_query(),
			//Application
			'app_charset'		=> 'utf-8',
			'app_mapping'		=> 'webapp_mapping_',
			'app_module'		=> 'home',
			'app_method'		=> 'index',
			//Admin
			'admin_username'	=> 'admin',
			'admin_password'	=> 'nimda',
			'admin_captcha'		=> TRUE,
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
			'captcha_size'		=> [210, 86],
			'captcha_params'	=> [__DIR__ . '/files/fonts/ArchitectsDaughter_R.ttf', 28],
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
		if (preg_match('/^\w+(?=\/([\-\w]*))?/', $this['request_query'], $reflex))
		{
			list($this['app_module'], $this['app_method']) = [...$reflex, $this['app_method']];
		}
		if (method_exists($this, $module = "{$this['request_method']}_{$this['app_module']}"))
		{
			$this['app_mapping'] = $this;
			$this['app_module'] = [$this, $module];
			$this['app_method'] = isset($reflex[1]) ? [$reflex[1]] : [];
		}
		else
		{
			$this['app_mapping'] .= $this['app_module'];
			$this['app_module'] = [$this['app_mapping'], strtr("{$this['request_method']}_{$this['app_method']}", '-', '_')];
			$this['app_method'] = [];
		}
		return $this['app_module'][1];


		if (preg_match('/^\w+(?=\/([\-\w]*))?/', $this['request_query'], $reflex))
		{
			$this['app_mapping'] = [$this['app_mapping'] . $reflex[0], "{$this['request_method']}_{$this['app_module']}"];



			$this['app_module'] = $reflex[0];
			if (method_exists($this, $method = "{$this['request_method']}_{$this['app_module']}"))
			{
				return $this->response_content($method, $reflex[1] ?? NULL);
			}
			if (isset($reflex[1]))
			{
				$this['app_method'] = $reflex[1];
			}
			return;
		}
		$this->response_content("{$this['request_method']}_{$this['app_method']}");
		//以下是演示继承webapp全局admin登录验证代码，方法有很多种根据自己实际需求不同而调整
		// if (in_array(parent::__construct(new sapi), ['get_captcha', 'get_qrcode', 'get_scss'])) return;
		// if ($this->admin === FALSE)
		// {
		// 	if ($this['request_method'] === 'post')
		// 	{
		// 		$data = $this->errors(new webapp_echo_json($this, ['signature' => NULL]));
		// 		if ($form = webapp_html_echo::form_sign_in($this)->fetch($this['captcha_echo']))
		// 		{
		// 			if ($this->admin($signature = $this->signature($form['username'], $form['password'])))
		// 			{
		// 				$this->response_refresh(0);
		// 				$this->response_cookie_encrypt($this['admin_cookie'], $data['signature'] = $signature);
		// 			}
		// 			else
		// 			{
		// 				$data['errors'][] = 'Sign in failed';
		// 			}
		// 		}
		// 	}
		// 	else
		// 	{
		// 		$data = new webapp_html_echo($this);
		// 		$data->title('Sign In Admin');
		// 		$form = webapp_html_echo::form_sign_in($this, $data->xml->body);
		// 	}
		// 	return $this->response_content($data);
		// }
	}
	function __destruct()
	{
		$retval = 404;
		if (is_object($this['app_mapping']))
		{
			if (is_callable($this['app_module']))
			{
				$retval = $this['app_module'](...$this['app_method']);
				if (method_exists($this['app_mapping'], '__toString'))
				{
					$this->print($this['app_mapping']);
				}
			}
		}
		else
		{
			if (method_exists(...$this['app_module']))
			{
				$reflex = new ReflectionMethod(...$this['app_module']);
				if ($reflex->isPublic() && $reflex->getNumberOfRequiredParameters() === 0 && $reflex->getDeclaringClass()->isUserDefined())
				{
					$retval = $reflex->invoke($mapping = new $this['app_mapping']($this));
					if (method_exists($mapping, '__toString'))
					{
						$this->print($mapping);
					}
				}
			}
		}
		if ($this->sapi->response_sent() === FALSE)
		{
			if (is_int($retval))
			{
				$this->sapi->response_status($retval);
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
		return $this['app_mapping']->{$name};
	}
	function __call(string $method, array $params):mixed
	{
		return $this['app_mapping']->{$method}(...$params);
	}
	final function __invoke(string $classname, mixed ...$params):object
	{
		return $this['app_mapping'] = new $classname($this, ...$params);
	}
	function __toString():string
	{
		rewind($this->io);
		return stream_get_contents($this->io);
	}
	// final function __debugInfo():array
	// {
	// 	return $this->errors;
	// }
	final function io():mixed
	{
		return $this->io ?? fopen('php://memory', 'r+');
	}
	final function errors(object $object):object
	{
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
	final function offsetGet(mixed $key):mixed
	{
		return $this->configs[$key] ?? NULL;
	}
	final function offsetSet(mixed $key, mixed $value):void
	{
		$this->configs[$key] = $value;
	}
	final function offsetUnset(mixed $key):void
	{
		unset($this->configs[$key]);
	}
	function void():void{}
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
	function random(int $length):string
	{
		return random_bytes($length);
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
	function iphex(string $ip):string
	{
		return str_pad(bin2hex(inet_pton($ip)), 32, 0, STR_PAD_LEFT);
	}
	function hexip(string $hex):string
	{
		return inet_ntop(hex2bin($hex));
	}

	
	final function signature(string $username, string $password, string $additional = NULL):?string
	{
		return $this->encrypt(pack('VCCa*', time(), strlen($username), strlen($password), $username . $password . $additional));
	}
	final function authorize(closure $authenticate, string $signature = NULL):bool
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
		return new webapp_xml('<webapp/>');
	}
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
		return $this->errors($mysql);
	}
	function debugtime(?float &$time = 0):float
	{
		return $time = microtime(TRUE) - $time;
	}
	function charsplit(string $content):array
	{
		return preg_match_all('/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/', $content, $pattern) === FALSE ? [] : $pattern[0];
	}
	//这里函数名可能需要更改 mapinstance tryinstance breakinstance
	function rewrite(string $classname, string $prefix):?object
	{
		if (is_callable($callback = [$this, "{$prefix}_{$this['request_method']}_{$this['app_module']}"]))
		{
			//$this->response_content($callback, $this->echo = new $classname($this));
			$this->response_content(function($object, $callback)
			{
				$retval = $callback($object);
				if (method_exists($object, '__toString'))
				{
					$this->print($object);
				}
				return $retval;
			}, $object = new $classname($this), $callback);
			return $object;
		}
		return NULL;
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
	function page(string $name = 'page'):int
	{
		return intval($this->request_query($name));
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
	function request_content(string $format = NULL):string|array|webapp_xml
	{
		return match($format ?? strtolower(strpos($type = $this->request_header('Content-Type'), ';') === FALSE ? $type : strstr($type, ';', TRUE)))
		{
			'application' => $this->xml($this->sapi->request_content()),
			'application/json' => json_decode($this->sapi->request_content(), TRUE),
			'multipart/form-data',
			'application/x-www-form-urlencoded' => $this->sapi->request_formdata($this->uploadedfiles),
			default => $this->sapi->request_content()
		};
	}
	function request_uploadedfile(string $name):ArrayObject
	{
		if ($this->uploadedfiles === NULL)
		{
			$this->sapi->request_formdata($this->uploadedfiles);
		}
		if (array_key_exists($name, $this->uploadedfiles) === FALSE || is_array($this->uploadedfiles[$name]))
		{
			$this->uploadedfiles[$name] = new class($this->uploadedfiles[$name] ?? []) extends ArrayObject
			{
				function size():int
				{
					return array_sum(array_column((array)$this, 'size'));
				}
				function detect(string $mime):bool
				{
					foreach ($this as $files)
					{
						//感觉在不久的将来这里需要改
						if (preg_match('/^(' . str_replace(['/', '*', ','], ['\\/', '.*', '|'], $mime) . ')$/', $files['type']) === 0)
						{
							return FALSE;
						}
					}
					return TRUE;
				}
				// function map(callable $callback):static
				// {
				// 	$this->exchangeArray(array_map($callback, (array)$this));
				// 	return $this;
				// }
				// function moveto(string $outdir, callable $rename)
				// {
				// 	$success = [];
				// 	foreach ($this as $files)
				// 	{
				// 		if (is_string($basename = $rename($files)) && move_uploaded_file($files['tmp_name'], $filename = "{$outdir}/{$basename}"))
				// 		{
				// 			$success[$rename] = $filename;
				// 		}
				// 	}
				// 	return $count;
				// }
			};
		}
		return $this->uploadedfiles[$name];
	}
	//response
	function response_status(int $code):void
	{
		$this->response_content([$this->sapi, 'response_status'], $code);
	}
	function response_header(string $name, string $value):void
	{
		//$this->headers[ucwords($name, '-')] = $value;
		$this->headers[$name] = $value;
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
	function response_content_sendfile(string $filename):void
	{
		$this->response_header('X-Sendfile', $filename);
	}
	function response_location(string $url):void
	{
		$this->response_header('Location', $url);
	}
	function response_refresh(int $second = 0, string $url = NULL):void
	{
		$this->response_header('Refresh', strlen($url) ? "{$second}; url={$url}" : $second);
	}
	function response_content(mixed $echo, mixed ...$params):void
	{
		if (is_scalar($echo))
		{
			$this['app_mapping'] = [$this, $echo];
			$this['app_method'] = $params;
		}
		else
		{
			if (method_exists($echo, '__toString') && empty($params))
			{
				$this['app_mapping'] = $this;
				$this['app_method'] = [$echo];
			}
			else
			{
				$this['app_mapping'] = $echo;
				$this['app_method'] = $params;
			}
		}
	}
	//append function
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
	function get_captcha(string $random = NULL)
	{
		if ($this['captcha_echo'])
		{
			if (is_string($random) && ($format = $this->captcha_format($random)))
			{
				$this->response_content_type('image/jpeg');
				$this->image(...$this['captcha_size'])->captcha($format[1], ...$this['captcha_params'])->jpeg($this->io);
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
	function get_qrcode(string $encode = NULL)
	{
		if ($this['qrcode_echo'])
		{
			if (is_string($encode) && is_string($decode = $this->url64_decode($encode)) && strlen($decode) < $this['qrcode_maxdata'])
			{
				$this->response_content_type('image/png');
				webapp_image::qrcode($decode, $this['qrcode_ecc'], $this['qrcode_size'])->png($this->io);
				return;
			}
			return 400;
		}
		return 404;
	}
	function get_scss(string $filename = NULL)
	{
		$this->response_content_type('text/css');
		$this->response_cache_control('no-cache');
		if (preg_match('/^\w+/', $filename) && file_exists($infile = "work/webapp/files/ps/{$filename}.scss"))
		{
			if (filemtime($infile) > filemtime($outfile = "work/webapp/files/ps/{$filename}.css"))
			{
				include 'scss/scss.php';
				$scss = new Leafo\ScssPhp\Compiler;
				$scss->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
				file_put_contents($outfile, $scss->compile(file_get_contents($infile)));
			}
			$this->response_content_sendfile("webapp/files/ps/{$filename}.css");
			return;
		}
	}
}
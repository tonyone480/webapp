<?php
class webapp_image
{
	private
	$file,
	$image,
	$colors = [0, 0xffffff],
	$color = 0,
	$width,
	$height;
	// static function from(string $filename):self
	// {
	// 	if (is_file($filename))
	// 	{
	// 		$fileinfo = getimagesize($filename);
	// 		return match ($fileinfo[2])
	// 		{
	// 			1 => new static(imagecreatefromgif($filename)),
	// 			2 => new static(imagecreatefromjpeg($filename)),
	// 			3 => new static(imagecreatefrompng($filename)),
	// 			default => static::create(),
	// 		};
	// 	}
	// 	return new static(getimagesizefromstring($filename));
	// }
	function __construct(string $x, int $y = NULL)
	{
		if ($y === NULL)
		{
			if (is_file($x))
			{
				if ($info = getimagesize($x))
				{
					$this->width = $info[0];
					$this->height = $info[1];
					switch ($info[2])
					{
						case 1://GIF
							$this->image = imagecreatefromgif($x);
							return;
						case 2://JPG
							$this->image = imagecreatefromjpeg($x);
							return;
						case 3://PNG
							$this->image = imagecreatefrompng($x);
							return;
					};
				}
			}
			else
			{
				if ($info = getimagesizefromstring($x))
				{
					$this->image = imagecreatefromstring($x);
					$this->width = $info[0];
					$this->height = $info[1];
					return;
				}
			}
			throw new error;
		}
		$this->image = imagecreatetruecolor($this->width = $x, $this->height = $y);
		$this->color(1)->fill(0, 0)->color(0);
	}
	function import(string $filename)
	{

	}
	function colorat(int $x, int $y, string $name = NULL):self
	{
		$this->color = imagecolorat($this->image, $x, $y);
		if ($name)
		{
			$this->colors[$name] = $this->color;
		}
		return $this;
	}
	function color(string $name, int $red = 0, int $green = 0, int $blue = 0, float $alpha = 0):self
	{
		if (func_num_args() > 4)
		{
			$this->colors[$name] = $this->color = $alpha
				? imagecolorallocate($this->image, $red, $green, $blue)
				: imagecolorallocatealpha($this->image, $red, $green, $blue, intval(127 * $alpha));
		}
		else
		{
			if (array_key_exists($name, $this->colors))
			{
				$this->color = $this->colors[$name];
			}
		}
		return $this;
	}
	function fill(int $x, int $y):self
	{
		imagefill($this->image, $x, $y, $this->color);
		return $this;
	}
	function line(int $x0, int $y0, int $x1, int $y1):self
	{
		imageline($this->image, $x0, $y0, $x1, $y1, $this->color);
		return $this;
	}
	function pixel(int $x, int $y):self
	{
		imagesetpixel($this->image, $x, $y, $this->color);
		return $this;
	}
	function polygon(array $points, bool $filled = FALSE, bool $open = FALSE):self
	{
		($filled ? 'imagefilledpolygon' : ($open ? 'imageopenpolygon' : 'imagepolygon'))($this->image, $points, count($points), $this->color);
		return $this;
	}
	function rectangle(int $x0, int $y0, int $x1, int $y1, bool $filled = FALSE):self
	{
		($filled ? 'imagefilledrectangle' : 'imagerectangle')($this->image, $x0, $y0, $x1, $y1, $this->color);
		return $this;
	}
	function square(int $x, int $y, int $size, bool $filled = FALSE):self
	{
		return $this->rectangle($x, $y, $x + $size, $y + $size, $filled);
	}
	function string(int $x, int $y, string $word, int $font = 4):self
	{
		imagestring($this->image, $font, $x, $y, $word, $this->color);
		return $this;
	}
	function text(int $x, int $y, string $word, string $font, int $size = 24, int $angle = 0):self
	{
		imagettftext($this->image, $size, $angle, $x, $y, $this->color, $font, $word);
		return $this;
	}
	function resize(int $width, int $height):self
	{
		$image = imagecreate($width, $height);
		imagecopyresized($image, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
		imagedestroy($this->image);
		$this->image = $image;
		return $this;
	}
	//将真彩色图像转换为调色板图像
	function palette(int $color = 255, bool $dithered = TRUE):self
	{
		imagetruecolortopalette($this->image, $dithered, $color);
		return $this;
	}
	//反转图像的所有颜色
	function negate():self
	{
		imagefilter($this->image, IMG_FILTER_NEGATE);
		return $this;
	}
	//将图像转换为灰度
	function grayscale():self
	{
		imagefilter($this->image, IMG_FILTER_GRAYSCALE);
		return $this;
	}
	//改变图像的亮度
	function brightness(int $level):self
	{
		imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level);
		return $this;
	}
	//改变图像的对比度
	function contrast(int $level):self
	{
		imagefilter($this->image, IMG_FILTER_CONTRAST, $level);
		return $this;
	}
	//使用边缘检测来突出显示图像中的边缘
	function edgedetect():self
	{
		imagefilter($this->image, IMG_FILTER_EDGEDETECT);
		return $this;
	}
	//压花图像
	function emboss():self
	{
		imagefilter($this->image, IMG_FILTER_EMBOSS);
		return $this;
	}
	//模糊图像
	function blur(int $type = IMG_FILTER_GAUSSIAN_BLUR):self
	{
		imagefilter($this->image, $type);
		return $this;
	}
	//使图像更平滑
	function smooth(int $level = 1):self
	{
		imagefilter($this->image, IMG_FILTER_SMOOTH, $level);
		return $this;
	}
	//将像素化效果应用于图像
	function pixelate(int $block = 2, bool $advanced = FALSE):self
	{
		imagefilter($this->image, IMG_FILTER_PIXELATE, $block, $advanced);
		return $this;
	}

	// function wave(array $values = []):webapp_img
	// {
	// 	$values += ['x0' => 12, 'x1' => 14, 'y0' => 11, 'y1' => 5];
	// 	$values['x0'] *= mt_rand(1, 3);
	// 	$x = mt_rand(0, 100);
	// 	for ($i = 0; $i < $this->width; ++$i)
	// 	{
	// 		imagecopy($this->image, $this->image, sin($x + $i / $values['x0']) * $values['x1'], $i - 1, 0, $i, $this->width, 1);
	// 	}
	// 	$values['y0'] *= mt_rand(1, 3);
	// 	$y = mt_rand(0, 100);
	// 	for ($i = 0; $i < $this->width; ++$i)
	// 	{
	// 		imagecopy($this->image, $this->image, $i - 1, sin($y + $i / $values['y0']) * $values['y1'], $i, 0, 1, $this->height);
	// 	}
	// 	imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
	// 	return $this;
	// }
	function jpeg($output = 'php://output', int $quality = 75):bool
	{
		return imagejpeg($this->image, $output, $quality);
	}
	function png($output = 'php://output'):bool
	{
		return imagepng($this->image, $output);
	}
	function captcha(array $format, string $font, int $size):self
	{
		$width = 0;
		$writing = [];
		$fix = $size * 0.4;
		foreach ($format as $read)
		{
			$angle = $read[1] * 0.4;
			$fixsize = $size + ceil($fix * ($read[2] / 128));
			$calc = imagettfbbox($fixsize, $angle, $font, $read[0]);
			$min_x = min($calc[0], $calc[2], $calc[4], $calc[6]);
			$max_x = max($calc[0], $calc[2], $calc[4], $calc[6]);
			$min_y = min($calc[1], $calc[3], $calc[5], $calc[7]);
			$max_y = max($calc[1], $calc[3], $calc[5], $calc[7]);
			$width += ($writing[] = [
				'left'	=> abs($min_x),
				'top'	=> abs($min_y),
				'width'	=> $max_x - $min_x,
				'height'=> $max_y - $min_y,
				'angle'	=> $angle,
				'code'	=> $read[0],
				'size' => $fixsize
			])['width'];
		}
		$offset = ($this->width - $width) * 0.5;
		foreach ($writing as $write)
		{
			$this->text($offset + $write['left'],
				($this->height - $write['height']) * 0.5 + $write['top'],
				$write['code'],
				$font,
				$write['size'],
				$write['angle']);
			$offset += $write['width'];
		}
		return $this;
	}
	static function qrcode(string $data, int $ecclevel = 0, int $pixel = 4, int $margin = 2):self
	{
		include 'qrcode/phpqrcode.php';
		// switch (1)
		// {
		// 	case preg_match('/^[0-9]+$/', $data): $mode = QR_MODE_NUM; break;
		// 	case preg_match('/^[0-9A-Z\$%\*\+\–\.\/\: ]+$/', $data): $mode = QR_MODE_AN; break;
		// 	default: $mode = QR_MODE_8;
		// }
		$size = count($data = (new QRcode())->encodeString8bit($data, 0, $ecclevel)->data);
		$image = new static($resize = $size + $margin * 2, $resize);
		for ($x = 0; $x < $size; ++$x)
		{
			for ($y = 0; $y < $size; ++$y)
			{
				if (ord($data[$x][$y]) & 1)
				{
					$image->pixel($x + $margin, $y + $margin);
				}
			}
		}
		return $image->resize($resize *= $pixel, $resize);
	}
}
<?php
declare(strict_types=1);
class webapp_image implements IteratorAggregate
{
	private
	$colors = [0, 0xffffff],
	$color = 0,
	$width,
	$height;
	function __construct(private $image)
	{
		$this->width = imagesx($image);
		$this->height = imagesy($image);
	}
	function getIterator():Traversable
	{
		for ($y = 0; $y < $this->height; ++$y)
		{
			for ($x = 0; $x < $this->width; ++$x)
			{
				yield $x => $y;
			}
		}
	}
	function colorat(int $x, int $y, string $name = NULL):static
	{
		$this->color = imagecolorat($this->image, $x, $y);
		if ($name)
		{
			$this->colors[$name] = $this->color;
		}
		return $this;
	}
	function color(string $name, int $red = 0, int $green = 0, int $blue = 0, float $alpha = 0):static
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
	function fill(int $x, int $y):static
	{
		imagefill($this->image, $x, $y, $this->color);
		return $this;
	}
	function line(int $x0, int $y0, int $x1, int $y1):static
	{
		imageline($this->image, $x0, $y0, $x1, $y1, $this->color);
		return $this;
	}
	function pixel(int $x, int $y):static
	{
		imagesetpixel($this->image, $x, $y, $this->color);
		return $this;
	}
	function polygon(array $points, bool $filled = FALSE, bool $open = FALSE):static
	{
		($filled ? 'imagefilledpolygon' : ($open ? 'imageopenpolygon' : 'imagepolygon'))($this->image, $points, count($points), $this->color);
		return $this;
	}
	function rectangle(int $x0, int $y0, int $x1, int $y1, bool $filled = FALSE):static
	{
		($filled ? 'imagefilledrectangle' : 'imagerectangle')($this->image, $x0, $y0, $x1, $y1, $this->color);
		return $this;
	}
	function square(int $x, int $y, int $size, bool $filled = FALSE):static
	{
		return $this->rectangle($x, $y, $x + $size, $y + $size, $filled);
	}
	function string(int $x, int $y, string $word, int $font = 4):static
	{
		imagestring($this->image, $font, $x, $y, $word, $this->color);
		return $this;
	}
	function text(int $x, int $y, string $word, string $font, int $size = 24, int $angle = 0):static
	{
		imagettftext($this->image, $size, $angle, $x, $y, $this->color, $font, $word);
		return $this;
	}
	function resize(int $width, int $height):static
	{
		$image = imagecreate($width, $height);
		imagecopyresized($image, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
		imagedestroy($this->image);
		$this->image = $image;
		$this->width = $width;
		$this->height = $height;
		return $this;
	}
	//将真彩色图像转换为调色板图像
	function palette(int $color = 255, bool $dithered = TRUE):static
	{
		imagetruecolortopalette($this->image, $dithered, $color);
		return $this;
	}
	//反转图像的所有颜色
	function negate():static
	{
		imagefilter($this->image, IMG_FILTER_NEGATE);
		return $this;
	}
	//将图像转换为灰度
	function grayscale():static
	{
		imagefilter($this->image, IMG_FILTER_GRAYSCALE);
		return $this;
	}
	//改变图像的亮度
	function brightness(int $level):static
	{
		imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level);
		return $this;
	}
	//改变图像的对比度
	function contrast(int $level):static
	{
		imagefilter($this->image, IMG_FILTER_CONTRAST, $level);
		return $this;
	}
	//使用边缘检测来突出显示图像中的边缘
	function edgedetect():static
	{
		imagefilter($this->image, IMG_FILTER_EDGEDETECT);
		return $this;
	}
	//压花图像
	function emboss():static
	{
		imagefilter($this->image, IMG_FILTER_EMBOSS);
		return $this;
	}
	//模糊图像
	function blur(int $type = IMG_FILTER_GAUSSIAN_BLUR):static
	{
		imagefilter($this->image, $type);
		return $this;
	}
	//使图像更平滑
	function smooth(int $level = 1):static
	{
		imagefilter($this->image, IMG_FILTER_SMOOTH, $level);
		return $this;
	}
	//将像素化效果应用于图像
	function pixelate(int $block = 2, bool $advanced = FALSE):static
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
	function bmp(mixed $output = 'php://output', bool $compressed = TRUE):bool
	{
		return imagebmp($this->image, $output, $compressed);
	}
	function gif(mixed $output = 'php://output'):bool
	{
		return imagegif($this->image, $output);
	}
	function jpeg(mixed $output = 'php://output', int $quality = 75):bool
	{
		return imagejpeg($this->image, $output, $quality);
	}
	function png(mixed $output = 'php://output'):bool
	{
		return imagepng($this->image, $output);
	}
	function webp(mixed $output = 'php://output'):bool
	{
		return imagewebp($this->image, $output, $quality);
	}
	static function from(string $filename):?static
	{
		return ($image = is_array($info = @getimagesize($filename)) ? match($info[2])
		{
			1 =>		imagecreatefromgif($filename),
			2 =>		imagecreatefromjpeg($filename),
			3 =>		imagecreatefrompng($filename),
			6 =>		imagecreatefrombmp($filename),
			18 =>		imagecreatefromwebp($filename),
			15 =>		imagecreatefromwbmp($filename),
			16 =>		imagecreatefromxbm($filename),
			default =>	FALSE
		} : imagecreatefromstring($filename)) ? new static($image) : NULL;
	}
	static function create(int $width, int $height):static
	{
		return (new static(imagecreatetruecolor($width, $height)))->color(1)->fill(0, 0)->color(0);
	}



	static function hsl_decode(float $hue, float $saturation = 1, float $lightness = 0.5):int
	{
		if ($saturation)
		{
			$color = fn($p, $q, $t) => (($t < 0 ? $t += 1 : $t) > 1 ? $t -= 1 : $t) ? match (TRUE)
			{
				$t < 1 / 6 => $p + ($q - $p) * 6 * $t,
				$t < 1 / 2 => $q,
				$t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
				default => $p
			} : 0;
			$q = $lightness < 0.5 ? $lightness * (1 + $saturation) : $lightness + $saturation - $lightness * $saturation;
			$p = 2 * $lightness - $q;
			$r = $color($p, $q, $hue + 1 / 3);
			$g = $color($p, $q, $hue);
			$b = $color($p, $q, $hue - 1 / 3);
			return round($r * 255) << 16 | round($g * 255) << 8 | round($b * 255);
		}
		return 0;
	}
	static function octbit_encode(int $value):int
	{
		return (($value >> 22) & 0b11) << 4 | (($value >> 14) & 0b11) << 2 | ($value >> 6) & 0b11;
	}
	static function octbit_decode(int $value):int
	{
		return static::hsl_decode(abs($value % 255) / 255);
	}
	static function hex_encode(int $value):string
	{
		return str_pad(dechex($value), 6, '0', STR_PAD_LEFT);
	}
	static function hex_decode(string $value):int
	{
		return hexdec($value);
	}


	function octbit()
	{
		foreach ($this as $x => $y)
		{
			$color = static::octbit_encode(imagecolorat($this->image, $x, $y));
			imagesetpixel($this->image, $x, $y, static::octbit_decode($color));
		}
		
		
		
	}



	static function captcha(array $contents, int $width, int $height, string $font, int $size)
	{
		$offset = 0;
		$writing = [];
		$fix = $size * 0.4;
		foreach ($contents as $read)
		{
			$angle = $read[1] * 0.4;
			$fixsize = $size + ceil($fix * ($read[2] / 128));
			$calc = imagettfbbox($fixsize, $angle, $font, $read[0]);
			$min_x = min($calc[0], $calc[2], $calc[4], $calc[6]);
			$max_x = max($calc[0], $calc[2], $calc[4], $calc[6]);
			$min_y = min($calc[1], $calc[3], $calc[5], $calc[7]);
			$max_y = max($calc[1], $calc[3], $calc[5], $calc[7]);
			$offset += ($writing[] = [
				'left'	=> abs($min_x),
				'top'	=> abs($min_y),
				'width'	=> $max_x - $min_x,
				'height'=> $max_y - $min_y,
				'angle'	=> $angle,
				'code'	=> $read[0],
				'size' => $fixsize
			])['width'];
		}
		$offset = ($width - $offset) * 0.5;
		$image = static::create($width, $height);
		foreach ($writing as $write)
		{
			$image->text($offset + $write['left'],
				($image->height - $write['height']) * 0.5 + $write['top'],
				$write['code'],
				$font,
				$write['size'],
				$write['angle']);
			$offset += $write['width'];
		}
		return $image;
	}
	static function qrcode(string $data, int $ecclevel = 0, int $pixel = 4, int $margin = 2):static
	{
		$size = count($data = (include __DIR__ . '/lib/qrcode/interface.php')($data, $ecclevel));
		$image = static::create($resize = $size + $margin * 2, $resize);
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
<?php

/**
* Simple Image management class
* © 2012 Ilya I. Averkov <admin@jsmart.web.id>
* © 2012 Irfan Mahfudz Guntur <info@bsmsite.com>
*/

namespace BSM;

class Image
{
	protected $image;
	protected $width;
	protected $height;
	protected $font;
	
	public function __construct() {
		if(!function_exists('imagecreatefrompng')) {
			throw new \Exception('PHP graphics support is unavailable!');
		}
		$args = func_get_args();
		switch(func_num_args()) {
			case 2:
				$this->image = imagecreatetruecolor($this->width = $args[0], $this->height = $args[1]);
			break;
			case 1:
				if(!file_exists($args[0])) {
					throw new \Exception('File not found: ' . $args[0]);
				}
				if(!is_readable($args[0])) {
					throw new \Exception('Permission denied: ' . $args[0]);
				}
				
				$im = @ getimagesize($args[0]);
				if($im[0] == 0) {
					throw new \Exception('Not an image file: ' . $args[0]);
				}
				
				$type = self::extByImageType($im[2]);
				
				if($type == 'bmp') {
					$this->fromBMP($args[0]);
				} else {
					$fx = 'imagecreatefrom' . ($type == 'jpg' ? 'jpeg' : $type);
				
					if(($this->image = @ $fx($args[0])) === false) {
						throw new \Exception('Not an image file: ' . $args[0]);
					}
					
					$this->width = $im[0];
					$this->height = $im[1];
				}
			break;
			default:
				throw new \Exception('Wrong usage, the constructor of BSM\\Image class takes 1 or 2 arguments');
			break;
		}
		
		imagealphablending($this->image, true);
		imagesavealpha($this->image, true);
	}
	
	public function __destruct() {
		imagedestroy($this->image);
	}
	
	private function fromBMP($path) {
		$file = fopen($path, 'rb');
		$read = fgets($file, 10);
		
		while(! feof($file) && ($read <> '')) {
			$read .= fgets($file, 1024);
		}
		
		$temp = unpack('H*', $read);
		$hex = $temp[1];
		$header = substr($hex, 0, 108);
		
		if(substr($header, 0, 4) == '424d') {
            $header_parts = str_split($header, 2);
            $this->width = hexdec($header_parts[19] . $header_parts[18]);
            $this->height = hexdec($header_parts[23] . $header_parts[22]);
			unset($header_parts);
		}
		
		$x = 0;
		$y = 1;
		
		$this->image = imagecreatetruecolor($this->width, $this->height);
        $body = substr($hex, 108);
        $body_size = strlen($body) / 2;
		$header_size = $this->width * $this->height;
        $use_padding = ($body_size > ($header_size * 3) + 4);
		
		for($i = 0; $i < $body_size; $i += 3) {
			if($x >= $this->width) {
				if($use_padding) {
                    $i += $this->width % 4;
				}
				
				$x = 0;
				$y ++;
				
				if ($y > $this->height) break;
            }
            
            $i_pos = $i * 2;
			$color = imagecolorallocate (
				$this->image,
				hexdec($body[$i_pos + 4] . $body[$i_pos + 5]),
				hexdec($body[$i_pos + 2] . $body[$i_pos + 3]),
				hexdec($body[$i_pos] . $body[$i_pos + 1])
			);
			imagesetpixel($this->image, $x, $this->height - $y, $color);
			
			$x ++;
		}
    }
	
	public static function extByImageType($mime_type) {
		switch(strtolower($mime_type)) {
			case IMAGETYPE_PNG: case 'image/png': return 'png'; break;
			case IMAGETYPE_GIF: case 'image/gif': return 'gif'; break;
			case IMAGETYPE_JPEG: case 'image/jpeg': return 'jpg'; break;
			case IMAGETYPE_BMP: case '': return 'bmp'; break;
			default: throw new \Exception('Not an image MIME type: ' . $mime_type); break;
		}
	}
	
	public function & width() {
		return $this->width;
	}
	
	public function & height() {
		return $this->height;
	}
	
	public function setAntialiasing($antialiasing) {
		if(!function_exists('imageantialias')) return false;
		return imageantialias($this->image, $antialiasing);
	}
	
	private function color($red, $green, $blue) {
		return imagecolorallocate($this->image, $red, $green, $blue);
	}
	
	public function gaussianBlur() {
		// TODO: blur radius
		return @ function_exists('imagefilter') ? imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR) : false;
	}
	
	public function brightness($level) {
		return @ function_exists('imagefilter') ? imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level) : false;
	}
	
	public function contrast($level) {
		return @ function_exists('imagefilter') ? imagefilter($this->image, IMG_FILTER_CONTRAST, $level) : false;
	}
	
	public function invert() {
		return @ function_exists('imagefilter') ? imagefilter($this->image, IMG_FILTER_NEGATE) : false;
	}
	
	public function pixelate($block_size) {
		return @ function_exists('imagefilter') ? imagefilter($this->image, IMG_FILTER_PIXELATE, $block_size) : false;
	}
	
	private function _vignetteEffect($w, $h, $sharp, $level, $x, $y, & $rgb) {
		$l = sin(M_PI / $w * $x) * sin(M_PI / $h * $y);
		$l = pow($l, $sharp);
		$l = 1 - $level * (1 - $l);
		
		$rgb['red'] *= $l;
		$rgb['green'] *= $l;
		$rgb['blue'] *= $l;
	}
	
	public function vignette($sharp = 0.4, $level = 0.7) {
		for($x = 0; $x < $this->width; ++ $x) {
			for($y = 0; $y < $this->height; ++ $y) {
				$rgb = imagecolorsforindex($this->image, imagecolorat($this->image, $x, $y));
				$this->_vignetteEffect($this->width, $this->height, $sharp, $level, $x, $y, $rgb);
				$color = imagecolorallocate($this->image, $rgb['red'], $rgb['green'], $rgb['blue']);
				imagesetpixel($this->image, $x, $y, $color);   
			}
		}
	}
	
	function sepia() {
		if(function_exists('imagefilter')) {
			imagefilter($this->image, IMG_FILTER_GRAYSCALE);
			imagefilter($this->image, IMG_FILTER_COLORIZE, 100, 50, 0);
		} else {
			$total = imagecolorstotal($this->image);
			for($i = 0; $i < $total; $i ++) {
				$index = imagecolorsforindex($this->image, $i);
				$red = ($index['red'] * 0.393 + $index['green'] * 0.769 + $index['blue'] * 0.189) / 1.351;
				$green = ($index['red'] * 0.349 + $index['green'] * 0.686 + $index['blue'] * 0.168) / 1.203;
				$blue = ($index['red'] * 0.272 + $index['green'] * 0.534 + $index['blue'] * 0.131) / 2.140;
				imagecolorset($this->image, $i, $red, $green, $blue);
			}
		}
	}
	
	function hue($angle) {
		if($angle % 360 == 0) return;
		
		for($x = 0; $x < $this->width; $x ++) {
			for($y = 0; $y < $this->height; $y ++) {
				$rgb = imagecolorat($this->image, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				$alpha = ($rgb & 0x7F000000) >> 24;
				list($h, $s, $l) = self::rgbToHsl($r, $g, $b);
				$h += $angle / 360;
				if($h > 1) $h --;
				list($r, $g, $b) = self::hslToRgb($h, $s, $l);
				imagesetpixel($this->image, $x, $y, imagecolorallocatealpha($this->image, $r, $g, $b, $alpha));
			}
		}
	}
	
	public function drawFilledRectangle($x1, $y1, $x2, $y2, $red, $green, $blue) {
		return @ imagefilledrectangle($this->image, $x1, $y1, $x2, $y2, $this->color($red, $green, $blue));
	}
	
	public function fillBackground($red, $green, $blue) {
		return @ imagefill($this->image, 0, 0, $this->color($red, $green, $blue));
	}
	
	public function drawBorder($red, $green, $blue) {
		return @ imagerectangle($this->image, 0, 0, $this->width - 1, $this->height - 1, $this->color($red, $green, $blue));
	}
	
	public function setPixel($x, $y, $red, $green, $blue) {
		return @ imagesetpixel($this->image, $x, $y, $this->color($red, $green, $blue));
	}
	
	public function drawLine($x1, $y1, $x2, $y2, $red, $green, $blue) {
		return @ imageline($this->image, $x1, $y1, $x2, $y2, $this->color($red, $green, $blue));
	}
	
	public function text($size, $x, $y, $string, $red, $green, $blue) {
		return @ imagestring($this->image, $size, $x, $y, $string, $this->color($red, $green, $blue));
	}
	
	public function centeredText($size, $x, $y, $string, $red, $green, $blue) {
		$x = $x - round((imagefontwidth($size) * strlen($string)) / 2);
		return $this->text($size, $x, $y, $string, $this->color($red, $green, $blue));
	}
	
	public function setFont($filename) {
		$this->font = $filename;
	}
	
	public function leftSmoothText($size, $x, $y, $string, $red, $green, $blue) {
		if(is_null($this->font) && ! @ is_readable($this->font)) {
			return $this->centeredText(2, $x, $y, $string, $red, $green, $blue);
		}
		return @ imagettftext($this->image, $size, 0.0, $x, $y, $this->color($red, $green, $blue), $this->font, $string) ? true : $this->centeredText(2, $x, $y, $string, $red, $green, $blue);
	}
	
	public function centeredSmoothText($size, $x, $y, $string, $red, $green, $blue) {
		if(is_null($this->font) && ! @ is_readable($this->font)) {
			return $this->centeredText(2, $x, $y, $string, $red, $green, $blue);
		}
		if($box = @ imagettfbbox($size, 0.0, $this->font,  $string)) {
			$width = $box[2] - $box[0];
			$height = $box[5] - $box[1];
			$x = $x - round($width / 2);
			$y = $y - round($height / 2);
		}
		return @ imagettftext($this->image, $size, 0.0, $x, $y, $this->color($red, $green, $blue), $this->font, $string) ? true : $this->centeredText(2, $x, $y, $string, $red, $green, $blue);
	}
	
	private function randomAngle($angle) {
		return rand(- round($angle), round($angle));
	}
	
	public function fuzzyCenteredSmoothText($size, $x, $y, $string, $red, $green, $blue, $angle) {
		if(is_null($this->font) && ! @ is_readable($this->font)) {
			return $this->centeredText(2, $x, $y, $string, $red, $green, $blue);
		}
		
		if($box = @ imagettfbbox($size, 0.0, $this->font,  $string)) {
			$width = $box[2] - $box[0];
			$height = $box[5] - $box[1];
			$x = $x - round($width / 2);
			$y = $y - round($height / 2);
		}
		
		$step = round($width / ($length = strlen($string)));
		
		$left = $x;
		for($i = 0; $i < $length; $i ++) {
			imagettftext (
				$this->image,
				$size,
				$this->randomAngle($angle),
				$left,
				$y,
				$this->color($red, $green, $blue),
				$this->font,
				$string[$i]
			) ? true : $this->centeredText(2, $x, $y, $string, $red, $green, $blue);
			$left += $step;
		}
	}
	
	public function rightText($size, $x, $y, $string, $red, $green, $blue) {
		$x = $x - (imagefontwidth($size) * strlen($string));
		return $this->text($size, $x, $y, $string, $this->color($red, $green, $blue));
	}
	
	public function verticalText($size, $x, $y, $string, $red, $green, $blue) {
		return imagestringup($this->image, $size, $x, $y, $string, $this->color($red, $green, $blue));
	}
	
	function arrow($x1, $y1, $x2, $y2, $alength, $awidth, $red, $green, $blue) {
		if($alength > 1) {
			$this->arrow($x1, $y1, $x2, $y2, $alength - 1, $awidth - 1, $red, $green, $blue);
		}
		
		$distance = sqrt(pow($x1 - $x2, 2) + pow($y1 - $y2, 2));
		$dx = $x2 + ($x1 - $x2) * $alength / $distance;
		$dy = $y2 + ($y1 - $y2) * $alength / $distance;
		$k = $awidth / $alength;
		
		$x2o = $x2 - $dx;
		$y2o = $dy - $y2;
		$x3 = $y2o * $k + $dx;
		$y3 = $x2o * $k + $dy;
		$x4 = $dx - $y2o * $k;
		$y4 = $dy - $x2o * $k;
		
		imageline($this->image, $x1, $y1, $dx, $dy, $this->color($red, $green, $blue));
		imageline($this->image, $x3, $y3, $x4, $y4, $this->color($red, $green, $blue));
		imageline($this->image, $x3, $y3, $x2, $y2, $this->color($red, $green, $blue));
		imageline($this->image, $x2, $y2, $x4, $y4, $this->color($red, $green, $blue));
	}
	
	public function asBmp() {
		$result = '';
		$bpline = $this->width * 3;
		$stride = ($bpline + 3) & ~ 3;
		$size_image = $stride * $this->height;
		$off_bits = 54;
		$size = $off_bits + $size_image;
		
		$result .= 'BM';
		$result .= pack('VvvV', $size, 0, 0, $off_bits);
		$result .= pack('VVVvvVVVVVV', 40, $this->width, $this->height, 1, 24, 0, $size_image, 0, 0, 0, 0);
		
		$numpad = $stride - $bpline;
		for($y = $this->height - 1; $y >= 0; -- $y) {
			for($x = 0; $x < $this->width; ++ $x) $result .= pack('V', imagecolorat($this->image, $x, $y));
			for($i = 0; $i < $numpad; ++ $i) $result .= pack('C', 0);
		}
		return $result;
	}
	
	public function saveAsBmp($filename) {
		if(is_dir($filename) || !is_writeable(dirname($filename))) {
			return false;
		}
		
		return @ file_put_contents($filename, $this->asBmp());
	}
	
	public function saveAsPng($filename) {
		if(is_dir($filename) || !is_writeable(dirname($filename))) {
			return false;
		}
		@ imagesavealpha($this->image, true);
		return @ imagepng($this->image, $filename, 9);
	}
	
	public function saveAsJpeg($filename, $quality = 100, $progressive = false) {
		if(is_dir($filename) || !is_writeable(dirname($filename))) {
			return false;
		}
		
		if($progressive) {
			@ imageinterlace($this->image, true);
		}
		
		return @ imagejpeg($this->image, $filename, $quality);
	}
	
	public function asPng() {
		ob_start();
		@ imagesavealpha($this->image, true);
		imagepng($this->image, NULL, 9);
		$result = ob_get_contents();
		ob_clean();
		return $result;
	}
	
	public function asJpeg($quality = 100, $progressive = false) {
		if($progressive) {
			@ imageinterlace($this->image, true);
		}
		
		ob_start();
		imagejpeg($this->image, NULL, $quality);
		$result = ob_get_contents();
		ob_clean();
		return $result;
	}
	
	public function autoResize($maxwidth, $maxheight) {
		if($maxwidth == 0) $maxwidth = 4096;
		if($maxheight == 0) $maxheight = 4096;
		if($this->width > $maxwidth || $this->height > $maxheight) {
			if(($w = $this->width / $maxwidth) > ($h = $this->height / $maxheight)) {
				$dest = imagecreatetruecolor($maxwidth, ($destheight = floor($this->height / $w)));
				if(@ imagecopyresampled($dest, $this->image, 0, 0, 0, 0, $maxwidth, $destheight, $this->width, $this->height)) {
					imagedestroy($this->image);
					$this->image = $dest;
					$this->width = $maxwidth;
					$this->height = $destheight;
				}
			} else {
				$dest = imagecreatetruecolor(($destwidth = floor($this->width / $h)), $maxheight);
				if(@ imagecopyresampled($dest, $this->image, 0, 0, 0, 0, $destwidth, $maxheight, $this->width, $this->height)) {
					imagedestroy($this->image);
					$this->image = $dest;
					$this->width = $destwidth;
					$this->height = $maxheight;
				}
			}
		}
	}
	
	public function limitWidthTo($maxwidth) {
		return $this->autoResize($maxwidth, 0);
	}
	
	public function limitHeightTo($maxheight) {
		return $this->autoResize(0, $maxheight);
	}
	
	public function autoCrop($width, $height) {
		$dest = imagecreatetruecolor($width, $height);
		if(($w = $this->width / $width) > ($h = $this->height / $height)) {
			$h_offset = ($this->width / 2) - (($destwidth = $width * $h) / 2);
			if(@ imagecopyresampled($dest, $this->image, 0, 0, $h_offset, 0, $width, $height, $destwidth, $this->height)) {
				imagedestroy($this->image);
				$this->image = $dest;
				$this->width = $width;
				$this->height = $height;
			}
		} else {
			$v_offset = ($this->height / 2) - (($destheight = $height * $w) / 2);
			if(@ imagecopyresampled($dest, $this->image, 0, 0, 0, $v_offset, $width, $height, $this->width, $destheight)) {
				imagedestroy($this->image);
				$this->image = $dest;
				$this->width = $width;
				$this->height = $height;
			}
		}
	}
	
	public function includeExternalImage($external, $x = 0, $y = 0) {
		
		if($external instanceof self) {
			return @ imagecopy($this->image, $external->asImageResource(), $x, $y, 0, 0, $external->width(), $external->height());
		}
		
		if(! @ is_readable($external)) {
			return false;
		}
		
		$im = @ getimagesize($external);
		if($im[0] == 0) {
			return false;
		}
		
		$type = self::extByImageType($im[2]);
		$fx = 'imagecreatefrom' . ($type == 'jpg' ? 'jpeg' : $type);
		
		if(($source = @ $fx($external)) === false) {
			return false;
		}
		
		return @ imagecopy($this->image, $source, $x, $y, 0, 0, $im[0], $im[1]);
	}
	
	public function __toString() {
		return '<img src="data:image/png;base64,' . base64_encode($this->asPng()) . '" alt="Image" />';
	}
	
	public function replaceAlpha($r, $g, $b) {
		$background = new self($this->width, $this->height);
		$background->fillBackground($r, $g, $b);
		$background->includeExternalImage($this);
		imagedestroy($this->image);
		$this->image = $background->asImageResource();
	}
	
	public static function rgbToHsl($r, $g, $b) {
		$var_R = ($r / 255);
		$var_G = ($g / 255);
		$var_B = ($b / 255);
		
		$var_Min = min($var_R, $var_G, $var_B);
		$var_Max = max($var_R, $var_G, $var_B);
		$del_Max = $var_Max - $var_Min;
		
		$v = $var_Max;
		
		if($del_Max == 0) {
			$h = 0;
			$s = 0;
		} else {
			$s = $del_Max / $var_Max;
			$del_R = ((($var_Max - $var_R) / 6) + ($del_Max / 2)) / $del_Max;
			$del_G = ((($var_Max - $var_G) / 6) + ($del_Max / 2)) / $del_Max;
			$del_B = ((($var_Max - $var_B) / 6) + ($del_Max / 2)) / $del_Max;
			if($var_R == $var_Max) $h = $del_B - $del_G;
			else if($var_G == $var_Max) $h = (1 / 3) + $del_R - $del_B;
			else if($var_B == $var_Max) $h = (2 / 3) + $del_G - $del_R;
			if($h < 0) $h ++;
			if($h > 1) $h --;
		}
		return array($h, $s, $v);
	}
	
	public function asImageResource() {
		$destination = imagecreatetruecolor($this->width, $this->height);
		imagealphablending($destination, true);
		imagesavealpha($destination, true);
		imagecopy($destination, $this->image, 0, 0, 0, 0, $this->width, $this->height);
		return $destination;
	}

	public static function hslToRgb($h, $s, $v) {
		if($s == 0) {
			$r = $g = $B = $v * 255;
			return array($r, $g, $B);
		}
		
		$var_H = $h * 6;
		$var_i = floor($var_H);
		$var_1 = $v * (1 - $s);
		$var_2 = $v * (1 - $s * ($var_H - $var_i));
		$var_3 = $v * (1 - $s * (1 - ($var_H - $var_i)));
		
		switch($var_i) {
			case 0:
				$var_R = $v;
				$var_G = $var_3;
				$var_B = $var_1;
			break;
			case 1:
				$var_R = $var_2;
				$var_G = $v;
				$var_B = $var_1;
			break;
			case 2:
				$var_R = $var_1;
				$var_G = $v;
				$var_B = $var_3;
			break;
			case 3:
				$var_R = $var_1;
				$var_G = $var_2;
				$var_B = $v;
			break;
			case 4:
				$var_R = $var_3;
				$var_G = $var_1;
				$var_B = $v;
			break;
			default:
				$var_R = $v;
				$var_G = $var_1;
				$var_B = $var_2;
			break;
		}
		
		return array($var_R * 255, $var_G * 255, $var_B * 255);
	}
}

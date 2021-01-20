<?php
define('QR_MODE_8', 2);
// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

class qrstr {
	public static function set(&$srctab, $x, $y, $repl, $replLen = false) {
		$srctab[$y] = substr_replace($srctab[$y], ($replLen !== false)?substr($repl,0,$replLen):$repl, $x, ($replLen !== false)?$replLen:strlen($repl));
	}
}	

define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_FROM_RANDOM', 2);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false

class QRtools {
    public static function binarize($frame)
    {
        $len = count($frame);
        foreach ($frame as &$frameLine) {
            for($i=0; $i<$len; $i++) {
                $frameLine[$i] = (ord($frameLine[$i])&1)?'1':'0';
            }
        }
        return $frame;
    }
}

define('QRSPEC_VERSION_MAX', 40);
define('QRSPEC_WIDTH_MAX',   177);
define('QRCAP_WIDTH',        0);
define('QRCAP_WORDS',        1);
define('QRCAP_REMINDER',     2);
define('QRCAP_EC',           3);

    class QRspec {
    
        public static $capacity = array(
            array(  0,    0, 0, array(   0,    0,    0,    0)),
            array( 21,   26, 0, array(   7,   10,   13,   17)), // 1
            array( 25,   44, 7, array(  10,   16,   22,   28)),
            array( 29,   70, 7, array(  15,   26,   36,   44)),
            array( 33,  100, 7, array(  20,   36,   52,   64)),
            array( 37,  134, 7, array(  26,   48,   72,   88)), // 5
            array( 41,  172, 7, array(  36,   64,   96,  112)),
            array( 45,  196, 0, array(  40,   72,  108,  130)),
            array( 49,  242, 0, array(  48,   88,  132,  156)),
            array( 53,  292, 0, array(  60,  110,  160,  192)),
            array( 57,  346, 0, array(  72,  130,  192,  224)), //10
            array( 61,  404, 0, array(  80,  150,  224,  264)),
            array( 65,  466, 0, array(  96,  176,  260,  308)),
            array( 69,  532, 0, array( 104,  198,  288,  352)),
            array( 73,  581, 3, array( 120,  216,  320,  384)),
            array( 77,  655, 3, array( 132,  240,  360,  432)), //15
            array( 81,  733, 3, array( 144,  280,  408,  480)),
            array( 85,  815, 3, array( 168,  308,  448,  532)),
            array( 89,  901, 3, array( 180,  338,  504,  588)),
            array( 93,  991, 3, array( 196,  364,  546,  650)),
            array( 97, 1085, 3, array( 224,  416,  600,  700)), //20
            array(101, 1156, 4, array( 224,  442,  644,  750)),
            array(105, 1258, 4, array( 252,  476,  690,  816)),
            array(109, 1364, 4, array( 270,  504,  750,  900)),
            array(113, 1474, 4, array( 300,  560,  810,  960)),
            array(117, 1588, 4, array( 312,  588,  870, 1050)), //25
            array(121, 1706, 4, array( 336,  644,  952, 1110)),
            array(125, 1828, 4, array( 360,  700, 1020, 1200)),
            array(129, 1921, 3, array( 390,  728, 1050, 1260)),
            array(133, 2051, 3, array( 420,  784, 1140, 1350)),
            array(137, 2185, 3, array( 450,  812, 1200, 1440)), //30
            array(141, 2323, 3, array( 480,  868, 1290, 1530)),
            array(145, 2465, 3, array( 510,  924, 1350, 1620)),
            array(149, 2611, 3, array( 540,  980, 1440, 1710)),
            array(153, 2761, 3, array( 570, 1036, 1530, 1800)),
            array(157, 2876, 0, array( 570, 1064, 1590, 1890)), //35
            array(161, 3034, 0, array( 600, 1120, 1680, 1980)),
            array(165, 3196, 0, array( 630, 1204, 1770, 2100)),
            array(169, 3362, 0, array( 660, 1260, 1860, 2220)),
            array(173, 3532, 0, array( 720, 1316, 1950, 2310)),
            array(177, 3706, 0, array( 750, 1372, 2040, 2430)) //40
        );
        
        //----------------------------------------------------------------------
        public static function getDataLength($version, $level)
        {
            return self::$capacity[$version][QRCAP_WORDS] - self::$capacity[$version][QRCAP_EC][$level];
        }
        
        //----------------------------------------------------------------------
        public static function getECCLength($version, $level)
        {
            return self::$capacity[$version][QRCAP_EC][$level];
        }
        
        //----------------------------------------------------------------------
        public static function getWidth($version)
        {
            return self::$capacity[$version][QRCAP_WIDTH];
        }
        
        //----------------------------------------------------------------------
        public static function getRemainder($version)
        {
            return self::$capacity[$version][QRCAP_REMINDER];
        }
        
        //----------------------------------------------------------------------
        public static function getMinimumVersion($size, $level)
        {

            for($i=1; $i<= QRSPEC_VERSION_MAX; $i++) {
                $words  = self::$capacity[$i][QRCAP_WORDS] - self::$capacity[$i][QRCAP_EC][$level];
                if($words >= $size) 
                    return $i;
            }

            return -1;
        }
    
        //######################################################################
        
        public static $lengthTableBits = array(
            array(10, 12, 14),
            array( 9, 11, 13),
            array( 8, 16, 16),
            array( 8, 10, 12)
        );
        
        //----------------------------------------------------------------------
        public static function lengthIndicator($mode, $version)
        {

            if ($version <= 9) {
                $l = 0;
            } else if ($version <= 26) {
                $l = 1;
            } else {
                $l = 2;
            }

            return self::$lengthTableBits[$mode][$l];
        }
        
        //----------------------------------------------------------------------
        public static function maximumWords($mode, $version)
        {
            if($version <= 9) {
                $l = 0;
            } else if($version <= 26) {
                $l = 1;
            } else {
                $l = 2;
            }

            $bits = self::$lengthTableBits[$mode][$l];
            $words = (1 << $bits) - 1;

            return $words;
        }

        // Error correction code -----------------------------------------------
        // Table of the error correction code (Reed-Solomon block)
        // See Table 12-16 (pp.30-36), JIS X0510:2004.

        public static $eccTable = array(
            array(array( 0,  0), array( 0,  0), array( 0,  0), array( 0,  0)),
            array(array( 1,  0), array( 1,  0), array( 1,  0), array( 1,  0)), // 1
            array(array( 1,  0), array( 1,  0), array( 1,  0), array( 1,  0)),
            array(array( 1,  0), array( 1,  0), array( 2,  0), array( 2,  0)),
            array(array( 1,  0), array( 2,  0), array( 2,  0), array( 4,  0)),
            array(array( 1,  0), array( 2,  0), array( 2,  2), array( 2,  2)), // 5
            array(array( 2,  0), array( 4,  0), array( 4,  0), array( 4,  0)),
            array(array( 2,  0), array( 4,  0), array( 2,  4), array( 4,  1)),
            array(array( 2,  0), array( 2,  2), array( 4,  2), array( 4,  2)),
            array(array( 2,  0), array( 3,  2), array( 4,  4), array( 4,  4)),
            array(array( 2,  2), array( 4,  1), array( 6,  2), array( 6,  2)), //10
            array(array( 4,  0), array( 1,  4), array( 4,  4), array( 3,  8)),
            array(array( 2,  2), array( 6,  2), array( 4,  6), array( 7,  4)),
            array(array( 4,  0), array( 8,  1), array( 8,  4), array(12,  4)),
            array(array( 3,  1), array( 4,  5), array(11,  5), array(11,  5)),
            array(array( 5,  1), array( 5,  5), array( 5,  7), array(11,  7)), //15
            array(array( 5,  1), array( 7,  3), array(15,  2), array( 3, 13)),
            array(array( 1,  5), array(10,  1), array( 1, 15), array( 2, 17)),
            array(array( 5,  1), array( 9,  4), array(17,  1), array( 2, 19)),
            array(array( 3,  4), array( 3, 11), array(17,  4), array( 9, 16)),
            array(array( 3,  5), array( 3, 13), array(15,  5), array(15, 10)), //20
            array(array( 4,  4), array(17,  0), array(17,  6), array(19,  6)),
            array(array( 2,  7), array(17,  0), array( 7, 16), array(34,  0)),
            array(array( 4,  5), array( 4, 14), array(11, 14), array(16, 14)),
            array(array( 6,  4), array( 6, 14), array(11, 16), array(30,  2)),
            array(array( 8,  4), array( 8, 13), array( 7, 22), array(22, 13)), //25
            array(array(10,  2), array(19,  4), array(28,  6), array(33,  4)),
            array(array( 8,  4), array(22,  3), array( 8, 26), array(12, 28)),
            array(array( 3, 10), array( 3, 23), array( 4, 31), array(11, 31)),
            array(array( 7,  7), array(21,  7), array( 1, 37), array(19, 26)),
            array(array( 5, 10), array(19, 10), array(15, 25), array(23, 25)), //30
            array(array(13,  3), array( 2, 29), array(42,  1), array(23, 28)),
            array(array(17,  0), array(10, 23), array(10, 35), array(19, 35)),
            array(array(17,  1), array(14, 21), array(29, 19), array(11, 46)),
            array(array(13,  6), array(14, 23), array(44,  7), array(59,  1)),
            array(array(12,  7), array(12, 26), array(39, 14), array(22, 41)), //35
            array(array( 6, 14), array( 6, 34), array(46, 10), array( 2, 64)),
            array(array(17,  4), array(29, 14), array(49, 10), array(24, 46)),
            array(array( 4, 18), array(13, 32), array(48, 14), array(42, 32)),
            array(array(20,  4), array(40,  7), array(43, 22), array(10, 67)),
            array(array(19,  6), array(18, 31), array(34, 34), array(20, 61)),//40
        );                                                                       

        //----------------------------------------------------------------------
        // CACHEABLE!!!
        
        public static function getEccSpec($version, $level, array &$spec)
        {
            if (count($spec) < 5) {
                $spec = array(0,0,0,0,0);
            }

            $b1   = self::$eccTable[$version][$level][0];
            $b2   = self::$eccTable[$version][$level][1];
            $data = self::getDataLength($version, $level);
            $ecc  = self::getECCLength($version, $level);

            if($b2 == 0) {
                $spec[0] = $b1;
                $spec[1] = (int)($data / $b1);
                $spec[2] = (int)($ecc / $b1);
                $spec[3] = 0; 
                $spec[4] = 0;
            } else {
                $spec[0] = $b1;
                $spec[1] = (int)($data / ($b1 + $b2));
                $spec[2] = (int)($ecc  / ($b1 + $b2));
                $spec[3] = $b2;
                $spec[4] = $spec[1] + 1;
            }
        }

        // Alignment pattern ---------------------------------------------------

        // Positions of alignment patterns.
        // This array includes only the second and the third position of the 
        // alignment patterns. Rest of them can be calculated from the distance 
        // between them.
         
        // See Table 1 in Appendix E (pp.71) of JIS X0510:2004.
         
        public static $alignmentPattern = array(      
            array( 0,  0),
            array( 0,  0), array(18,  0), array(22,  0), array(26,  0), array(30,  0), // 1- 5
            array(34,  0), array(22, 38), array(24, 42), array(26, 46), array(28, 50), // 6-10
            array(30, 54), array(32, 58), array(34, 62), array(26, 46), array(26, 48), //11-15
            array(26, 50), array(30, 54), array(30, 56), array(30, 58), array(34, 62), //16-20
            array(28, 50), array(26, 50), array(30, 54), array(28, 54), array(32, 58), //21-25
            array(30, 58), array(34, 62), array(26, 50), array(30, 54), array(26, 52), //26-30
            array(30, 56), array(34, 60), array(30, 58), array(34, 62), array(30, 54), //31-35
            array(24, 50), array(28, 54), array(32, 58), array(26, 54), array(30, 58), //35-40
        );                                                                                  

        
        /** --------------------------------------------------------------------
         * Put an alignment marker.
         * @param frame
         * @param width
         * @param ox,oy center coordinate of the pattern
         */
        public static function putAlignmentMarker(array &$frame, $ox, $oy)
        {
            $finder = array(
                "\xa1\xa1\xa1\xa1\xa1",
                "\xa1\xa0\xa0\xa0\xa1",
                "\xa1\xa0\xa1\xa0\xa1",
                "\xa1\xa0\xa0\xa0\xa1",
                "\xa1\xa1\xa1\xa1\xa1"
            );                        
            
            $yStart = $oy-2;         
            $xStart = $ox-2;
            
            for($y=0; $y<5; $y++) {
                QRstr::set($frame, $xStart, $yStart+$y, $finder[$y]);
            }
        }

        //----------------------------------------------------------------------
        public static function putAlignmentPattern($version, &$frame, $width)
        {
            if($version < 2)
                return;

            $d = self::$alignmentPattern[$version][1] - self::$alignmentPattern[$version][0];
            if($d < 0) {
                $w = 2;
            } else {
                $w = (int)(($width - self::$alignmentPattern[$version][0]) / $d + 2);
            }

            if($w * $w - 3 == 1) {
                $x = self::$alignmentPattern[$version][0];
                $y = self::$alignmentPattern[$version][0];
                self::putAlignmentMarker($frame, $x, $y);
                return;
            }

            $cx = self::$alignmentPattern[$version][0];
            for($x=1; $x<$w - 1; $x++) {
                self::putAlignmentMarker($frame, 6, $cx);
                self::putAlignmentMarker($frame, $cx,  6);
                $cx += $d;
            }

            $cy = self::$alignmentPattern[$version][0];
            for($y=0; $y<$w-1; $y++) {
                $cx = self::$alignmentPattern[$version][0];
                for($x=0; $x<$w-1; $x++) {
                    self::putAlignmentMarker($frame, $cx, $cy);
                    $cx += $d;
                }
                $cy += $d;
            }
        }

        // Version information pattern -----------------------------------------

		// Version information pattern (BCH coded).
        // See Table 1 in Appendix D (pp.68) of JIS X0510:2004.
        
		// size: [QRSPEC_VERSION_MAX - 6]
		
        public static $versionPattern = array(
            0x07c94, 0x085bc, 0x09a99, 0x0a4d3, 0x0bbf6, 0x0c762, 0x0d847, 0x0e60d,
            0x0f928, 0x10b78, 0x1145d, 0x12a17, 0x13532, 0x149a6, 0x15683, 0x168c9,
            0x177ec, 0x18ec4, 0x191e1, 0x1afab, 0x1b08e, 0x1cc1a, 0x1d33f, 0x1ed75,
            0x1f250, 0x209d5, 0x216f0, 0x228ba, 0x2379f, 0x24b0b, 0x2542e, 0x26a64,
            0x27541, 0x28c69
        );

        //----------------------------------------------------------------------
        public static function getVersionPattern($version)
        {
            if($version < 7 || $version > QRSPEC_VERSION_MAX)
                return 0;

            return self::$versionPattern[$version -7];
        }

        // Format information --------------------------------------------------
        // See calcFormatInfo in tests/test_qrspec.c (orginal qrencode c lib)
        
        public static $formatInfo = array(
            array(0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976),
            array(0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0),
            array(0x355f, 0x3068, 0x3f31, 0x3a06, 0x24b4, 0x2183, 0x2eda, 0x2bed),
            array(0x1689, 0x13be, 0x1ce7, 0x19d0, 0x0762, 0x0255, 0x0d0c, 0x083b)
        );

        public static function getFormatInfo($mask, $level)
        {
            if($mask < 0 || $mask > 7)
                return 0;
                
            if($level < 0 || $level > 3)
                return 0;                

            return self::$formatInfo[$level][$mask];
        }

        // Frame ---------------------------------------------------------------
        // Cache of initial frames.
         
        public static $frames = array();

        /** --------------------------------------------------------------------
         * Put a finder pattern.
         * @param frame
         * @param width
         * @param ox,oy upper-left coordinate of the pattern
         */
        public static function putFinderPattern(&$frame, $ox, $oy)
        {
            $finder = array(
                "\xc1\xc1\xc1\xc1\xc1\xc1\xc1",
                "\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
                "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
                "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
                "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
                "\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
                "\xc1\xc1\xc1\xc1\xc1\xc1\xc1"
            );                            
            
            for($y=0; $y<7; $y++) {
                QRstr::set($frame, $ox, $oy+$y, $finder[$y]);
            }
        }

        //----------------------------------------------------------------------
        public static function createFrame($version)
        {
            $width = self::$capacity[$version][QRCAP_WIDTH];
            $frameLine = str_repeat ("\0", $width);
            $frame = array_fill(0, $width, $frameLine);

            // Finder pattern
            self::putFinderPattern($frame, 0, 0);
            self::putFinderPattern($frame, $width - 7, 0);
            self::putFinderPattern($frame, 0, $width - 7);
            
            // Separator
            $yOffset = $width - 7;
            
            for($y=0; $y<7; $y++) {
                $frame[$y][7] = "\xc0";
                $frame[$y][$width - 8] = "\xc0";
                $frame[$yOffset][7] = "\xc0";
                $yOffset++;
            }
            
            $setPattern = str_repeat("\xc0", 8);
            
            QRstr::set($frame, 0, 7, $setPattern);
            QRstr::set($frame, $width-8, 7, $setPattern);
            QRstr::set($frame, 0, $width - 8, $setPattern);
        
            // Format info
            $setPattern = str_repeat("\x84", 9);
            QRstr::set($frame, 0, 8, $setPattern);
            QRstr::set($frame, $width - 8, 8, $setPattern, 8);
            
            $yOffset = $width - 8;

            for($y=0; $y<8; $y++,$yOffset++) {
                $frame[$y][8] = "\x84";
                $frame[$yOffset][8] = "\x84";
            }

            // Timing pattern  
            
            for($i=1; $i<$width-15; $i++) {
                $frame[6][7+$i] = chr(0x90 | ($i & 1));
                $frame[7+$i][6] = chr(0x90 | ($i & 1));
            }
            
            // Alignment pattern  
            self::putAlignmentPattern($version, $frame, $width);
            
            // Version information 
            if($version >= 7) {
                $vinf = self::getVersionPattern($version);

                $v = $vinf;
                
                for($x=0; $x<6; $x++) {
                    for($y=0; $y<3; $y++) {
                        $frame[($width - 11)+$y][$x] = chr(0x88 | ($v & 1));
                        $v = $v >> 1;
                    }
                }

                $v = $vinf;
                for($y=0; $y<6; $y++) {
                    for($x=0; $x<3; $x++) {
                        $frame[$y][$x+($width - 11)] = chr(0x88 | ($v & 1));
                        $v = $v >> 1;
                    }
                }
            }
    
            // and a little bit...  
            $frame[$width - 8][8] = "\x81";
            
            return $frame;
        }

        //----------------------------------------------------------------------
        public static function newFrame($version)
        {
            if($version < 1 || $version > QRSPEC_VERSION_MAX) 
                return null;

            if(!isset(self::$frames[$version])) {
                
                self::$frames[$version] = self::createFrame($version);
            }
            
            if(is_null(self::$frames[$version]))
                return null;

            return self::$frames[$version];
        }

        //----------------------------------------------------------------------
        public static function rsBlockNum($spec)     { return $spec[0] + $spec[3]; }
        public static function rsBlockNum1($spec)    { return $spec[0]; }
        public static function rsDataCodes1($spec)   { return $spec[1]; }
        public static function rsEccCodes1($spec)    { return $spec[2]; }
        public static function rsBlockNum2($spec)    { return $spec[3]; }
        public static function rsDataCodes2($spec)   { return $spec[4]; }
        public static function rsEccCodes2($spec)    { return $spec[2]; }
        public static function rsDataLength($spec)   { return ($spec[0] * $spec[1]) + ($spec[3] * $spec[4]);    }
        public static function rsEccLength($spec)    { return ($spec[0] + $spec[3]) * $spec[2]; }
        
    }

    class QRinputItem {
    
        public $mode;
        public $size;
        public $data;
        public $bstream;

        public function __construct($mode, $size, $data, $bstream = null) 
        {
            $this->mode = $mode;
            $this->size = $size;
            $this->data = $data;
            $this->bstream = $bstream;
        }

        //----------------------------------------------------------------------
        public function encodeMode8($version)
        {
            try {
                $bs = new QRbitstream();

                $bs->appendNum(4, 0x4);
                $bs->appendNum(QRspec::lengthIndicator(QR_MODE_8, $version), $this->size);

                for($i=0; $i<$this->size; $i++) {
                    $bs->appendNum(8, ord($this->data[$i]));
                }

                $this->bstream = $bs;
                return 0;
            
            } catch (Exception $e) {
                return -1;
            }
        }

        //----------------------------------------------------------------------
        public function encodeModeStructure()
        {
            try {
                $bs =  new QRbitstream();
                
                $bs->appendNum(4, 0x03);
                $bs->appendNum(4, ord($this->data[1]) - 1);
                $bs->appendNum(4, ord($this->data[0]) - 1);
                $bs->appendNum(8, ord($this->data[2]));

                $this->bstream = $bs;
                return 0;
            
            } catch (Exception $e) {
                return -1;
            }
        }
        
        //----------------------------------------------------------------------
        public function estimateBitStreamSizeOfEntry($version)
        {
            $bits = 0;

            if($version == 0) 
                $version = 1;
			$bits = QRinput::estimateBitsMode8($this->size);


            $l = QRspec::lengthIndicator($this->mode, $version);
            $m = 1 << $l;
            $num = (int)(($this->size + $m - 1) / $m);

            $bits += $num * (4 + $l);

            return $bits;
        }
        
        //----------------------------------------------------------------------
        public function encodeBitStream($version)
        {
            try {
            
                unset($this->bstream);
                $words = QRspec::maximumWords($this->mode, $version);
                
                if($this->size > $words) {
                
                    $st1 = new QRinputItem($this->mode, $words, $this->data);
                    $st2 = new QRinputItem($this->mode, $this->size - $words, array_slice($this->data, $words));

                    $st1->encodeBitStream($version);
                    $st2->encodeBitStream($version);
                    
                    $this->bstream = new QRbitstream();
                    $this->bstream->append($st1->bstream);
                    $this->bstream->append($st2->bstream);
                    
                    unset($st1);
                    unset($st2);
                    
                } else {
                    $ret = $this->encodeMode8($version);
                    if($ret < 0)
                        return -1;
                }

                return $this->bstream->size();
            
            } catch (Exception $e) {
                return -1;
            }
        }
    };
    
    //##########################################################################

    class QRinput {

        public $items;
        
        private $version;
        private $level;
        
        //----------------------------------------------------------------------
        public function __construct($version = 0, $level = QR_ECLEVEL_L)
        {
            if ($version < 0 || $version > QRSPEC_VERSION_MAX || $level > QR_ECLEVEL_H) {
                throw new Exception('Invalid version no');
                return NULL;
            }
            
            $this->version = $version;
            $this->level = $level;
        }
        
        //----------------------------------------------------------------------
        public function getVersion()
        {
            return $this->version;
        }
        
        //----------------------------------------------------------------------
        public function setVersion($version)
        {
            if($version < 0 || $version > QRSPEC_VERSION_MAX) {
                throw new Exception('Invalid version no');
                return -1;
            }

            $this->version = $version;

            return 0;
        }
        
        //----------------------------------------------------------------------
        public function getErrorCorrectionLevel()
        {
            return $this->level;
        }

        //----------------------------------------------------------------------
        public function append($mode, $size, $data)
        {
            $this->items[] = new QRinputItem($mode, $size, $data);
        }
        

        //----------------------------------------------------------------------
        public static function estimateBitsMode8($size)
        {
            return $size * 8;
        }

        //----------------------------------------------------------------------
        public function estimateBitStreamSize($version)
        {
            $bits = 0;

            foreach($this->items as $item) {
                $bits += $item->estimateBitStreamSizeOfEntry($version);
            }

            return $bits;
        }
        
        //----------------------------------------------------------------------
        public function estimateVersion()
        {
            $version = 0;
            $prev = 0;
            do {
                $prev = $version;
                $bits = $this->estimateBitStreamSize($prev);
                $version = QRspec::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
                if ($version < 0) {
                    return -1;
                }
            } while ($version > $prev);

            return $version;
        }
        
        //----------------------------------------------------------------------
        public static function lengthOfCode($mode, $version, $bits)
        {
			$payload = $bits - 4 - QRspec::lengthIndicator($mode, $version);
			$size = (int)($payload / 8);

            
            $maxsize = QRspec::maximumWords($mode, $version);
            if($size < 0) $size = 0;
            if($size > $maxsize) $size = $maxsize;

            return $size;
        }
        
        //----------------------------------------------------------------------
        public function createBitStream()
        {
            $total = 0;

            foreach($this->items as $item) {
                $bits = $item->encodeBitStream($this->version);
                
                if($bits < 0) 
                    return -1;
                    
                $total += $bits;
            }

            return $total;
        }
        
        //----------------------------------------------------------------------
        public function convertData()
        {
            $ver = $this->estimateVersion();
            if($ver > $this->getVersion()) {
                $this->setVersion($ver);
            }

            for(;;) {
                $bits = $this->createBitStream();
                
                if($bits < 0) 
                    return -1;
                    
                $ver = QRspec::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
                if($ver < 0) {
                    throw new Exception('WRONG VERSION');
                    return -1;
                } else if($ver > $this->getVersion()) {
                    $this->setVersion($ver);
                } else {
                    break;
                }
            }

            return 0;
        }
        
        //----------------------------------------------------------------------
        public function appendPaddingBit(&$bstream)
        {
            $bits = $bstream->size();
            $maxwords = QRspec::getDataLength($this->version, $this->level);
            $maxbits = $maxwords * 8;

            if ($maxbits == $bits) {
                return 0;
            }

            if ($maxbits - $bits < 5) {
                return $bstream->appendNum($maxbits - $bits, 0);
            }

            $bits += 4;
            $words = (int)(($bits + 7) / 8);

            $padding = new QRbitstream();
            $ret = $padding->appendNum($words * 8 - $bits + 4, 0);
            
            if($ret < 0) 
                return $ret;

            $padlen = $maxwords - $words;
            
            if($padlen > 0) {
                
                $padbuf = array();
                for($i=0; $i<$padlen; $i++) {
                    $padbuf[$i] = ($i&1)?0x11:0xec;
                }
                
                $ret = $padding->appendBytes($padlen, $padbuf);
                
                if($ret < 0)
                    return $ret;
                
            }

            $ret = $bstream->append($padding);
            
            return $ret;
        }

        //----------------------------------------------------------------------
        public function mergeBitStream()
        {
            if($this->convertData() < 0) {
                return null;
            }

            $bstream = new QRbitstream();
            
            foreach($this->items as $item) {
                $ret = $bstream->append($item->bstream);
                if($ret < 0) {
                    return null;
                }
            }

            return $bstream;
        }

        //----------------------------------------------------------------------
        public function getBitStream()
        {

            $bstream = $this->mergeBitStream();
            
            if($bstream == null) {
                return null;
            }
            
            $ret = $this->appendPaddingBit($bstream);
            if($ret < 0) {
                return null;
            }

            return $bstream;
        }
        
        //----------------------------------------------------------------------
        public function getByteStream()
        {
            $bstream = $this->getBitStream();
            if($bstream == null) {
                return null;
            }
            
            return $bstream->toByte();
        }
    }
        



    class QRbitstream {
    
        public $data = array();
        
        //----------------------------------------------------------------------
        public function size()
        {
            return count($this->data);
        }
        
        //----------------------------------------------------------------------
        public function allocate($setLength)
        {
            $this->data = array_fill(0, $setLength, 0);
            return 0;
        }
    
        //----------------------------------------------------------------------
        public static function newFromNum($bits, $num)
        {
            $bstream = new QRbitstream();
            $bstream->allocate($bits);
            
            $mask = 1 << ($bits - 1);
            for($i=0; $i<$bits; $i++) {
                if($num & $mask) {
                    $bstream->data[$i] = 1;
                } else {
                    $bstream->data[$i] = 0;
                }
                $mask = $mask >> 1;
            }

            return $bstream;
        }
        
        //----------------------------------------------------------------------
        public static function newFromBytes($size, $data)
        {
            $bstream = new QRbitstream();
            $bstream->allocate($size * 8);
            $p=0;

            for($i=0; $i<$size; $i++) {
                $mask = 0x80;
                for($j=0; $j<8; $j++) {
                    if($data[$i] & $mask) {
                        $bstream->data[$p] = 1;
                    } else {
                        $bstream->data[$p] = 0;
                    }
                    $p++;
                    $mask = $mask >> 1;
                }
            }

            return $bstream;
        }
        
        //----------------------------------------------------------------------
        public function append(QRbitstream $arg)
        {
            if (is_null($arg)) {
                return -1;
            }
            
            if($arg->size() == 0) {
                return 0;
            }
            
            if($this->size() == 0) {
                $this->data = $arg->data;
                return 0;
            }
            
            $this->data = array_values(array_merge($this->data, $arg->data));

            return 0;
        }
        
        //----------------------------------------------------------------------
        public function appendNum($bits, $num)
        {
            if ($bits == 0) 
                return 0;

            $b = QRbitstream::newFromNum($bits, $num);
            
            if(is_null($b))
                return -1;

            $ret = $this->append($b);
            unset($b);

            return $ret;
        }

        //----------------------------------------------------------------------
        public function appendBytes($size, $data)
        {
            if ($size == 0) 
                return 0;

            $b = QRbitstream::newFromBytes($size, $data);
            
            if(is_null($b))
                return -1;

            $ret = $this->append($b);
            unset($b);

            return $ret;
        }
        
        //----------------------------------------------------------------------
        public function toByte()
        {
        
            $size = $this->size();

            if($size == 0) {
                return array();
            }
            
            $data = array_fill(0, (int)(($size + 7) / 8), 0);
            $bytes = (int)($size / 8);

            $p = 0;
            
            for($i=0; $i<$bytes; $i++) {
                $v = 0;
                for($j=0; $j<8; $j++) {
                    $v = $v << 1;
                    $v |= $this->data[$p];
                    $p++;
                }
                $data[$i] = $v;
            }
            
            if($size & 7) {
                $v = 0;
                for($j=0; $j<($size & 7); $j++) {
                    $v = $v << 1;
                    $v |= $this->data[$p];
                    $p++;
                }
                $data[$bytes] = $v;
            }

            return $data;
        }

    }

    class QRrsItem {
    
        public $mm;                  // Bits per symbol 
        public $nn;                  // Symbols per block (= (1<<mm)-1) 
        public $alpha_to = array();  // log lookup table 
        public $index_of = array();  // Antilog lookup table 
        public $genpoly = array();   // Generator polynomial 
        public $nroots;              // Number of generator roots = number of parity symbols 
        public $fcr;                 // First consecutive root, index form 
        public $prim;                // Primitive element, index form 
        public $iprim;               // prim-th root of 1, index form 
        public $pad;                 // Padding bytes in shortened block 
        public $gfpoly;
    
        //----------------------------------------------------------------------
        public function modnn($x)
        {
            while ($x >= $this->nn) {
                $x -= $this->nn;
                $x = ($x >> $this->mm) + ($x & $this->nn);
            }
            
            return $x;
        }
        
        //----------------------------------------------------------------------
        public static function init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
        {
            // Common code for intializing a Reed-Solomon control block (char or int symbols)
            // Copyright 2004 Phil Karn, KA9Q
            // May be used under the terms of the GNU Lesser General Public License (LGPL)

            $rs = null;
            
            // Check parameter ranges
            if($symsize < 0 || $symsize > 8)                     return $rs;
            if($fcr < 0 || $fcr >= (1<<$symsize))                return $rs;
            if($prim <= 0 || $prim >= (1<<$symsize))             return $rs;
            if($nroots < 0 || $nroots >= (1<<$symsize))          return $rs; // Can't have more roots than symbol values!
            if($pad < 0 || $pad >= ((1<<$symsize) -1 - $nroots)) return $rs; // Too much padding

            $rs = new QRrsItem();
            $rs->mm = $symsize;
            $rs->nn = (1<<$symsize)-1;
            $rs->pad = $pad;

            $rs->alpha_to = array_fill(0, $rs->nn+1, 0);
            $rs->index_of = array_fill(0, $rs->nn+1, 0);
          
            // PHP style macro replacement ;)
            $NN =& $rs->nn;
            $A0 =& $NN;
            
            // Generate Galois field lookup tables
            $rs->index_of[0] = $A0; // log(zero) = -inf
            $rs->alpha_to[$A0] = 0; // alpha**-inf = 0
            $sr = 1;
          
            for($i=0; $i<$rs->nn; $i++) {
                $rs->index_of[$sr] = $i;
                $rs->alpha_to[$i] = $sr;
                $sr <<= 1;
                if($sr & (1<<$symsize)) {
                    $sr ^= $gfpoly;
                }
                $sr &= $rs->nn;
            }
            
            if($sr != 1){
                // field generator polynomial is not primitive!
                $rs = NULL;
                return $rs;
            }

            /* Form RS code generator polynomial from its roots */
            $rs->genpoly = array_fill(0, $nroots+1, 0);
        
            $rs->fcr = $fcr;
            $rs->prim = $prim;
            $rs->nroots = $nroots;
            $rs->gfpoly = $gfpoly;

            /* Find prim-th root of 1, used in decoding */
            for($iprim=1;($iprim % $prim) != 0;$iprim += $rs->nn)
            ; // intentional empty-body loop!
            
            $rs->iprim = (int)($iprim / $prim);
            $rs->genpoly[0] = 1;
            
            for ($i = 0,$root=$fcr*$prim; $i < $nroots; $i++, $root += $prim) {
                $rs->genpoly[$i+1] = 1;

                // Multiply rs->genpoly[] by  @**(root + x)
                for ($j = $i; $j > 0; $j--) {
                    if ($rs->genpoly[$j] != 0) {
                        $rs->genpoly[$j] = $rs->genpoly[$j-1] ^ $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[$j]] + $root)];
                    } else {
                        $rs->genpoly[$j] = $rs->genpoly[$j-1];
                    }
                }
                // rs->genpoly[0] can never be zero
                $rs->genpoly[0] = $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[0]] + $root)];
            }
            
            // convert rs->genpoly[] to index form for quicker encoding
            for ($i = 0; $i <= $nroots; $i++)
                $rs->genpoly[$i] = $rs->index_of[$rs->genpoly[$i]];

            return $rs;
        }
        
        //----------------------------------------------------------------------
        public function encode_rs_char($data, &$parity)
        {
            $MM       =& $this->mm;
            $NN       =& $this->nn;
            $ALPHA_TO =& $this->alpha_to;
            $INDEX_OF =& $this->index_of;
            $GENPOLY  =& $this->genpoly;
            $NROOTS   =& $this->nroots;
            $FCR      =& $this->fcr;
            $PRIM     =& $this->prim;
            $IPRIM    =& $this->iprim;
            $PAD      =& $this->pad;
            $A0       =& $NN;

            $parity = array_fill(0, $NROOTS, 0);

            for($i=0; $i< ($NN-$NROOTS-$PAD); $i++) {
                
                $feedback = $INDEX_OF[$data[$i] ^ $parity[0]];
                if($feedback != $A0) {      
                    // feedback term is non-zero
            
                    // This line is unnecessary when GENPOLY[NROOTS] is unity, as it must
                    // always be for the polynomials constructed by init_rs()
                    $feedback = $this->modnn($NN - $GENPOLY[$NROOTS] + $feedback);
            
                    for($j=1;$j<$NROOTS;$j++) {
                        $parity[$j] ^= $ALPHA_TO[$this->modnn($feedback + $GENPOLY[$NROOTS-$j])];
                    }
                }
                
                // Shift 
                array_shift($parity);
                if($feedback != $A0) {
                    array_push($parity, $ALPHA_TO[$this->modnn($feedback + $GENPOLY[0])]);
                } else {
                    array_push($parity, 0);
                }
            }
        }
    }
    
    //##########################################################################
    
    class QRrs {
    
        public static $items = array();
        
        //----------------------------------------------------------------------
        public static function init_rs($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
        {
            foreach(self::$items as $rs) {
                if($rs->pad != $pad)       continue;
                if($rs->nroots != $nroots) continue;
                if($rs->mm != $symsize)    continue;
                if($rs->gfpoly != $gfpoly) continue;
                if($rs->fcr != $fcr)       continue;
                if($rs->prim != $prim)     continue;

                return $rs;
            }

            $rs = QRrsItem::init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad);
            array_unshift(self::$items, $rs);

            return $rs;
        }
    }

 
	define('N1', 3);
	define('N2', 3);
	define('N3', 40);
	define('N4', 10);

	class QRmask {
	
		public $runLength = array();
		
		//----------------------------------------------------------------------
		public function __construct() 
        {
            $this->runLength = array_fill(0, QRSPEC_WIDTH_MAX + 1, 0);
        }
        
        //----------------------------------------------------------------------
        public function writeFormatInformation($width, &$frame, $mask, $level)
        {
            $blacks = 0;
            $format =  QRspec::getFormatInfo($mask, $level);

            for($i=0; $i<8; $i++) {
                if($format & 1) {
                    $blacks += 2;
                    $v = 0x85;
                } else {
                    $v = 0x84;
                }
                
                $frame[8][$width - 1 - $i] = chr($v);
                if($i < 6) {
                    $frame[$i][8] = chr($v);
                } else {
                    $frame[$i + 1][8] = chr($v);
                }
                $format = $format >> 1;
            }
            
            for($i=0; $i<7; $i++) {
                if($format & 1) {
                    $blacks += 2;
                    $v = 0x85;
                } else {
                    $v = 0x84;
                }
                
                $frame[$width - 7 + $i][8] = chr($v);
                if($i == 0) {
                    $frame[8][7] = chr($v);
                } else {
                    $frame[8][6 - $i] = chr($v);
                }
                
                $format = $format >> 1;
            }

            return $blacks;
        }
        
        //----------------------------------------------------------------------
        public function mask0($x, $y) { return ($x+$y)&1;                       }
        public function mask1($x, $y) { return ($y&1);                          }
        public function mask2($x, $y) { return ($x%3);                          }
        public function mask3($x, $y) { return ($x+$y)%3;                       }
        public function mask4($x, $y) { return (((int)($y/2))+((int)($x/3)))&1; }
        public function mask5($x, $y) { return (($x*$y)&1)+($x*$y)%3;           }
        public function mask6($x, $y) { return ((($x*$y)&1)+($x*$y)%3)&1;       }
        public function mask7($x, $y) { return ((($x*$y)%3)+(($x+$y)&1))&1;     }
        
        //----------------------------------------------------------------------
        private function generateMaskNo($maskNo, $width, $frame)
        {
            $bitMask = array_fill(0, $width, array_fill(0, $width, 0));
            
            for($y=0; $y<$width; $y++) {
                for($x=0; $x<$width; $x++) {
                    if(ord($frame[$y][$x]) & 0x80) {
                        $bitMask[$y][$x] = 0;
                    } else {
                        $maskFunc = call_user_func(array($this, 'mask'.$maskNo), $x, $y);
                        $bitMask[$y][$x] = ($maskFunc == 0)?1:0;
                    }
                    
                }
            }
            
            return $bitMask;
        }
  
        //----------------------------------------------------------------------
        public function makeMaskNo($maskNo, $width, $s, &$d, $maskGenOnly = false) 
        {
            $b = 0;
            $bitMask = $this->generateMaskNo($maskNo, $width, $s, $d);
            


            if ($maskGenOnly)
                return;
                
            $d = $s;

            for($y=0; $y<$width; $y++) {
                for($x=0; $x<$width; $x++) {
                    if($bitMask[$y][$x] == 1) {
                        $d[$y][$x] = chr(ord($s[$y][$x]) ^ (int)$bitMask[$y][$x]);
                    }
                    $b += (int)(ord($d[$y][$x]) & 1);
                }
            }

            return $b;
        }
        
        //----------------------------------------------------------------------
        public function makeMask($width, $frame, $maskNo, $level)
        {
            $masked = array_fill(0, $width, str_repeat("\0", $width));
            $this->makeMaskNo($maskNo, $width, $frame, $masked);
            $this->writeFormatInformation($width, $masked, $maskNo, $level);
       
            return $masked;
        }
        
        //----------------------------------------------------------------------
        public function calcN1N3($length)
        {
            $demerit = 0;

            for($i=0; $i<$length; $i++) {
                
                if($this->runLength[$i] >= 5) {
                    $demerit += (N1 + ($this->runLength[$i] - 5));
                }
                if($i & 1) {
                    if(($i >= 3) && ($i < ($length-2)) && ($this->runLength[$i] % 3 == 0)) {
                        $fact = (int)($this->runLength[$i] / 3);
                        if(($this->runLength[$i-2] == $fact) &&
                           ($this->runLength[$i-1] == $fact) &&
                           ($this->runLength[$i+1] == $fact) &&
                           ($this->runLength[$i+2] == $fact)) {
                            if(($this->runLength[$i-3] < 0) || ($this->runLength[$i-3] >= (4 * $fact))) {
                                $demerit += N3;
                            } else if((($i+3) >= $length) || ($this->runLength[$i+3] >= (4 * $fact))) {
                                $demerit += N3;
                            }
                        }
                    }
                }
            }
            return $demerit;
        }
        
        //----------------------------------------------------------------------
        public function evaluateSymbol($width, $frame)
        {
            $head = 0;
            $demerit = 0;

            for($y=0; $y<$width; $y++) {
                $head = 0;
                $this->runLength[0] = 1;
                
                $frameY = $frame[$y];
                
                if ($y>0)
                    $frameYM = $frame[$y-1];
                
                for($x=0; $x<$width; $x++) {
                    if(($x > 0) && ($y > 0)) {
                        $b22 = ord($frameY[$x]) & ord($frameY[$x-1]) & ord($frameYM[$x]) & ord($frameYM[$x-1]);
                        $w22 = ord($frameY[$x]) | ord($frameY[$x-1]) | ord($frameYM[$x]) | ord($frameYM[$x-1]);
                        
                        if(($b22 | ($w22 ^ 1))&1) {                                                                     
                            $demerit += N2;
                        }
                    }
                    if(($x == 0) && (ord($frameY[$x]) & 1)) {
                        $this->runLength[0] = -1;
                        $head = 1;
                        $this->runLength[$head] = 1;
                    } else if($x > 0) {
                        if((ord($frameY[$x]) ^ ord($frameY[$x-1])) & 1) {
                            $head++;
                            $this->runLength[$head] = 1;
                        } else {
                            $this->runLength[$head]++;
                        }
                    }
                }
    
                $demerit += $this->calcN1N3($head+1);
            }

            for($x=0; $x<$width; $x++) {
                $head = 0;
                $this->runLength[0] = 1;
                
                for($y=0; $y<$width; $y++) {
                    if($y == 0 && (ord($frame[$y][$x]) & 1)) {
                        $this->runLength[0] = -1;
                        $head = 1;
                        $this->runLength[$head] = 1;
                    } else if($y > 0) {
                        if((ord($frame[$y][$x]) ^ ord($frame[$y-1][$x])) & 1) {
                            $head++;
                            $this->runLength[$head] = 1;
                        } else {
                            $this->runLength[$head]++;
                        }
                    }
                }
            
                $demerit += $this->calcN1N3($head+1);
            }

            return $demerit;
        }
        
        
        //----------------------------------------------------------------------
        public function mask($width, $frame, $level)
        {
            $minDemerit = PHP_INT_MAX;
            $bestMaskNum = 0;
            $bestMask = array();
            
            $checked_masks = array(0,1,2,3,4,5,6,7);
            
            if (QR_FIND_FROM_RANDOM !== false) {
            
                $howManuOut = 8-(QR_FIND_FROM_RANDOM % 9);
                for ($i = 0; $i <  $howManuOut; $i++) {
                    $remPos = rand (0, count($checked_masks)-1);
                    unset($checked_masks[$remPos]);
                    $checked_masks = array_values($checked_masks);
                }
            
            }
            
            $bestMask = $frame;
             
            foreach($checked_masks as $i) {
                $mask = array_fill(0, $width, str_repeat("\0", $width));

                $demerit = 0;
                $blacks = 0;
                $blacks  = $this->makeMaskNo($i, $width, $frame, $mask);
                $blacks += $this->writeFormatInformation($width, $mask, $i, $level);
                $blacks  = (int)(100 * $blacks / ($width * $width));
                $demerit = (int)((int)(abs($blacks - 50) / 5) * N4);
                $demerit += $this->evaluateSymbol($width, $mask);
                
                if($demerit < $minDemerit) {
                    $minDemerit = $demerit;
                    $bestMask = $mask;
                    $bestMaskNum = $i;
                }
            }
            
            return $bestMask;
        }
        
        //----------------------------------------------------------------------
    }


    class QRrsblock {
        public $dataLength;
        public $data = array();
        public $eccLength;
        public $ecc = array();
        
        public function __construct($dl, $data, $el, &$ecc, QRrsItem $rs)
        {
            $rs->encode_rs_char($data, $ecc);
        
            $this->dataLength = $dl;
            $this->data = $data;
            $this->eccLength = $el;
            $this->ecc = $ecc;
        }
    };
    
    //##########################################################################

    class QRrawcode {
        public $version;
        public $datacode = array();
        public $ecccode = array();
        public $blocks;
        public $rsblocks = array(); //of RSblock
        public $count;
        public $dataLength;
        public $eccLength;
        public $b1;
        
        //----------------------------------------------------------------------
        public function __construct(QRinput $input)
        {
            $spec = array(0,0,0,0,0);
            
            $this->datacode = $input->getByteStream();
            if(is_null($this->datacode)) {
                throw new Exception('null imput string');
            }

            QRspec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

            $this->version = $input->getVersion();
            $this->b1 = QRspec::rsBlockNum1($spec);
            $this->dataLength = QRspec::rsDataLength($spec);
            $this->eccLength = QRspec::rsEccLength($spec);
            $this->ecccode = array_fill(0, $this->eccLength, 0);
            $this->blocks = QRspec::rsBlockNum($spec);
            
            $ret = $this->init($spec);
            if($ret < 0) {
                throw new Exception('block alloc error');
                return null;
            }

            $this->count = 0;
        }
        
        //----------------------------------------------------------------------
        public function init(array $spec)
        {
            $dl = QRspec::rsDataCodes1($spec);
            $el = QRspec::rsEccCodes1($spec);
            $rs = QRrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);
            

            $blockNo = 0;
            $dataPos = 0;
            $eccPos = 0;
            for($i=0; $i<QRspec::rsBlockNum1($spec); $i++) {
                $ecc = array_slice($this->ecccode,$eccPos);
                $this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($this->datacode, $dataPos), $el,  $ecc, $rs);
                $this->ecccode = array_merge(array_slice($this->ecccode,0, $eccPos), $ecc);
                
                $dataPos += $dl;
                $eccPos += $el;
                $blockNo++;
            }

            if(QRspec::rsBlockNum2($spec) == 0)
                return 0;

            $dl = QRspec::rsDataCodes2($spec);
            $el = QRspec::rsEccCodes2($spec);
            $rs = QRrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);
            
            if($rs == NULL) return -1;
            
            for($i=0; $i<QRspec::rsBlockNum2($spec); $i++) {
                $ecc = array_slice($this->ecccode,$eccPos);
                $this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
                $this->ecccode = array_merge(array_slice($this->ecccode,0, $eccPos), $ecc);
                
                $dataPos += $dl;
                $eccPos += $el;
                $blockNo++;
            }

            return 0;
        }
        
        //----------------------------------------------------------------------
        public function getCode()
        {
            $ret;

            if($this->count < $this->dataLength) {
                $row = $this->count % $this->blocks;
                $col = $this->count / $this->blocks;
                if($col >= $this->rsblocks[0]->dataLength) {
                    $row += $this->b1;
                }
                $ret = $this->rsblocks[$row]->data[$col];
            } else if($this->count < $this->dataLength + $this->eccLength) {
                $row = ($this->count - $this->dataLength) % $this->blocks;
                $col = ($this->count - $this->dataLength) / $this->blocks;
                $ret = $this->rsblocks[$row]->ecc[$col];
            } else {
                return 0;
            }
            $this->count++;
            
            return $ret;
        }
    }

    //##########################################################################
    
    class QRcode {
    
        public $version;
        public $width;
        public $data; 
        
        //----------------------------------------------------------------------
        public function encodeMask(QRinput $input)
        {
            if($input->getVersion() < 0 || $input->getVersion() > QRSPEC_VERSION_MAX) {
                throw new Exception('wrong version');
            }
            if($input->getErrorCorrectionLevel() > QR_ECLEVEL_H) {
                throw new Exception('wrong level');
            }

			$raw = new QRrawcode($input);

            $version = $raw->version;
            $width = QRspec::getWidth($version);
            $frame = QRspec::newFrame($version);
            
            $filler = new FrameFiller($width, $frame);
            if(is_null($filler)) {
                return NULL;
            }

            // inteleaved data and ecc codes
            for($i=0; $i<$raw->dataLength + $raw->eccLength; $i++) {
                $code = $raw->getCode();
                $bit = 0x80;
                for($j=0; $j<8; $j++) {
                    $addr = $filler->next();
                    $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                    $bit = $bit >> 1;
                }
            }
            
            unset($raw);
            
            // remainder bits
            $j = QRspec::getRemainder($version);
            for($i=0; $i<$j; $i++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02);
            }
            
            $frame = $filler->frame;
            unset($filler);
            
            
            // masking
            $maskObj = new QRmask();
            $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());

            $this->version = $version;
            $this->width = $width;
            $this->data = $masked;
            
            return $this;
        }
        public function encodeString8bit($string, $version, $level)
        {
			$input = new QRinput($version, $level);

			$input->append(QR_MODE_8, strlen($string), str_split($string));

			return $this->encodeMask($input, -1);
        }

    }
    
    //##########################################################################
    
    class FrameFiller {
    
        public $width;
        public $frame;
        public $x;
        public $y;
        public $dir;
        public $bit;
        
        //----------------------------------------------------------------------
        public function __construct($width, &$frame)
        {
            $this->width = $width;
            $this->frame = $frame;
            $this->x = $width - 1;
            $this->y = $width - 1;
            $this->dir = -1;
            $this->bit = -1;
        }
        
        //----------------------------------------------------------------------
        public function setFrameAt($at, $val)
        {
            $this->frame[$at['y']][$at['x']] = chr($val);
        }
        
        //----------------------------------------------------------------------
        public function getFrameAt($at)
        {
            return ord($this->frame[$at['y']][$at['x']]);
        }
        
        //----------------------------------------------------------------------
        public function next()
        {
            do {
            
                if($this->bit == -1) {
                    $this->bit = 0;
                    return array('x'=>$this->x, 'y'=>$this->y);
                }

                $x = $this->x;
                $y = $this->y;
                $w = $this->width;

                if($this->bit == 0) {
                    $x--;
                    $this->bit++;
                } else {
                    $x++;
                    $y += $this->dir;
                    $this->bit--;
                }

                if($this->dir < 0) {
                    if($y < 0) {
                        $y = 0;
                        $x -= 2;
                        $this->dir = 1;
                        if($x == 6) {
                            $x--;
                            $y = 9;
                        }
                    }
                } else {
                    if($y == $w) {
                        $y = $w - 1;
                        $x -= 2;
                        $this->dir = -1;
                        if($x == 6) {
                            $x--;
                            $y -= 8;
                        }
                    }
                }
                if($x < 0 || $y < 0) return null;

                $this->x = $x;
                $this->y = $y;

            } while(ord($this->frame[$y][$x]) & 0x80);
                        
            return array('x'=>$x, 'y'=>$y);
        }
        
    }
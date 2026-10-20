<?php
/**
 * A small PDF writer for invoices: A4 pages, Helvetica text, lines and
 * filled boxes. No plugin or library needed.
 *
 * Text uses the PDF standard fonts, which cover English and Western European
 * letters; anything else (e.g. Chinese names) is shown as "?".
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

class TP_PDF {
	const W = 595.28; // A4 in points.
	const H = 841.89;

	private $pages = array();
	private $cur   = '';
	public $y      = 0;

	public function __construct() {
		$this->add_page();
	}

	public function add_page() {
		if ( '' !== $this->cur ) {
			$this->pages[] = $this->cur;
		}
		$this->cur = '';
		$this->y   = 48;
	}

	/** Text width in points (Helvetica metrics, close enough for layout). */
	public static function width( $text, $size, $bold = false ) {
		static $narrow = array( 'i' => 222, 'j' => 222, 'l' => 222, 'f' => 278, 't' => 278, 'r' => 333, 'I' => 278, ' ' => 278, '.' => 278, ',' => 278, ':' => 278, ';' => 278, '\'' => 191, '|' => 260, '(' => 333, ')' => 333, '-' => 333, '/' => 278, '!' => 278 );
		static $wide   = array( 'm' => 833, 'w' => 722, 'M' => 833, 'W' => 944, '@' => 1015, '%' => 889 );
		$w = 0;
		foreach ( str_split( self::enc( $text ) ) as $ch ) {
			if ( isset( $narrow[ $ch ] ) ) {
				$w += $narrow[ $ch ];
			} elseif ( isset( $wide[ $ch ] ) ) {
				$w += $wide[ $ch ];
			} elseif ( ctype_upper( $ch ) ) {
				$w += 667;
			} else {
				$w += 556; // Digits, $, most lowercase.
			}
		}
		return $w * $size / 1000 * ( $bold ? 1.06 : 1 );
	}

	/** UTF-8 → WinAnsi (Windows-1252) for the standard fonts. */
	private static function enc( $text ) {
		$text = strtr( (string) $text, array( '→' => '->', '★' => '*', '✓' => 'v' ) );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$out = @mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false === $out ? preg_replace( '/[^\x20-\x7E]/', '?', $text ) : $out;
		}
		return preg_replace( '/[^\x20-\x7E]/', '?', $text );
	}

	private static function esc( $text ) {
		return strtr( self::enc( $text ), array( '\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ' ) );
	}

	private static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$n = hexdec( substr( str_pad( $hex, 6, '0' ), 0, 6 ) );
		return sprintf( '%.3F %.3F %.3F', ( $n >> 16 & 255 ) / 255, ( $n >> 8 & 255 ) / 255, ( $n & 255 ) / 255 );
	}

	/**
	 * Write text. $x/$y from the top-left, in points. $align: left|right (x is the right edge)|center.
	 */
	public function text( $x, $y, $text, $size = 10, $bold = false, $color = '#16213a', $align = 'left' ) {
		if ( 'right' === $align ) {
			$x -= self::width( $text, $size, $bold );
		} elseif ( 'center' === $align ) {
			$x -= self::width( $text, $size, $bold ) / 2;
		}
		$this->cur .= sprintf( "BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", self::rgb( $color ), $bold ? 'F2' : 'F1', $size, $x, self::H - $y, self::esc( $text ) );
	}

	/** Cut text to fit a width, adding "…". */
	public static function fit( $text, $max, $size, $bold = false ) {
		$text = (string) $text;
		if ( self::width( $text, $size, $bold ) <= $max ) {
			return $text;
		}
		while ( '' !== $text && self::width( $text . '...', $size, $bold ) > $max ) {
			$text = mb_substr( $text, 0, -1 );
		}
		return rtrim( $text ) . '...';
	}

	/** Split text into lines that fit a width. */
	public static function wrap( $text, $max, $size, $bold = false ) {
		$lines = array();
		foreach ( preg_split( '/\R/', (string) $text ) as $para ) {
			$line = '';
			foreach ( preg_split( '/\s+/', trim( $para ) ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( '' !== $line && self::width( $try, $size, $bold ) > $max ) {
					$lines[] = $line;
					$line    = $word;
				} else {
					$line = $try;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	public function line( $x1, $y1, $x2, $y2, $color = '#d5dcea', $width = 0.75 ) {
		$this->cur .= sprintf( "%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::rgb( $color ), $width, $x1, self::H - $y1, $x2, self::H - $y2 );
	}

	public function box( $x, $y, $w, $h, $color ) {
		$this->cur .= sprintf( "%s rg %.2F %.2F %.2F %.2F re f\n", self::rgb( $color ), $x, self::H - $y - $h, $w, $h );
	}

	/** The finished file. */
	public function output() {
		$pages   = array_merge( $this->pages, array( $this->cur ) );
		$objects = array(
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
			4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
		);
		$kids = array();
		$n    = 5;
		foreach ( $pages as $content ) {
			$objects[ $n ]     = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $n + 1 );
			$objects[ $n + 1 ] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
			$kids[]            = $n . ' 0 R';
			$n                += 2;
		}
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
		ksort( $objects );
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $i => $body ) {
			$offsets[ $i ] = strlen( $out );
			$out          .= $i . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $off ) {
			$out .= sprintf( "%010d 00000 n \n", $off );
		}
		$out .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $out;
	}
}

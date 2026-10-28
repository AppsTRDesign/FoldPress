<?php
/**
 * Document previews rendered on the server (no third party): Word, Excel, PowerPoint,
 * OpenDocument, text, Markdown, CSV and ZIP listings. Every text node is escaped; only a
 * fixed set of tags is produced. Optional full-layout view via Microsoft Office Online.
 *
 * @package FoldPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FoldPress_Preview
 */
class FoldPress_Preview {

	const MAX_XML   = 8388608;  // 8 MB uncompressed XML part.
	const MAX_TEXT  = 524288;   // 512 KB of text.
	const MAX_ROWS  = 300;
	const MAX_COLS  = 40;
	const MAX_SHEET = 8;

	const TEXT_EXT   = array( 'txt', 'text', 'log', 'md', 'markdown', 'json', 'xml', 'css', 'scss', 'less', 'yaml', 'yml', 'ini', 'sql', 'srt', 'vtt', 'java', 'cs', 'c', 'cpp', 'h', 'py', 'rb', 'go', 'ts', 'rtf' );
	const OFFICE_EXT = array( 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp' );

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_foldpress_preview', array( $this, 'ajax' ) );
	}

	/**
	 * Extensions the viewer can show.
	 *
	 * @return string[]
	 */
	public static function extensions() {
		return array_merge( self::TEXT_EXT, self::OFFICE_EXT, array( 'csv', 'tsv', 'zip' ) );
	}

	/**
	 * Office Online viewer URL (only for publicly reachable sites).
	 *
	 * @param string $url File URL.
	 * @return string
	 */
	private static function office_url( $url ) {
		if ( ! FoldPress_Settings::get( 'office_viewer' ) ) {
			return '';
		}
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || 'localhost' === $host || preg_match( '/\.(local|test|localhost|lan)$/', $host ) || ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) {
			return '';
		}
		return 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode( $url );
	}

	/**
	 * AJAX: build a preview.
	 */
	public function ajax() {
		check_ajax_referer( FoldPress_Ajax::NONCE, 'nonce' );
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'upload_files' ) || ! current_user_can( 'read_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'appstrdesign-foldpress' ) ), 403 );
		}
		$path = (string) get_attached_file( $id );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! is_readable( $path ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found on disk.', 'appstrdesign-foldpress' ) ) );
		}
		$out = array(
			'kind'   => 'none',
			'office' => in_array( $ext, self::OFFICE_EXT, true ) ? self::office_url( (string) wp_get_attachment_url( $id ) ) : '',
		);
		try {
			if ( 'docx' === $ext ) {
				$out = array_merge( $out, array( 'kind' => 'doc', 'html' => self::docx( $path ) ) );
			} elseif ( 'xlsx' === $ext ) {
				$out = array_merge( $out, array( 'kind' => 'sheets', 'sheets' => self::xlsx( $path ) ) );
			} elseif ( 'pptx' === $ext ) {
				$out = array_merge( $out, array( 'kind' => 'slides', 'slides' => self::pptx( $path ) ) );
			} elseif ( in_array( $ext, array( 'odt', 'odp', 'ods' ), true ) ) {
				$out = array_merge( $out, array( 'kind' => 'doc', 'html' => self::odf( $path ) ) );
			} elseif ( in_array( $ext, array( 'csv', 'tsv' ), true ) ) {
				$out = array_merge( $out, array( 'kind' => 'sheets', 'sheets' => array( self::csv( $path, 'tsv' === $ext ? "\t" : null ) ) ) );
			} elseif ( 'zip' === $ext ) {
				$out = array_merge( $out, array( 'kind' => 'zip', 'entries' => self::zip( $path ) ) );
			} elseif ( 'rtf' === $ext ) {
				$out = array_merge( $out, array( 'kind' => 'text', 'text' => self::rtf( $path ), 'lang' => 'txt' ) );
			} elseif ( in_array( $ext, self::TEXT_EXT, true ) ) {
				$text = (string) file_get_contents( $path, false, null, 0, self::MAX_TEXT ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$out  = array_merge(
					$out,
					array(
						'kind'      => in_array( $ext, array( 'md', 'markdown' ), true ) ? 'markdown' : 'text',
						'text'      => wp_check_invalid_utf8( $text, true ),
						'lang'      => $ext,
						'truncated' => filesize( $path ) > self::MAX_TEXT,
					)
				);
			}
		} catch ( Exception $e ) {
			$out['kind']  = 'none';
			$out['error'] = __( 'This document could not be read for preview.', 'appstrdesign-foldpress' );
		}
		wp_send_json_success( $out );
	}

	/* ===================== Helpers ===================== */

	/**
	 * Read one XML part from a zip container safely.
	 *
	 * @param ZipArchive $zip  Archive.
	 * @param string     $name Entry.
	 * @return DOMDocument|null
	 */
	private static function part( $zip, $name ) {
		$st = $zip->statName( $name );
		if ( ! $st || $st['size'] > self::MAX_XML ) {
			return null;
		}
		$xml = $zip->getFromName( $name );
		if ( ! $xml || false !== stripos( $xml, '<!DOCTYPE' ) || false !== stripos( $xml, '<!ENTITY' ) ) {
			return null;
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $ok ? $dom : null;
	}

	/**
	 * Open a zip container.
	 *
	 * @param string $path Path.
	 * @return ZipArchive
	 * @throws Exception When it cannot be opened.
	 */
	private static function open( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'zip' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			throw new Exception( 'open' );
		}
		return $zip;
	}

	/**
	 * Word (.docx) to simple HTML.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function docx( $path ) {
		$zip = self::open( $path );
		$dom = self::part( $zip, 'word/document.xml' );
		$zip->close();
		if ( ! $dom ) {
			throw new Exception( 'doc' );
		}
		$xp = new DOMXPath( $dom );
		$xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
		$body = $xp->query( '//w:body' )->item( 0 );
		return $body ? self::docx_children( $xp, $body ) : '';
	}

	/**
	 * Render paragraphs / tables under a node.
	 *
	 * @param DOMXPath $xp   XPath.
	 * @param DOMNode  $node Node.
	 * @return string
	 */
	private static function docx_children( $xp, $node ) {
		$html = '';
		foreach ( $node->childNodes as $c ) {
			if ( 'w:p' === $c->nodeName ) {
				$html .= self::docx_p( $xp, $c );
			} elseif ( 'w:tbl' === $c->nodeName ) {
				$html .= '<table>';
				foreach ( $xp->query( 'w:tr', $c ) as $tr ) {
					$html .= '<tr>';
					foreach ( $xp->query( 'w:tc', $tr ) as $tc ) {
						$html .= '<td>' . self::docx_children( $xp, $tc ) . '</td>';
					}
					$html .= '</tr>';
				}
				$html .= '</table>';
			} elseif ( 'w:sdt' === $c->nodeName ) {
				$content = $xp->query( 'w:sdtContent', $c )->item( 0 );
				if ( $content ) {
					$html .= self::docx_children( $xp, $content );
				}
			}
		}
		return $html;
	}

	/**
	 * One paragraph.
	 *
	 * @param DOMXPath $xp XPath.
	 * @param DOMNode  $p  Paragraph.
	 * @return string
	 */
	private static function docx_p( $xp, $p ) {
		$style = $xp->query( 'w:pPr/w:pStyle/@w:val', $p )->item( 0 );
		$style = $style ? strtolower( $style->nodeValue ) : '';
		$list  = $xp->query( 'w:pPr/w:numPr', $p )->length > 0;
		$align = $xp->query( 'w:pPr/w:jc/@w:val', $p )->item( 0 );
		$text  = '';
		foreach ( $xp->query( './/w:r', $p ) as $r ) {
			$run = '';
			foreach ( $r->childNodes as $n ) {
				if ( 'w:t' === $n->nodeName ) {
					$run .= esc_html( $n->textContent );
				} elseif ( 'w:tab' === $n->nodeName ) {
					$run .= '&emsp;';
				} elseif ( 'w:br' === $n->nodeName || 'w:cr' === $n->nodeName ) {
					$run .= '<br>';
				}
			}
			if ( '' === $run ) {
				continue;
			}
			if ( $xp->query( 'w:rPr/w:b[not(@w:val="0") and not(@w:val="false")]', $r )->length ) {
				$run = '<strong>' . $run . '</strong>';
			}
			if ( $xp->query( 'w:rPr/w:i[not(@w:val="0") and not(@w:val="false")]', $r )->length ) {
				$run = '<em>' . $run . '</em>';
			}
			if ( $xp->query( 'w:rPr/w:u[not(@w:val="none")]', $r )->length ) {
				$run = '<u>' . $run . '</u>';
			}
			$text .= $run;
		}
		$cls = $align && in_array( $align->nodeValue, array( 'center', 'right', 'both' ), true ) ? ' class="is-' . esc_attr( $align->nodeValue ) . '"' : '';
		if ( preg_match( '/^(heading|baslik|başlık)\s*([1-6])$/u', $style, $m ) || preg_match( '/^heading([1-6])$/', $style, $m2 ) ) {
			$lvl = isset( $m[2] ) ? (int) $m[2] : (int) $m2[1];
			return '<h' . $lvl . $cls . '>' . $text . '</h' . $lvl . '>';
		}
		if ( 'title' === $style ) {
			return '<h1 class="is-title">' . $text . '</h1>';
		}
		if ( $list ) {
			return '<p class="is-li">' . $text . '</p>';
		}
		return '' === $text ? '<p class="is-empty"></p>' : '<p' . $cls . '>' . $text . '</p>';
	}

	/**
	 * Column letters to index.
	 *
	 * @param string $ref Cell ref, e.g. "AB12".
	 * @return int
	 */
	private static function col( $ref ) {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$n       = 0;
		for ( $i = 0, $l = strlen( $letters ); $i < $l; $i++ ) {
			$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $n - 1 );
	}

	/**
	 * Excel (.xlsx) sheets.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private static function xlsx( $path ) {
		$zip    = self::open( $path );
		$shared = array();
		$ss     = self::part( $zip, 'xl/sharedStrings.xml' );
		if ( $ss ) {
			foreach ( $ss->getElementsByTagName( 'si' ) as $si ) {
				$shared[] = $si->textContent;
			}
		}
		$names = array();
		$wb    = self::part( $zip, 'xl/workbook.xml' );
		if ( $wb ) {
			foreach ( $wb->getElementsByTagName( 'sheet' ) as $s ) {
				$names[] = $s->getAttribute( 'name' );
			}
		}
		$sheets = array();
		for ( $i = 1; $i <= self::MAX_SHEET; $i++ ) {
			$dom = self::part( $zip, 'xl/worksheets/sheet' . $i . '.xml' );
			if ( ! $dom ) {
				break;
			}
			$rows = array();
			$max  = 0;
			foreach ( $dom->getElementsByTagName( 'row' ) as $row ) {
				if ( count( $rows ) >= self::MAX_ROWS ) {
					break;
				}
				$cells = array();
				foreach ( $row->getElementsByTagName( 'c' ) as $c ) {
					$idx = self::col( $c->getAttribute( 'r' ) );
					if ( $idx >= self::MAX_COLS ) {
						continue;
					}
					$t = $c->getAttribute( 't' );
					$v = $c->getElementsByTagName( 'v' )->item( 0 );
					if ( 's' === $t && $v ) {
						$val = isset( $shared[ (int) $v->nodeValue ] ) ? $shared[ (int) $v->nodeValue ] : '';
					} elseif ( 'inlineStr' === $t ) {
						$val = $c->textContent;
					} elseif ( 'b' === $t && $v ) {
						$val = $v->nodeValue ? 'TRUE' : 'FALSE';
					} else {
						$val = $v ? $v->nodeValue : '';
					}
					$cells[ $idx ] = $val;
					$max           = max( $max, $idx + 1 );
				}
				$r_index          = max( 0, (int) $row->getAttribute( 'r' ) - 1 );
				$rows[ $r_index ] = $cells;
			}
			$grid = array();
			if ( $rows ) {
				$last = min( max( array_keys( $rows ) ), self::MAX_ROWS - 1 );
				for ( $r = 0; $r <= $last; $r++ ) {
					$line = array();
					for ( $c = 0; $c < $max; $c++ ) {
						$line[] = isset( $rows[ $r ][ $c ] ) ? (string) $rows[ $r ][ $c ] : '';
					}
					$grid[] = $line;
				}
			}
			$sheets[] = array(
				/* translators: %d: sheet number */
				'name' => isset( $names[ $i - 1 ] ) ? $names[ $i - 1 ] : sprintf( __( 'Sheet %d', 'appstrdesign-foldpress' ), $i ),
				'rows' => $grid,
			);
		}
		$zip->close();
		return $sheets;
	}

	/**
	 * PowerPoint (.pptx) slides as text blocks.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private static function pptx( $path ) {
		$zip   = self::open( $path );
		$files = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$n = (string) $zip->getNameIndex( $i );
			if ( preg_match( '#^ppt/slides/slide(\d+)\.xml$#', $n, $m ) ) {
				$files[ (int) $m[1] ] = $n;
			}
		}
		ksort( $files );
		$slides = array();
		foreach ( array_slice( $files, 0, 200, true ) as $name ) {
			$dom = self::part( $zip, $name );
			if ( ! $dom ) {
				continue;
			}
			$paras = array();
			foreach ( $dom->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/drawingml/2006/main', 'p' ) as $p ) {
				$t = '';
				foreach ( $p->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/drawingml/2006/main', 't' ) as $tn ) {
					$t .= $tn->textContent;
				}
				if ( '' !== trim( $t ) ) {
					$paras[] = $t;
				}
			}
			$slides[] = $paras;
		}
		$zip->close();
		return $slides;
	}

	/**
	 * OpenDocument text / slides / sheets: paragraphs.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function odf( $path ) {
		$zip = self::open( $path );
		$dom = self::part( $zip, 'content.xml' );
		$zip->close();
		if ( ! $dom ) {
			throw new Exception( 'odf' );
		}
		$html = '';
		$ns   = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
		$xp   = new DOMXPath( $dom );
		$xp->registerNamespace( 'text', $ns );
		foreach ( $xp->query( '//text:h | //text:p' ) as $n ) {
			$t = esc_html( $n->textContent );
			if ( 'text:h' === $n->nodeName ) {
				$lvl   = min( 6, max( 1, (int) $n->getAttributeNS( $ns, 'outline-level' ) ) );
				$html .= '<h' . $lvl . '>' . $t . '</h' . $lvl . '>';
			} else {
				$html .= '' === $t ? '<p class="is-empty"></p>' : '<p>' . $t . '</p>';
			}
		}
		return $html;
	}

	/**
	 * CSV / TSV.
	 *
	 * @param string      $path Path.
	 * @param string|null $sep  Separator (auto when null).
	 * @return array
	 */
	private static function csv( $path, $sep = null ) {
		$fh   = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$rows = array();
		if ( ! $fh ) {
			return array(
				'name' => 'CSV',
				'rows' => $rows,
			);
		}
		if ( null === $sep ) {
			$head = (string) fgets( $fh );
			rewind( $fh );
			$counts = array(
				','  => substr_count( $head, ',' ),
				';'  => substr_count( $head, ';' ),
				"\t" => substr_count( $head, "\t" ),
			);
			arsort( $counts );
			$sep = (string) key( $counts );
		}
		while ( count( $rows ) < self::MAX_ROWS && ( $r = fgetcsv( $fh, 0, $sep ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			$rows[] = array_slice( array_map( 'strval', (array) $r ), 0, self::MAX_COLS );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array(
			'name' => strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) ),
			'rows' => $rows,
		);
	}

	/**
	 * ZIP listing.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private static function zip( $path ) {
		$zip = self::open( $path );
		$out = array();
		for ( $i = 0; $i < min( 1000, $zip->numFiles ); $i++ ) {
			$st = $zip->statIndex( $i );
			if ( $st ) {
				$out[] = array(
					'name' => $st['name'],
					'size' => (int) $st['size'],
					'dir'  => '/' === substr( $st['name'], -1 ),
				);
			}
		}
		$zip->close();
		return $out;
	}

	/**
	 * Very small RTF to text.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function rtf( $path ) {
		$s = (string) file_get_contents( $path, false, null, 0, self::MAX_TEXT ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$s = preg_replace( '/\\\\par[d]?/', "\n", $s );
		$s = preg_replace( "/\\\\'([0-9a-f]{2})/i", '', $s );
		$s = preg_replace( '/\\\\[a-z]+-?\d* ?/i', '', $s );
		$s = str_replace( array( '{', '}' ), '', $s );
		return trim( wp_check_invalid_utf8( $s, true ) );
	}
}

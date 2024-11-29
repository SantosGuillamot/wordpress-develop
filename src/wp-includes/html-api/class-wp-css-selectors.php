<?php
/**
 * HTML API: WP_CSS_Selectors class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since TBD
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Core class used by the HTML processor to parse CSS selectors.
 *
 * This class is designed for internal use by the HTML processor.
 *
 * This class is instantiated via the `WP_CSS_Selector_List::from_selector( string $selector )` method.
 * It accepts a CSS selector string and returns an instance of itself or `null` if the selector
 * is invalid or unsupported.
 *
 * A subset of the CSS selector grammar is supported. The grammar is defined in the CSS Syntax
 * specification, which is available at {@link https://www.w3.org/TR/selectors/#grammar}.
 *
 * @todo Review this grammar, especially the complex selector for accurate support information.
 * The supported grammar is:
 *
 *     <selector-list> = <complex-selector-list>
 *     <complex-selector-list> = <complex-selector>#
 *     <compound-selector-list> = <compound-selector>#
 *     <complex-selector> = [ <type-selector> <combinator>? ]* <compound-selector>
 *     <compound-selector> = [ <type-selector>? <subclass-selector>* ]!
 *     <combinator> = '>' | [ '|' '|' ]
 *     <type-selector> = <ident-token> | '*'
 *     <subclass-selector> = <id-selector> | <class-selector> | <attribute-selector>
 *     <id-selector> = <hash-token>
 *     <class-selector> = '.' <ident-token>
 *     <attribute-selector> = '[' <ident-token> ']' |
 *                            '[' <ident-token> <attr-matcher> [ <string-token> | <ident-token> ] <attr-modifier>? ']'
 *     <attr-matcher> = [ '~' | '|' | '^' | '$' | '*' ]? '='
 *     <attr-modifier> = i | s
 *
 * @link https://www.w3.org/TR/selectors/#grammar Refer to the grammar for more details.
 *
 * Supported selector syntax:
 * - Type selectors (tag names, e.g. `div`)
 * - Class selectors (e.g. `.class-name`)
 * - ID selectors (e.g. `#unique-id`)
 * - Attribute selectors (e.g. `[attribute-name]` or `[attribute-name="value"]`)
 * - Comma-separated selector lists (e.g. `.selector-1, .selector-2`)
 * - The following combinators. Only type (element) selectors are allowed in non-final position:
 *   - descendant (e.g. `el .descendant`)
 *   - child (`el > .child`)
 *
 * Unsupported selector syntax:
 * - Pseudo-element selectors (e.g. `::before`)
 * - Pseudo-class selectors (e.g. `:hover` or `:nth-child(2)`)
 * - Namespace prefixes (e.g. `svg|title` or `[xlink|href]`)
 * - The following combinators:
 *   - Next sibling (`el + el`)
 *   - Subsequent sibling (`el ~ el`)
 *
 * Future ideas
 *   - Namespace type selectors could be implemented with select namespaces in order to
 *     select elements from a namespace, for example:
 *     - `svg|*` to select all SVG elements
 *     - `html|title` to select only HTML TITLE elements.
 *
 * @since TBD
 *
 * @access private
 *
 * @see {@link https://www.w3.org/TR/css-syntax-3/}
 * @see {@link https://www.w3.org/tr/selectors/}
 * @see {@link https://www.w3.org/TR/selectors-api2/}
 * @see {@link https://www.w3.org/TR/selectors-4/}
 *
 */
class WP_CSS_Selector_List implements IWP_CSS_Selector_Matcher {
	public function matches( WP_HTML_Processor $processor ): bool {
		if ( $processor->get_token_type() !== '#tag' ) {
			return false;
		}

		foreach ( $this->selectors as $selector ) {
			if ( $selector->matches( $processor ) ) {
				return true;
			}
		}
		return false;
	}

	private $selectors;

	private function __construct( array $selectors ) {
		$this->selectors = $selectors;
	}

	/**
	 * Takes a CSS selectors string and returns an instance of itself or `null` if the selector
	 * is invalid or unsupported.
	 *
	 * @since TBD
	 *
	 * @param string $selectors CSS selectors string.
	 * @return self|null
	 */
	public static function from_selectors( string $selectors ): ?self {
		return self::parse( $selectors );
	}

	/**
	 * Returns a list of selectors.
	 *
	 * @since TBD
	 *
	 * @return WP_CSS_Selectors|null
	 */
	private static function parse( string $input ) {
		// > A selector string is a list of one or more complex selectors ([SELECTORS4], section 3.1) that may be surrounded by whitespace and matches the dom_selectors_group production.
		$input = trim( $input, " \t\r\n\r" );

		if ( '' === $input ) {
			null;
		}

		/*
		 * > The input stream consists of the filtered code points pushed into it as the input byte stream is decoded.
		 * >
		 * > To filter code points from a stream of (unfiltered) code points input:
		 * > Replace any U+000D CARRIAGE RETURN (CR) code points, U+000C FORM FEED (FF) code points, or pairs of U+000D CARRIAGE RETURN (CR) followed by U+000A LINE FEED (LF) in input by a single U+000A LINE FEED (LF) code point.
		 * > Replace any U+0000 NULL or surrogate code points in input with U+FFFD REPLACEMENT CHARACTER (�).
		 *
		 * https://www.w3.org/TR/css-syntax-3/#input-preprocessing
		 */
		$input = str_replace( array( "\r\n" ), "\n", $input );
		$input = str_replace( array( "\r", "\f" ), "\n", $input );
		$input = str_replace( "\0", "\u{FFFD}", $input );

		$offset = 0;

		$selector = WP_CSS_Complex_Selector::parse( $input, $offset );
		if ( null === $selector ) {
			return null;
		}
		WP_CSS_Selector_Parser::parse_whitespace( $input, $offset );

		$selectors = array( $selector );
		while ( $offset < strlen( $input ) ) {
			// Each loop should stop on a `,` selector list delimiter.
			if ( ',' !== $input[ $offset ] ) {
				return null;
			}
			++$offset;
			WP_CSS_Selector_Parser::parse_whitespace( $input, $offset );
			$selector = WP_CSS_Complex_Selector::parse( $input, $offset );
			if ( null === $selector ) {
				return null;
			}
			$selectors[] = $selector;
			WP_CSS_Selector_Parser::parse_whitespace( $input, $offset );
		}

		return new WP_CSS_Selector_List( $selectors );
	}
}

interface IWP_CSS_Selector_Matcher {
	/**
	 * @return bool
	 */
	public function matches( WP_HTML_Processor $processor ): bool;
}

interface IWP_CSS_Selector_Parser {
	/**
	 * @return static|null
	 */
	public static function parse( string $input, int &$offset );
}

abstract class WP_CSS_Selector_Parser implements IWP_CSS_Selector_Parser, IWP_CSS_Selector_Matcher {
	const UTF8_MAX_CODEPOINT_VALUE = 0x10FFFF;

	public static function parse_whitespace( string $input, int &$offset ): bool {
		$length   = strspn( $input, " \t\r\n\f", $offset );
		$advanced = $length > 0;
		$offset  += $length;
		return $advanced;
	}

	/**
	 * Tokenization of hash tokens
	 *
	 * > U+0023 NUMBER SIGN (#)
	 * >   If the next input code point is an ident code point or the next two input code points are a valid escape, then:
	 * >     1. Create a <hash-token>.
	 * >     2. If the next 3 input code points would start an ident sequence, set the
	 * >        <hash-token>’s type flag to "id".
	 * >     3. Consume an ident sequence, and set the <hash-token>’s value to the
	 * >        returned string.
	 * >     4. Return the <hash-token>.
	 * >   Otherwise, return a <delim-token> with its value set to the current input code point.
	 *
	 * This implementation is not interested in the <delim-token>, a '#' delim token is not relevant for selectors.
	 */
	protected static function parse_hash_token( string $input, int &$offset ): ?string {
		if ( $offset + 1 >= strlen( $input ) || '#' !== $input[ $offset ] ) {
			return null;
		}

		$updated_offset = $offset + 1;
		$result         = self::parse_ident( $input, $updated_offset );

		if ( null === $result ) {
			return null;
			$offset = $updated_offset;
		}

		$offset = $updated_offset;
		return $result;
	}

	/**
	 * Parse an ident token
	 *
	 * CAUTION: This method is _not_ for parsing and ID selector!
	 *
	 * > 4.3.11. Consume an ident sequence
	 * > This section describes how to consume an ident sequence from a stream of code points. It returns a string containing the largest name that can be formed from adjacent code points in the stream, starting from the first.
	 * >
	 * > Note: This algorithm does not do the verification of the first few code points that are necessary to ensure the returned code points would constitute an <ident-token>. If that is the intended use, ensure that the stream starts with an ident sequence before calling this algorithm.
	 * >
	 * > Let result initially be an empty string.
	 * >
	 * > Repeatedly consume the next input code point from the stream:
	 * >
	 * > ident code point
	 * >   Append the code point to result.
	 * > the stream starts with a valid escape
	 * >   Consume an escaped code point. Append the returned code point to result.
	 * > anything else
	 * >   Reconsume the current input code point. Return result.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#consume-name
	 *
	 * @return string|null
	 */
	protected static function parse_ident( string $input, int &$offset ): ?string {
		if ( ! self::check_if_three_code_points_would_start_an_ident_sequence( $input, $offset ) ) {
			return null;
		}

		$ident = '';

		while ( $offset < strlen( $input ) ) {
			if ( self::next_two_are_valid_escape( $input, $offset ) ) {
				// Move past the `\` character.
				++$offset;
				$ident .= self::consume_escaped_codepoint( $input, $offset );
				continue;
			} elseif ( self::is_ident_codepoint( $input, $offset ) ) {
				// @todo this should append and advance the correct number of bytes.
				$ident  .= $input[ $offset ];
				$offset += 1;
				continue;
			}
			break;
		}

		return $ident;
	}

	/**
	 * Parse a string token
	 *
	 * > 4.3.5. Consume a string token
	 * > This section describes how to consume a string token from a stream of code points. It returns either a <string-token> or <bad-string-token>.
	 * >
	 * > This algorithm may be called with an ending code point, which denotes the code point that ends the string. If an ending code point is not specified, the current input code point is used.
	 * >
	 * > Initially create a <string-token> with its value set to the empty string.
	 * >
	 * > Repeatedly consume the next input code point from the stream:
	 * >
	 * > ending code point
	 * >   Return the <string-token>.
	 * > EOF
	 * >   This is a parse error. Return the <string-token>.
	 * > newline
	 * >   This is a parse error. Reconsume the current input code point, create a <bad-string-token>, and return it.
	 * > U+005C REVERSE SOLIDUS (\)
	 * >   If the next input code point is EOF, do nothing.
	 * >   Otherwise, if the next input code point is a newline, consume it.
	 * >   Otherwise, (the stream starts with a valid escape) consume an escaped code point and append the returned code point to the <string-token>’s value.
	 * >
	 * > anything else
	 * >   Append the current input code point to the <string-token>’s value.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#consume-string-token
	 *
	 * This implementation will never return a <bad-string-token> because
	 * the <bad-string-token> is not a part of the selector grammar. That
	 * case is treated as failure to parse and null is returned.
	 *
	 * @return string|null
	 */
	protected static function parse_string( string $input, int &$offset ): ?string {
		if ( $offset + 1 >= strlen( $input ) ) {
			return null;
		}

		$ending_code_point = $input[ $offset ];
		if ( '"' !== $ending_code_point && "'" !== $ending_code_point ) {
			return null;
		}

		$string_token = '';

		$updated_offset = $offset + 1;
		while ( $updated_offset < strlen( $input ) ) {
			switch ( $input[ $updated_offset ] ) {
				case '\\':
					++$updated_offset;
					if ( $updated_offset >= strlen( $input ) ) {
						break;
					}
					if ( "\n" === $input[ $updated_offset ] ) {
						++$updated_offset;
						break;
					} else {
						$string_token .= self::consume_escaped_codepoint( $input, $updated_offset );
					}
					break;

				/*
				 * This case would return a <bad-string-token>.
				 * The <bad-string-token> is not a part of the selector grammar
				 * so we do not return it and instead treat this as a
				 * failure to parse a string token.
				 */
				case "\n":
					return null;

				case $ending_code_point:
					++$updated_offset;
					break 2;

				default:
					$string_token .= $input[ $updated_offset ];
					++$updated_offset;
			}
		}

		$offset = $updated_offset;
		return $string_token;
	}

	/**
	 * Consume an escaped code point.
	 *
	 * > 4.3.7. Consume an escaped code point
	 * > This section describes how to consume an escaped code point. It assumes that the U+005C
	 * > REVERSE SOLIDUS (\) has already been consumed and that the next input code point has
	 * > already been verified to be part of a valid escape. It will return a code point.
	 * >
	 * > Consume the next input code point.
	 * >
	 * > hex digit
	 * >   Consume as many hex digits as possible, but no more than 5. Note that this means 1-6
	 * >   hex digits have been consumed in total. If the next input code point is whitespace,
	 * >   consume it as well. Interpret the hex digits as a hexadecimal number. If this number is
	 * >   zero, or is for a surrogate, or is greater than the maximum allowed code point, return
	 * >   U+FFFD REPLACEMENT CHARACTER (�). Otherwise, return the code point with that value.
	 * > EOF
	 * >   This is a parse error. Return U+FFFD REPLACEMENT CHARACTER (�).
	 * > anything else
	 * >   Return the current input code point.
	 */
	protected static function consume_escaped_codepoint( $input, &$offset ): ?string {
		$hex_length = strspn( $input, '0123456789abcdefABCDEF', $offset, 6 );
		if ( $hex_length > 0 ) {
			$codepoint_value = hexdec( substr( $input, $offset, $hex_length ) );

			// > A surrogate is a leading surrogate or a trailing surrogate.
			// > A leading surrogate is a code point that is in the range U+D800 to U+DBFF, inclusive.
			// > A trailing surrogate is a code point that is in the range U+DC00 to U+DFFF, inclusive.
			// The surrogate ranges are adjacent, so the complete range is 0xD800..=0xDFFF,
			// inclusive.
			$codepoint_char = (
				0 === $codepoint_value ||
				$codepoint_value > self::UTF8_MAX_CODEPOINT_VALUE ||
				( 0xD800 <= $codepoint_value && $codepoint_value <= 0xDFFF )
			) ?
				"\u{FFFD}" :
				mb_chr( $codepoint_value, 'UTF-8' );

			$offset += $hex_length;

			// If the next input code point is whitespace, consume it as well.
			if (
				strlen( $input ) > $offset &&
				(
					"\n" === $input[ $offset ] ||
					"\t" === $input[ $offset ] ||
					' ' === $input[ $offset ]
				)
			) {
				++$offset;
			}
			return $codepoint_char;
		}

		$codepoint_char = mb_substr( $input, $offset, 1, 'UTF-8' );
		$offset        += strlen( $codepoint_char );
		return $codepoint_char;
	}

	/*
	 * Utiltities
	 * ==========
	 *
	 * The following functions do not consume any input.
	 */

	/**
	 * > 4.3.8. Check if two code points are a valid escape
	 * > This section describes how to check if two code points are a valid escape. The algorithm described here can be called explicitly with two code points, or can be called with the input stream itself. In the latter case, the two code points in question are the current input code point and the next input code point, in that order.
	 * >
	 * > Note: This algorithm will not consume any additional code point.
	 * >
	 * > If the first code point is not U+005C REVERSE SOLIDUS (\), return false.
	 * >
	 * > Otherwise, if the second code point is a newline, return false.
	 * >
	 * > Otherwise, return true.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#starts-with-a-valid-escape
	 *
	 * @todo this does not check whether the second codepoint is valid.
	 */
	protected static function next_two_are_valid_escape( string $input, int $offset ): bool {
		if ( $offset + 1 >= strlen( $input ) ) {
			return false;
		}
		return '\\' === $input[ $offset ] && "\n" !== $input[ $offset + 1 ];
	}

	/**
	 * Check if the next code point is an "ident start code point".
	 *
	 * Caution! This method does not do any bounds checking, it should not be passed
	 * a string with an offset that is out of bounds.
	 *
	 * > ident-start code point
	 * >   A letter, a non-ASCII code point, or U+005F LOW LINE (_).
	 * > uppercase letter
	 * >   A code point between U+0041 LATIN CAPITAL LETTER A (A) and U+005A LATIN CAPITAL LETTER Z (Z) inclusive.
	 * > lowercase letter
	 * >   A code point between U+0061 LATIN SMALL LETTER A (a) and U+007A LATIN SMALL LETTER Z (z) inclusive.
	 * > letter
	 * >   An uppercase letter or a lowercase letter.
	 * > non-ASCII code point
	 * >   A code point with a value equal to or greater than U+0080 <control>.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#ident-start-code-point
	 */
	protected static function is_ident_start_codepoint( string $input, int $offset ): bool {
		return (
			'_' === $input[ $offset ] ||
			( 'a' <= $input[ $offset ] && $input[ $offset ] <= 'z' ) ||
			( 'A' <= $input[ $offset ] && $input[ $offset ] <= 'Z' ) ||
			ord( $input[ $offset ] ) > 0x7F
		);
	}

	/**
	 * Check if the next code point is an "ident code point".
	 *
	 * Caution! This method does not do any bounds checking, it should not be passed
	 * a string with an offset that is out of bounds.
	 *
	 * > ident code point
	 * >   An ident-start code point, a digit, or U+002D HYPHEN-MINUS (-).
	 * > digit
	 * >   A code point between U+0030 DIGIT ZERO (0) and U+0039 DIGIT NINE (9) inclusive.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#ident-code-point
	 */
	protected static function is_ident_codepoint( string $input, int $offset ): bool {
		return '-' === $input[ $offset ] ||
			( '0' <= $input[ $offset ] && $input[ $offset ] <= '9' ) ||
			self::is_ident_start_codepoint( $input, $offset );
	}

	/**
	 * > 4.3.9. Check if three code points would start an ident sequence
	 * > This section describes how to check if three code points would start an ident sequence. The algorithm described here can be called explicitly with three code points, or can be called with the input stream itself. In the latter case, the three code points in question are the current input code point and the next two input code points, in that order.
	 * >
	 * > Note: This algorithm will not consume any additional code points.
	 * >
	 * > Look at the first code point:
	 * >
	 * > U+002D HYPHEN-MINUS
	 * >   If the second code point is an ident-start code point or a U+002D HYPHEN-MINUS, or the second and third code points are a valid escape, return true. Otherwise, return false.
	 * > ident-start code point
	 * >   Return true.
	 * > U+005C REVERSE SOLIDUS (\)
	 * >   If the first and second code points are a valid escape, return true. Otherwise, return false.
	 * > anything else
	 * >   Return false.
	 *
	 * https://www.w3.org/TR/css-syntax-3/#would-start-an-identifier
	 */
	protected static function check_if_three_code_points_would_start_an_ident_sequence( string $input, int $offset ): bool {
		if ( $offset >= strlen( $input ) ) {
			return false;
		}

		// > U+005C REVERSE SOLIDUS (\)
		if ( '\\' === $input[ $offset ] ) {
			return self::next_two_are_valid_escape( $input, $offset );
		}

		// > U+002D HYPHEN-MINUS
		if ( '-' === $input[ $offset ] ) {
			$after_initial_hyphen_minus_offset = $offset + 1;
			if ( $after_initial_hyphen_minus_offset >= strlen( $input ) ) {
				return false;
			}

			// > If the second code point is… U+002D HYPHEN-MINUS… return true
			if ( '-' === $input[ $after_initial_hyphen_minus_offset ] ) {
				return true;
			}

			// > If the second and third code points are a valid escape… return true.
			if ( self::next_two_are_valid_escape( $input, $after_initial_hyphen_minus_offset ) ) {
				return true;
			}

			// > If the second code point is an ident-start code point… return true.
			if ( self::is_ident_start_codepoint( $input, $after_initial_hyphen_minus_offset ) ) {
				return true;
			}

			// > Otherwise, return false.
			return false;
		}

		// > ident-start code point
		// >   Return true.
		// > anything else
		// >   Return false.
		return self::is_ident_start_codepoint( $input, $offset );
	}
}

final class WP_CSS_ID_Selector extends WP_CSS_Selector_Parser {
	/** @var string */
	public $ident;

	private function __construct( string $ident ) {
		$this->ident = $ident;
	}

	/**
	 * Parse an ID selector
	 *
	 * > <id-selector> = <hash-token>
	 *
	 * https://www.w3.org/TR/selectors/#grammar
	 *
	 * @return self|null
	 */
	public static function parse( string $input, int &$offset ): ?self {
		$ident = self::parse_hash_token( $input, $offset );
		if ( null === $ident ) {
			return null;
		}
		return new self( $ident );
	}

	public function matches( WP_HTML_Processor $processor ): bool {
		$case_insensitive = method_exists( $processor, 'is_quirks_mode' ) && $processor->is_quirks_mode();
		return $case_insensitive ?
			0 === strcasecmp( $processor->get_attribute( 'id' ), $this->ident ) :
			$processor->get_attribute( 'id' ) === $this->ident;
	}
}

final class WP_CSS_Class_Selector extends WP_CSS_Selector_Parser {
	public function matches( WP_HTML_Processor $processor ): bool {
		return (bool) $processor->has_class( $this->ident );
	}

	/** @var string */
	public $ident;

	private function __construct( string $ident ) {
		$this->ident = $ident;
	}

	/**
	 * Parse a class selector
	 *
	 * > <class-selector> = '.' <ident-token>
	 *
	 * https://www.w3.org/TR/selectors/#grammar
	 *
	 * @return self|null
	 */
	public static function parse( string $input, int &$offset ): ?self {
		if ( $offset + 1 >= strlen( $input ) || '.' !== $input[ $offset ] ) {
			return null;
		}

		$updated_offset = $offset + 1;
		$result         = self::parse_ident( $input, $updated_offset );

		if ( null === $result ) {
			return null;
		}

		$offset = $updated_offset;
		return new self( $result );
	}
}

final class WP_CSS_Type_Selector extends WP_CSS_Selector_Parser {
	public function matches( WP_HTML_Processor $processor ): bool {
		if ( '*' === $this->ident ) {
			return true;
		}
		return 0 === strcasecmp( $processor->get_tag(), $this->ident );
	}

	/**
	 * @var string
	 *
	 * The type identifier string or '*'.
	 */
	public $ident;

	private function __construct( string $ident ) {
		$this->ident = $ident;
	}

	/**
	 * Parse a type selector
	 *
	 * > <type-selector> = <wq-name> | <ns-prefix>? '*'
	 * > <ns-prefix> = [ <ident-token> | '*' ]? '|'
	 * > <wq-name> = <ns-prefix>? <ident-token>
	 *
	 * Namespaces (e.g. |div, *|div, or namespace|div) are not supported,
	 * so this selector effectively matches * or ident.
	 *
	 * https://www.w3.org/TR/selectors/#grammar
	 *
	 * @return self|null
	 */
	public static function parse( string $input, int &$offset ): ?self {
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		if ( '*' === $input[ $offset ] ) {
			++$offset;
			return new self( '*' );
		}

		$result = self::parse_ident( $input, $offset );
		if ( null === $result ) {
			return null;
		}

		return new self( $result );
	}
}

final class WP_CSS_Attribute_Selector extends WP_CSS_Selector_Parser {
	public function matches( WP_HTML_Processor $processor ): bool {
		$att_value = $processor->get_attribute( $this->name );
		if ( null === $att_value ) {
			return false;
		}

		if ( null === $this->value ) {
			return true;
		}

		$case_insensitive = self::MODIFIER_CASE_INSENSITIVE === $this->modifier;

		switch ( $this->matcher ) {
			case self::MATCH_EXACT:
				return $case_insensitive ?
					0 === strcasecmp( $att_value, $this->value ) :
					$att_value === $this->value;

			case self::MATCH_ONE_OF_EXACT:
				// @todo
				throw new Exception( 'One of attribute matching is not supported yet.' );

			case self::MATCH_EXACT_OR_EXACT_WITH_HYPHEN:
				// Attempt the full match first
				if (
					$case_insensitive ?
					0 === strcasecmp( $att_value, $this->value ) :
					$att_value === $this->value
				) {
					return true;
				}

				// Partial match
				if ( strlen( $att_value ) < strlen( $this->value ) + 1 ) {
					return false;
				}

				$starts_with = "{$this->value}-";
				return 0 === substr_compare( $att_value, $starts_with, 0, strlen( $starts_with ), $case_insensitive );

			case self::MATCH_PREFIXED_BY:
				return 0 === substr_compare( $att_value, $this->value, 0, strlen( $this->value ), $case_insensitive );

			case self::MATCH_SUFFIXED_BY:
				return 0 === substr_compare( $att_value, $this->value, -strlen( $this->value ), null, $case_insensitive );

			case self::MATCH_CONTAINS:
				return false !== (
					$case_insensitive ?
						stripos( $att_value, $this->value ) :
						strpos( $att_value, $this->value )
				);
		}
	}

	/**
	 * [att=val]
	 * Represents an element with the att attribute whose value is exactly "val".
	 */
	const MATCH_EXACT = 'MATCH_EXACT';

	/**
	 * [attr~=value]
	 * Represents elements with an attribute name of attr whose value is a
	 * whitespace-separated list of words, one of which is exactly value.
	 */
	const MATCH_ONE_OF_EXACT = 'MATCH_ONE_OF_EXACT';

	/**
	 * [attr|=value]
	 * Represents elements with an attribute name of attr whose value can be exactly value or
	 * can begin with value immediately followed by a hyphen, - (U+002D). It is often used for
	 * language subcode matches.
	 */
	const MATCH_EXACT_OR_EXACT_WITH_HYPHEN = 'MATCH_EXACT_OR_EXACT_WITH_HYPHEN';

	/**
	 * [attr^=value]
	 * Represents elements with an attribute name of attr whose value is prefixed (preceded)
	 * by value.
	 */
	const MATCH_PREFIXED_BY = 'MATCH_PREFIXED_BY';

	/**
	 * [attr$=value]
	 * Represents elements with an attribute name of attr whose value is suffixed (followed)
	 * by value.
	 */
	const MATCH_SUFFIXED_BY = 'MATCH_SUFFIXED_BY';

	/**
	 * [attr*=value]
	 * Represents elements with an attribute name of attr whose value contains at least one
	 * occurrence of value within the string.
	 */
	const MATCH_CONTAINS = 'MATCH_CONTAINS';

	/**
	 * Modifier for case sensitive matching
	 * [attr=value s]
	 */
	const MODIFIER_CASE_SENSITIVE = 'case-sensitive';

	/**
	 * Modifier for case insensitive matching
	 * [attr=value i]
	 */
	const MODIFIER_CASE_INSENSITIVE = 'case-insensitive';


	/**
	 * The attribute name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The attribute matcher.
	 *
	 * @var string|null
	 */
	public $matcher;

	/**
	 * The attribute value.
	 *
	 * @var string|null
	 */
	public $value;

	/**
	 * The attribute modifier.
	 *
	 * @var string|null
	 */
	public $modifier;

	private function __construct( string $name, ?string $matcher = null, ?string $value = null, ?string $modifier = null ) {
		$this->name     = $name;
		$this->matcher  = $matcher;
		$this->value    = $value;
		$this->modifier = $modifier;
	}

	/**
	 * Parse a attribute selector
	 *
	 * > <attribute-selector> = '[' <wq-name> ']' |
	 * >                        '[' <wq-name> <attr-matcher> [ <string-token> | <ident-token> ] <attr-modifier>? ']'
	 * > <attr-matcher> = [ '~' | '|' | '^' | '$' | '*' ]? '='
	 * > <attr-modifier> = i | s
	 * > <wq-name> = <ns-prefix>? <ident-token>
	 *
	 * Namespaces are not supported, so attribute names are effectively identifiers.
	 *
	 * https://www.w3.org/TR/selectors/#grammar
	 *
	 * @return self|null
	 */
	public static function parse( string $input, int &$offset ): ?self {
		// Need at least 3 bytes [x]
		if ( $offset + 2 >= strlen( $input ) ) {
			return null;
		}

		$updated_offset = $offset;

		if ( '[' !== $input[ $updated_offset ] ) {
			return null;
		}
		++$updated_offset;

		self::parse_whitespace( $input, $updated_offset );
		$attr_name = self::parse_ident( $input, $updated_offset );
		if ( null === $attr_name ) {
			return null;
		}
		self::parse_whitespace( $input, $updated_offset );

		if ( $updated_offset >= strlen( $input ) ) {
			return null;
		}

		if ( ']' === $input[ $updated_offset ] ) {
			$offset = $updated_offset + 1;
			return new self( $attr_name );
		}

		// need to match at least `=x]` at this point
		if ( $updated_offset + 3 >= strlen( $input ) ) {
			return null;
		}

		if ( '=' === $input[ $updated_offset ] ) {
			++$updated_offset;
			$attr_matcher = WP_CSS_Attribute_Selector::MATCH_EXACT;
		} elseif ( '=' === $input[ $updated_offset + 1 ] ) {
			switch ( $input[ $updated_offset ] ) {
				case '~':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_ONE_OF_EXACT;
					$updated_offset += 2;
					break;
				case '|':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_EXACT_OR_EXACT_WITH_HYPHEN;
					$updated_offset += 2;
					break;
				case '^':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_PREFIXED_BY;
					$updated_offset += 2;
					break;
				case '$':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_SUFFIXED_BY;
					$updated_offset += 2;
					break;
				case '*':
					$attr_matcher    = WP_CSS_Attribute_Selector::MATCH_CONTAINS;
					$updated_offset += 2;
					break;
				default:
					return null;
			}
		} else {
			return null;
		}

		self::parse_whitespace( $input, $updated_offset );
		$attr_val =
			self::parse_string( $input, $updated_offset ) ??
			self::parse_ident( $input, $updated_offset );

		if ( null === $attr_val ) {
			return null;
		}

		self::parse_whitespace( $input, $updated_offset );
		if ( $updated_offset >= strlen( $input ) ) {
			return null;
		}

		$attr_modifier = null;
		switch ( $input[ $updated_offset ] ) {
			case 'i':
			case 'I':
				$attr_modifier = WP_CSS_Attribute_Selector::MODIFIER_CASE_INSENSITIVE;
				++$updated_offset;
				break;

			case 's':
			case 'S':
				$attr_modifier = WP_CSS_Attribute_Selector::MODIFIER_CASE_SENSITIVE;
				++$updated_offset;
				break;
		}

		if ( null !== $attr_modifier ) {
			self::parse_whitespace( $input, $updated_offset );
			if ( $updated_offset >= strlen( $input ) ) {
				return null;
			}
		}

		if ( ']' === $input[ $updated_offset ] ) {
			$offset = $updated_offset + 1;
			return new self( $attr_name, $attr_matcher, $attr_val, $attr_modifier );
		}

		return null;
	}
}

/**
 * This corresponds to <compound-selector> in the grammar.
 *
 * > <compound-selector> = [ <type-selector>? <subclass-selector>* ]!
 */
final class WP_CSS_Selector extends WP_CSS_Selector_Parser {
	public function matches( WP_HTML_Processor $processor ): bool {
		if ( $this->type_selector ) {
			if ( ! $this->type_selector->matches( $processor ) ) {
				return false;
			}
		}
		foreach ( $this->subclass_selectors as $subclass_selector ) {
			if ( ! $subclass_selector->matches( $processor ) ) {
				return false;
			}
		}
		return true;
	}

	/** @var WP_CSS_Type_Selector|null */
	public $type_selector;

	/** @var array<WP_CSS_ID_Selector|WP_CSS_Class_Selector|WP_CSS_Attribute_Selector>|null */
	public $subclass_selectors;

	private function __construct( ?WP_CSS_Type_Selector $type_selector, array $subclass_selectors ) {
		$this->type_selector      = $type_selector;
		$this->subclass_selectors = array() === $subclass_selectors ? null : $subclass_selectors;
	}

	/**
	 * > <compound-selector> = [ <type-selector>? <subclass-selector>* ]!
	 */
	public static function parse( string $input, int &$offset ): ?self {
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		$updated_offset = $offset;
		$type_selector  = WP_CSS_Type_Selector::parse( $input, $updated_offset );

		$subclass_selectors            = array();
		$last_parsed_subclass_selector = self::parse_subclass_selector( $input, $updated_offset );
		while ( null !== $last_parsed_subclass_selector ) {
			$subclass_selectors[]          = $last_parsed_subclass_selector;
			$last_parsed_subclass_selector = self::parse_subclass_selector( $input, $updated_offset );
		}

		if ( null !== $type_selector || array() !== $subclass_selectors ) {
			$offset = $updated_offset;
			return new self( $type_selector, $subclass_selectors );
		}
		return null;
	}

	/**
	 * @return WP_CSS_ID_Selector|WP_CSS_Class_Selector|WP_CSS_Attribute_Selector|null
	 */
	private static function parse_subclass_selector( string $input, int &$offset ) {
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		$next_char = $input[ $offset ];
		return '.' === $next_char ?
			WP_CSS_Class_Selector::parse( $input, $offset ) : (
			'#' === $next_char ?
			WP_CSS_ID_Selector::parse( $input, $offset ) : (
			'[' === $next_char ?
			WP_CSS_Attribute_Selector::parse( $input, $offset ) :
			null ) );
	}
}


/**
 * This corresponds to <complex-selector> in the grammar.
 *
 * > <complex-selector> = <compound-selector> [ <combinator>? <compound-selector> ]*
 */
final class WP_CSS_Complex_Selector extends WP_CSS_Selector_Parser {
	public function matches( WP_HTML_Processor $processor ): bool {
		if ( count( $this->selectors ) === 1 ) {
			return $this->selectors[0]->matches( $processor );
		}

		// First selector must match this location.
		if ( ! $this->selectors[0]->matches( $processor ) ) {
			return false;
		}

		$breadcrumbs = array_slice( array_reverse( $processor->get_breadcrumbs() ), 1 );
		$selectors   = array_slice( $this->selectors, 1 );
		return $this->explore_matches( $selectors, $breadcrumbs );
	}

	/**
	 * This only looks at breadcrumbs and can therefore only support type selectors.
	 *
	 * @param array<WP_CSS_Selector> $selectors
	 */
	private function explore_matches( array $selectors, array $breadcrumbs ): bool {
		if ( array() === $selectors ) {
			return true;
		}
		if ( array() === $breadcrumbs ) {
			return false;
		}

		$combinator = $selectors[0];
		$selector   = $selectors[1];

		switch ( $combinator ) {
			case self::COMBINATOR_CHILD:
				if ( '*' === $selector->type_selector->ident || strcasecmp( $breadcrumbs[0], $selector->type_selector->ident ) === 0 ) {
					return $this->explore_matches( array_slice( $selectors, 2 ), array_slice( $breadcrumbs, 1 ) );
				}
				return $this->explore_matches( $selectors, array_slice( $breadcrumbs, 1 ) );

			case self::COMBINATOR_DESCENDANT:
				$ident = $selector->type_selector->ident;

				// Find _all_ the breadcrumbs that match and recurse from each of them.
				for ( $i = 0; $i < count( $breadcrumbs ); $i++ ) {
					if ( '*' === $selector->type_selector->ident || strcasecmp( $breadcrumbs[ $i ], $selector->type_selector->ident ) === 0 ) {
						$next_crumbs = array_slice( $breadcrumbs, $i + 1 );
						if ( $this->explore_matches( array_slice( $selectors, 2 ), $next_crumbs ) ) {
							return true;
						}
					}
				}
				return false;

			default:
				throw new Exception( "Combinator '{$combinator}' is not supported yet." );
		}
	}

	const COMBINATOR_CHILD              = '>';
	const COMBINATOR_DESCENDANT         = ' ';
	const COMBINATOR_NEXT_SIBLING       = '+';
	const COMBINATOR_SUBSEQUENT_SIBLING = '~';

	/**
	 * even indexes are WP_CSS_Selector, odd indexes are string combinators.
	 * In reverse order to match the current element and then work up the tree.
	 * Any non-final selector is a type selector.
	 *
	 * @var array<WP_CSS_Selector>
	 */
	public $selectors = array();

	private function __construct( array $selectors ) {
		$this->selectors = array_reverse( $selectors );
	}

	public static function parse( string $input, int &$offset ): ?self {
		if ( $offset >= strlen( $input ) ) {
			return null;
		}

		$updated_offset = $offset;
		$selector       = WP_CSS_Selector::parse( $input, $updated_offset );
		if ( null === $selector ) {
			return null;
		}

		$selectors                       = array( $selector );
		$has_preceding_subclass_selector = null !== $selector->subclass_selectors;

		$found_whitespace = self::parse_whitespace( $input, $updated_offset );
		while ( $updated_offset < strlen( $input ) ) {
			if (
				self::COMBINATOR_CHILD === $input[ $updated_offset ] ||
				self::COMBINATOR_NEXT_SIBLING === $input[ $updated_offset ] ||
				self::COMBINATOR_SUBSEQUENT_SIBLING === $input[ $updated_offset ]
			) {
				$combinator = $input[ $updated_offset ];
				++$updated_offset;
				self::parse_whitespace( $input, $updated_offset );

				// Failure to find a selector here is a parse error
				$selector = WP_CSS_Selector::parse( $input, $updated_offset );
			} elseif ( $found_whitespace ) {
				/*
				* Whitespace is ambiguous, it could be a descendant combinator or
				* insignificant whitespace.
				*/
				$selector = WP_CSS_Selector::parse( $input, $updated_offset );
				if ( null === $selector ) {
					break;
				}
				$combinator = self::COMBINATOR_DESCENDANT;
			} else {
				break;
			}

			if ( null === $selector ) {
				return null;
			}

			// `div > .className` is valid, but `.className > div` is not.
			if ( $has_preceding_subclass_selector ) {
				throw new Exception( 'Unsupported non-final subclass selector.' );
			}
			$has_preceding_subclass_selector = null !== $selector->subclass_selectors;

			$selectors[] = $combinator;
			$selectors[] = $selector;

			$found_whitespace = self::parse_whitespace( $input, $updated_offset );
		}
		$offset = $updated_offset;
		return new self( $selectors );
	}
}

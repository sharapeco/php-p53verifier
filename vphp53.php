<?php
/*
	php vphp53.php CWD file|dir
*/
require_once __DIR__ . '/includes/directory_walker.php';

// each() で発生する E_DEPRECATED を抑制する
error_reporting(E_ERROR | E_PARSE);

require_once 'Shaked/php.tools/src/Core/constants.php';
require_once 'Shaked/php.tools/src/Core/FormatterPass.php';
require_once 'Shaked/php.tools/src/Additionals/AdditionalPass.php';

const VT_EMPTY_ARRAY = 'VT_EMPTY_ARRAY';
const VT_BRACE_OPEN = '{';
const VT_BRACE_CLOSE = '}';
const VT_FUNCTION = 'VT_FUNCTION';

final class P53Verifier extends AdditionalPass {

	private $errors = [];

	public function getErrors() {
		return $this->errors;
	}

	public function verify($source, $debug = false) {
		$this->tkns = token_get_all($source);

		$currentLine = 0;
		$contextStack = [];
		$functionBrace = false;
		$functionDepth = 0;

		$prevStackDesc = null;

		foreach ($this->tkns as $index => $token) {
			list ($id, $text) = $this->getToken($token);
			if (isset($token[2])) {
				$currentLine = $token[2];
			}
			$this->ptr = $index;

			if ($debug) {
				$stackDesc = $this->printStack($contextStack);
				if ($stackDesc !== $prevStackDesc) {
					echo $stackDesc . "\n";
					$prevStackDesc = $stackDesc;
				}
			}

			switch ($id) {
			case VT_BRACE_OPEN:
				if ($functionBrace) {
					$contextStack[] = VT_FUNCTION;
					$functionDepth++;
				} else {
					$contextStack[] = VT_BRACE_OPEN;
				}
				$functionBrace = false;
				break;
			case VT_BRACE_CLOSE:
				if (!isset($contextStack[0])) {
					$this->errors[] = 'Missing brace';
					return;
				}
				$start = array_pop($contextStack);
				if ($start === VT_FUNCTION) {
					$functionDepth--;
				}
				break;
			case ST_BRACKET_OPEN:
				if ($this->isShortArray()) {
					$this->errors[] = ['Short array syntax []' , $currentLine];
				}
				$contextStack[] = ST_BRACKET_OPEN;
				break;
			case ST_BRACKET_CLOSE:
				if (!isset($contextStack[0])) {
					$this->errors[] = 'Missing bracket';
					return;
				}
				$start = array_pop($contextStack);
				break;
			case ST_PARENTHESES_OPEN:
				if (isset($contextStack[0]) && T_ARRAY == end($contextStack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
					array_pop($contextStack);
					$contextStack[] = VT_EMPTY_ARRAY;
				} elseif (!$this->leftTokenIs([T_ARRAY, T_STRING])) {
					$contextStack[] = ST_PARENTHESES_OPEN;
				}
				break;
			case ST_PARENTHESES_CLOSE:
				if (!isset($contextStack[0])) {
					$this->errors[] = 'Missing parentheses';
					return;
				}
				$start = array_pop($contextStack);
				break;
			case T_FUNCTION:
				// 次に来る brace を function のものとする
				$functionBrace = true;
				break;
			case T_STRING:
				if ($text === 'self' && $functionDepth >= 2) {
					$this->errors[] = ['Cannot access self:: in closure', $currentLine];
				}
				if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
					$contextStack[] = $text; // T_STRING;
				}
				list ($isNew, $type) = $this->isNewIdentifier($text);
				if ($isNew) {
					$this->errors[] = [$type . ' ' . $text, $currentLine];
				}
				break;
			case T_VARIABLE:
				if ($text === '$this' && $functionDepth >= 2) {
					$this->errors[] = ['Cannot access $this in closure', $currentLine];
				}
				break;
			case T_ARRAY:
				if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
					$contextStack[] = T_ARRAY;
				}
				break;
			case T_LNUMBER:
				if (preg_match('/^0b/i', $text)) {
					$this->errors[] = ['Binary integer literal', $currentLine];
				}
				break;
			case T_COALESCE:
				$this->errors[] = ['Null coalescing operator ??', $currentLine];
				break;
			case T_ELLIPSIS:
				$this->errors[] = ['Variable argument syntax ...', $currentLine];
				break;
			case T_FINALLY:
				$this->errors[] = ['Finally block', $currentLine];
				break;
			case T_POW:
			case T_POW_EQUAL:
				$this->errors[] = ['Power operator **', $currentLine];
				break;
			case T_SPACESHIP:
				$this->errors[] = ['Spaceship operator <=>', $currentLine];
				break;
			case T_INSTEADOF:
			case T_TRAIT:
			case T_TRAIT_C:
				$this->errors[] = ['Traits', $currentLine];
				break;
			case T_YIELD:
			case T_YIELD_FROM:
				$this->errors[] = ['Generator syntax (yield)', $currentLine];
				break;
			}
		}
	}

	public function isNewIdentifier($text) {
		static $identifiers = [
			['DateTimeImmutable', 'class'],
			['DateTimeInterface', 'interface'],
			['getimagesizefromstring', 'function'],
			['http_response_code', 'function'],
		];
		foreach ($identifiers as list($id, $type)) {
			if ($id === $text) {
				return [true, $type];
			}
		}
		return [false, null];
	}

	public function printStack(array $stack) {
		$stack = array_map(function($token) {
			switch ($token) {
			case T_FUNCTION: return 'FUNCTION';
			case T_STRING: return 'STRING';
			case T_VARIABLE: return 'VARIABLE';
			case T_ARRAY: return 'ARRAY';
			case T_LNUMBER: return 'NUMBER';
			default: return $token;
			}
		}, $stack);
		return implode(' > ', $stack);
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function candidate($source, $foundTokens) {
		return true;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function format($source) {
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Convert short to long arrays.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return '';
	}
}

function main($argv) {
	if (!isset($argv[2])) {
		fputs(STDERR, 'Usage: vphp53 <path>' . PHP_EOL);
		exit(1);
	}

	$path = realpath($argv[1] . DIRECTORY_SEPARATOR . $argv[2]);
	$walker = new DirectoryWalker($path, '[.]php$');
	$success = 0;
	$total = 0;
	while (($file = $walker->next())) {
		$success += verify($file) ? 1 : 0;
		$total++;
	}

	exit(0);
}

// @return success: boolean
function verify($file) {
	$source = file_get_contents($file);
	if ($source === false) {
		throw new RuntimeException('Could not open the file: ' . $file);
	}

	$verifier = new P53Verifier();
	$verifier->verify($source);
	$errors = $verifier->getErrors();
	if (count($errors) === 0) {
		return true;
	}

	echo $file, PHP_EOL;
	foreach ($errors as $error) {
		list ($message, $line) = $error;
		echo '  Line ', $line, ': ', $message, PHP_EOL;
	}

	return false;
}

main($argv);

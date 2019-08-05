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

final class P53Verifier extends AdditionalPass {
	const EMPTY_ARRAY = 'ST_EMPTY_ARRAY';

	private $errors = [];

	public function getErrors() {
		return $this->errors;
	}

	public function verify($source) {
		$this->tkns = token_get_all($source);

		$currentLine = 0;
		$contextStack = [];
		foreach ($this->tkns as $index => $token) {
			list ($id, $text) = $this->getToken($token);
			if (isset($token[2])) {
				$currentLine = $token[2];
			}
			$this->ptr = $index;
			switch ($id) {
			case ST_BRACKET_OPEN:
				$found = ST_BRACKET_OPEN;
				if ($this->isShortArray()) {
					$this->errors[] = ['Short array syntax []' , $currentLine];
				}
				$contextStack[] = $found;
				break;
			case ST_BRACKET_CLOSE:
				if (isset($contextStack[0]) && !$this->leftTokenIs(ST_BRACKET_OPEN)) {
					array_pop($contextStack);
				}
				break;
			case T_STRING:
				if ($this->rightTokenIs(ST_PARENTHESES_OPEN)) {
					$contextStack[] = T_STRING;
				}
				list ($isNew, $type) = $this->isNewIdentifier($text);
				if ($isNew) {
					$this->errors[] = [$type . ' ' . $text, $currentLine];
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
			case ST_PARENTHESES_OPEN:
				if (isset($contextStack[0]) && T_ARRAY == end($contextStack) && $this->rightTokenIs(ST_PARENTHESES_CLOSE)) {
					array_pop($contextStack);
					$contextStack[] = self::EMPTY_ARRAY;
				} elseif (!$this->leftTokenIs([T_ARRAY, T_STRING])) {
					$contextStack[] = ST_PARENTHESES_OPEN;
				}
				break;
			case ST_PARENTHESES_CLOSE:
				if (isset($contextStack[0])) {
					array_pop($contextStack);
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
		];
		foreach ($identifiers as list($id, $type)) {
			if ($id === $text) {
				return [true, $type];
			}
		}
		return [false, null];
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

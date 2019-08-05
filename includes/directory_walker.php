<?php

class DirectoryWalker {

	// string
	protected $base;

	// string
	protected $filter;

	// string[]
	protected $rest = [];

	public function __construct($path, $filter = '.') {
		$this->base = $path;
		$this->filter = $filter;
		$this->addPath($path);
	}

	protected function addPath($path) {
		if (!file_exists($path)) {
			throw new RuntimeException('Path "' . $path . '" does not exist.');
		} else if (is_file($path)) {
			$this->rest[] = $path;
		} else if (is_dir($path)) {
			foreach (new DirectoryIterator($path) as $fi) {
				if ($fi->isDot()) continue;
				$this->rest[] = $fi->getPathname();
			}
		}
	}

	public function next() {
		do {
			if (count($this->rest) === 0) {
				return null;
			}
			$path = array_shift($this->rest);
			if (is_dir($path)) {
				$this->addPath($path);
				continue;
			}
		} while (!preg_match('{' . $this->filter . '}', $path));

		return $path;
	}

}

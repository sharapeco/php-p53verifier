<?php
class C {
	const NUM = 3;

	private $k = 5;

	public function getNumFn() {
		echo self::NUM;
		echo $this->k;

		return function($x) {
			// short array syntax
			return [
				// self:: in clouse
				self::NUM,
				// $this in closure
				$this->k,
				$x,
			];
		};
	}
}

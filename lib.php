<?php

function getbits($bitstr, $bitindex, $len) {
	$size = strlen($bitstr);

	$out = 0;
	$bytepos = (int) floor($bitindex / 8);
	$bitpos = $bitindex - $bytepos * 8;

	$i = 0;
	while ($i < $len) {
		if ($bytepos >= $size)
			throw new Exception("Not enough binary data to read $len bits from $bitindex, only $size bytes");
		$count = min(8 - $bitpos, $len - $i);
		$out <<= $count;

		$byte = ord($bitstr[$bytepos]);
		$mask = (((1 << $count) - 1) << ((8 - $bitpos) - $count));
		$out |= ($byte & $mask) >> ((8 - $bitpos) - $count);

		$i += $count;
		$bitpos = 0;
		$bytepos++;
	}

	return $out;
}

// value is a number, not another bitstring, so this is limited to what fits in a word
function setbits(&$bitstr, $bitindex, $value, $count) {
	$size = strlen($bitstr);

	$bytepos = (int) floor($bitindex / 8);
	$bitpos = $bitindex - $bytepos * 8;

	while ($count > 0) {
		// mask off already used top bits
		$from = $value & ((1 << $count) - 1);

		$base = 0;
		if ($bytepos < $size)
			$base = ord($bitstr[$bytepos]);

		if ($count + $bitpos < 8) {
			// We won't finish a byte; less than 8 bits remain
			$bitstr[$bytepos] = chr($base + ($from << ((8 - $bitpos) - $count)));
			$bitpos += $count;
			$count = 0;
		} else {
			// We'll finish a byte

			$c = 8 - $bitpos; // how many to grab

			// grab $c bits starting from $count
			$val = $from >> ($count - $c);

			$bitstr[$bytepos] = chr($base + $val);

			$bytepos += 1;
			$bitpos = 0;

			$count -= $c;
		}
	}
}

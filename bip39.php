<?php

// works for all values 128-256
function addChecksum($data) {
	$bits = strlen($data) / 4;
	$bytes = (int) ceil($bits / 8);
	$checksum = hex2bin(substr(hash('sha256', $data), 0, 2*$bytes));

	$hole = $bytes * 8 - $bits;
	$mask = (pow(2, $bytes * 8) - 1) & ~(pow(2, $hole) - 1);
	$checksum = ord($checksum) & $mask;

	return $data . chr($checksum);
}

function encode($dict, $len, $rand = null) {
	$index_bits = (int) floor(log(count($dict), 2));
	if ($index_bits < 8)
		throw new Exception("If you need less than 255 dictionary entries, rewrite the function");
	if (count($dict) !== pow(2, $index_bits))
		throw new Exception("Power of 2 dictionaries, or rewrite the function please");
	$mask = pow(2, $index_bits) - 1;

	$generated = false;
	if (!$rand) {
		$bytes = ceil(($len * $index_bits) / 8);
		$rand = random_bytes($bytes); // 132 bits becomes 136, last are ignored
		$generated = true;
	} else {
		$bytes = $len;
		$len = floor(($bytes * 8) / $index_bits);
	}

	$words = [];
	$index = 0;
	$consumed = 0;
	for ($i = 0; $i < $bytes; $i++) {
		$rest = $index_bits - $consumed;
		if ($rest > 8) {
			$index <<= 8;
			$index += ord($rand[$i]);
			$consumed += 8;
			continue;
		}

		// we could handle smaller dictionaries here w/an inner loop, or decrementing $i

		$index <<= $rest;
		$index += ord($rand[$i]) >> (8 - $rest);

		//echo "$index chosen\n";
		$words []= $dict[$index];

		$consumed = 8 - $rest;
		$index = ord($rand[$i]) & (255 >> $rest);
		//var_dump(compact('index', 'consumed', 'rest'));
	}

	if ($generated)
		return [implode(' ', $words), $rand];
	else
		return implode(' ', $words);
}

// works for 128-256 bits of entropy; 12 - 24 words
// https://github.com/bitcoin/bips/blob/master/bip-0039.mediawiki
function bip39encode($dict, $len, $rand = null) {
	$index_bits = (int) floor(log(count($dict), 2));

	$generated = false;
	if (!$rand) {
		$bytes = ceil(($len * $index_bits) / 8);
		$rand = addChecksum(random_bytes($bytes)); // 128 bits, +4 from checksum
		$generated = true;
	} else {
		$checksummed = addChecksum(substr($rand, 0, -1));
		if ($rand !== $checksummed)
			throw new Exception(
				'Checksum doesn\'t match, should end in ' . bin2hex(substr($checksummed, -1, 1)) .
				', got ' . bin2hex(substr($rand, -1, 1))
			);
		$bytes = $len;
		$len = floor(($bytes * 8) / $index_bits);
	}

	if ($bytes < 16 || $bytes > 32)
		throw new Exception("We can only handle 128-256 bits of entropy for BIP-39 right now");


	if ($generated)
		return [encode($dict, $bytes, $rand), $rand];
	else
		return encode($dict, $bytes, $rand);
}

function decode($str, $dict) {
	$index_bits = (int) floor(log(count($dict), 2));
	if ($index_bits < 8)
		throw new Exception("If you need less than 255 dictionary entries, rewrite the function");
	if (count($dict) !== pow(2, $index_bits))
		throw new Exception("Power of 2 dictionaries, or rewrite the function please");

	$bytes = (int) floor($index_bits / 8);
	$bits = $index_bits % 8;
	//var_dump(compact('bytes', 'bits'));

	$words = explode(' ', $str);
	$out = '';

	$pos = 0;
	$rest = 0;
	for ($i = 0; $i < count($words); $i++) {
		$found = array_search($words[$i], $dict);
		if ($found === false) {
			//echo "Word not found: $words[$i]\n";
			return false;
		}

		//if ($index_bits == 11) var_dump($words[$i], $found);

		$j = $index_bits;
		while ($j > 0) {
			// how much do I have left? How much to fill in prior byte?

			// mask off already used top bits
			$from = $found & (pow(2, $j) - 1);

			if ($pos + $j >= 8) {
				// We'll finish a byte

				$count = 8 - $pos; // how many to grab

				// grab $count bits starting from $j
				$val = $from >> ($j - $count);

				$rest += $val;

				$out .= chr($rest);
				$rest = 0;

				$pos = 0;
				$j -= $count;
			} else {
				// We won't finish a byte; less than 8 bits remain
				$rest += $from << ((8 - $pos) - $j);
				$pos += $j;
				$j = 0;
			}
		}
		/*
		for ($j = $bytes - 1; $j >= 0; $j--) {
			$out .= chr($found >> ((8 * $j) + $bits));
		}
		if ($bits) {
			if ($rest === null)
				$rest = 0;
			if ($pos + $bits < 8) {
				$rest += ($found & (255 >> (8 - $bits))) << ((8 - $pos) - $bits);
				$pos += $bits;
			} else {
			}
		}
		 */
	}
	if ($pos)
		$out .= chr($rest);

	return $out;
}

function bip39decode($str, $dict) {
	$binary = decode($str, $dict);
	if ($binary === false)
		return false;
	$checksummed = addChecksum(substr($binary, 0, -1));
	if ($binary !== $checksummed) {
		throw new Exception(
			'Checksum doesn\'t match, should end in ' . bin2hex(substr($checksummed, -1, 1)) .
			', got ' . bin2hex(substr($binary, -1, 1))
		);
	}
	return $binary;
}

if (get_included_files()[0] == __FILE__) {
	$dict = file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES);

	list($words, $rand) = bip39encode($dict, 12);
	echo "words: " . $words . "\n";
	echo "hex: " . bin2hex($rand) . "\n";
}

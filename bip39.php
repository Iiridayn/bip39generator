<?php

// works for all values 128-256
function addChecksum($bytes) {
	return $bytes . hex2bin(substr(hash('sha256', $bytes), 0, 2));
}

// works for 128-256 bits of entropy; 12 - 24 words
// https://github.com/bitcoin/bips/blob/master/bip-0039.mediawiki
function bip39($dict, $len, $rand = null) {
	$index_bits = (int) floor(log(count($dict), 2));

	$generated = false;
	if (!$rand) {
		$bytes = ceil(($len * $index_bits) / 8);
		$rand = addChecksum(random_bytes($bytes)); // 128 bits, +4 from checksum
		$generated = true;
	} else {
		// A bit stricter than the standard - always requires a full 2 bytes, works from 128-256 bits
		if ($rand !== addChecksum(substr($rand, 0, -1)))
			throw new Exception('Checksum doesn\'t match');
		$bytes = $len;
		$len = floor(($bytes * 8) / $index_bits);
	}

	if ($bytes < 16 || $bytes > 32)
		throw new Exception("We can only handle 128-256 bits of entropy for BIP-39 right now");


	if ($generated)
		return [run($dict, $bytes, $rand), $rand];
	else
		return run($dict, $bytes, $rand);
}

function run($dict, $len, $rand = null) {
	$index_bits = (int) floor(log(count($dict), 2));
	if ($index_bits < 8)
		throw new Exception("If you need less than 255 dictionary entries, rewrite the function");
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

if (get_included_files()[0] == __FILE__) {
	$dict = file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES);

	list($words, $rand) = bip39($dict, 12);
	echo "words: " . $words . "\n";
	echo "hex: " . bin2hex($rand) . "\n";
}

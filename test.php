<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);
require('bip39.php');

function mustEqual($a, $b, $exp = '') {
	if ($a !== $b) {
		echo ($exp ? $exp . ': ' : '') . var_export($a, true) . ' !== ' . var_export($b, true);
	}
}

function mustThrow($a, $exp = '') {
	try {
		$a();
	} catch (Exception $e) {
		return;
	}
	echo ($exp ? $exp . ': ' : '') . var_export($a, true) . ' did not throw an exception';
}

$base8 = range(0, 255);
mustEqual(run($base8, 2, "\x00\x00"), implode(' ', [$base8[0], $base8[0]]), 'base 8');
mustEqual(run($base8, 2, "\x00\xFF"), implode(' ', [$base8[0], $base8[255]]), 'base 8');
mustEqual(run($base8, 2, "\xFF\xFF"), implode(' ', [$base8[255], $base8[255]]), 'base 8');
mustEqual(run($base8, 4, "\x01\x02\x03\x04"), implode(' ', [$base8[1], $base8[2], $base8[3], $base8[4]]), 'base 8');

$base11 = range(0, 2047);
mustEqual(run($base11, 2, "\x00\x00"), implode(' ', [$base11[0]]), 'base 11');
mustEqual(run($base11, 2, "\x00\x1F"), implode(' ', [$base11[0]]), 'base 11');
mustEqual(run($base11, 2, "\xFF\xFF"), implode(' ', [$base11[2047]]), 'base 11');
mustEqual(run($base11, 3, "\x00\x00\x00"), implode(' ', [$base11[0], $base11[0]]), 'base 11');
mustEqual(run($base11, 3, "\x00\x00\x03"), implode(' ', [$base11[0], $base11[0]]), 'base 11');
mustEqual(run($base11, 3, "\xFF\xFF\xFF"), implode(' ', [$base11[2047], $base11[2047]]), 'base 11');

mustEqual(run($base11, 5, "\x00\x20\x08\x01\x80"), implode(' ', [$base11[1], $base11[2], $base11[3]]), 'base 11');
mustEqual(count(explode(' ', run($base11, 12)[0])), 12);

// Limitations
mustThrow(fn() => run(range(0, 1), 1));
mustEqual(count(explode(' ', run(range(0, pow(2, 20)-1), 3)[0])), 3);

// BIP 39 - uses a checksum, otherwise basically the same
$bipdict = file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES);
mustEqual(
	bip39($bipdict, 17, hex2bin("400131ddbc4019ea172ee84649bbdd7f66")),
	"divorce another jazz joy account vital frequent tackle edge evidence warrior yard"
);
mustEqual(
	bip39($bipdict, 17, addChecksum(hex2bin("400131ddbc4019ea172ee84649bbdd7f"))),
	"divorce another jazz joy account vital frequent tackle edge evidence warrior yard"
);
mustThrow(fn() => bip39($bipdict, 17, hex2bin("400131ddbc4019ea172ee84649bbdd7f00")), 'bad checksum');
mustEqual(count(explode(' ', bip39($bipdict, 12)[0])), 12);

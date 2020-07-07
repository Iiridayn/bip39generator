<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);
require('bip39.php');
require_once('lib.php');

function mustEqual($a, $b, $exp = '') {
	if ($a !== $b) {
		echo ($exp ? $exp . ': ' : '') . bin2hex($a) . ' !== ' . bin2hex($b) . "\n";
	}
}

function mustThrow($a, $exp = '') {
	try {
		$a();
	} catch (Exception $e) {
		return;
	}
	echo ($exp ? $exp . ': ' : '') . var_export($a, true) . " did not throw an exception\n";
}

// lib

mustEqual(getbits(hex2bin('00'), 0, 1), 0x00, '0');
mustEqual(getbits(hex2bin('ff'), 0, 1), 0x01, '1 from ff');
mustEqual(getbits(hex2bin('55'), 0, 3), 0x02, '3 at 0 from 55');
mustEqual(getbits(hex2bin('55'), 1, 3), 0x05, '3 at 1 from 55');
mustEqual(getbits(hex2bin('5555'), 6, 3), 0x02, '3 at 7 from 5555');
mustEqual(getbits(hex2bin('5555'), 7, 3), 0x05, '3 at 6 from 5555');
mustEqual(getbits(hex2bin('5555'), 8, 3), 0x02, '3 at 8 from 5555');
mustEqual(getbits(hex2bin('555555'), 6, 9), 0x0AA, '9 at 6 from 555555');
mustEqual(getbits(hex2bin('555555'), 7, 9), 0x155, '9 at 6 from 555555');
mustThrow(fn() => getbits(hex2bin('00'), 8, 1), 'not enough string');
mustThrow(fn() => getbits(hex2bin('00'), 7, 2), 'not enough string');

$base8 = array_map(fn($x) => strval($x), range(0, 255));
$base11 = array_map(fn($x) => strval($x), range(0, 2047));
$base20 = array_map(fn($x) => strval($x), range(0, pow(2, 20) - 1));

// encode

// Limitations
mustThrow(fn() => encode(range(0, 1), 1));
mustThrow(fn() => encode(range(0, 256), 1));

mustEqual(encode($base8, 2, "\x00\x00"), implode(' ', [$base8[0], $base8[0]]), 'base 8');
mustEqual(encode($base8, 2, "\x00\xFF"), implode(' ', [$base8[0], $base8[255]]), 'base 8');
mustEqual(encode($base8, 2, "\xFF\xFF"), implode(' ', [$base8[255], $base8[255]]), 'base 8');
mustEqual(encode($base8, 4, "\x01\x02\x03\x04"), implode(' ', [$base8[1], $base8[2], $base8[3], $base8[4]]), 'base 8');

mustEqual(encode($base11, 2, "\x00\x00"), implode(' ', [$base11[0]]), 'base 11');
mustEqual(encode($base11, 2, "\x00\x1F"), implode(' ', [$base11[0]]), 'base 11');
mustEqual(encode($base11, 2, "\xFF\xFF"), implode(' ', [$base11[2047]]), 'base 11');
mustEqual(encode($base11, 3, "\x00\x00\x00"), implode(' ', [$base11[0], $base11[0]]), 'base 11');
mustEqual(encode($base11, 3, "\x00\x00\x03"), implode(' ', [$base11[0], $base11[0]]), 'base 11');
mustEqual(encode($base11, 3, "\xFF\xFF\xFF"), implode(' ', [$base11[2047], $base11[2047]]), 'base 11');

mustEqual(encode($base11, 5, "\x00\x20\x08\x01\x80"), implode(' ', [$base11[1], $base11[2], $base11[3]]), 'base 11');
mustEqual(count(explode(' ', encode($base11, 12)[0])), 12);

// very large dictionary
mustEqual(count(explode(' ', encode($base20, 3)[0])), 3);

// BIP 39 - uses a checksum, otherwise basically the same
$bipdict = file(__DIR__ . '/english.txt', FILE_IGNORE_NEW_LINES);
mustEqual(
	bip39encode($bipdict, 17, hex2bin("0000000000000000000000000000000030")),
	"abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about"
);
mustEqual(
	bip39encode($bipdict, 17, hex2bin("ffffffffffffffffffffffffffffffff50")),
	"zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo wrong"
);
mustEqual(
	bip39encode($bipdict, 25, hex2bin("ffffffffffffffffffffffffffffffffffffffffffffffff44")),
	"zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo when"
);
mustEqual(
	bip39encode($bipdict, 17, hex2bin("400131ddbc4019ea172ee84649bbdd7f60")),
	"divorce another jazz joy account vital frequent tackle edge evidence warrior yard"
);
mustEqual(
	bip39encode($bipdict, 17, addChecksum(hex2bin("400131ddbc4019ea172ee84649bbdd7f"))),
	"divorce another jazz joy account vital frequent tackle edge evidence warrior yard"
);
mustThrow(fn() => bip39encode($bipdict, 17, hex2bin("400131ddbc4019ea172ee84649bbdd7f00")), 'bad checksum');
mustEqual(count(explode(' ', bip39encode($bipdict, 12)[0])), 12);


// decode

// Limitations
mustThrow(fn() => decode('0', range(0, 1)));
mustThrow(fn() => decode('0', range(0, 256)));
mustEqual(decode('lolnope', $base8), false);

mustEqual(decode(implode(' ', [$base8[0]]), $base8), "\x00");
mustEqual(decode(implode(' ', [$base8[0], $base8[0]]), $base8), "\x00\x00");
mustEqual(decode(implode(' ', [$base8[0], $base8[1]]), $base8), "\x00\x01");
mustEqual(decode(implode(' ', [$base8[255], $base8[255]]), $base8), "\xFF\xFF");
mustEqual(decode(implode(' ', [$base8[1], $base8[2], $base8[3], $base8[4]]), $base8), "\x01\x02\x03\x04");

mustEqual(decode(implode(' ', [$base11[0]]), $base11), "\x00\x00");
mustEqual(decode(implode(' ', [$base11[0], $base11[0]]), $base11), "\x00\x00\x00");
mustEqual(decode(implode(' ', [$base11[1]]), $base11), "\x00\x20");
mustEqual(decode(implode(' ', [$base11[2047]]), $base11), "\xFF\xE0");
mustEqual(decode(implode(' ', [$base11[2047], $base11[2047]]), $base11), "\xFF\xFF\xFC");
mustEqual(decode(implode(' ', [$base11[1], $base11[2], $base11[3]]), $base11), "\x00\x20\x08\x01\x80");

mustEqual(decode(implode(' ', [$base20[1], $base20[pow(2, 20) - 1]]), $base20), "\x00\x00\x1f\xff\xff");

// BIP 39
mustEqual(
	bip39decode("abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about", $bipdict),
	hex2bin("0000000000000000000000000000000030")
);
mustEqual(
	bip39decode("zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo wrong", $bipdict),
	hex2bin("ffffffffffffffffffffffffffffffff50")
);
mustEqual(
	bip39decode("zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo when", $bipdict),
	hex2bin("ffffffffffffffffffffffffffffffffffffffffffffffff44")
);
mustEqual(
	bip39decode("divorce another jazz joy account vital frequent tackle edge evidence warrior yard", $bipdict),
	hex2bin("400131ddbc4019ea172ee84649bbdd7f60"),
);

mustEqual(decode(encode($base8, 2, "\x00\x00"), $base8), "\x00\x00");
mustEqual(decode(encode($base8, 2, "\xff\xff"), $base8), "\xff\xff");
mustEqual(decode(encode($base11, 5, "\x00\x20\x08\x01\x80"), $base11), "\x00\x20\x08\x01\x80");

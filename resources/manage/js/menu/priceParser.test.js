import { describe, expect, it } from 'vitest';
import { parsePrice, parseNumber } from './priceParser';

describe('parsePrice', () => {
	it.each([
		['2,50', 2.5], ['2.5', 2.5], ['€ 4,50', 4.5], ['4,50 €', 4.5], ['€4.50', 4.5],
		['5 euro', 5], ['5 EURO', 5], ['EUR 3', 3], ['3,-', 3], ['3.-', 3], ['0', 0], ['  2,50  ', 2.5],
		['1.250,00', 1250], ['1,250.00', 1250], ['12', 12], [2.5, 2.5], ['2,505', 2.51],
	])('reads %s as %s', (input, expected) => {
		expect(parsePrice(input)).toBe(expected);
	});

	it.each([
		'', '   ', 'Koffie', '25cl', '33 cl', '-2', '2,50 per stuk', '?', null, undefined, '1,2,3',
		'- € 4,50', 'kaas 5 euro', '25cl ..... 2,50', 'cl',
	])('does not read %s as a price', (input) => {
		expect(parsePrice(input)).toBeNull();
	});
});

describe('parseNumber', () => {
	it.each([['21', 21], ['21%', 21], ['6,5 %', 6.5]])('reads %s as %s', (input, expected) => {
		expect(parseNumber(input)).toBe(expected);
	});
	it.each(['€ 2', ''])('does not read %s as a number', (input) => {
		expect(parseNumber(input)).toBeNull();
	});
});

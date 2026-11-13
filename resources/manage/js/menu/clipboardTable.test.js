import { describe, expect, it } from 'vitest';
import { parseClipboardTable } from './clipboardTable';

describe('parseClipboardTable', () => {
	it('splits tabs and lines', () => {
		expect(parseClipboardTable('Pils\t2,50\nDuvel\t4,50')).toEqual([['Pils', '2,50'], ['Duvel', '4,50']]);
	});
	it('accepts Windows line ends', () => {
		expect(parseClipboardTable('a\t1\r\nb\t2\r\n')).toEqual([['a', '1'], ['b', '2']]);
	});
	it('keeps a quoted cell with a line break as one cell', () => {
		expect(parseClipboardTable('"Duvel\n33cl"\t4,50\nCola\t2')).toEqual([['Duvel 33cl', '4,50'], ['Cola', '2']]);
	});
	it('reads doubled quotes inside a quoted cell', () => {
		expect(parseClipboardTable('"Wijn ""rood"""\t4')).toEqual([['Wijn "rood"', '4']]);
	});
	it('keeps a quote that is not at the start of a cell', () => {
		expect(parseClipboardTable('Pizza 12"\t9')).toEqual([['Pizza 12"', '9']]);
	});
	it('drops empty lines and trims cells', () => {
		expect(parseClipboardTable('\n  Pils \t 2 \n\n\t\nCola\t2\n')).toEqual([['Pils', '2'], ['Cola', '2']]);
	});
	it('pads short rows', () => {
		expect(parseClipboardTable('a\tb\tc\nd')).toEqual([['a', 'b', 'c'], ['d', '', '']]);
	});
	it('returns one column for plain lines', () => {
		expect(parseClipboardTable('Pils 2,50\nCola 2')).toEqual([['Pils 2,50'], ['Cola 2']]);
	});
	it('returns an empty list for empty text', () => {
		expect(parseClipboardTable('')).toEqual([]);
		expect(parseClipboardTable(null)).toEqual([]);
	});
});

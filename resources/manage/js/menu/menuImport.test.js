import { describe, expect, it } from 'vitest';
import {
	COLUMN_TYPES, smartSplitLine, prepareSheet, guessColumnTypes, setColumnType, buildImportRows, canImport,
	sheetFromItems, matchByName, namesToCreate, headingOrderFromItems,
} from './menuImport';

describe('COLUMN_TYPES', () => {
	it('lists the assignable types', () => {
		expect(COLUMN_TYPES).toEqual(['name', 'description', 'price', 'vat_percentage', 'category', 'heading', 'ignore']);
	});
});

describe('smartSplitLine', () => {
	it.each([
		['Pils 25cl ..... 2,50', ['Pils 25cl', '2,50']],
		['Pils 25cl…… 2,50', ['Pils 25cl', '2,50']],
		['Duvel - € 4,50', ['Duvel', '€ 4,50']],
		['Duvel – 4,50 €', ['Duvel', '4,50 €']],
		['Cola: 2,5', ['Cola', '2,5']],
		['Croque monsieur - ham en kaas 5 euro', ['Croque monsieur - ham en kaas', '5 euro']],
		['7up 2,50', ['7up', '2,50']],
		['Wijn (rood) 3,-', ['Wijn (rood)', '3,-']],
		['Koffie', ['Koffie', '']],
		['Pils 25cl', ['Pils 25cl', '']],
		['Pils 33 cl', ['Pils 33 cl', '']],
		['BIEREN', ['BIEREN', '']],
		['2,50', ['', '2,50']],
	])('splits %s', (line, expected) => { expect(smartSplitLine(line)).toEqual(expected); });
});

describe('prepareSheet', () => {
	it('splits a one-column paste', () => {
		expect(prepareSheet([['Pils 2,50'], ['Koffie']])).toEqual([['Pils', '2,50'], ['Koffie', '']]);
	});
	it('leaves a table unchanged', () => {
		const rows = [['Pils', '2,50'], ['Cola', '2']];
		expect(prepareSheet(rows)).toEqual(rows);
	});
});

describe('guessColumnTypes', () => {
	it('finds name and price', () => {
		expect(guessColumnTypes([['Pils', '2,50'], ['Cola', '2']])).toEqual(['name', 'price']);
	});
	it('finds name, description and price, and ignores the rest', () => {
		expect(guessColumnTypes([
			['Pils', '25cl', 'vat', '2,50'], ['Duvel', '33cl fles', 'fles', '€ 4,50'], ['Koffie', '', '', ''],
		])).toEqual(['name', 'description', 'ignore', 'price']);
	});
	it('uses the last price-like column as the price', () => {
		expect(guessColumnTypes([['Pils', '21', '2,50'], ['Cola', '21', '2']])).toEqual(['name', 'ignore', 'price']);
	});
	it('accepts a price column before the name', () => {
		expect(guessColumnTypes([['2,50', 'Pils'], ['2', 'Cola']])).toEqual(['price', 'name']);
	});
	it('tolerates a header row and a missing price', () => {
		expect(guessColumnTypes([['Product', 'Prijs'], ['Pils', '2,50'], ['Cola', '2'], ['Koffie', '']])).toEqual(['name', 'price']);
	});
	it('never guesses category or heading', () => {
		const types = guessColumnTypes([['Pils', 'Bar', 'Bieren', '2,50'], ['Cola', 'Bar', 'Frisdrank', '2']]);
		expect(types).not.toContain('category');
		expect(types).not.toContain('heading');
	});
	it('returns an empty list for no rows', () => { expect(guessColumnTypes([])).toEqual([]); });
});

describe('setColumnType', () => {
	it('moves a type away from its previous column', () => {
		expect(setColumnType(['name', 'price', 'ignore'], 2, 'price')).toEqual(['name', 'ignore', 'price']);
	});
	it('allows ignore on several columns', () => {
		expect(setColumnType(['name', 'price', 'ignore'], 0, 'ignore')).toEqual(['ignore', 'price', 'ignore']);
	});
});

describe('buildImportRows', () => {
	it('builds rows and marks errors', () => {
		expect(buildImportRows(
			[['Pils', '25cl', '2,50', '21%'], ['Koffie', '', '', ''], ['', '', '3', ''], ['Thee', '', 'gratis', '']],
			['name', 'description', 'price', 'vat_percentage'],
		)).toEqual([
			{ name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21, category: '', heading: '', errors: [] },
			{ name: 'Koffie', description: '', price: null, vat_percentage: null, category: '', heading: '', errors: ['price'] },
			{ name: '', description: '', price: 3, vat_percentage: null, category: '', heading: '', errors: ['name'] },
			{ name: 'Thee', description: '', price: null, vat_percentage: null, category: '', heading: '', errors: ['price'] },
		]);
	});
	it('reads category and heading as trimmed text without causing errors', () => {
		const types = ['name', 'price', 'category', 'heading'];
		expect(buildImportRows([['Pils', '2', ' Bar ', ' Bieren '], ['Cola', '2', '', '']], types)).toEqual([
			{ name: 'Pils', description: '', price: 2, vat_percentage: null, category: 'Bar', heading: 'Bieren', errors: [] },
			{ name: 'Cola', description: '', price: 2, vat_percentage: null, category: '', heading: '', errors: [] },
		]);
	});
});

describe('canImport', () => {
	it('needs a name column and a price column', () => {
		const rows = buildImportRows([['Pils', '2']], ['name', 'ignore']);
		expect(canImport(['name', 'ignore'], rows)).toBe(false);
	});
	it('is false while a row has errors', () => {
		const types = ['name', 'price'];
		expect(canImport(types, buildImportRows([['Pils', '2'], ['Koffie', '']], types))).toBe(false);
	});
	it('is false without rows', () => { expect(canImport(['name', 'price'], [])).toBe(false); });
	it('is true for clean rows', () => {
		const types = ['name', 'price'];
		expect(canImport(types, buildImportRows([['Pils', '2']], types))).toBe(true);
	});
});

describe('sheetFromItems', () => {
	const TYPES = ['name', 'description', 'price', 'vat_percentage'];
	const ALL_TYPES = [...TYPES, 'category', 'heading'];
	const items = [
		{ id: 1, name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21 },
		{ id: 2, name: 'Cola', description: null, price: 2, vat_percentage: null },
	];
	const withNames = [
		{ id: 1, name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21, category: { id: 1, name: 'Drinks' }, heading: { id: 4, name: 'Bieren', position: 1 } },
		{ id: 2, name: 'Cola', description: null, price: 2, vat_percentage: null, category: { id: 1, name: 'Drinks' } },
	];

	it('builds one row per product with the four column types', () => {
		expect(sheetFromItems(items)).toEqual({
			cells: [['Pils', '25cl', '2.50', '21'], ['Cola', '', '2.00', '']],
			types: TYPES,
		});
	});
	it('adds a category column and a heading column with the names', () => {
		expect(sheetFromItems(withNames)).toEqual({
			cells: [['Pils', '25cl', '2.50', '21', 'Drinks', 'Bieren'], ['Cola', '', '2.00', '', 'Drinks', '']],
			types: ALL_TYPES,
		});
	});
	it('leaves out a column that is empty for all products', () => {
		const sheet = sheetFromItems([
			{ id: 1, name: 'Pils', description: null, price: 2.5, vat_percentage: null, category: { id: 1, name: 'Drinks' } },
		]);
		expect(sheet).toEqual({ cells: [['Pils', '2.50', 'Drinks']], types: ['name', 'price', 'category'] });
		expect(sheetFromItems([{ id: 1, name: 'Pils', price: 2 }])).toEqual({ cells: [['Pils', '2.00']], types: ['name', 'price'] });
	});
	it('round trips category and heading names', () => {
		const sheet = sheetFromItems(withNames);
		expect(buildImportRows(sheet.cells, sheet.types).map(row => [row.category, row.heading]))
			.toEqual([['Drinks', 'Bieren'], ['Drinks', '']]);
	});
	it('writes a VAT with decimals as plain number text', () => {
		const sheet = sheetFromItems([{ name: 'Wijn', description: 'rood', price: 4, vat_percentage: 6.5 }]);
		expect(sheet.cells[0][3]).toBe('6.5');
	});
	it('gives an empty sheet for no products', () => {
		expect(sheetFromItems([])).toEqual({ cells: [], types: TYPES });
	});
	it('reads back to the same products', () => {
		const sheet = sheetFromItems(items);
		expect(buildImportRows(sheet.cells, sheet.types)).toEqual([
			{ name: 'Pils', description: '25cl', price: 2.5, vat_percentage: 21, category: '', heading: '', errors: [] },
			{ name: 'Cola', description: '', price: 2, vat_percentage: null, category: '', heading: '', errors: [] },
		]);
	});
});

describe('matchByName', () => {
	it('ignores letter case and outer spaces', () => {
		expect(matchByName(' bieren ', [{ id: 1, name: 'Bieren' }]).id).toBe(1);
		expect(matchByName('Bier', [{ id: 1, name: 'Bieren' }])).toBeNull();
		expect(matchByName('', [{ id: 1, name: 'Bieren' }])).toBeNull();
		expect(matchByName('  ', [{ id: 1, name: '' }])).toBeNull();
	});
});

describe('namesToCreate', () => {
	const rows = ['Bieren', 'bieren', 'Snacks', '', 'Frisdrank'].map(heading => ({ heading, category: '' }));
	it('returns each unmatched name once, preferred names first', () => {
		expect(namesToCreate(rows, 'heading', [{ id: 1, name: 'Snacks' }], ['Frisdrank'])).toEqual(['Frisdrank', 'Bieren']);
	});
	it('keeps the order of appearance without a preferred order', () => {
		expect(namesToCreate(rows, 'heading', [])).toEqual(['Bieren', 'Snacks', 'Frisdrank']);
	});
	it('works on categories too', () => {
		expect(namesToCreate([{ category: 'Bar' }, { category: 'bar' }], 'category', [])).toEqual(['Bar']);
	});
});

describe('headingOrderFromItems', () => {
	it('returns each heading name once, by position then id', () => {
		const a = { id: 2, name: 'Bieren', position: 2 };
		const b = { id: 5, name: 'Frisdrank', position: 1 };
		expect(headingOrderFromItems([
			{ name: 'Pils', heading: a }, { name: 'Chips' }, { name: 'Cola', heading: b }, { name: 'Duvel', heading: a },
		])).toEqual(['Frisdrank', 'Bieren']);
	});
});

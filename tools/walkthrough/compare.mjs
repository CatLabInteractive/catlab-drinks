#!/usr/bin/env node
// Compare two walk-through reports item by item.
// Usage: node tools/walkthrough/compare.mjs <baseline.report.json> [<report.json>]
// The second file defaults to tools/walkthrough/output/report.json. Rules: tools/walkthrough/README.md,
// section "Compare with the baseline". Exit code 0: no differences, 1: differences, 2: bad arguments.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// Build-dependent names of the issue 251 error (a b-form-checkbox click or a b-form-select change).
// The development build shows the real names, a production build shows minified names.
// If a new build shows a new minified name, add it here.
export const ISSUE_251_VARIANTS = [
	'assign is not a function',
	'i is not a function',
	'el[assignKey] is not a function',
	'e[Lu] is not a function',
];

const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const ISSUE_251 = ISSUE_251_VARIANTS.map((v) => new RegExp('(^|[\\s:])' + escape(v) + '(?=$|[\\s(])', 'g'));

/** Remove what changes between two runs of the same code: hosts, IDs, run IDs, UUIDs, hashes, file positions. */
export function normalizeMessage(message) {
	let m = String(message);
	for (const re of ISSUE_251) m = m.replace(re, '$1ISSUE-251');
	m = m.replace(/https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?/g, '');
	m = m.replace(/(\.(?:m?js|ts|vue|php)(?:\?[^\s:)]*)?):\d+(?::\d+)?/g, '$1');
	m = m.replace(/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/gi, 'UUID');
	m = m.replace(/\b[0-9a-f]{8,}\b/gi, 'HASH');
	m = m.replace(/\/\d+(?=[/?#\s)]|$)/g, '/N');
	m = m.replace(/\b\d{4,}\b/g, 'N');
	return m;
}

/** The failed API calls in a note: label, method, path without query, and status. The answer and the body are left out. */
export function apiLines(note) {
	const lines = [];
	const re = /\[([\w-]+)\] (GET|POST|PUT|PATCH|DELETE) (\S+) -> (\d{3})/g;
	for (const m of String(note || '').matchAll(re)) {
		lines.push(`[${m[1]}] ${m[2]} ${normalizeMessage(m[3].split('?')[0])} -> ${m[4]}`);
	}
	return lines;
}

// Cause P2 of the baseline: the offline queue of the POS retries a rejected sale in the background,
// so the item that gets the 422 depends on timing.
export function isP2(message) {
	return /\b422\b/.test(message) && (/\/pos-api\/v1\/events\/N\/orders\b/.test(message) || /status code 422/.test(message));
}

const BLOCK_EXTRA = ['nfc-card-payment', 'nfc-card-insufficient-funds'];

/** The IDs of the POS block: from the first pos-sale-* item to pos-summary, plus the two card payment items. */
export function posBlock(report) {
	const ids = report.map((r) => r.id);
	const start = ids.findIndex((id) => id.startsWith('pos-sale-'));
	const end = ids.indexOf('pos-summary');
	const block = new Set(BLOCK_EXTRA);
	if (start >= 0 && end >= start) for (const id of ids.slice(start, end + 1)) block.add(id);
	return block;
}

function signature(item, inBlock) {
	const messages = new Set([...(item.consoleErrors || []).map(normalizeMessage), ...apiLines(item.note)]);
	const p2 = new Set();
	if (inBlock) {
		for (const m of messages) if (isP2(m)) { p2.add(m); messages.delete(m); }
	}
	let status = item.status;
	// In the block, an item that fails only on the P2 422 counts as a pass.
	if (inBlock && status === 'fail' && p2.size && !messages.size) {
		const note = String(item.note || '');
		const thrown = note.startsWith('error: ') || note.includes('not run:') || /blocked (request|websocket)/.test(note);
		if (!thrown) status = 'pass';
	}
	return { status, messages, p2 };
}

const quote = (list) => list.map((m) => JSON.stringify(m)).join(', ');

/** Compare two reports (arrays of { id, status, consoleErrors, note }). Returns the list of differences. */
export function compareReports(baseline, run) {
	const blockA = posBlock(baseline);
	const blockB = posBlock(run);
	const byId = (report) => new Map(report.map((r) => [r.id, r]));
	const a = byId(baseline);
	const b = byId(run);
	const differences = [];
	const p2A = new Set();
	const p2B = new Set();
	for (const [id, itemA] of a) {
		const itemB = b.get(id);
		if (!itemB) { differences.push(`${id}: only in the baseline`); continue; }
		const sa = signature(itemA, blockA.has(id));
		const sb = signature(itemB, blockB.has(id));
		sa.p2.forEach((m) => p2A.add(m));
		sb.p2.forEach((m) => p2B.add(m));
		const parts = [];
		if (sa.status !== sb.status) parts.push(`status ${sa.status} -> ${sb.status}`);
		const added = [...sb.messages].filter((m) => !sa.messages.has(m));
		const removed = [...sa.messages].filter((m) => !sb.messages.has(m));
		if (added.length) parts.push('added: ' + quote(added));
		if (removed.length) parts.push('removed: ' + quote(removed));
		if (parts.length) differences.push(`${id}: ${parts.join('; ')}`);
	}
	for (const id of b.keys()) if (!a.has(id)) differences.push(`${id}: only in the run`);
	if (p2A.size && !p2B.size) differences.push('POS block: the P2 422 disappeared from all items');
	if (!p2A.size && p2B.size) differences.push('POS block: the P2 422 appeared: ' + quote([...p2B]));
	if (p2A.size && p2B.size) {
		const added = [...p2B].filter((m) => !p2A.has(m));
		if (added.length) differences.push('POS block: new P2 message: ' + quote(added));
	}
	return { differences, baselineItems: a.size, runItems: b.size };
}

function main(argv) {
	const dir = path.dirname(fileURLToPath(import.meta.url));
	const [basePath, runPath = path.join(dir, 'output', 'report.json')] = argv;
	if (!basePath) {
		console.error('Usage: node tools/walkthrough/compare.mjs <baseline.report.json> [<report.json>]');
		return 2;
	}
	let baseline, run;
	try {
		baseline = JSON.parse(fs.readFileSync(basePath, 'utf8'));
		run = JSON.parse(fs.readFileSync(runPath, 'utf8'));
	} catch (e) {
		console.error('Cannot read a report: ' + e.message);
		return 2;
	}
	const { differences, baselineItems, runItems } = compareReports(baseline, run);
	for (const d of differences) console.log(d);
	console.log(`${differences.length} differences (baseline ${baselineItems} items, run ${runItems} items)`);
	return differences.length ? 1 : 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
	process.exitCode = main(process.argv.slice(2));
}

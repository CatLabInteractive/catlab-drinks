// Shared helpers for the frontend walk-through: configuration, browser, request guard,
// per-item console capture, screenshots, dialogs, and the NFC simulator client.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);

export const DIR = path.dirname(fileURLToPath(import.meta.url));
export const OUT = path.join(DIR, 'output');
export const BASE = (process.env.WALK_BASE_URL || 'http://localhost:8098').replace(/\/$/, '');
export const SIM = (process.env.WALK_NFC_SIM || 'http://127.0.0.1:3030').replace(/\/$/, '');
export const USER = process.env.WALK_USER || 'walk@example.test';
export const PASSWORD = process.env.WALK_PASSWORD || 'walk-password-1';
export const RUN = process.env.WALK_RUN_ID || String(Date.now()).slice(-6);
// A new card UID for each run, so that a second run on the same database gets a blank card.
// The time that one item may take. After a timeout the run stops (see walkthrough.mjs).
export const ITEM_TIMEOUT_MS = Number(process.env.WALK_ITEM_TIMEOUT_MS || 120000);
export const CARD_UID = process.env.WALK_CARD_UID || ('04a1b2c3' + RUN.padStart(6, '0'));

// Hosts that may be loaded. Everything else is aborted.
const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '[::1]', '::1']);
// Aborted requests to these hosts are expected (web fonts) and are not a failure.
const FONT_HOSTS = new Set(['fonts.googleapis.com', 'fonts.gstatic.com']);

/** Find Playwright: WALK_PLAYWRIGHT, a normal resolution, or the npx cache. */
function loadPlaywright() {
	const tries = [];
	if (process.env.WALK_PLAYWRIGHT) tries.push(process.env.WALK_PLAYWRIGHT);
	tries.push('playwright');
	const npx = path.join(os.homedir(), '.npm', '_npx');
	if (fs.existsSync(npx)) {
		for (const dir of fs.readdirSync(npx)) {
			const p = path.join(npx, dir, 'node_modules', 'playwright');
			if (fs.existsSync(path.join(p, 'package.json'))) tries.push(p);
		}
	}
	for (const t of tries) {
		try { return require(t); } catch (e) { /* try the next one */ }
	}
	throw new Error('Playwright not found. Run `npx -y playwright@1.62.0 --version` once, or set WALK_PLAYWRIGHT to the package folder.');
}

export const playwright = loadPlaywright();

// ---- Item bookkeeping -----------------------------------------------------

export const results = [];
export const hostsSeen = new Map(); // host -> count of requests the browser tried
let current = null;
const pages = []; // [label, page] of every page that newPage() opened

/**
 * Mark console errors that the current item expects (for example the 403 status of a page that
 * must answer 403). They are moved from consoleErrors to the note. Use it only for such cases.
 */
export function expectConsoleError(pattern) {
	if (current) current.expected.push(pattern);
}

export function note(text) {
	if (current) current.notes.push(text);
}

function isLocal(u) {
	return ['data:', 'blob:', 'about:', 'chrome-extension:'].includes(u.protocol) || LOCAL_HOSTS.has(u.hostname);
}

/** Wire the request guard, the hosts log, and dialogs onto a browser context. */
export async function guardContext(context, label) {
	await context.route('**/*', (route) => {
		const u = new URL(route.request().url());
		hostsSeen.set(u.host || u.protocol, (hostsSeen.get(u.host || u.protocol) || 0) + 1);
		if (isLocal(u)) return route.continue();
		if (!FONT_HOSTS.has(u.hostname)) {
			const msg = `blocked request to public host: ${route.request().method()} ${u.origin}${u.pathname}`;
			if (current) current.blocked.push(msg); else console.warn('[guard]', msg);
		}
		return route.abort('blockedbyclient');
	});
	if (context.routeWebSocket) {
		await context.routeWebSocket((u) => !LOCAL_HOSTS.has(u.hostname), (ws) => {
			const msg = `blocked websocket to public host: ${ws.url()}`;
			if (current) current.blocked.push(msg); else console.warn('[guard]', msg);
			ws.close();
		});
	}
	context.on('page', (page) => watchPage(page, label));
}

export const dialogs = { promptAnswer: 'Walk', dismissConfirm: false, log: [] };

function watchPage(page, label) {
	page.on('console', (m) => {
		if (m.type() !== 'error') return;
		const loc = m.location() && m.location().url ? m.location().url : '';
		// A web font that the guard aborted logs "Failed to load resource". Ignore only that case.
		if (/^Failed to load resource/.test(m.text()) && loc) {
			try { if (FONT_HOSTS.has(new URL(loc).hostname)) return; } catch (e) { /* keep it */ }
		}
		if (current) current.consoleErrors.push(`[${label}] ${m.text()}${loc ? ' (' + loc.replace(BASE, '') + ')' : ''}`);
	});
	// Keep the evidence of each failed API call: method, path, status, the start of the body sent, and the answer.
	page.on('response', async (r) => {
		if (r.status() < 400 || !/\/(api|pos-api)\//.test(r.url())) return;
		let answer = '';
		try { answer = (await r.text()).replace(/\s+/g, ' ').slice(0, 200); } catch (e) { /* no body */ }
		const sent = (r.request().postData() || '').slice(0, 300);
		note(`[${label}] ${r.request().method()} ${r.url().replace(BASE, '')} -> ${r.status()} ${answer}${sent ? ' | sent: ' + sent : ''}`);
	});
	page.on('pageerror', (e) => {
		if (current) current.consoleErrors.push(`[${label}] pageerror: ${e.message}`);
	});
	page.on('dialog', async (d) => {
		const text = `${d.type()}: ${d.message().replace(/\s+/g, ' ').slice(0, 160)}`;
		dialogs.log.push(text);
		note(`dialog ${text}`);
		try {
			if (d.type() === 'prompt') await d.accept(dialogs.promptAnswer);
			else if (d.type() === 'confirm' && dialogs.dismissConfirm) await d.dismiss();
			else await d.accept();
		} catch (e) { /* dialog already handled */ }
	});
}

/**
 * Run one walk-through item. `fn` returns the page to screenshot (or nothing).
 * An item fails when fn throws, when the console shows an error, or when a request to a public host was blocked.
 */
export async function runItem(id, fn, shotPage) {
	current = { id, consoleErrors: [], notes: [], blocked: [], expected: [] };
	dialogs.promptAnswer = 'Walk';
	dialogs.dismissConfirm = false;
	let error = null;
	let timedOut = false;
	let page = shotPage;
	const started = Date.now();
	try {
		const p = await Promise.race([
			fn(),
			new Promise((_, rej) => setTimeout(() => {
				timedOut = true;
				rej(new Error(`timeout: the item did not finish within ${ITEM_TIMEOUT_MS / 1000} s`));
			}, ITEM_TIMEOUT_MS)),
		]);
		if (p && typeof p.screenshot === 'function') page = p;
	} catch (e) {
		error = e;
	}
	if (error) {
		for (const [label, pg] of pages) {
			if (pg.isClosed()) continue;
			try { await pg.screenshot({ path: path.join(OUT, `${id}-fail-${label}.png`), fullPage: true }); } catch (e) { /* ignore */ }
		}
	}
	if (page && !page.isClosed()) {
		try {
			await page.waitForTimeout(600);
			await page.screenshot({ path: path.join(OUT, id + '.png'), fullPage: true });
		} catch (e) { current.notes.push('screenshot failed: ' + e.message.split('\n')[0]); }
	}
	const item = current;
	current = null;
	const notes = [...item.notes];
	for (const pattern of item.expected) {
		const hits = item.consoleErrors.filter((e) => pattern.test(e));
		item.consoleErrors = item.consoleErrors.filter((e) => !pattern.test(e));
		for (const h of hits) notes.push('expected console error: ' + h);
	}
	if (error) notes.unshift('error: ' + String(error.message || error).split('\n')[0]);
	notes.push(...item.blocked);
	const status = error || item.consoleErrors.length || item.blocked.length ? 'fail' : 'pass';
	const result = { id, status, consoleErrors: item.consoleErrors, note: notes.join(' | ') };
	results.push(result);
	// Not in the report: the caller must stop the run, because the item function may still drive the pages.
	Object.defineProperty(result, 'timedOut', { value: timedOut, enumerable: false });
	console.log(`${status === 'pass' ? 'PASS' : 'FAIL'} ${id} (${((Date.now() - started) / 1000).toFixed(1)} s)` +
		(item.consoleErrors.length ? ` console errors: ${item.consoleErrors.length}` : '') +
		(error ? ` - ${String(error.message).split('\n')[0]}` : ''));
	return result;
}

// ---- Browser ---------------------------------------------------------------

export async function launch() {
	return playwright.chromium.launch({
		channel: process.env.WALK_CHANNEL || 'chrome',
		headless: process.env.WALK_HEADED ? false : true,
		chromiumSandbox: false,
	});
}

export async function newPage(browser, label, options = {}) {
	const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, ...options });
	await guardContext(context, label);
	const page = await context.newPage();
	page.setDefaultTimeout(15000);
	pages.push([label, page]);
	return page;
}

// ---- Small page helpers ----------------------------------------------------

/** Rewrite a URL on any host (for example drinks.catlab.eu or APP_URL) to the local base. */
export function localUrl(url) {
	const u = new URL(url, BASE);
	return BASE + u.pathname + u.search + u.hash;
}

export async function goto(page, url) {
	await page.goto(url.startsWith('http') ? url : BASE + url);
	await page.waitForLoadState('networkidle').catch(() => {});
}

export async function text(page, selector) {
	return ((await page.locator(selector).first().textContent()) || '').replace(/\s+/g, ' ').trim();
}

export async function expectText(page, selector, needle, timeout = 15000) {
	await page.locator(selector).filter({ hasText: needle }).first().waitFor({ timeout });
}

export async function modal(page, title) {
	const m = page.locator('.modal.show').filter({ has: page.locator('.modal-title', { hasText: title }) }).last();
	await m.waitFor({ timeout: 15000 });
	await page.waitForTimeout(500);
	return m;
}

export async function noModal(page, timeout = 10000) {
	await page.waitForFunction(() => document.querySelectorAll('.modal.show').length === 0, null, { timeout });
}

/** Open a row's "Actions" dropdown and click an item in it. */
export async function rowAction(page, rowText, itemText) {
	const row = page.locator('tr', { hasText: rowText }).first();
	await row.locator('.dropdown-toggle').click();
	await page.locator('.dropdown-menu.show').locator('a, button', { hasText: itemText }).first().click();
}

export function assert(cond, message) {
	if (!cond) throw new Error(message);
}

// ---- NFC simulator ---------------------------------------------------------

export async function sim(method, p) {
	const r = await fetch(SIM + p, { method });
	const body = await r.text();
	if (!r.ok) throw new Error(`simulator ${method} ${p} -> ${r.status} ${body}`);
	return JSON.parse(body);
}

export function writeReport() {
	fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(results, null, 2));
	fs.writeFileSync(path.join(OUT, 'hosts.json'), JSON.stringify(Object.fromEntries(hostsSeen), null, 2));
	fs.writeFileSync(path.join(OUT, 'dialogs.json'), JSON.stringify(dialogs.log, null, 2));
}

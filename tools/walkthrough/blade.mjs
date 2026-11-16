// Walk-through items for the Blade pages (setup, login, registration, public pages, QR generator, top-up).
import { BASE, USER, PASSWORD, note, expectConsoleError, goto, text, assert } from './lib.mjs';

export async function login(p) {
	await goto(p, '/login');
	if (!p.url().includes('/login')) return; // already logged in
	await p.fill('#email', USER);
	await p.fill('#password', PASSWORD);
	await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}

export function bladeItems(s) {
	return {
		// ---- Blade pages ----
		'blade-setup': async () => {
			const p = s.manage;
			await goto(p, '/');
			if (!p.url().includes('/setup')) {
				await login(p);
				throw new Error('the database is not fresh: / did not redirect to /setup (logged in instead)');
			}
			await p.fill('#name', 'Walk Admin');
			await p.fill('#email', USER);
			await p.fill('#password', PASSWORD);
			await p.fill('#password-confirm', PASSWORD);
			await p.fill('#organisation_name', 'Walk Org');
			await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
			assert(p.url().includes('/getting-started'), 'expected /getting-started after setup, got ' + p.url());
			return p;
		},
		'blade-getting-started': async () => {
			const p = s.manage;
			await goto(p, '/getting-started');
			assert((await p.title()) !== '' || (await p.locator('h1').count()) > 0, 'page has no title or heading');
			return p;
		},
		'blade-register-closed': async () => {
			const p = await s.anon();
			// The closed registration page answers 403 on purpose. Chrome logs that status as a console error.
			expectConsoleError(/status of 403 .*\(\/register\)/);
			await goto(p, '/register');
			const body = await text(p, 'body');
			assert(/closed/i.test(body), 'expected the "registration closed" page, got: ' + body.slice(0, 120));
			return p;
		},
		'blade-login-wrong-password': async () => {
			const p = await s.anon();
			await goto(p, '/login');
			await p.fill('#email', USER);
			await p.fill('#password', 'wrong-password');
			await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
			await p.locator('.invalid-feedback, .alert-danger').first().waitFor();
			return p;
		},
		'blade-welcome': async () => {
			const p = await s.anon();
			await goto(p, '/');
			assert(!p.url().includes('/setup'), 'still redirected to /setup');
			await p.locator('a[href*="login"], a[href*="manage"]').first().waitFor();
			return p;
		},
		'blade-landing-pages': async () => {
			const p = await s.anon();
			for (const url of ['/student-union-cashless', '/indie-festival-payments', '/hacker-camp-nfc', '/whitepaper']) {
				const r = await p.goto(BASE + url);
				assert(r.status() === 200, `${url} -> ${r.status()}`);
			}
			return p;
		},
		'blade-docs': async () => {
			const p = await s.anon();
			const r = await p.goto(BASE + '/docs');
			assert(r.status() === 200, `/docs -> ${r.status()}`);
			await p.locator('.swagger-ui, #swagger-ui').first().waitFor();
			return p;
		},
		'blade-qr-generator': async () => {
			const p = await s.anon();
			const r = await p.goto(BASE + '/qr-generator');
			assert(r.status() === 200, '/qr-generator -> ' + r.status());
			await p.waitForTimeout(2000);
			const img = await p.request.get(BASE + '/qr-generator/code?uid=' + s.cardUid);
			assert(img.status() === 200 && /image/.test(img.headers()['content-type'] || ''), 'QR code image -> ' + img.status());
			return p;
		},
		'blade-topup-not-available': async () => {
			const p = await s.anon();
			assert(s.cardId, 'precondition: no card id (the card items did not run)');
			const r = await p.goto(BASE + '/topup/' + s.cardUid);
			assert(r.status() === 200, '/topup -> ' + r.status());
			note('page text: ' + (await text(p, 'body')).slice(0, 120));
			return p;
		},
		'blade-home': async () => {
			const p = s.manage;
			await goto(p, '/home');
			assert(!p.url().includes('/login'), 'redirected to login');
			return p;
		},
		'manage-logout': async () => {
			const p = s.manage;
			await goto(p, '/manage/events');
			await p.locator('a.nav-link', { hasText: 'Logout' }).click();
			await p.waitForURL((u) => !u.pathname.startsWith('/manage'), { timeout: 15000 });
			await goto(p, '/manage/events');
			assert(p.url().includes('/login'), 'Manage still open after logout: ' + p.url());
			return p;
		},
		'blade-login': async () => {
			const p = s.manage;
			await login(p);
			await goto(p, '/manage/events');
			assert(p.url().includes('/manage'), 'not in Manage after login: ' + p.url());
			await p.locator('h1', { hasText: 'Events' }).waitFor();
			return p;
		},

	};
}

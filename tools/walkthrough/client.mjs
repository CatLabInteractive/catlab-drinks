// Walk-through items for the client order page (/order/{token}).
import { goto, modal, noModal, assert } from './lib.mjs';

async function order(s, product, times, table) {
	const p = s.client;
	await goto(p, s.orderUrl);
	await p.locator('.order-table').waitFor();
	const row = p.locator('.order-table tr', { hasText: product });
	for (let i = 0; i < times; i++) await row.locator('button.btn-success').click();
	await p.locator('input[type=text]').last().fill(table);
	await p.locator('button', { hasText: 'Place order' }).click();
	const m = await modal(p, 'Confirm order');
	await m.locator('li', { hasText: product }).waitFor();
	await p.screenshot({ path: s.out(`client-order-${table}-confirm.png`) });
	await m.locator('.modal-footer .btn-success').click();
	const ok = await modal(p, 'on our way');
	await p.screenshot({ path: s.out(`client-order-${table}-success.png`) });
	await ok.locator('.modal-footer button', { hasText: 'New order' }).click();
	await noModal(p);
	return p;
}

export function clientItems(s) {
	return {
		'client-order-load': async () => {
			const p = s.client;
			assert(s.orderUrl, 'precondition: no order URL (manage-events-order-token did not run)');
			await goto(p, s.orderUrl);
			await p.locator('.order-table').waitFor();
			await p.locator('.order-table .order-heading', { hasText: 'Drinks' }).waitFor();
			await p.locator('.order-table tr', { hasText: 'Walk Beer' }).waitFor();
			return p;
		},
		'client-order-empty': async () => {
			const p = s.client;
			await p.locator('button', { hasText: 'Place order' }).click();
			const m = await modal(p, 'Oops');
			await m.locator('.modal-footer button').click();
			await noModal(p);
			return p;
		},
		'client-order-place': async () => {
			const p = s.client;
			await goto(p, s.orderUrl);
			const row = p.locator('.order-table tr', { hasText: 'Walk Cola' });
			await row.locator('button.btn-success').click();
			await row.locator('button.btn-danger').click();
			assert((await row.locator('.amount').textContent()).trim() === '0', 'minus button did not lower the amount');
			return order(s, 'Walk Beer', 2, '12');
		},
		'client-order-place-second': async () => order(s, 'Walk Fries', 1, '13'),
		'client-language-toggle': async () => {
			const p = s.client;
			await goto(p, s.orderUrl);
			const dd = p.locator('.navbar .nav-item.dropdown').last();
			await dd.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'Nederlands' }).click();
			await p.locator('h2', { hasText: 'Bestellen' }).waitFor();
			await p.screenshot({ path: s.out('client-language-toggle-nl.png') });
			await dd.locator('.dropdown-toggle').click();
			await p.locator('.dropdown-menu.show a', { hasText: 'English' }).click();
			await p.locator('h2', { hasText: 'Order' }).waitFor();
			return p;
		},
	};
}

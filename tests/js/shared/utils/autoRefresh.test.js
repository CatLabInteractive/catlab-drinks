import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { AutoRefresh } from '@resources/shared/js/utils/autoRefresh';

let doc;

function fakeDocument() {
	return Object.assign(new EventTarget(), { hidden: false });
}

function becomeVisible() {
	doc.hidden = false;
	doc.dispatchEvent(new Event('visibilitychange'));
}

beforeEach(() => {
	vi.useFakeTimers();
	doc = fakeDocument();
});

afterEach(() => {
	vi.useRealTimers();
});

describe('AutoRefresh', () => {
	it('loads on start and on each tick', async () => {
		const load = vi.fn(() => Promise.resolve());
		new AutoRefresh(load, { intervalMs: 30000, doc }).start();

		await vi.advanceTimersByTimeAsync(60000);

		expect(load).toHaveBeenCalledTimes(3);
	});

	it('does not load while hidden', async () => {
		const load = vi.fn(() => Promise.resolve());
		new AutoRefresh(load, { intervalMs: 30000, doc }).start();
		doc.hidden = true;

		await vi.advanceTimersByTimeAsync(90000);

		expect(load).toHaveBeenCalledTimes(1);
	});

	it('refreshes when the document becomes visible', async () => {
		const load = vi.fn(() => Promise.resolve());
		new AutoRefresh(load, { intervalMs: 30000, doc }).start();
		doc.hidden = true;
		await vi.advanceTimersByTimeAsync(90000);

		becomeVisible();

		expect(load).toHaveBeenCalledTimes(2);
	});

	it('skips a tick while a load runs', async () => {
		const load = vi.fn(() => new Promise(() => {}));
		const refresher = new AutoRefresh(load, { intervalMs: 30000, doc });
		refresher.start();

		await vi.advanceTimersByTimeAsync(90000);
		refresher.refresh();

		expect(load).toHaveBeenCalledTimes(1);
	});

	it('reports running through onChange', async () => {
		const onChange = vi.fn();
		const refresher = new AutoRefresh(() => Promise.resolve(), { intervalMs: 30000, doc, onChange });

		await refresher.refresh();

		expect(onChange.mock.calls).toEqual([[true], [false]]);
		expect(refresher.running).toBe(false);
	});

	it('keeps running after a failed load', async () => {
		const load = vi.fn(() => Promise.reject(new Error('offline')));
		new AutoRefresh(load, { intervalMs: 30000, doc }).start();

		await vi.advanceTimersByTimeAsync(30000);

		expect(load).toHaveBeenCalledTimes(2);
	});

	it('passes a failed load to onError', async () => {
		const error = new Error('offline');
		const onError = vi.fn();
		const refresher = new AutoRefresh(() => Promise.reject(error), { intervalMs: 30000, doc, onError });

		await refresher.refresh();

		expect(onError).toHaveBeenCalledWith(error);
	});

	it('stop removes the timer and the listener', async () => {
		const load = vi.fn(() => Promise.resolve());
		const refresher = new AutoRefresh(load, { intervalMs: 30000, doc });
		refresher.start();
		await vi.advanceTimersByTimeAsync(0);

		refresher.stop();
		await vi.advanceTimersByTimeAsync(90000);
		becomeVisible();
		await refresher.refresh();

		expect(load).toHaveBeenCalledTimes(1);
	});
});

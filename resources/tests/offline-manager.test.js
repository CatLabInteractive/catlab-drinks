/**
 * Tests for the OfflineManager service.
 *
 * OfflineManager tracks online/offline connectivity using navigator.onLine
 * and browser events, and allows API request success/failure to update the state.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { OfflineManager } from '../shared/js/services/OfflineManager';

describe('OfflineManager', () => {

	let manager;

	beforeEach(() => {
		// Default to online
		Object.defineProperty(navigator, 'onLine', { value: true, writable: true, configurable: true });
		manager = new OfflineManager();
	});

	describe('initial state', () => {
		it('should report online when navigator.onLine is true', () => {
			expect(manager.isOnline()).toBe(true);
		});

		it('should report offline when navigator.onLine is false', () => {
			Object.defineProperty(navigator, 'onLine', { value: false, writable: true, configurable: true });
			const offlineManager = new OfflineManager();
			expect(offlineManager.isOnline()).toBe(false);
		});
	});

	describe('markOnline / markOffline', () => {
		it('should transition to offline when markOffline is called', () => {
			expect(manager.isOnline()).toBe(true);
			manager.markOffline();
			expect(manager.isOnline()).toBe(false);
		});

		it('should transition back to online when markOnline is called', () => {
			manager.markOffline();
			expect(manager.isOnline()).toBe(false);
			manager.markOnline();
			expect(manager.isOnline()).toBe(true);
		});

		it('should not emit event when state does not change', () => {
			const listener = vi.fn();
			manager.on(listener);
			manager.markOnline(); // already online
			expect(listener).not.toHaveBeenCalled();
		});
	});

	describe('event listeners', () => {
		it('should notify listeners when state changes to offline', () => {
			const listener = vi.fn();
			manager.on(listener);
			manager.markOffline();
			expect(listener).toHaveBeenCalledWith(false);
		});

		it('should notify listeners when state changes to online', () => {
			manager.markOffline();
			const listener = vi.fn();
			manager.on(listener);
			manager.markOnline();
			expect(listener).toHaveBeenCalledWith(true);
		});

		it('should support multiple listeners', () => {
			const listener1 = vi.fn();
			const listener2 = vi.fn();
			manager.on(listener1);
			manager.on(listener2);
			manager.markOffline();
			expect(listener1).toHaveBeenCalledWith(false);
			expect(listener2).toHaveBeenCalledWith(false);
		});

		it('should stop notifying after unbind', () => {
			const listener = vi.fn();
			const binding = manager.on(listener);
			binding.unbind();
			manager.markOffline();
			expect(listener).not.toHaveBeenCalled();
		});
	});

	describe('browser events', () => {
		it('should react to window offline event', () => {
			const listener = vi.fn();
			manager.on(listener);
			window.dispatchEvent(new Event('offline'));
			expect(manager.isOnline()).toBe(false);
			expect(listener).toHaveBeenCalledWith(false);
		});

		it('should react to window online event', () => {
			manager.markOffline();
			const listener = vi.fn();
			manager.on(listener);
			window.dispatchEvent(new Event('online'));
			expect(manager.isOnline()).toBe(true);
			expect(listener).toHaveBeenCalledWith(true);
		});
	});
});

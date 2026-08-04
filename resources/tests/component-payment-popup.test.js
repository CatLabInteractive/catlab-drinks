/**
 * Behavioral tests for PaymentPopup: the payment modal lifecycle driven
 * by PaymentService events, the payment method buttons, and the
 * table service "Pay later" option.
 */
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PaymentPopup from '../shared/js/components/PaymentPopup.vue';
import { globalMountOptions } from './helpers/bootstrapVueStubs';

function makePaymentService(overrides = {}) {
	const handlers = {};
	return {
		allow_nfc_payments: false,
		allow_cash_payments: true,
		allow_voucher_payment: true,
		allow_pay_later: false,
		voucher_value: 2,

		on(event, callback) {
			handlers[event] = handlers[event] || [];
			handlers[event].push(callback);
			return { unbind: () => {} };
		},
		trigger(event, payload) {
			(handlers[event] || []).forEach((cb) => cb(payload));
		},

		cash: vi.fn(),
		vouchers: vi.fn(),
		payLater: vi.fn(),
		cancel: vi.fn(),

		...overrides,
	};
}

function mountPopup(paymentService) {
	return mount(PaymentPopup, {
		global: globalMountOptions({ mocks: { $paymentService: paymentService } }),
	});
}

function startTransaction(paymentService, price = 500) {
	paymentService.trigger('transaction:start', {
		price,
		error: null,
		loading: false,
	});
}

describe('PaymentPopup', () => {
	it('opens on transaction:start and shows the amount', async () => {
		const paymentService = makePaymentService();
		const wrapper = mountPopup(paymentService);

		expect(wrapper.find('.modal-stub').exists()).toBe(false);

		startTransaction(paymentService, 550);
		await wrapper.vm.$nextTick();

		expect(wrapper.find('.modal-stub').exists()).toBe(true);
		expect(wrapper.text()).toContain('€5.50');
	});

	it('closes on transaction:done without cancelling', async () => {
		const paymentService = makePaymentService();
		const wrapper = mountPopup(paymentService);

		startTransaction(paymentService);
		await wrapper.vm.$nextTick();

		paymentService.trigger('transaction:done');
		await wrapper.vm.$nextTick();

		expect(wrapper.find('.modal-stub').exists()).toBe(false);
		expect(paymentService.cancel).not.toHaveBeenCalled();
	});

	it('forwards cash and voucher payments to the payment service', async () => {
		const paymentService = makePaymentService();
		const wrapper = mountPopup(paymentService);

		startTransaction(paymentService, 500);
		await wrapper.vm.$nextTick();

		const buttons = wrapper.findAll('p.text-center button');
		expect(buttons).toHaveLength(2);

		await buttons[0].trigger('click');
		expect(paymentService.cash).toHaveBeenCalled();

		await buttons[1].trigger('click');
		expect(paymentService.vouchers).toHaveBeenCalled();

		// 500 cents at voucher_value 2 = 3 vouchers, rounded up.
		expect(buttons[1].text()).toContain('3');
	});

	it('hides the pay later button unless the service allows it', async () => {
		const paymentService = makePaymentService({ allow_pay_later: false });
		const wrapper = mountPopup(paymentService);

		startTransaction(paymentService);
		await wrapper.vm.$nextTick();

		expect(wrapper.text()).not.toContain('Pay later');
	});

	it('resolves pay later through the payment service when allowed', async () => {
		const paymentService = makePaymentService({ allow_pay_later: true });
		const wrapper = mountPopup(paymentService);

		startTransaction(paymentService);
		await wrapper.vm.$nextTick();

		const payLaterButton = wrapper
			.findAll('button')
			.find((b) => b.text().includes('Pay later'));
		expect(payLaterButton).toBeTruthy();

		await payLaterButton.trigger('click');
		expect(paymentService.payLater).toHaveBeenCalled();
	});

	it('shows card errors reported by the transaction', async () => {
		const paymentService = makePaymentService();
		const wrapper = mountPopup(paymentService);

		startTransaction(paymentService);
		paymentService.trigger('transaction:change', {
			error: 'Insufficient funds',
			loading: false,
		});
		await wrapper.vm.$nextTick();

		expect(wrapper.text()).toContain('Insufficient funds');
	});
});

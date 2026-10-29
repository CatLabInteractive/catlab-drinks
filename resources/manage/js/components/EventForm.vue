<!--
  - CatLab Drinks - Simple bar automation system
  - Copyright (C) 2019 Thijs Van der Schaeghe
  - CatLab Interactive bvba, Gent, Belgium
  - http://www.catlab.eu/
  -
  - This program is free software; you can redistribute it and/or modify
  - it under the terms of the GNU General Public License as published by
  - the Free Software Foundation; either version 3 of the License, or
  - (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU General Public License for more details.
  -
  - You should have received a copy of the GNU General Public License along
  - with this program; if not, write to the Free Software Foundation, Inc.,
  - 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
  -->

<template>
	<form @submit.prevent="onSubmit" class="event-form">

		<!-- 1. Name -->
		<div v-if="showBlock(1)" class="event-form__block">
			<label :for="nameInputId" class="event-form__question">
				{{ isWizard ? $t('What is the event called?') : $t('Name') }}
			</label>
			<p v-if="isWizard" class="text-muted small">{{ $t('For example "Quiz 2026" or "Summer bar Friday".') }}</p>
			<b-form-input :id="nameInputId" type="text" v-model="answers.name" :autofocus="isWizard"
						  :state="touchedName && errors.name ? false : null"
						  @input="touchedName = true" @blur="touchedName = true" />
			<p v-if="touchedName && errors.name" class="text-danger small mt-1 mb-0">{{ $t('Please enter a name.') }}</p>
		</div>

		<!-- 2. How do guests pay? -->
		<div v-if="showBlock(2)" class="event-form__block">
			<p class="event-form__question">{{ $t('How do your guests pay?') }}</p>
			<p class="text-muted small">{{ $t('Pick everything that applies.') }}</p>

			<div class="event-form__tiles" :class="{ 'event-form__tiles--compact': !isWizard }">
				<button type="button" class="event-tile" :class="{ 'event-tile--selected': answers.payment_cards }"
						:aria-pressed="answers.payment_cards ? 'true' : 'false'" @click="togglePayment('payment_cards')">
					<span class="event-tile__icon" aria-hidden="true">💳</span>
					<span class="event-tile__text">
						<strong>{{ $t('NFC topup cards') }}</strong>
						<span class="event-tile__description">{{ $t('Guests load credit on a card and tap at the POS. Recommended.') }}</span>
					</span>
				</button>

				<button type="button" class="event-tile" :class="{ 'event-tile--selected': answers.payment_cash }"
						:aria-pressed="answers.payment_cash ? 'true' : 'false'" @click="togglePayment('payment_cash')">
					<span class="event-tile__icon" aria-hidden="true">💶</span>
					<span class="event-tile__text">
						<strong>{{ $t('Cash') }}</strong>
						<span class="event-tile__description">{{ $t('Cash or Payconiq at the bar, outside the system.') }}</span>
					</span>
				</button>

				<button type="button" class="event-tile" :class="{ 'event-tile--selected': answers.payment_vouchers }"
						:aria-pressed="answers.payment_vouchers ? 'true' : 'false'" @click="togglePayment('payment_vouchers')">
					<span class="event-tile__icon" aria-hidden="true">🎟️</span>
					<span class="event-tile__text">
						<strong>{{ $t('Vouchers') }}</strong>
						<span class="event-tile__description">{{ $t('Drink tokens with a fixed value.') }}</span>
					</span>
				</button>
			</div>

			<b-form-group v-if="answers.payment_vouchers" :label="$t('Value of one voucher')" :label-for="voucherInputId"
						  label-cols-sm="auto" class="mt-2 mb-0 align-items-center">
				<b-input-group prepend="€" class="event-form__voucher-value">
					<b-form-input :id="voucherInputId" type="number" step="0.01" min="0.01"
								  v-model="answers.payment_voucher_value" />
				</b-input-group>
			</b-form-group>

			<p v-if="errors.payment === 'none_selected'" class="text-danger small mt-2 mb-0">{{ $t('Pick at least one payment method.') }}</p>
			<p v-if="errors.payment === 'voucher_value'" class="text-danger small mt-2 mb-0">{{ $t('Enter the value of one voucher.') }}</p>
		</div>

		<!-- 3. Can guests order from their own phone? -->
		<div v-if="showBlock(3)" class="event-form__block">
			<p class="event-form__question">{{ $t('Can guests order from their own phone?') }}</p>
			<p class="text-muted small">{{ $t('Via the order page with QR code or link.') }}</p>

			<div class="event-form__tiles" role="radiogroup">
				<button type="button" class="event-tile" role="radio"
						:class="{ 'event-tile--selected': answers.online_ordering === ONLINE_ORDERING.NONE }"
						:aria-checked="answers.online_ordering === ONLINE_ORDERING.NONE ? 'true' : 'false'"
						@click="selectOnlineOrdering(ONLINE_ORDERING.NONE)">
					<span class="event-tile__icon" aria-hidden="true">🧑‍🍳</span>
					<span class="event-tile__text">
						<strong>{{ $t('No, only at the bar') }}</strong>
						<span class="event-tile__description">{{ $t('Staff take orders on the POS.') }}</span>
					</span>
				</button>

				<button type="button" class="event-tile" role="radio"
						:class="{ 'event-tile--selected': answers.online_ordering === ONLINE_ORDERING.UNPAID }"
						:aria-checked="answers.online_ordering === ONLINE_ORDERING.UNPAID ? 'true' : 'false'"
						@click="selectOnlineOrdering(ONLINE_ORDERING.UNPAID)">
					<span class="event-tile__icon" aria-hidden="true">📱</span>
					<span class="event-tile__text">
						<strong>{{ $t('Yes, and we handle payment ourselves') }}</strong>
						<span class="event-tile__description">{{ $t('Guests order ahead and pay at the bar or on pickup.') }}</span>
					</span>
				</button>

				<button type="button" class="event-tile" role="radio" :disabled="!integrationAvailable"
						:class="{ 'event-tile--selected': answers.online_ordering === ONLINE_ORDERING.INTEGRATION }"
						:aria-checked="answers.online_ordering === ONLINE_ORDERING.INTEGRATION ? 'true' : 'false'"
						@click="selectOnlineOrdering(ONLINE_ORDERING.INTEGRATION)">
					<span class="event-tile__icon" aria-hidden="true">🔗</span>
					<span class="event-tile__text">
						<strong>{{ $t('Yes, through an integration that passes the card') }}</strong>
						<span class="event-tile__description">{{ $t('E.g. QuizWitz. Orders are charged to the linked NFC card.') }}</span>
						<span v-if="!integrationAvailable" class="event-tile__description text-danger">{{ $t('Requires NFC topup cards') }}</span>
					</span>
				</button>
			</div>

			<p v-if="errors.onlineOrdering" class="text-muted small mt-2 mb-0">{{ $t('Choose how guests order.') }}</p>

			<details class="event-form__advanced mt-3">
				<summary>{{ $t('Advanced settings') }}</summary>
				<b-form-checkbox v-model="answers.split_orders_by_categories" class="mt-2">
					{{ $t('Split orders by product category (bar / kitchen)') }}
				</b-form-checkbox>
				<b-form-group :label="$t('Checkin URL (callback)')" :label-for="checkinInputId" class="mt-2 mb-0">
					<b-form-input :id="checkinInputId" type="text" v-model="answers.checkin_url" placeholder="https://" />
				</b-form-group>
			</details>
		</div>

		<div class="d-flex justify-content-end align-items-center mt-4">
			<span v-if="saving" class="text-muted mr-auto">{{ $t('Saving') }}</span>

			<template v-if="isWizard && step < 3">
				<b-button type="button" variant="light" class="mr-2" @click="$emit('cancel')">{{ $t('Cancel') }}</b-button>
				<b-button type="submit" variant="primary" :disabled="!stepValid">{{ $t('Next') }} ›</b-button>
			</template>

			<template v-else-if="isWizard">
				<b-button type="button" variant="light" class="mr-2" @click="back">‹ {{ $t('Back') }}</b-button>
				<b-button type="submit" variant="success" :disabled="!errors.valid || saving">{{ $t('Create event') }}</b-button>
			</template>

			<template v-else>
				<b-button type="button" variant="light" class="mr-2" @click="reset">{{ $t('Reset') }}</b-button>
				<b-button type="submit" variant="success" :disabled="!errors.valid || saving">{{ $t('Save') }}</b-button>
			</template>
		</div>

	</form>
</template>

<script>

import { ONLINE_ORDERING, answersFromEvent, eventFromAnswers, validateAnswers } from '../services/eventSettings';

let instanceCounter = 0;

/**
 * The event form, as three plain questions.
 *
 * mode 'wizard' asks them one step at a time (creating an event);
 * mode 'single' shows them stacked on one screen (editing an event).
 * Emits `submit` with the event payload, `cancel`, and `step` with the
 * current step number in wizard mode.
 */
export default {
	props: {
		event: {
			type: Object,
			default: () => ({}),
		},
		mode: {
			type: String,
			default: 'single',
			validator: (value) => ['wizard', 'single'].includes(value),
		},
		saving: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['submit', 'cancel', 'step'],

	data() {
		const id = ++instanceCounter;
		return {
			answers: answersFromEvent(this.event),
			step: 1,
			touchedName: false,
			ONLINE_ORDERING,
			nameInputId: 'event-form-name-' + id,
			voucherInputId: 'event-form-voucher-value-' + id,
			checkinInputId: 'event-form-checkin-url-' + id,
		};
	},

	computed: {
		isWizard() {
			return this.mode === 'wizard';
		},

		errors() {
			return validateAnswers(this.answers);
		},

		stepValid() {
			switch (this.step) {
				case 1:
					return this.errors.name === null;
				case 2:
					return this.errors.payment === null;
				default:
					return this.errors.onlineOrdering === null;
			}
		},

		integrationAvailable() {
			return !!this.answers.payment_cards;
		},
	},

	watch: {
		event() {
			this.reset();
		},

		'answers.payment_cards'(hasCards) {
			if (!hasCards && this.answers.online_ordering === ONLINE_ORDERING.INTEGRATION) {
				this.answers.online_ordering = null;
			}
		},
	},

	mounted() {
		if (this.isWizard) {
			this.$emit('step', this.step);
		}
	},

	methods: {
		showBlock(number) {
			return !this.isWizard || this.step === number;
		},

		togglePayment(key) {
			this.answers[key] = !this.answers[key];
		},

		selectOnlineOrdering(value) {
			if (value === ONLINE_ORDERING.INTEGRATION && !this.integrationAvailable) {
				return;
			}
			this.answers.online_ordering = value;
		},

		next() {
			if (!this.stepValid || this.step >= 3) {
				return;
			}
			this.step += 1;
			this.$emit('step', this.step);
		},

		back() {
			if (this.step <= 1) {
				return;
			}
			this.step -= 1;
			this.$emit('step', this.step);
		},

		reset() {
			this.answers = answersFromEvent(this.event);
			this.step = 1;
			this.touchedName = false;
			if (this.isWizard) {
				this.$emit('step', this.step);
			}
		},

		onSubmit() {
			if (this.isWizard && this.step < 3) {
				this.next();
				return;
			}
			if (!this.errors.valid || this.saving) {
				return;
			}
			this.$emit('submit', eventFromAnswers(this.answers, this.event));
		},
	},
};
</script>

<style scoped>
.event-form__block + .event-form__block {
	margin-top: 1.5rem;
}

.event-form__question {
	display: block;
	font-size: 1.1rem;
	font-weight: 600;
	margin-bottom: 0.25rem;
}

.event-form__tiles {
	display: grid;
	gap: 0.5rem;
}

.event-tile {
	display: flex;
	align-items: flex-start;
	gap: 0.6rem;
	width: 100%;
	text-align: left;
	padding: 0.6rem 0.75rem;
	border: 2px solid #dee2e6;
	border-radius: 0.5rem;
	background: #fff;
	color: inherit;
	cursor: pointer;
}

.event-tile:hover:not(:disabled) {
	border-color: #adb5bd;
}

.event-tile:focus {
	outline: none;
	box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.event-tile--selected,
.event-tile--selected:hover:not(:disabled) {
	border-color: #28a745;
	background: #f0faf3;
}

.event-tile:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.event-tile__icon {
	font-size: 1.4rem;
	line-height: 1.2;
}

.event-tile__text {
	display: flex;
	flex-direction: column;
}

.event-tile__description {
	font-size: 0.85rem;
	color: #6c757d;
}

.event-form__tiles--compact {
	grid-template-columns: repeat(3, minmax(0, 1fr));
}

.event-form__tiles--compact .event-tile {
	flex-direction: column;
	align-items: center;
	text-align: center;
	gap: 0.25rem;
}

.event-form__tiles--compact .event-tile__description {
	display: none;
}

.event-form__voucher-value {
	max-width: 10rem;
}

.event-form__advanced summary {
	cursor: pointer;
	color: #6c757d;
	font-size: 0.9rem;
}
</style>

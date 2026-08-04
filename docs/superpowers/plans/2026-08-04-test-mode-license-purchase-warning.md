# Test-Mode License Purchase Warning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show a warning modal before navigating to the license shop when the current organisation is in testing mode on a shared instance.

**Architecture:** Frontend-only change in the Manage Devices view: the "Buy License" dropdown item switches from a direct `href` to a click handler that checks `window.ORGANISATION_TEST_MODE` — false navigates immediately (unchanged behaviour), true opens a Bootstrap-Vue confirmation modal whose "Continue to purchase" button performs the same navigation.

**Tech Stack:** Vue 3 compat + Bootstrap-Vue (`b-modal`), vue-i18n.

**Spec:** `docs/superpowers/specs/2026-08-04-test-mode-license-purchase-warning-design.md`.

## Global Constraints

- Branch: `feature/sso-account-deeplink` (already checked out; this rides in PR #191).
- `resources/manage/js/views/Devices.vue` and the i18n files use TABS.
- `window.ORGANISATION_TEST_MODE` is set at Manage boot; treat it as the sole test-mode signal. No backend changes.
- Same-tab navigation (`window.location.href`), matching the current `href` behaviour.
- Existing i18n keys reused: `'Cancel'`; the link label `'How to set up your own instance'` exists as a `$t()` key in `App.vue` but has NO entry in any locale file (falls back to key text) — do not add it.
- No new JS unit tests (spec decision): verification is `npm run dev` + existing suites green.
- Never commit `package-lock.json`.

---

### Task 1: Warning modal in Devices.vue + i18n

**Files:**
- Modify: `resources/manage/js/views/Devices.vue` (dropdown item ~line 103, template modals ~line 218-235, `data()` ~line 314-322, `methods` near `buyLicenseUrl` ~line 420), `resources/shared/js/i18n/nl.js`, `resources/shared/js/i18n/fr.js`, `resources/shared/js/i18n/de.js`, `resources/shared/js/i18n/es.js`, `resources/shared/js/i18n/en.js`
- Test: none new (build + existing suites)

**Interfaces:**
- Consumes: `window.ORGANISATION_TEST_MODE` (boot global), existing `buyLicenseUrl(device): string` method, existing modal pattern (`ref` + `this.$refs.x.show()/hide()`).
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Change the dropdown item**

In `resources/manage/js/views/Devices.vue` (~line 103), replace:

```html
							<b-dropdown-item :href="buyLicenseUrl(row.item)" :title="$t('Buy License')">
								🔑
								{{ $t('Buy License') }}
							</b-dropdown-item>
```

with:

```html
							<b-dropdown-item @click="buyLicense(row.item)" :title="$t('Buy License')">
								🔑
								{{ $t('Buy License') }}
							</b-dropdown-item>
```

- [ ] **Step 2: Add the warning modal to the template**

Directly after the closing `</b-modal>` of the "Enter license modal" (before `<signed-cards-modal ref="signedCardsModal" />`), add:

```html
	<!-- Test-mode license purchase warning -->
	<b-modal :title="$t('Testing mode')" ref="buyLicenseWarningModal">

		<p>
			{{ $t('Your organisation is using this shared instance in testing mode. You can buy a license and it will work here, but for production events we recommend setting up your own instance.') }}
			<a href="https://github.com/CatLabInteractive/catlab-drinks#run-your-own-instance" target="_blank" rel="noopener">{{ $t('How to set up your own instance') }}</a>
		</p>
		<p>{{ $t('Are you sure you want to continue to the license purchase?') }}</p>

		<template #modal-footer>
			<b-btn type="button" variant="light" @click="$refs.buyLicenseWarningModal.hide()">{{ $t('Cancel') }}</b-btn>
			<b-btn type="button" variant="warning" @click="confirmBuyLicense()">
				<span class="mr-1">🔑</span> {{ $t('Continue to purchase') }}
			</b-btn>
		</template>
	</b-modal>
```

- [ ] **Step 3: Add state + methods**

In `data()`, after `licenseError: null` add (mind the comma on the previous line):

```js
				buyLicenseDevice: null
```

In `methods`, directly after the `buyLicenseUrl(device)` method, add:

```js
			buyLicense(device) {
				if (!window.ORGANISATION_TEST_MODE) {
					window.location.href = this.buyLicenseUrl(device);
					return;
				}

				this.buyLicenseDevice = device;
				this.$refs.buyLicenseWarningModal.show();
			},

			confirmBuyLicense() {
				window.location.href = this.buyLicenseUrl(this.buyLicenseDevice);
			},
```

- [ ] **Step 4: i18n**

Add these four keys to each of nl/fr/de/es/en (`resources/shared/js/i18n/*.js`), next to the `'Rename on CatLab Accounts'` entry, matching each file's quoting/tab style. en.js maps them to themselves (its convention); translations:

nl:
```js
	'Testing mode': 'Testmodus',
	'Your organisation is using this shared instance in testing mode. You can buy a license and it will work here, but for production events we recommend setting up your own instance.': 'Jouw organisatie gebruikt deze gedeelde omgeving in testmodus. Je kan een licentie kopen en die zal hier werken, maar voor productie-evenementen raden we aan een eigen omgeving op te zetten.',
	'Are you sure you want to continue to the license purchase?': 'Ben je zeker dat je wil doorgaan naar de licentie-aankoop?',
	'Continue to purchase': 'Doorgaan naar aankoop',
```

fr:
```js
	'Testing mode': 'Mode test',
	'Your organisation is using this shared instance in testing mode. You can buy a license and it will work here, but for production events we recommend setting up your own instance.': 'Votre organisation utilise cette instance partagée en mode test. Vous pouvez acheter une licence et elle fonctionnera ici, mais pour des événements en production nous recommandons de mettre en place votre propre instance.',
	'Are you sure you want to continue to the license purchase?': 'Voulez-vous vraiment continuer vers l\'achat de licence ?',
	'Continue to purchase': 'Continuer vers l\'achat',
```

de:
```js
	'Testing mode': 'Testmodus',
	'Your organisation is using this shared instance in testing mode. You can buy a license and it will work here, but for production events we recommend setting up your own instance.': 'Deine Organisation nutzt diese geteilte Instanz im Testmodus. Du kannst eine Lizenz kaufen und sie wird hier funktionieren, aber für Produktionsveranstaltungen empfehlen wir eine eigene Instanz.',
	'Are you sure you want to continue to the license purchase?': 'Möchtest du wirklich mit dem Lizenzkauf fortfahren?',
	'Continue to purchase': 'Weiter zum Kauf',
```

es:
```js
	'Testing mode': 'Modo de prueba',
	'Your organisation is using this shared instance in testing mode. You can buy a license and it will work here, but for production events we recommend setting up your own instance.': 'Tu organización está usando esta instancia compartida en modo de prueba. Puedes comprar una licencia y funcionará aquí, pero para eventos en producción recomendamos configurar tu propia instancia.',
	'Are you sure you want to continue to the license purchase?': '¿Seguro que quieres continuar con la compra de la licencia?',
	'Continue to purchase': 'Continuar con la compra',
```

If a file's existing long strings use different escaping conventions (e.g. double quotes), match the file.

- [ ] **Step 5: Build + suites**

```bash
npm run dev
npx vitest run
npx jest
git checkout -- package-lock.json
```

Expected: build succeeds; vitest 188, jest 32 green (no PHP changes — skip phpunit).

- [ ] **Step 6: Commit**

```bash
git add resources/manage/js/views/Devices.vue resources/shared/js/i18n/
git commit -m "Warn before license purchase when organisation is in testing mode"
```

/*
 * Device capability gating for the POS app.
 *
 * allow_sales / allow_topup are admin-controlled flags loaded from
 * GET /pos-api/v1/devices/current at boot. Missing flags (old cached
 * responses) are treated as enabled so existing devices keep working.
 */

// Route names that require the sales capability.
const SALES_ROUTES = [
	'home',
	'events',
	'menu',
	'hq',
	'sales',
	'summary',
	'summary-names',
	'attendees',
	'checkIn',
];

// Route names that require the topup capability.
const TOPUP_ROUTES = [
	'cards',
	'transactions',
	'testTransactions',
];

export function getDeviceCapabilities() {
	return {
		allowSales: window.DEVICE_ALLOW_SALES !== false,
		allowTopup: window.DEVICE_ALLOW_TOPUP !== false,
	};
}

/**
 * Decide whether navigation to a route is allowed.
 * Returns the route name to redirect to, or null when allowed.
 */
export function resolveCapabilityRedirect(routeName, capabilities) {
	const { allowSales, allowTopup } = capabilities;

	if (!allowSales && SALES_ROUTES.indexOf(routeName) !== -1) {
		return allowTopup ? 'cards' : 'disabled';
	}

	if (!allowTopup && TOPUP_ROUTES.indexOf(routeName) !== -1) {
		return allowSales ? 'events' : 'disabled';
	}

	if (routeName === 'disabled' && (allowSales || allowTopup)) {
		// Device regained a capability; leave the dead-end screen.
		return allowSales ? 'events' : 'cards';
	}

	return null;
}

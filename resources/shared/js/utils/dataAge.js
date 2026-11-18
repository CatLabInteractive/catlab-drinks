/**
 * Helpers that describe how old the data on a page is.
 */

/** Data older than this is shown with a warning. */
export const STALE_AFTER_MS = 120000;

/**
 * @param {number|null} loadedAt Time of the last successful load (ms), or null if none.
 * @param {number} now
 * @returns {boolean}
 */
export function isStale(loadedAt, now) {
	return loadedAt === null || now - loadedAt > STALE_AFTER_MS;
}

/**
 * @param {number} ms
 * @returns {{ unit: 's'|'min', value: number }}
 */
export function describeAge(ms) {
	const seconds = Math.max(0, Math.floor(ms / 1000));
	if (seconds < 60) {
		return { unit: 's', value: seconds };
	}
	return { unit: 'min', value: Math.floor(seconds / 60) };
}

/**
 * @param {number} loadedAt
 * @param {string} locale
 * @returns {string}
 */
export function formatLoadTime(loadedAt, locale) {
	return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(loadedAt));
}

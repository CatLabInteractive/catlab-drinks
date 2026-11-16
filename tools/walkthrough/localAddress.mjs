// Start-up check: the script's own requests (Node fetch to the NFC simulator, page.request to the stack)
// do not pass the browser route guard, so their addresses must be local before anything starts.
export const LOCAL_HOSTNAMES = new Set(['localhost', '127.0.0.1', '[::1]']);

/** Return one message for each variable in `env` whose value is not a URL on a local host. */
export function addressProblems(env) {
	const problems = [];
	for (const [name, value] of Object.entries(env)) {
		if (value === undefined || value === '') continue;
		let hostname = null;
		try {
			const u = new URL(value);
			if (u.protocol === 'http:' || u.protocol === 'https:') hostname = u.hostname;
		} catch (e) { /* not a URL */ }
		if (!LOCAL_HOSTNAMES.has(hostname)) {
			problems.push(`${name}=${value} is not allowed. Use an http(s) URL on localhost, 127.0.0.1 or [::1].`);
		}
	}
	return problems;
}

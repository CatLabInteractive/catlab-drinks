import http from 'node:http';
import { Server } from 'socket.io';

const UID_PATTERN = /^[0-9a-f]{8,20}$/i;
const READERS = 'readers';

/**
 * Takes the place of the nfc-socketio service: a fake card reader that
 * speaks the socket protocol of RemoteNfcReader.ts.
 */
export function createSimulator(options = {}) {
	const password = options.password ?? '';
	const cards = new Map(); // uid -> ndef (base64) or null for a new card
	let current = null;

	const httpServer = http.createServer((request, response) => {
		handleRequest(request, response);
	});
	const io = new Server(httpServer, { cors: { origin: '*' } });

	const send = (response, status, body) => {
		response.writeHead(status, {
			'Content-Type': 'application/json',
			'Access-Control-Allow-Origin': '*',
		});
		response.end(JSON.stringify(body));
	};

	const handleRequest = (request, response) => {
		const path = new URL(request.url, 'http://localhost').pathname;
		const method = request.method;
		const tapMatch = path.match(/^\/cards\/([^/]+)\/tap$/);
		const cardMatch = path.match(/^\/cards\/([^/]+)$/);
		if (method === 'GET' && path === '/cards') {
			send(response, 200, api.state());
		} else if (method === 'POST' && tapMatch) {
			try {
				api.tap(decodeURIComponent(tapMatch[1]));
				send(response, 200, api.state());
			} catch (error) {
				if (!(error instanceof RangeError)) {
					throw error;
				}
				send(response, 400, { error: error.message });
			}
		} else if (method === 'POST' && path === '/remove') {
			api.remove();
			send(response, 200, api.state());
		} else if (method === 'DELETE' && cardMatch) {
			api.forget(decodeURIComponent(cardMatch[1]));
			send(response, 200, api.state());
		} else {
			send(response, 404, { error: 'Not found' });
		}
	};

	const acknowledge = (callback, payload) => {
		if (typeof callback === 'function') {
			callback(payload);
		}
	};

	io.on('connection', (socket) => {
		socket.on('hello', (payload, callback) => {
			if (password !== '' && payload?.password !== password) {
				acknowledge(callback, { success: false });
				return;
			}
			socket.join(READERS);
			acknowledge(callback, { success: true });
			if (current !== null) {
				socket.emit('nfc:card:connect', { uid: current });
			}
		});

		socket.on('nfc:password', (payload, callback) => {
			if (!socket.rooms.has(READERS) || payload?.uid !== current) {
				return;
			}
			acknowledge(callback, { success: true });
			const ndef = cards.get(payload.uid);
			socket.emit('nfc:data', ndef === null
				? { uid: payload.uid, data: Buffer.alloc(16).toString('base64') }
				: { uid: payload.uid, ndef });
		});

		socket.on('nfc:write', (payload, callback) => {
			if (!socket.rooms.has(READERS)) {
				return;
			}
			if (current === null || payload?.uid !== current) {
				acknowledge(callback, { success: false, error: { error: { name: 'CardNotPresent' } } });
				return;
			}
			if (typeof payload.ndef !== 'string' || payload.ndef === '') {
				acknowledge(callback, { success: false, error: { error: { name: 'InvalidNdef' } } });
				return;
			}
			cards.set(current, payload.ndef);
			acknowledge(callback, { success: true });
		});
	});

	const remove = () => {
		if (current === null) {
			return;
		}
		const uid = current;
		current = null;
		io.to(READERS).emit('nfc:card:disconnect', { uid });
	};

	const api = {
		httpServer,

		listen(port, host) {
			return new Promise((resolve, reject) => {
				httpServer.once('error', reject);
				httpServer.listen(port, host, () => {
					httpServer.off('error', reject);
					resolve(httpServer.address().port);
				});
			});
		},

		async close() {
			// io.close() also closes httpServer, which rejects when it never listened.
			await new Promise((resolve) => {
				io.close(() => resolve());
			});
		},

		tap(uid) {
			if (typeof uid !== 'string' || !UID_PATTERN.test(uid)) {
				throw new RangeError('A card UID is 8 to 20 hexadecimal characters.');
			}
			uid = uid.toLowerCase();
			remove();
			if (!cards.has(uid)) {
				cards.set(uid, null);
			}
			current = uid;
			io.to(READERS).emit('nfc:card:connect', { uid });
		},

		remove,

		forget(uid) {
			uid = String(uid).toLowerCase();
			if (uid === current) {
				remove();
			}
			cards.delete(uid);
		},

		state() {
			return {
				current,
				cards: [...cards].map(([uid, ndef]) => ({ uid, ndef })),
			};
		},
	};

	return api;
}

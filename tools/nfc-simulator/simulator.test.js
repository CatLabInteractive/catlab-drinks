// @vitest-environment node
import { afterEach, describe, expect, test } from 'vitest';
import { io } from 'socket.io-client';
import { createSimulator } from './simulator.mjs';

let sim;
let socket;
let base;

async function start(options = {}) {
	sim = createSimulator(options);
	const port = await sim.listen(0, '127.0.0.1');
	base = 'http://127.0.0.1:' + port;
	socket = io('http://127.0.0.1:' + port, { transports: ['websocket'] });
	await once(socket, 'connect');
}

function once(target, event) {
	return new Promise((resolve) => target.once(event, resolve));
}

function emitWithAck(target, event, payload) {
	return new Promise((resolve) => target.emit(event, payload, resolve));
}

function hello(target, password) {
	return emitWithAck(target, 'hello', { password });
}

const uid = '04a1b2c3d4e5f6';

afterEach(async () => {
	if (socket) {
		socket.close();
	}
	if (sim) {
		await sim.close();
	}
	sim = null;
	socket = null;
});

describe('nfc simulator socket protocol', () => {
	test('hello with the right password answers success', async () => {
		await start({ password: 'secret' });
		expect(await hello(socket, 'secret')).toEqual({ success: true });
	});

	test('hello with a wrong password answers failure and gets no card events', async () => {
		await start({ password: 'secret' });
		expect((await hello(socket, 'wrong')).success).toBe(false);
		let events = 0;
		socket.on('nfc:card:connect', () => events++);
		sim.tap('04a1b2c3d4e5f6');
		await new Promise((r) => setTimeout(r, 100));
		expect(events).toBe(0);
	});

	test('a new card connects and sends 16 zero bytes as data', async () => {
		await start();
		await hello(socket, '');
		const connect = once(socket, 'nfc:card:connect');
		sim.tap('04A1B2C3D4E5F6');
		expect(await connect).toEqual({ uid: '04a1b2c3d4e5f6' });
		const data = once(socket, 'nfc:data');
		socket.emit('nfc:password', { uid: '04a1b2c3d4e5f6', password: 'x' });
		const payload = await data;
		expect(payload.uid).toBe('04a1b2c3d4e5f6');
		expect(payload.ndef).toBeUndefined();
		expect(Buffer.from(payload.data, 'base64')).toEqual(Buffer.alloc(16));
	});

	test('a write is stored and the next tap returns it as ndef', async () => {
		await start();
		await hello(socket, '');
		sim.tap(uid);
		await once(socket, 'nfc:card:connect');
		let data = once(socket, 'nfc:data');
		socket.emit('nfc:password', { uid, password: 'x' });
		await data;
		const ack = await emitWithAck(socket, 'nfc:write', { uid, ndef: 'AQID' });
		expect(ack).toEqual({ success: true });
		sim.remove();
		sim.tap(uid);
		await once(socket, 'nfc:card:connect');
		data = once(socket, 'nfc:data');
		socket.emit('nfc:password', { uid, password: 'x' });
		expect(await data).toEqual({ uid, ndef: 'AQID' });
		expect(sim.state().cards).toEqual([{ uid, ndef: 'AQID' }]);
	});

	test('a write to a card that is not on the reader fails and changes nothing', async () => {
		await start();
		await hello(socket, '');
		sim.tap(uid);
		await once(socket, 'nfc:card:connect');
		const data = once(socket, 'nfc:data');
		socket.emit('nfc:password', { uid, password: 'x' });
		await data;
		await emitWithAck(socket, 'nfc:write', { uid, ndef: 'AQID' });
		sim.remove();
		const ack = await emitWithAck(socket, 'nfc:write', { uid, ndef: 'BBBB' });
		expect(ack.success).toBe(false);
		expect(ack.error.error.name).toBe('CardNotPresent');
		expect(sim.state().cards).toEqual([{ uid, ndef: 'AQID' }]);
	});

	test('a second tap disconnects the first card before it connects the second', async () => {
		await start();
		await hello(socket, '');
		const order = [];
		socket.on('nfc:card:disconnect', (d) => order.push('off:' + d.uid));
		socket.on('nfc:card:connect', (d) => order.push('on:' + d.uid));
		sim.tap('aaaaaaaa');
		sim.tap('bbbbbbbb');
		await new Promise((r) => setTimeout(r, 100));
		expect(order).toEqual(['on:aaaaaaaa', 'off:aaaaaaaa', 'on:bbbbbbbb']);
	});

	test('a client that says hello while a card is on the reader gets the connect event', async () => {
		await start();
		sim.tap('aaaaaaaa');
		const connect = once(socket, 'nfc:card:connect');
		await hello(socket, '');
		expect(await connect).toEqual({ uid: 'aaaaaaaa' });
	});

	test('remove sends disconnect, forget makes the card new again, an invalid uid throws', async () => {
		await start();
		await hello(socket, '');
		sim.tap(uid);
		await once(socket, 'nfc:card:connect');
		const data = once(socket, 'nfc:data');
		socket.emit('nfc:password', { uid, password: 'x' });
		await data;
		await emitWithAck(socket, 'nfc:write', { uid, ndef: 'AQID' });
		const off = once(socket, 'nfc:card:disconnect');
		sim.remove();
		expect(await off).toEqual({ uid });
		sim.forget(uid);
		expect(sim.state()).toEqual({ current: null, cards: [] });
		expect(() => sim.tap('not-hex')).toThrow(RangeError);
	});
});

describe('nfc simulator http interface', () => {
	test('POST /cards/{uid}/tap puts the card on the reader', async () => {
		await start();
		await hello(socket, '');
		const connect = once(socket, 'nfc:card:connect');
		const res = await fetch(base + '/cards/04a1b2c3d4e5f6/tap', { method: 'POST' });
		expect(res.status).toBe(200);
		expect(res.headers.get('access-control-allow-origin')).toBe('*');
		expect(await res.json()).toEqual({ current: '04a1b2c3d4e5f6', cards: [{ uid: '04a1b2c3d4e5f6', ndef: null }] });
		expect(await connect).toEqual({ uid: '04a1b2c3d4e5f6' });
	});

	test('POST /remove takes the card off and GET /cards shows the state', async () => {
		await start();
		sim.tap('aaaaaaaa');
		expect((await (await fetch(base + '/remove', { method: 'POST' })).json()).current).toBe(null);
		expect(await (await fetch(base + '/cards')).json()).toEqual({ current: null, cards: [{ uid: 'aaaaaaaa', ndef: null }] });
	});

	test('DELETE /cards/{uid} forgets the card', async () => {
		await start();
		sim.tap('aaaaaaaa');
		const res = await fetch(base + '/cards/aaaaaaaa', { method: 'DELETE' });
		expect(await res.json()).toEqual({ current: null, cards: [] });
	});

	test('an invalid uid gives 400 and creates no card', async () => {
		await start();
		const res = await fetch(base + '/cards/not-hex/tap', { method: 'POST' });
		expect(res.status).toBe(400);
		expect((await res.json()).error).toEqual(expect.any(String));
		expect(sim.state().cards).toEqual([]);
	});

	test('a malformed url gives 400 and the simulator keeps running', async () => {
		await start();
		const res = await fetch(base + '/cards/%zz/tap', { method: 'POST' });
		expect(res.status).toBe(400);
		expect((await res.json()).error).toEqual(expect.any(String));
		expect(sim.state().cards).toEqual([]);
		expect((await fetch(base + '/cards')).status).toBe(200);
	});

	test('an unknown route gives 404', async () => {
		await start();
		expect((await fetch(base + '/nothing')).status).toBe(404);
	});
});

describe('nfc simulator edge cases', () => {
	test('close on a simulator that never listened resolves', async () => {
		const idle = createSimulator();
		await expect(idle.close()).resolves.toBeUndefined();
	});

	test('a write with an ndef that is not a non-empty string fails and stores nothing', async () => {
		await start();
		await hello(socket, '');
		sim.tap(uid);
		await once(socket, 'nfc:card:connect');
		for (const ndef of [undefined, '', 5, null]) {
			const ack = await emitWithAck(socket, 'nfc:write', { uid, ndef });
			expect(ack).toEqual({ success: false, error: { error: { name: 'InvalidNdef' } } });
		}
		expect(sim.state().cards).toEqual([{ uid, ndef: null }]);
	});

	test('nfc:password for a card that is not on the reader is ignored', async () => {
		await start();
		await hello(socket, '');
		sim.tap('aaaaaaaa');
		sim.tap('bbbbbbbb');
		await new Promise((r) => setTimeout(r, 50));
		let events = 0;
		socket.on('nfc:data', () => events++);
		socket.emit('nfc:password', { uid: 'aaaaaaaa', password: 'x' });
		await new Promise((r) => setTimeout(r, 100));
		expect(events).toBe(0);
	});
});

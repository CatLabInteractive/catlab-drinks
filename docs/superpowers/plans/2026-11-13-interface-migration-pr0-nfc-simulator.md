# NFC Simulator Implementation Plan (interface migration, pull request 0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A small Node program takes the place of the `nfc-socketio` card reader service. With it, the POS card screens can be tested in a desktop browser.

**Architecture:** One module creates an HTTP server with a socket.io server on it. The socket side speaks the events that `resources/shared/js/nfccards/nfc/RemoteNfcReader.ts` uses. The HTTP side lets a person or a test script put a virtual card on the reader and remove it. Cards live in memory.

**Tech Stack:** Node (ES modules, `.mjs`), `socket.io` 4 (server), `socket.io-client` 4 (already installed, used by the test), Vitest.

**Spec:** `docs/superpowers/specs/2026-11-13-vue3-interface-migration-design.md`, section "Pull request 0: NFC simulator".

## Global Constraints

- The simulator is a development tool. It changes no file below `resources/`, `app/`, or `public/`.
- The simulator does not go into the frontend bundles. `webpack.mix.js` stays as it is.
- Location: `tools/nfc-simulator/`. Start command: `npm run nfc-simulator`.
- This pull request changes `package.json`. The matching `package-lock.json` change is part of it. Use `npm install`, never `npm update`.
- Work on the branch `feature/nfc-simulator`, made from `main`. Open a pull request. Do not push to `main`.
- Before each commit, run `git branch --show-current` and make sure that the result is `feature/nfc-simulator`.
- Commit messages end with the line `Co-Authored-By: Claude Fable 5.2 <noreply@anthropic.com>`.

## Review Focus

- A second card is put on the reader while the first card is still there. Expected: the first card disconnects, then the second card connects.
- The POS writes to a card that is no longer on the reader. Expected: the write fails with an error that the POS can read (`response.error.error.name`), and the stored card does not change.
- The POS connects with a wrong reader password. Expected: `hello` answers `success: false` and that socket gets no card events.
- The POS page reloads while a card is on the reader. Expected: after the new `hello`, the POS gets `nfc:card:connect` for that card.
- The HTTP interface gets a UID that is not hexadecimal. Expected: status 400 and no new card.

Task 1 and Task 2 hold the tests for these lines.

## File Structure

| File | Responsibility |
|------|----------------|
| `tools/nfc-simulator/simulator.mjs` | `createSimulator()`: card store, socket protocol, HTTP interface |
| `tools/nfc-simulator/cli.mjs` | Reads the environment, starts the simulator, prints the address |
| `tools/nfc-simulator/simulator.test.js` | Vitest tests, Node environment |
| `tools/nfc-simulator/README.md` | How to start it and how to connect the POS |
| `package.json` | `socket.io` in `devDependencies`, script `nfc-simulator` |
| `vitest.config.js` | `include` also takes `tools/**/*.test.js` |

## The protocol (copied from the code, 2026-11-13)

Source: `RemoteNfcReader.ts` in this repository and `src/reader.js` of the public repository `catlab-drinks/nfc-socketio`.

| Direction | Event | Payload | Acknowledgement |
|-----------|-------|---------|-----------------|
| POS to reader | `hello` | `{ password }` | `{ success: true }` or `{ success: false }` |
| reader to POS | `nfc:card:connect` | `{ uid }` | none |
| POS to reader | `nfc:password` | `{ uid, password }` | `{ success: true }`. If the POS gives no callback, the reader sends nothing. |
| reader to POS | `nfc:data` | `{ uid, ndef }` for a card with content, `{ uid, data }` for a new card. Both values are base64. | none |
| POS to reader | `nfc:write` | `{ uid, ndef }` (base64) | `{ success: true }` or `{ success: false, error: { error: { name } } }` |
| reader to POS | `nfc:card:disconnect` | `{ uid }` | none |

A new card sends `data` with 16 zero bytes. The POS reads the first three zero bytes as "brand new card" and writes empty content.

---

### Task 1: Card store and socket protocol

**Files:**
- Create: `tools/nfc-simulator/simulator.mjs`
- Create: `tools/nfc-simulator/simulator.test.js`
- Modify: `package.json` (add `"socket.io": "^4.8.1"` to a new `devDependencies` block)
- Modify: `vitest.config.js` (`include: ['resources/**/*.test.js', 'tools/**/*.test.js']`)

**Interfaces:**
- Produces: `createSimulator(options)` in `simulator.mjs`.
  - `options.password`: string, default `''`. An empty string accepts each password.
  - Returns an object with these members:
    - `httpServer`: a Node `http.Server`, not listening yet.
    - `listen(port, host)`: returns a `Promise<number>` with the real port. Port `0` selects a free port.
    - `close()`: returns a `Promise<void>`.
    - `tap(uid)`: puts the card on the reader and returns nothing. If the UID is new, it creates the card. It throws `RangeError` for an invalid UID.
    - `remove()`: takes the current card off the reader. Does nothing without a card.
    - `forget(uid)`: deletes the stored card, so that the next tap gives a new card.
    - `state()`: returns `{ current: string | null, cards: Array<{ uid: string, ndef: string | null }> }`.
  - A valid UID matches `/^[0-9a-f]{8,20}$/i`. The store keeps UIDs in lower case.

- [ ] **Step 1: Install the server package**

Run: `npm install --save-dev socket.io@^4.8.1`
Expected: `package.json` has `devDependencies.socket.io`. `git diff --stat` shows only `package.json` and `package-lock.json`.

- [ ] **Step 2: Write the failing tests**

Put `// @vitest-environment node` on the first line of `tools/nfc-simulator/simulator.test.js`. Each test creates a simulator, calls `listen(0, '127.0.0.1')`, connects with `io('http://127.0.0.1:' + port)` from `socket.io-client`, and closes both in `afterEach`. Write a helper `once(socket, event)` that returns a promise with the payload, and a helper `hello(socket, password)` that returns the acknowledgement.

```js
test('hello with the right password answers success', async () => {
	// simulator created with { password: 'secret' }
	expect(await hello(socket, 'secret')).toEqual({ success: true });
});

test('hello with a wrong password answers failure and gets no card events', async () => {
	expect((await hello(socket, 'wrong')).success).toBe(false);
	let events = 0;
	socket.on('nfc:card:connect', () => events++);
	sim.tap('04a1b2c3d4e5f6');
	await new Promise((r) => setTimeout(r, 100));
	expect(events).toBe(0);
});

test('a new card connects and sends 16 zero bytes as data', async () => {
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
	// tap, nfc:password, then:
	const ack = await emitWithAck(socket, 'nfc:write', { uid, ndef: 'AQID' });
	expect(ack).toEqual({ success: true });
	sim.remove();
	// tap again, nfc:password again, then:
	expect(await data).toEqual({ uid, ndef: 'AQID' });
	expect(sim.state().cards).toEqual([{ uid, ndef: 'AQID' }]);
});

test('a write to a card that is not on the reader fails and changes nothing', async () => {
	// tap, nfc:password, write 'AQID', remove, then:
	const ack = await emitWithAck(socket, 'nfc:write', { uid, ndef: 'BBBB' });
	expect(ack.success).toBe(false);
	expect(ack.error.error.name).toBe('CardNotPresent');
	expect(sim.state().cards).toEqual([{ uid, ndef: 'AQID' }]);
});

test('a second tap disconnects the first card before it connects the second', async () => {
	const order = [];
	socket.on('nfc:card:disconnect', (d) => order.push('off:' + d.uid));
	socket.on('nfc:card:connect', (d) => order.push('on:' + d.uid));
	sim.tap('aaaaaaaa');
	sim.tap('bbbbbbbb');
	await new Promise((r) => setTimeout(r, 100));
	expect(order).toEqual(['on:aaaaaaaa', 'off:aaaaaaaa', 'on:bbbbbbbb']);
});

test('a client that says hello while a card is on the reader gets the connect event', async () => {
	sim.tap('aaaaaaaa');
	const connect = once(socket, 'nfc:card:connect');
	await hello(socket, '');
	expect(await connect).toEqual({ uid: 'aaaaaaaa' });
});

test('remove sends disconnect, forget makes the card new again, an invalid uid throws', async () => {
	// tap + write 'AQID', then remove: expect nfc:card:disconnect { uid }
	sim.forget(uid);
	expect(sim.state()).toEqual({ current: null, cards: [] });
	expect(() => sim.tap('not-hex')).toThrow(RangeError);
});
```

- [ ] **Step 3: Run the tests and make sure that they fail**

Run: `npx vitest run tools/nfc-simulator`
Expected: FAIL, because `./simulator.mjs` does not exist.

- [ ] **Step 4: Implement `createSimulator(options)` in `tools/nfc-simulator/simulator.mjs`**

Use `http.createServer` and `new Server(httpServer, { cors: { origin: '*' } })` from `socket.io`. Send card events only to sockets that completed `hello` (put them in a socket.io room). If the client gave no acknowledgement function, do not call one. The failed write uses the name `CardNotPresent`. The HTTP request handler comes in Task 2. For now each HTTP request that is not for socket.io answers 404.

- [ ] **Step 5: Run the tests and make sure that they pass**

Run: `npx vitest run tools/nfc-simulator`
Expected: PASS, 8 tests.

Run: `npm test`
Expected: PASS. The tests below `resources/` still run.

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json vitest.config.js tools/nfc-simulator/simulator.mjs tools/nfc-simulator/simulator.test.js
git commit -m "Add the card store and socket protocol of the NFC simulator"
```

---

### Task 2: HTTP interface, start command, and instructions

**Files:**
- Modify: `tools/nfc-simulator/simulator.mjs`
- Modify: `tools/nfc-simulator/simulator.test.js`
- Create: `tools/nfc-simulator/cli.mjs`
- Create: `tools/nfc-simulator/README.md`
- Modify: `package.json` (script `"nfc-simulator": "node tools/nfc-simulator/cli.mjs"`)

**Interfaces:**
- Consumes: `createSimulator(options)` and its members from Task 1.
- Produces: the HTTP interface, on the same port as socket.io. Each answer is JSON and has the header `Access-Control-Allow-Origin: *`.

  | Request | Action | Answer |
  |---------|--------|--------|
  | `GET /cards` | none | 200 with `state()` |
  | `POST /cards/{uid}/tap` | `tap(uid)` | 200 with `state()`, or 400 with `{ error }` for an invalid UID |
  | `POST /remove` | `remove()` | 200 with `state()` |
  | `DELETE /cards/{uid}` | `forget(uid)` | 200 with `state()` |
  | other | none | 404 with `{ error }` |

- Produces: `cli.mjs`. It reads `NFC_SIM_PORT` (default `3030`), `NFC_SIM_HOST` (default `127.0.0.1`), and `NFC_SIM_PASSWORD` (default empty). It prints the address and one example `curl` line for a tap.

- [ ] **Step 1: Write the failing tests**

Add to `simulator.test.js`. Use the global `fetch` of Node.

```js
test('POST /cards/{uid}/tap puts the card on the reader', async () => {
	await hello(socket, '');
	const connect = once(socket, 'nfc:card:connect');
	const res = await fetch(base + '/cards/04a1b2c3d4e5f6/tap', { method: 'POST' });
	expect(res.status).toBe(200);
	expect(await res.json()).toEqual({ current: '04a1b2c3d4e5f6', cards: [{ uid: '04a1b2c3d4e5f6', ndef: null }] });
	expect(await connect).toEqual({ uid: '04a1b2c3d4e5f6' });
});

test('POST /remove takes the card off and GET /cards shows the state', async () => {
	sim.tap('aaaaaaaa');
	expect((await (await fetch(base + '/remove', { method: 'POST' })).json()).current).toBe(null);
	expect(await (await fetch(base + '/cards')).json()).toEqual({ current: null, cards: [{ uid: 'aaaaaaaa', ndef: null }] });
});

test('DELETE /cards/{uid} forgets the card', async () => {
	sim.tap('aaaaaaaa');
	const res = await fetch(base + '/cards/aaaaaaaa', { method: 'DELETE' });
	expect(await res.json()).toEqual({ current: null, cards: [] });
});

test('an invalid uid gives 400 and creates no card', async () => {
	const res = await fetch(base + '/cards/not-hex/tap', { method: 'POST' });
	expect(res.status).toBe(400);
	expect(sim.state().cards).toEqual([]);
});

test('an unknown route gives 404', async () => {
	expect((await fetch(base + '/nothing')).status).toBe(404);
});
```

- [ ] **Step 2: Run the tests and make sure that they fail**

Run: `npx vitest run tools/nfc-simulator`
Expected: FAIL. The five new tests get 404 or a wrong body.

- [ ] **Step 3: Implement the HTTP handler in `simulator.mjs`, then `cli.mjs`, then the npm script**

The handler must not take requests whose path starts with `/socket.io/`.

- [ ] **Step 4: Run the tests and make sure that they pass**

Run: `npx vitest run tools/nfc-simulator`
Expected: PASS, 13 tests.

- [ ] **Step 5: Start the program by hand**

Run: `NFC_SIM_PORT=3999 npm run nfc-simulator` in the background, then `curl -s -X POST http://127.0.0.1:3999/cards/04a1b2c3d4e5f6/tap`.
Expected: the answer is `{"current":"04a1b2c3d4e5f6","cards":[{"uid":"04a1b2c3d4e5f6","ndef":null}]}`. Stop the program.

- [ ] **Step 6: Write `tools/nfc-simulator/README.md`**

The file has these parts:

- The purpose of the tool.
- The start command.
- The three environment variables with their defaults.
- The HTTP table from this task.
- The POS steps: open the POS settings, set the NFC server to `http://127.0.0.1:3030`, set a password, save.
- The limits from the spec: no reader faults, no card that leaves during a write, no Android reader.

- [ ] **Step 7: Commit**

```bash
git add package.json tools/nfc-simulator
git commit -m "Add the HTTP interface and start command of the NFC simulator"
```

---

### Task 3: Proof with the real POS, and the pull request

**Files:**
- Modify: `CLAUDE.md` (one short section "NFC simulator" that points to `tools/nfc-simulator/README.md`)

- [ ] **Step 1: Run the POS against the simulator**

Start the local stack with `docker compose` (see `docker-compose.yml`) and a development build (`npm run dev`). Start the simulator. Pair a POS device in a desktop browser. Set the NFC server address in the POS settings to the simulator.
Expected: the POS shows the reader as connected.

- [ ] **Step 2: Do one card cycle**

Tap a new card with `curl`. If the POS asks for it, approve the public key of the device in Manage. Top up the card in the POS. Remove the card. Tap it again.
Expected: the second tap shows the balance of the top-up. `GET /cards` shows a non-empty `ndef` for the card. The browser console shows no error from `RemoteNfcReader`.

If a step fails because the simulator differs from the real service, correct the simulator and add a test for the difference to `simulator.test.js`. Do not change files below `resources/`.

- [ ] **Step 3: Add the section to `CLAUDE.md` and commit**

```bash
git add CLAUDE.md
git commit -m "Document the NFC simulator"
```

- [ ] **Step 4: Run the full checks**

Run: `npm test` and `npx jest`
Expected: both PASS.

Run: `git status --short`
Expected: no changed file below `resources/`, `app/`, or `public/`.

- [ ] **Step 5: Open the pull request**

Push `feature/nfc-simulator` and open a pull request to `main`. The description states what Step 2 showed. It ends with the line `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

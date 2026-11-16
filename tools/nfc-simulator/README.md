# NFC simulator

This tool takes the place of the NFC reader service that the POS talks to.
It lets you test card payments on a computer that has no NFC reader.
The simulator speaks the same socket.io protocol as the real service.
You put cards on the reader and take them off with simple HTTP requests.

## Start

```bash
npm run nfc-simulator
```

The command prints the address and an example `curl` line for a tap.
To stop the simulator, press Ctrl+C.

## Environment variables

| Variable | Default | Meaning |
|----------|---------|---------|
| `NFC_SIM_PORT` | `3030` | The port for both socket.io and HTTP |
| `NFC_SIM_HOST` | `127.0.0.1` | The address to listen on |
| `NFC_SIM_PASSWORD` | empty | The password that the POS must send. An empty value accepts every password. |

## HTTP interface

Every answer is JSON and has the header `Access-Control-Allow-Origin: *`.
If a browser sends a preflight request, the simulator answers any `OPTIONS` request with 204.
A card UID has 8 to 20 hexadecimal characters.

| Request | Action | Answer |
|---------|--------|--------|
| `GET /cards` | Show the state | 200 with the state |
| `POST /cards/{uid}/tap` | Put the card on the reader | 200 with the state, or 400 for an invalid UID |
| `POST /remove` | Take the card off the reader | 200 with the state |
| `DELETE /cards/{uid}` | Forget the card, so it is new again | 200 with the state |
| other | None | 404 |

The state has the form `{ "current": "uid or null", "cards": [{ "uid": "...", "ndef": "base64 or null" }] }`.
A tap on a second card first takes the first card off the reader.
The simulator keeps the data that the POS writes to a card until you forget the card or stop the simulator.

## Use it with the POS

1. Start the simulator.
2. Open the POS settings.
3. Set the NFC server to `http://127.0.0.1:3030`.
4. If you set `NFC_SIM_PASSWORD`, enter the same password. Otherwise enter any password.
5. Save the settings.
6. Tap a card, for example with `curl -X POST http://127.0.0.1:3030/cards/04a1b2c3d4e5f6/tap`.

## Limits

- The simulator cannot produce reader faults.
- A card cannot leave the reader during a write.
- The simulator does not replace the Android reader.

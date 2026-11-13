import { createSimulator } from './simulator.mjs';

const port = Number(process.env.NFC_SIM_PORT || 3030);
const host = process.env.NFC_SIM_HOST || '127.0.0.1';
const password = process.env.NFC_SIM_PASSWORD || '';

const simulator = createSimulator({ password });
const actualPort = await simulator.listen(port, host);

console.log(`NFC simulator listening on http://${host}:${actualPort}`);
console.log(`Tap a card: curl -X POST http://${host}:${actualPort}/cards/04a1b2c3d4e5f6/tap`);

const shutdown = async () => {
	await simulator.close();
	process.exit(0);
};
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

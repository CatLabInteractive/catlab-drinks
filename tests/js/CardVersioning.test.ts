import { KeyManager, PublicKeyEntry } from '../../resources/shared/js/nfccards/crypto/KeyManager';

// Card version constants (duplicated here to avoid importing Card.ts which pulls in NDEF and NFC modules)
const CARD_VERSION_LEGACY = 0;
const CARD_VERSION_ASYMMETRIC = 1;

describe('Card Version Constants', () => {

	test('legacy version should be 0', () => {
		expect(CARD_VERSION_LEGACY).toBe(0);
	});

	test('asymmetric version should be 1', () => {
		expect(CARD_VERSION_ASYMMETRIC).toBe(1);
	});
});

describe('KeyManager cross-device signing verification', () => {

	let store: { [key: string]: string };

	beforeEach(() => {
		store = {};
		const mockLocalStorage = {
			getItem: jest.fn((key: string) => store[key] || null),
			setItem: jest.fn((key: string, value: string) => { store[key] = value; }),
			removeItem: jest.fn((key: string) => { delete store[key]; }),
			clear: jest.fn(),
			length: 0,
			key: jest.fn()
		};
		Object.defineProperty(global, 'localStorage', { value: mockLocalStorage, writable: true });
	});

	test('device A signs, device B verifies with approved key', () => {
		const deviceA = new KeyManager();
		deviceA.initialize('device-a-uid', 1, 'secret-a');

		const deviceB = new KeyManager();
		deviceB.initialize('device-b-uid', 2, 'secret-b');

		// Share A's public key with B (as approved)
		deviceB.loadPublicKeys([{
			id: 1,
			uid: 'device-a-uid',
			public_key: deviceA.getPublicKeyHex(),
			approved_at: '2024-01-01'
		}]);

		// Simulate card data
		const cardData = '\x00\x01' + // version
			'device-a-uid\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00' + // 36 bytes device UID
			'\x00\x00\x03\xe8'; // balance 1000

		const signature = deviceA.sign(cardData + 'card-hardware-uid');

		// Verify
		const valid = deviceB.verify('device-a-uid', cardData + 'card-hardware-uid', signature);
		expect(valid).toBe(true);
	});

	test('signature fails when card data is tampered', () => {
		const deviceA = new KeyManager();
		deviceA.initialize('device-a-uid', 1, 'secret-a');

		const deviceB = new KeyManager();
		deviceB.initialize('device-b-uid', 2, 'secret-b');

		deviceB.loadPublicKeys([{
			id: 1,
			uid: 'device-a-uid',
			public_key: deviceA.getPublicKeyHex(),
			approved_at: '2024-01-01'
		}]);

		const originalData = 'version:1;balance:1000';
		const signature = deviceA.sign(originalData);

		// Tamper with data (e.g., change balance)
		const tamperedData = 'version:1;balance:9999';
		const valid = deviceB.verify('device-a-uid', tamperedData, signature);
		expect(valid).toBe(false);
	});

	test('signature fails for different card UID (replay attack prevention)', () => {
		const deviceA = new KeyManager();
		deviceA.initialize('device-a-uid', 1, 'secret-a');

		const deviceB = new KeyManager();
		deviceB.initialize('device-b-uid', 2, 'secret-b');

		deviceB.loadPublicKeys([{
			id: 1,
			uid: 'device-a-uid',
			public_key: deviceA.getPublicKeyHex(),
			approved_at: '2024-01-01'
		}]);

		const payloadForCard1 = 'balance:1000' + 'card-uid-1';
		const signature = deviceA.sign(payloadForCard1);

		// Try to use same signature on different card
		const payloadForCard2 = 'balance:1000' + 'card-uid-2';
		const valid = deviceB.verify('device-a-uid', payloadForCard2, signature);
		expect(valid).toBe(false);
	});

	test('verification fails for unapproved key', () => {
		const deviceA = new KeyManager();
		deviceA.initialize('device-a-uid', 1, 'secret-a');

		const deviceB = new KeyManager();
		deviceB.initialize('device-b-uid', 2, 'secret-b');

		// Load A's key but NOT approved (approved_at is null)
		deviceB.loadPublicKeys([{
			id: 1,
			uid: 'device-a-uid',
			public_key: deviceA.getPublicKeyHex(),
			approved_at: null
		}]);

		const data = 'test data';
		const signature = deviceA.sign(data);

		// Should fail because key is not approved
		const valid = deviceB.verify('device-a-uid', data, signature);
		expect(valid).toBe(false);
	});

	test('multiple devices can verify each others signatures', () => {
		const devices: KeyManager[] = [];
		const publicKeys: PublicKeyEntry[] = [];

		// Create 3 devices
		for (let i = 0; i < 3; i++) {
			const km = new KeyManager();
			km.initialize(`device-${i}`, i, `secret-${i}`);
			devices.push(km);
			publicKeys.push({
				id: i,
				uid: `device-${i}`,
				public_key: km.getPublicKeyHex(),
				approved_at: '2024-01-01'
			});
		}

		// Load all public keys into each device
		devices.forEach(d => d.loadPublicKeys(publicKeys));

		// Each device signs data, and all others verify
		for (let signer = 0; signer < 3; signer++) {
			const data = `signed by device ${signer}`;
			const signature = devices[signer].sign(data);

			for (let verifier = 0; verifier < 3; verifier++) {
				if (verifier === signer) continue;
				const valid = devices[verifier].verify(`device-${signer}`, data, signature);
				expect(valid).toBe(true);
			}
		}
	});
});

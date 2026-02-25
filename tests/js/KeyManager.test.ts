import { KeyManager, PublicKeyEntry, ECDSA_SIGNATURE_LENGTH } from '../../resources/shared/js/nfccards/crypto/KeyManager';

describe('KeyManager', () => {

	let keyManager: KeyManager;

	beforeEach(() => {
		// Mock localStorage
		const store: { [key: string]: string } = {};
		const mockLocalStorage = {
			getItem: jest.fn((key: string) => store[key] || null),
			setItem: jest.fn((key: string, value: string) => { store[key] = value; }),
			removeItem: jest.fn((key: string) => { delete store[key]; }),
			clear: jest.fn(() => { Object.keys(store).forEach(k => delete store[k]); }),
			length: 0,
			key: jest.fn()
		};
		Object.defineProperty(global, 'localStorage', { value: mockLocalStorage, writable: true });

		keyManager = new KeyManager();
	});

	describe('initialization', () => {
		test('should not be initialized before generateKeyPair is called', () => {
			keyManager.initialize('test-device-uid', 1, 'test-secret');
			expect(keyManager.isInitialized()).toBe(false);
		});

		test('should generate a new key pair on explicit generateKeyPair call', () => {
			keyManager.generateKeyPair('test-device-uid', 1, 'test-secret');

			expect(keyManager.isInitialized()).toBe(true);
			expect(keyManager.getPublicKeyHex()).toBeTruthy();
			expect(keyManager.getDeviceUid()).toBe('test-device-uid');
			expect(keyManager.getDeviceId()).toBe(1);
		});

		test('should store encrypted key in localStorage on generate', () => {
			keyManager.generateKeyPair('test-device-uid', 1, 'test-secret');

			expect(localStorage.setItem).toHaveBeenCalledWith(
				'catlab_drinks_device_keypair_test-device-uid',
				expect.any(String)
			);
		});

		test('should load existing key pair from localStorage via initialize', () => {
			// Generate first
			keyManager.generateKeyPair('test-device-uid', 1, 'test-secret');
			const firstPublicKey = keyManager.getPublicKeyHex();

			// Create new instance and initialize (load) with same credentials
			const keyManager2 = new KeyManager();
			keyManager2.initialize('test-device-uid', 1, 'test-secret');
			const secondPublicKey = keyManager2.getPublicKeyHex();

			expect(firstPublicKey).toBe(secondPublicKey);
		});

		test('should not load key pair with wrong secret', () => {
			keyManager.generateKeyPair('test-device-uid', 1, 'test-secret');

			const keyManager2 = new KeyManager();
			keyManager2.initialize('test-device-uid', 1, 'wrong-secret');

			// Key should NOT be loaded since decryption failed
			expect(keyManager2.isInitialized()).toBe(false);
		});

		test('hasStoredKeyPair should detect existing stored key', () => {
			expect(keyManager.hasStoredKeyPair('test-device-uid')).toBe(false);

			keyManager.generateKeyPair('test-device-uid', 1, 'test-secret');

			expect(keyManager.hasStoredKeyPair('test-device-uid')).toBe(true);
		});
	});

	describe('signing and verification', () => {
		let keyManager1: KeyManager;
		let keyManager2: KeyManager;

		beforeEach(() => {
			keyManager1 = new KeyManager();
			keyManager1.generateKeyPair('device-1', 1, 'secret-1');

			keyManager2 = new KeyManager();
			keyManager2.generateKeyPair('device-2', 2, 'secret-2');

			// Load device-1's public key into device-2's key manager
			const publicKeys: PublicKeyEntry[] = [
				{
					id: 1,
					uid: 'device-1',
					public_key: keyManager1.getPublicKeyHex(),
					approved_at: '2024-01-01T00:00:00Z'
				}
			];
			keyManager2.loadPublicKeys(publicKeys);
		});

		test('should sign data and produce a 48-byte signature', () => {
			const data = 'test data payload';
			const signature = keyManager1.sign(data);

			expect(signature.length).toBe(ECDSA_SIGNATURE_LENGTH);
		});

		test('should verify a valid signature by device UID', () => {
			const data = 'test data payload';
			const signature = keyManager1.sign(data);

			const result = keyManager2.verify('device-1', data, signature);
			expect(result).toBe(true);
		});

		test('should verify a valid signature by numeric device ID', () => {
			const data = 'test data payload';
			const signature = keyManager1.sign(data);

			const result = keyManager2.verify(1, data, signature);
			expect(result).toBe(true);
		});

		test('should reject an invalid signature (wrong data)', () => {
			const data = 'test data payload';
			const signature = keyManager1.sign(data);

			const result = keyManager2.verify('device-1', 'tampered data', signature);
			expect(result).toBe(false);
		});

		test('should reject signature from unknown device', () => {
			const data = 'test data payload';
			const signature = keyManager1.sign(data);

			const result = keyManager2.verify('unknown-device', data, signature);
			expect(result).toBe(false);
		});

		test('should reject signature signed by different key', () => {
			const data = 'test data payload';
			const signature = keyManager2.sign(data);

			const result = keyManager2.verify('device-1', data, signature);
			expect(result).toBe(false);
		});
	});

	describe('public key management', () => {
		test('should load multiple public keys', () => {
			const km1 = new KeyManager();
			km1.generateKeyPair('dev-a', 1, 'sec-a');

			const km2 = new KeyManager();
			km2.generateKeyPair('dev-b', 2, 'sec-b');

			const km3 = new KeyManager();
			km3.generateKeyPair('dev-c', 3, 'sec-c');

			const keys: PublicKeyEntry[] = [
				{ id: 1, uid: 'dev-a', public_key: km1.getPublicKeyHex(), approved_at: '2024-01-01T00:00:00Z' },
				{ id: 2, uid: 'dev-b', public_key: km2.getPublicKeyHex(), approved_at: '2024-01-01T00:00:00Z' },
			];

			km3.loadPublicKeys(keys);

			expect(km3.hasPublicKey('dev-a')).toBe(true);
			expect(km3.hasPublicKey('dev-b')).toBe(true);
			expect(km3.hasPublicKey('dev-c')).toBe(false);
			expect(km3.hasPublicKeyById(1)).toBe(true);
			expect(km3.hasPublicKeyById(2)).toBe(true);
			expect(km3.hasPublicKeyById(3)).toBe(false);
		});

		test('should not load unapproved keys', () => {
			const km1 = new KeyManager();
			km1.generateKeyPair('dev-a', 1, 'sec-a');

			const km2 = new KeyManager();
			km2.generateKeyPair('dev-b', 2, 'sec-b');

			const keys: PublicKeyEntry[] = [
				{ id: 1, uid: 'dev-a', public_key: km1.getPublicKeyHex(), approved_at: '2024-01-01T00:00:00Z' },
				{ id: 2, uid: 'dev-b', public_key: km2.getPublicKeyHex(), approved_at: null },
			];

			const km3 = new KeyManager();
			km3.generateKeyPair('dev-c', 3, 'sec-c');
			km3.loadPublicKeys(keys);

			expect(km3.hasPublicKey('dev-a')).toBe(true);
			expect(km3.hasPublicKey('dev-b')).toBe(false);
		});

		test('should skip entries with invalid public keys', () => {
			const keys: PublicKeyEntry[] = [
				{ id: 1, uid: 'bad-device', public_key: 'not-a-valid-key', approved_at: '2024-01-01T00:00:00Z' },
			];

			const km = new KeyManager();
			km.generateKeyPair('my-device', 1, 'secret');
			km.loadPublicKeys(keys);

			expect(km.hasPublicKey('bad-device')).toBe(false);
		});
	});

	describe('isApproved', () => {
		test('should return false for null approvedAt', () => {
			const km = new KeyManager();
			expect(km.isApproved(null)).toBe(false);
		});

		test('should return true for non-null approvedAt', () => {
			const km = new KeyManager();
			expect(km.isApproved('2024-01-01T00:00:00Z')).toBe(true);
		});
	});

	describe('error handling', () => {
		test('should throw when signing without initialization', () => {
			const km = new KeyManager();
			expect(() => km.sign('data')).toThrow('KeyManager not initialized');
		});

		test('should throw when getting public key without initialization', () => {
			const km = new KeyManager();
			expect(() => km.getPublicKeyHex()).toThrow('KeyManager not initialized');
		});
	});
});

import { determineKeyApprovalStatus } from '../../resources/shared/js/nfccards/crypto/keyApprovalStatus';

describe('determineKeyApprovalStatus', () => {
	it('is approved when local key, server key and approval exist', () => {
		expect(determineKeyApprovalStatus(true, true, '2026-01-01T00:00:00Z')).toBe('approved');
	});

	it('is pending when the server key exists but is not approved', () => {
		expect(determineKeyApprovalStatus(true, true, null)).toBe('pending');
	});

	it('is revoked when the local key exists but the server key is gone', () => {
		expect(determineKeyApprovalStatus(true, false, null)).toBe('revoked');
	});

	it('is none without a local key', () => {
		expect(determineKeyApprovalStatus(false, false, null)).toBe('none');
		expect(determineKeyApprovalStatus(false, true, '2026-01-01T00:00:00Z')).toBe('none');
	});
});

export type KeyApprovalStatus = 'none' | 'pending' | 'approved' | 'revoked';

/**
 * Decide the device key approval status from the device state.
 * Single source of truth for the POS boot sequence and the periodic
 * re-check in CardService.
 */
export function determineKeyApprovalStatus(
	hasLocalKey: boolean,
	hasServerKey: boolean,
	approvedAt: string | null
): KeyApprovalStatus {
	if (!hasLocalKey) {
		return 'none';
	}
	if (!hasServerKey) {
		return 'revoked';
	}
	if (approvedAt) {
		return 'approved';
	}
	return 'pending';
}

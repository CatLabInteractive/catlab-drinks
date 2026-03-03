export function isCardVersionSupported(cardVersion: number, minNfcVersion: number): boolean {
	return cardVersion >= minNfcVersion;
}

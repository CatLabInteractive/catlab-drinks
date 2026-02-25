# CatLab Drinks — Security White Paper

## Cashless Payment System for Events

### Version 1.0

---

## 1. Executive Summary

CatLab Drinks is a cashless point-of-sale (POS) system designed for events, festivals, and bars. It uses 
NFC-enabled cards (NTAG213) as physical payment tokens that store a digital balance. Multiple POS terminals 
operate concurrently, with support for offline operation when internet connectivity is unreliable.

This white paper describes the security architecture and measures taken to protect the integrity of the 
cashless payment ecosystem against fraud, replay attacks, and unauthorized modifications.

---

## 2. Threat Model

### 2.1 Primary Threats

| Threat | Description | Risk Level |
|--------|-------------|------------|
| **Balance forgery** | An attacker writes arbitrary credit to their NFC card | Critical |
| **Replay attack** | Copying signed data from one card to another | High |
| **Key interception** | An authenticated user extracts the signing key from the browser | High |
| **Rogue terminal** | An unauthorized device enters the ecosystem and signs cards | High |
| **Offline tampering** | Exploiting offline POS terminals to manipulate balances | Medium |
| **Write interruption** | Power loss during NFC write corrupts card data | Medium |

### 2.2 Previous Vulnerability (Version 0)

The original system used a single **shared symmetric HMAC-SHA256 key** across all POS terminals. This key 
was loaded client-side whenever any user logged into the management website, creating a critical vulnerability: 
any authenticated user could intercept the key and forge card balances.

---

## 3. Cryptographic Architecture (Version 1)

### 3.1 Asymmetric Key Model

Version 1 replaces the shared symmetric key with **per-device ECDSA P-192 key pairs**:

- **Each POS terminal** generates and stores its own unique **private key**
- **Public keys** are managed centrally at the organisation (tenant) level
- Terminals **sign** card data with their private key
- Other terminals **verify** signatures using approved public keys downloaded from the server

This eliminates the shared-secret vulnerability: no single key compromise affects the entire system.

### 3.2 Why ECDSA P-192?

| Criteria | ECDSA P-192 | ECDSA P-256 | HMAC-SHA256 |
|----------|-------------|-------------|-------------|
| Signature size | 48 bytes | 64 bytes | 32 bytes |
| Security level | 96-bit | 128-bit | 256-bit |
| Key type | Asymmetric | Asymmetric | Symmetric |
| Client-side exposure | Private key per-device | Private key per-device | Shared secret |

P-192 was chosen over P-256 to minimize signature size (48 vs 64 bytes), which is important given 
NTAG213's 144-byte memory constraint. The 96-bit security level is more than adequate for a drinks 
credit system where the economic incentive for attacks is low.

### 3.3 Key Storage

The private key is **AES-encrypted** using the device secret (provided by the server via the 
`GET /pos-api/v1/devices/current` API call) and stored in the browser's localStorage. This means:

1. The private key can only be decrypted after successful device authentication
2. The device secret never leaves the server unencrypted in the management API
3. If localStorage is cleared, the key pair is lost and a new one must be generated and approved

---

## 4. Card Data Integrity

### 4.1 Signature Scheme

The ECDSA P-192 signature covers:

```
signature = ECDSA_SIGN(
    SHA-256(version_byte + device_id + card_data + card_hardware_uid),
    device_private_key
)
```

**Fields included in the signature:**
- `version_byte` (1 byte): Prevents version downgrade attacks
- `device_id` (3 bytes): Identifies the signing terminal
- `card_data` (33 bytes): Balance, transaction count, timestamp, previous transactions, discount
- `card_hardware_uid` (variable): The card's unique hardware identifier

### 4.2 Replay Attack Prevention

The **card hardware UID** is included in the signed data but **not stored on the card** (it's read from 
the NFC hardware). This prevents an attacker from copying signed data from a high-balance card to a 
low-balance card — the signature verification will fail because the hardware UIDs differ.

### 4.3 Version Detection

Card data version is determined by the **first byte** of the payload:
- `0x01` → Version 1 (ECDSA asymmetric)
- Any other value → Version 0 (legacy HMAC-SHA256)

This is safe because:
- Positive balances under €167,772 have first byte `0x00`
- Negative balances have first byte `0xFF` (or similar high values)
- No legitimate balance produces `0x01` as the first byte

### 4.4 Unsigned Integer Fields

Version 1 uses **unsigned 32-bit integers** for:
- **Transaction count**: Will never be negative
- **Timestamp**: Extends Unix timestamp support beyond 2038 to 2106

Balance and previous transaction amounts remain signed (they can be negative for debits).

---

## 5. Key Management & Admin Workflow

### 5.1 Key Generation

Key generation is an **explicit manual action** — it is never automatic. The POS terminal shows a modal 
requiring the user to press "Generate Credentials". This ensures operators are aware that:
1. A new key pair will be created
2. The key requires administrator approval before the terminal can operate

### 5.2 Key Approval Flow

```
POS Terminal                        Server                         Admin Dashboard
     |                                |                                  |
     |-- Generate key pair locally -->|                                  |
     |-- Upload public key ---------->|                                  |
     |                                |-- Key in "Pending" state ------->|
     |                                |                                  |
     |                                |<--- Admin approves key ---------|
     |                                |                                  |
     |<-- Download approved keys -----|                                  |
     |                                |                                  |
     |   [Card operations now allowed]                                   |
```

**Key states:**
1. **No key**: Red 🔑 indicator — card operations blocked
2. **Pending**: Orange ⏳ indicator — card operations blocked, awaiting admin approval
3. **Approved**: Green indicator — card operations allowed

### 5.3 Key Revocation

Administrators can **instantly revoke** a key if a terminal is compromised. The admin dashboard clearly 
warns that revocation is a **destructive action** because:

- All cards last signed by that device will fail signature verification
- Affected cards must be re-scanned at an approved terminal to be re-signed

The admin dashboard shows:
- The number of cards affected by revocation
- A list of affected cards (clickable to view transaction history)
- A confirmation dialog with explicit warning text

### 5.4 Device Soft-Delete

Deleting a POS device **soft-deletes** it (preserving the public key record and signed card tracking). 
This ensures:
- Cards signed by the deleted device can still be verified by other terminals
- The admin can still view and manage the device's public key
- Transaction history for affected cards remains intact

### 5.5 Device ID Limits

Device IDs are stored as 3-byte unsigned integers in the card data (max value: 16,777,215). The API 
validates that newly created devices don't exceed this limit, preventing card data encoding failures.

---

## 6. Offline Operation

### 6.1 Transaction Splitting

Cards store the **last 5 previous transaction amounts** in the card data. When a POS terminal goes 
offline, these stored transactions allow the system to reconstruct missing transaction history when 
the terminal comes back online and the card is scanned at a connected terminal.

### 6.2 Transaction Merger

The `TransactionMerger` handles reconciliation of transactions received from different terminals in 
potentially different order. It:

1. Creates or merges transactions by their `card_sync_id` (sequential counter per card)
2. Uses database-level locking (`lockForUpdate`) to prevent race conditions
3. Maintains an "overflow" transaction to absorb any balance discrepancies
4. Handles out-of-order arrival gracefully

### 6.3 Card Data Upload

After every scan, the POS uploads the complete card state to the server. This allows the server to:
- Detect transactions that occurred at offline terminals
- Create "unknown" transaction records for later reconciliation
- Ensure the server-side balance stays synchronized

---

## 7. Physical Security

### 7.1 NFC Write Protection

The [nfc-socketio](https://github.com/catlab-drinks/nfc-socketio) service applies a **write password** 
to the NFC tags. The password is derived from the organisation secret and card UID. While NTAG213's 
4-byte password is insufficient for cryptographic security, it prevents accidental overwrites.

### 7.2 Write Failure Recovery

NFC write operations can be interrupted (e.g., card removed too quickly). The system handles this by:
- Storing the last known valid card state in the POS's localStorage
- Displaying a warning when a write fails
- Allowing the user to re-scan to recover

### 7.3 NTAG213 Memory Layout

| Component | Size |
|-----------|------|
| NTAG213 user memory | 144 bytes |
| TLV overhead | 3 bytes |
| Max NDEF message | 141 bytes |
| URI record (topup URL) | ~37 bytes |
| External record (v1 data) | ~104 bytes |

---

## 8. API Security

### 8.1 Authentication

| API | Auth Method | Description |
|-----|-------------|-------------|
| Management API (`/api/v1/`) | OAuth 2.0 | For admin dashboard operations |
| Device API (`/pos-api/v1/`) | Device access token | For POS terminal operations |

### 8.2 Device Authentication Flow

1. Admin creates a connect request (token + QR code)
2. Device scans QR or enters URL → `POST /api/v1/device-connect`
3. Device displays pairing code → admin enters via `POST /device-connect-requests/{token}/pair`
4. Device receives access token → stored in localStorage
5. On 401 response, all credentials are cleared and the device returns to the pairing screen

### 8.3 Public Key Distribution

POS devices download approved public keys via `GET /pos-api/v1/organisations/{id}/approved-public-keys`. 
This endpoint:
- Requires device authentication
- Returns only approved keys (pending keys are excluded)
- Uses the Charon `DevicePublicKeyResourceDefinition` for consistent API output

---

## 9. Monitoring & Audit

### 9.1 Admin Dashboard

The admin dashboard provides:
- **Devices page**: All registered terminals with public key status, online status, signed cards count
- **Public Keys page**: All public keys (including from deleted devices) with approve/revoke actions
- **Transactions page**: Full transaction history, filterable by card and organisation
- **Signed cards**: Per-device list of cards last signed by that terminal

### 9.2 Card Tracking

Each card records `last_signing_device_id` — the ID of the last POS terminal that wrote to the card. 
This enables:
- Tracing which terminal last interacted with a card
- Assessing the impact of a key revocation
- Auditing terminal-to-card relationships

---

## 10. Migration Strategy

The system supports **seamless rolling migration** from v0 to v1:

1. **Reading**: POS checks the first byte to determine format. Both v0 (HMAC) and v1 (ECDSA) are supported.
2. **Writing**: All writes use v1 format, regardless of the version read. Cards upgrade transparently on next scan.
3. **Backward compatibility**: As long as the organisation's HMAC key is configured, legacy v0 cards can be read and automatically upgraded.

---

## 11. Additional Threat Mitigations

### 11.1 Monotonic Transaction Counter

Each card maintains a `transaction_count` that increments with every operation. The POS detects **counter regression** 
(current count lower than the last seen count) as a corruption indicator. This prevents an attacker from rolling 
back a card to a previous higher-balance state — the stale counter will be detected and the card flagged as corrupt.

### 11.2 Server-Side Balance Reconciliation

After every card scan, the POS uploads the complete card state to the server. The server maintains an independent 
record of all transactions and expected balances. Discrepancies between the card's reported balance and the server's 
calculated balance are recorded as "overflow" transactions, creating an audit trail of any unexplained balance changes.

Administrators should periodically review overflow transactions for anomalies — a pattern of positive overflows 
for a specific card could indicate attempted fraud.

### 11.3 Insufficient Funds Enforcement

The POS enforces a **balance check before every sale**. A card cannot be debited below zero (unless remote orders 
have been placed while the terminal was offline). This client-side enforcement, combined with the ECDSA signature, 
prevents attackers from writing negative balances to generate unbounded credits.

### 11.4 Key Rotation Recommendations

While ECDSA P-192 keys do not have a natural expiration, organisations should consider:
- **Periodic key rotation** (e.g., annually) by generating new key pairs on terminals
- **Event-based rotation** — generate fresh keys for each new event/festival
- **Revocation of unused keys** — revoke keys from terminals that haven't been active for an extended period

The admin dashboard shows the `last_activity` timestamp for each device to facilitate this.

### 11.5 Rate Limiting & Key Registration Controls

- Only **registered, known devices** can submit public keys for approval
- Key registration requires prior device pairing (QR code + pairing code flow)
- The admin must explicitly approve each key — no auto-approval mechanism exists
- If a device submits a new public key, the previous key's `approved_at` is cleared, forcing re-approval

### 11.6 Audit Trail

All key management operations are recorded with timestamps and actor information:
- `approved_at` and `approved_by` — who approved a key and when
- `last_signing_device_id` on cards — which device last wrote to each card
- `last_activity` on devices — when the device was last seen
- Device soft-deletes preserve the complete history even after terminal decommissioning

### 11.7 Version Downgrade Prevention

The version byte (`0x01`) is included in the ECDSA signature. This prevents an attacker from:
- Stripping the v1 signature and replacing it with a forged v0 HMAC signature
- Modifying the version byte to bypass asymmetric verification

Once all legacy v0 cards have been migrated, organisations can disable v0 support entirely by removing 
the HMAC key from their configuration.

### 11.8 Defence in Depth

The system employs multiple layers of security that must all be defeated for a successful attack:

1. **NFC write password** — prevents casual overwrites (low security, 4 bytes)
2. **ECDSA signature** — prevents balance forgery without the private key (high security, 96-bit)
3. **Hardware UID binding** — prevents cross-card replay attacks
4. **Admin key approval** — prevents rogue terminal injection
5. **Server reconciliation** — detects anomalies after the fact
6. **Monotonic counter** — detects rollback/replay of stale card states

### 11.9 Future Considerations

Additional measures that could further strengthen the system:

- **Transaction velocity monitoring**: Alerting on cards with unusually rapid or large transactions
- **Anomaly detection**: ML-based detection of unusual card usage patterns (e.g., same card at 
  multiple distant terminals simultaneously)
- **Key fingerprint verification**: Displaying key fingerprints in the admin dashboard for 
  out-of-band verification during approval
- **Certificate pinning**: Pinning the server's TLS certificate in the POS app to prevent 
  MITM attacks on key distribution
- **Secure enclave integration**: Using hardware security modules (HSM) or browser WebAuthn 
  for private key storage where available
- **Expiring signatures**: Including a validity window in the signed data so cards must be 
  re-scanned periodically

---

## 12. Summary of Security Measures

| Measure | Protection Against |
|---------|-------------------|
| Per-device ECDSA key pairs | Key interception, shared secret compromise |
| Card hardware UID in signature | Replay attacks across cards |
| Admin key approval | Rogue terminals |
| Key revocation with impact tracking | Compromised terminals |
| Device soft-delete | Loss of audit trail |
| Monotonic transaction counter | Card state rollback/replay |
| Server-side balance reconciliation | Undetected balance manipulation |
| Insufficient funds enforcement | Negative balance exploitation |
| Version byte in signature | Version downgrade attacks |
| 3-byte device ID validation | Card data encoding failures |
| Unsigned timestamp | Year 2038 overflow |
| NFC space validation | Data truncation |
| NFC write password | Accidental overwrites |
| Write failure recovery | Data loss from interrupted writes |
| Transaction merger with locking | Race conditions, offline synchronization |
| 5 previous transactions | Offline transaction recovery |
| New key clears approval | Unauthorized key swap |

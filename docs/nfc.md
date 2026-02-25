NFC topup cards
===============
The goal of the NFC topup cards is to sell digital topup cards that can be used to purchase drinks at the bar 
and to allow people to order drinks from the remote app. Remotely ordered drinks should be paid automatically, 
so waiters don't have to walk around with NFC readers.

NTAG213
-------
Currently only NTAG213 is supported, but since the implementation is handled in 
the [nfc-socketio](https://github.com/catlab-drinks/nfc-socketio) package it is fairly trivial 
to support other cards as well.

Security
--------
Each organisation in the project MUST have a unique secret that is used for all NFC related actions.

Starting with card data version 1, the system uses **asymmetric encryption (ECDSA P-192)** instead of 
the shared symmetric key. Each POS terminal generates its own unique key pair, and uses its private key 
to sign card data. Other terminals verify signatures using approved public keys from the server.

Password
--------
The [nfc-socketio](https://github.com/catlab-drinks/nfc-socketio) service SHOULD password protect writing data to
the NFC tags. This card specific password is calculated based on the organisation secret key and the card uuid. 
Since the length of this password is limited (to 4 bytes in NGAT213), this security measure is more aimed towards 
usability (preventing accidental overwrite of the card) than security.

NDEF messages
-------------
CatLab Drinks writes 2 NDEF records to the tag.
- The first record is an uriRecord that links to a card specific topup page where users MAY be able to topup their 
card via online payment gateway.
- The second record contains a signed bytestring containing the balance of the card.

Card Data Versioning
--------------------
The second NDEF record (balance data) uses a versioned format. The version is determined by the first byte:

### Version 0 (Legacy)

The original format, still supported for reading. Uses HMAC-SHA256 with the organisation's shared symmetric key.

| Field | Size | Description |
|---|---|---|
| Balance | 4 bytes | Current card balance (32-bit signed integer) |
| Transaction Count | 4 bytes | Number of transactions (32-bit signed integer) |
| Timestamp | 4 bytes | Unix timestamp of last transaction (32-bit signed integer) |
| Previous Transactions | 20 bytes | Last 5 transaction amounts (5 × 4 bytes, 32-bit signed integers) |
| Discount Percentage | 1 byte | Discount percentage (0-100) |
| HMAC-SHA256 Signature | 32 bytes | HMAC-SHA256 signature of the payload using org secret |

**Total payload: 65 bytes**

**Version detection:** The first byte of v0 data is the high byte of the balance (32-bit signed integer).
For positive balances under 16M cents (€167,772), the first byte is `0x00`. For negative balances, the 
first byte is `0xFF` or similar. Only `0x01` is treated as version 1; any other value indicates version 0.

### Version 1 (Asymmetric)

The new format using per-device ECDSA P-192 asymmetric keys. All new writes use this format regardless of 
the version that was read. Fields are aligned to 4-byte boundaries where possible for efficient NFC page writes.

| Field | Size | Description |
|---|---|---|
| Version Header | 1 byte | Always `0x01` |
| Signer Device ID | 3 bytes | Unsigned device ID (24-bit big-endian, max 16,777,215) |
| Balance | 4 bytes | Current card balance (32-bit signed integer) |
| Transaction Count | 4 bytes | Number of transactions (32-bit unsigned integer) |
| Timestamp | 4 bytes | Unix timestamp of last transaction (32-bit unsigned integer, 2038-proof) |
| Previous Transactions | 20 bytes | Last 5 transaction amounts (5 × 4 bytes, 32-bit signed integers) |
| Discount Percentage | 1 byte | Discount percentage (0-100) |
| ECDSA Signature | 48 bytes | ECDSA P-192 signature (r: 24 bytes, s: 24 bytes) |

**Total payload: 85 bytes** (fits NTAG213's 144-byte limit with NDEF overhead + topup URL)

**Signature covers:** `version_header + device_id + card_data_payload + card_uid`  
The card UID is included in the signed data but NOT stored on the card (it's the card's hardware identifier), 
preventing signature replay attacks across different cards.

### NTAG213 Space Budget

NTAG213 provides 144 bytes of user memory. The NDEF message (URI record + external data record) plus 
TLV wrapper overhead (3 bytes) must fit within this limit:

- Max NDEF message size: **141 bytes**
- External record (85-byte payload + 19 bytes overhead): **104 bytes**
- Available for URI record: **37 bytes** (= 141 - 104)
- URI record overhead: **5 bytes** (header + type + prefix byte)
- Max topup URL content (domain + "/" + uid): **32 characters**

With the default domain `d.ctlb.eu` (9 chars), card UIDs up to **22 characters** are supported.
The POS validates this at runtime and shows an error if the topup URL is too long.

Key Management
--------------
### Key Generation
Key generation is a manual, explicit action. When a POS terminal first opens the NFC card component,
a modal is shown prompting the user to press "Generate Credentials". The generated ECDSA P-192 private key 
is encrypted with the device secret (provided by the server via `GET /pos-api/v1/devices/current`) using 
AES and stored in the browser's localStorage.

### NFC Status Indicator
The NFC status label in the toolbar uses colors to indicate the key status:
- **Red** 🔑: No credentials generated — card operations blocked
- **Orange** ⏳: Credentials generated, pending admin approval — card operations blocked
- **Green**: Credentials approved — card operations allowed

### Key Registration Flow
1. User manually triggers "Generate Credentials" on the POS terminal
2. POS generates key pair and stores encrypted private key locally
3. POS uploads its public key via `PUT /pos-api/v1/devices/current`
4. Public key enters "Pending" state on the server
5. Organisation admin reviews and approves the key in the admin dashboard
6. Once approved, the public key is distributed to all POS terminals via 
   `GET /pos-api/v1/organisations/{id}/approved-public-keys`

### Key Revocation
Admins can revoke a key if a terminal is compromised. **WARNING:** Revoking a key invalidates all cards 
that were last signed by that device. The admin dashboard shows the number of affected cards before confirmation.

### Device Soft-Delete
Deleting a device soft-deletes it (preserving the public key record). This ensures cards signed by the deleted 
device can still be tracked. The admin dashboard shows deleted devices with a "Deleted" badge.

Migration Strategy
------------------
The system supports seamless rolling migration as users interact with POS terminals:

**Reading:** The POS checks the first byte:
- If `0x01` → Version 1 (Asymmetric): Verify using the signer device's approved public key
- Anything else → Version 0 (Legacy): Decrypt using the old symmetric key (HMAC-SHA256)

**Writing:** Regardless of how a card was read, all new writes use Version 1 format with the POS 
terminal's own private key. This ensures gradual migration as cards are scanned.

Both NDEF messages are publicly readable; the password only write-protects the sectors, otherwise the 
first record would not be readable to phones and the topup link wouldn't work.

Mirroring
---------
One of my main concerns was writing to the tags. Writes can be interrupted at any time, and since the data I’m 
writing is rather long there isn’t any tear-protection available. I briefly thought about writing the balance 
data twice, so that there is always one record to recover from, but the space limitations of NTAG213 finally 
forced me to abandon the idea.

In the end I just went for storing the latest known (valid) data in the browser localstorage of the POS terminal, 
and throwing a big warning message whenever a write fails. This way, a user that presents their card and 
interrupts the write, will be asked to scan its card again. If a user would at this point walk away, 
he would end up with an invalid card. It will be up to my UX design to make sure that the bartenders 
handle this situation correctly and ask the user to scan their card again.

I also improved the NFC nodejs service to only write data that has changed since the last write, 
lowering the risk of tearing. Since the first NDEF message (with the topup url) will always stay 
the same, there is no reason to write that on every transaction.

Remote topups & remote orders
-----------------------------
Since we want our system to work offline, the NFC card is the single source of truth for the cards balance. 
Remote topups & remote orders cause some trouble since these actions won't change the data that is on the nfc card.

That means that, every time an NFC card is scanned, our system first needs to check if there are any pending transactions 
that are not applied to the card yet. In our online database we keep a list of all transactions that have not been 
synced to the card yet, and when these are applied to the card we update our records to make sure each transaction is 
only applied once.

After each scan we also upload the complete card data to the server so that it can fill in any unknown transactions 
unknown to the system. This might occur when one of the bars goes offline for a significant amount of time, in these cases
'unknown' transactions will be created in the database, that are then merged with the known transactions once the offline 
bar goes online again.

Note that offline bars might cause unexpected situations where cards that were topped up remotely don't show the 
new balance yet (as the bar doesn't know about the topup). In those cases the client will need to go to a bar that is 
online in order for the topup to be applied.

In case remote orders are also possible, the above situation might lead to negative balances on cards. That's why, with 
remote orders available, it is much more important to make sure that all bars have a connection to the system at all times. 
In those cases setting up a local network server that runs the CatLab Drinks software, might be desirable.

Remote orders
-------------
While the cards UID is enough for a topup, it is not random enough to be reliable for remote orders. Therefor, for each 
card known to the system, an `order token` is generated for each card. This order token can then be injected into a third
party system that allows users to order drinks straight from their table.

Alternatively, a card MAY also be assigned `aliases`, which is an external identifier from a separate application that is
used to link the cards owner to the card. When this external application loads the order page with this alias as `card` 
query parameter, the order will be paid from that cards' balance. (For example, orders made through 
`http://drinks.catlab.eu/order/iYkyGWx4grX6HLY9HqmnUNo4Pseavubi?card=abcdef` will be charged to card with alias `abcdef`).

NFC topup cards
===============

NTAG213
-------
Currently only NTAG213 is supported, but since the implementation is handled in 
the [nfc-socketio](https://github.com/catlab-drinks/nfc-socketio) package it is fairly trivial 
to support other cards as well.

Security
--------
Each organisation in the project MUST have a unique secret that is used for all NFC related actions.

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

This bytestring contains:
- current card balance
- transaction count
- timestamp last transaction (unix timestamp)
- last 5 transaction amounts

All data is stored in 32bit signed integers. The bytestring is then signed using HmacSHA256 with 
the organisation specific secret key.

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


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

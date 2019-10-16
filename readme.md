CatLab Drinks app
=================
Simple cash register webapp with support for NFC topup cards.

Online at [http://drinks.catlab.eu](http://drinks.catlab.eu).

Todo
----
- Currently the project implements a vendor specific single sign on system which
needs to be removed and replaced by the default Laravel authentication. So yea, that will happen one day.

- Also there is no documentation so... yea... that.

- Also the project is half english and half dutch and was, so translations and nationalization should be solved as well.

NFC cashless topup
-----------------
In order to use the NFC topup system you need to connect an acr122u card reader and install a [specific service](https://github.com/catlab-drinks/nfc-socketio) 
to handle the communication with the card reader.

Why is this here?
-----------------
I know it's a bit early to release this, but hopefully it will improve over time.

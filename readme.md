CatLab Drinks app
=================
Simple cash register webapp with support for NFC topup cards / cashless topup payments.

Online at [http://drinks.catlab.eu](http://drinks.catlab.eu).

Documentation
-------------
We have a tiny bit of documentation [in the docs folder](https://github.com/catlab-drinks/catlab-drinks/tree/master/docs).
But don't expect too much from us.

Setup
-----
Run ```composer install``` to download all required php libraries. Copy ```.env.example``` to ```.env``` and fill in the database
credentials. Finally, run ```php artisan migrate``` to initialize the database.

Run ```npm install``` to install all dependencies and then run ```npm run production``` to compile the resources.

You should now be able to register an account on the website.

Deploy scripts
--------------
There are two buildscripts in /build that you might want to use to deploy on production servers.

We run ```prepare.sh``` on our buildserver, then push the whole project over sftp and finally run ```upgrade.sh``` on 
the production server. There are cleaner ways to handle deploys, so feel free to use your own system.

Todo
----
- There is hardly any documentation.
- Also the project is half english and half dutch and was, so translations and nationalization should be solved as well.
- Add tests. This project has NOT enjoyed test driven development.

NFC cashless topup
-----------------
In order to use the NFC topup system you need to connect an acr122u card reader and install a [specific service](https://github.com/catlab-drinks/nfc-socketio) 
to handle the communication with the card reader.

Why is this here?
-----------------
I know it's a bit early to release this, but hopefully it will improve over time.

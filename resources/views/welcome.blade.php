<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>CatLab Drinks</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet" type="text/css">

        <link href="{{ asset('css/app.css') }}" rel="stylesheet">

        @include('blocks.favicon')

    @if(config('services.gtm'))
        <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','{{ config('services.gtm') }}');</script>
            <!-- End Google Tag Manager -->
        @endif
    </head>
    <body>

    @if(config('services.gtm'))
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm') }}"
                          height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

    <header>
        <div class="navbar navbar-dark bg-dark shadow-sm">
            <div class="container d-flex justify-content-between">
                <a href="#" class="navbar-brand d-flex align-items-center">
                    <strong>CatLab Drinks</strong>
                </a>
            </div>
        </div>
    </header>

    <div class="container">

        <div class="row">

            <div class="col-md-12">

                <br />
                <h1>CatLab Drinks</h1>
                <p>
                    Simple open source cash register system for events / quizzes / parties. Source code available at
                    <a href="https://github.com/catlab-drinks/catlab-drinks">GitHub</a>.
                </p>

            </div>

        </div>

        <div class="row">
            <div class="col-md-12">

                <h2>What is CatLab Drinks?</h2>

                <h3>Goal</h3>
                <p>
                    CatLab Drinks aims to be an open source cash register system for small events. The project tries to
                    provide a solution for:
                </p>

                <ul>
                    <li>Avoid calculation errors with vouchers / payments / order totals</li>
                    <li>Keep track of all sales & locations</li>
                    <li>Allow attendeeds to place orders from their own smartphone</li>
                    <li>Optionally, allow the user of NFC topup cards (NFC NTAG 213 tags)</li>
                    <li>All systems should stay opperational even in offline scenarios</li>

                </ul>

                <div class="alert alert-warning">
                    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
                    BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
                    NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
                    DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
                    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
                </div>

                <p>
                    <a href="{{ action('ClientController@manage') }}" class="btn btn-primary">Open web app</a>
					<a href="https://play.google.com/store/apps/details?id=eu.catlab.drinks" class="btn btn-primary">Install Android app</a>
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <h2>Screenshots</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6"><img class="img-fluid" src="/images/screenshot1.png" /></div>
            <div class="col-md-6"><img class="img-fluid" src="/images/screenshot2.png" /></div>
        </div>

        <div class="row">
            <div class="col-md-12">

                <h3>Background</h3>
                <p>
                    CatLab Drinks is developed for use during the quiz events of <a href="https://www.quizfabriek.be">De Quizfabriek</a>.
                    At these quizzes all attending teams must bring a tablet or smartphone to answer the quiz questions.
                    Using CatLab Drinks we have given the attendees the ability to send drink orders straight to the bar.
                    By giving each quiz team their own NFC topup card, and linking that card to the team in our internal
                    system, these orders are paid immediately and no money has to change hands. If a team prefers to order
                    drinks at the bar, he can still use the NFC topup card to pay for his drinks at the bar.
                </p>

                <h2>Setup</h2>
                <h3>Create an account</h3>
                <p>
                    After you've created an account you arrive at the <code>events</code> overview. Each event has its own
                    price list (<code>menu</code>) which you can change by clicking the <code>menu</code> icon. When you're done with
                    setting the correct prices for each item, you can op the <code>Bar HQ</code> by clicking on the
                    event name. This screen should be used at the bars. At this screen you can <code>open</code> or <code>close</code>
                    remote orders by clicking the status button. (It might be handy to close down remote orders
                    during very busy times.)
                </p>

                <h3>Digital NFC topup cards</h3>
                <p>
                    CatLab drinks implements a closed loop RFIC/NFC payment system.
                </p>

                <p>
                    To use the NFC topup cards you will need an acr122u card reader for each bar or cash register
                    (other readers might work too, but have not been tested) and for each team / attendee you'll need an
                    NTAG213 NFC chip. Communication between the card reader and the browser happens over a socket.io
                    connection, so you will need to <a href="https://github.com/catlab-drinks/nfc-socketio">install a separate service</a>
                    for that to work. We use a Raspberry Pi, but you can also run the service on a laptop.
                </p>

            </div>

        </div>

    </div>

    </body>
</html>

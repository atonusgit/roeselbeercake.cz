<?php
/**
 * Shortcodes
 * 
 * @author Anton Valle
 */
function stripe_checkout() {

    if ( strpos( $_SERVER['REQUEST_URI'], '/purchase/' ) !== false ) {

        if ( isset( $_GET["p"] ) ) {

            $stripe_pk = isset( $_GET['test'] ) ? getenv( 'STRIPE_PK_TEST' ) : getenv( 'STRIPE_PK_LIVE' );
            $testmode_alert = isset( $_GET['test'] ) ? "<div class='alert alert-warning' style='display: inline-block'>TEST MODE</div>" : "";

            return '
                <!-- Load Stripe.js on your website. -->
                <script src="https://js.stripe.com/v3"></script>' .

                $testmode_alert .

                '<!-- Create a button that your customers click to complete their purchase. Customize the styling to suit your branding. -->
                <button class="btn btn-success" id="checkout-button-' . $_GET["p"] . '" role="link" style="font-family: \'Arial\', Arial;">
                    Zaplatit
                </button>
                <p class="small">Powered by <a href="https://stripe.com/" target="_blank">Stripe</a></p>

                <div id="error-message"></div>

                <script>
                    (function() {
                    var stripe = Stripe("' . $stripe_pk . '");

                    var checkoutButton = document.getElementById("checkout-button-' . $_GET["p"] . '");
                    checkoutButton.addEventListener("click", function () {
                        // When the customer clicks on the button, redirect
                        // them to Checkout.
                        stripe.redirectToCheckout({
                        items: [{sku: "' . $_GET["p"] . '", quantity: 1}],

                        // Do not rely on the redirect to the successUrl for fulfilling
                        // purchases, customers may not always reach the success_url after
                        // a successful payment.
                        // Instead use one of the strategies described in
                        // https://stripe.com/docs/payments/checkout/fulfillment
                        successUrl: "' . site_url() . '/purchase-success",
                        cancelUrl: "' . site_url() . '/purchase-canceled",
                        })
                        .then(function (result) {
                        if (result.error) {
                            // If `redirectToCheckout` fails due to a browser or network
                            // error, display the localized error message to your customer.
                            var displayError = document.getElementById("error-message");
                            displayError.textContent = result.error.message;
                        }
                        });
                    });
                    })();
                </script>';

        } else {

            return '<button class="btn btn-secondary" style="font-family: \'Arial\', Arial;" disabled>Chybný link pro nákup, prosím kontaktujte nás</button>';

        }

    }

}

add_shortcode( 'stripe_checkout', 'stripe_checkout' );

function stripe_payment_intent() {

    session_start();

    if ( strpos( $_SERVER['REQUEST_URI'], '/pay/' ) !== false ) {

        if ( isset( $_GET["czk"] )
            && is_numeric( $_GET["czk"] )
            && $_GET['czk'] > 50
            && $_GET['czk'] < 99999999 ) {

            $stripe_sk = isset( $_GET['test'] ) ? getenv( 'STRIPE_SK_TEST' ) : getenv( 'STRIPE_SK_LIVE' );
            $testmode_param = isset( $_GET['test'] ) ? "/?test=1" : "";

            \Stripe\Stripe::setApiKey( $stripe_sk );
            
            $_SESSION['payment_intent'] = \Stripe\PaymentIntent::create( [
                'amount' => $_GET["czk"],
                'currency' => 'czk',
                // Verify your integration in this guide by including this parameter
                'metadata' => ['integration_check' => 'accept_a_payment'],
            ] );

            $url = add_query_arg( '_vs', wp_create_nonce( '_verifysession' ), site_url() . '/confirm-payment' . $testmode_param );
            header( 'location: ' . $url );
            exit;

        } else {

            return '<button class="btn btn-secondary" style="font-family: \'Arial\', Arial;" disabled>Chybějící nebo chybné platební údaje</button>';

        }

    }

}

add_shortcode( 'stripe_payment_intent', 'stripe_payment_intent' );

function stripe_payment() {

    session_start();

    if ( strpos( $_SERVER['REQUEST_URI'], '/confirm-payment/' ) !== false ) {

        if ( wp_verify_nonce( $_GET['_vs'], '_verifysession' )
            && isset( $_SESSION['payment_intent'] ) ) {

            $stripe_pk = isset( $_GET['test'] ) ? getenv( 'STRIPE_PK_TEST' ) : getenv( 'STRIPE_PK_LIVE' );
            $testmode_alert = isset( $_GET['test'] ) ? "<div class='alert alert-warning' style='display: inline-block'>TEST MODE</div>" : "";

            return '
                <script src="https://js.stripe.com/v3/"></script>
                <script>
                    var stripe = Stripe("' .  $stripe_pk . '");
                    var elements = stripe.elements();
                </script>
                <style>
                    /**
                     * The CSS shown here will not be introduced in the Quickstart guide, but shows
                     * how you can use CSS to style your Element\'s container.
                     */
                    .StripeElement {
                    box-sizing: border-box;
                    
                    height: 40px;
                    
                    padding: 10px 12px;
                    
                    border: 1px solid transparent;
                    border-radius: 4px;
                    background-color: white;
                    
                    box-shadow: 0 1px 3px 0 #e6ebf1;
                    -webkit-transition: box-shadow 150ms ease;
                    transition: box-shadow 150ms ease;
                    }
                    
                    .StripeElement--focus {
                    box-shadow: 0 1px 3px 0 #cfd7df;
                    }
                    
                    .StripeElement--invalid {
                    border-color: #fa755a;
                    }
                    
                    .StripeElement--webkit-autofill {
                    background-color: #fefde5 !important;
                    }
                </style>
                <form id="payment-form">' .

                    $testmode_alert .

                    '<div class="container-fluid px-0">
                        <div class="row">
                            <div class="col-md-10">
                                <div id="card-element" class="form-group">
                                    <!-- Elements will create input elements here -->
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-10">' .
                        
                            '<!-- We\'ll put the error messages in this element -->
                            <div id="card-errors" class="form-group" role="alert"></div>' .
                        
                            '<div id="submit-area">
                                <button id="submit" class="btn btn-block btn-success form-group" style="font-family: \'Arial\', Arial;">Zaplatit ' . $_SESSION['payment_intent']->amount / 100 . ' Kč' . ' </button>
                                <p class="small text-right">Powered by <a href="https://stripe.com/" target="_blank">Stripe</a></p>
                            </div>
                        </div>
                    </div>
                </form>
                <script>
                    jQuery( document ).ready( function( $ ) {' .
                
                        '// Set up Stripe.js and Elements to use in checkout form
                        var style = {
                            base: {
                            color: "#303238",
                            fontSize: "16px",
                            fontFamily: \'"Open Sans", sans-serif\',
                            fontSmoothing: "antialiased",
                            "::placeholder": {
                                color: "#CFD7DF",
                            },
                            },
                            invalid: {
                            color: "#e5424d",
                            ":focus": {
                                color: "#303238",
                            },
                            },
                        };' .
                        
                        'var card = elements.create("card", { style: style });
                        card.mount("#card-element");' .

                        'card.addEventListener(\'change\', function(event) {
                            var displayError = document.getElementById(\'card-errors\');
                            if (event.error) {
                                displayError.textContent = event.error.message;
                            } else {
                                displayError.textContent = \'\';
                            }
                        });' .

                        'var form = document.getElementById(\'payment-form\');' .

                        'form.addEventListener(\'submit\', function(ev) {
                            let previousButtonValue = $( "#submit-area" ).html();
                            $( "#submit-area" ).html( "\
                                <lottie-player\
                                    autoplay\
                                    control=\'false\'\
                                    loop\
                                    mode=\'normal\'\
                                    src=\'' . OCEANWP_THEME_URI . '/assets/img/lf20_mBK8iA.json\'\
                                    style=\'width: 50px; height: 20px\'>\
                                </lottie-player>" );
                            ev.preventDefault();
                            stripe.confirmCardPayment("' . $_SESSION['payment_intent']->client_secret . '", {
                                payment_method: {
                                    card: card,
                                    billing_details: {
                                    //name: \'Jenny Rosen\'
                                    }
                                }
                            }).then(function(result) {
                            if (result.error) {
                                // Show error to your customer (e.g., insufficient funds)
                                alert( result.error.message );
                                $( "#submit-area" ).html( previousButtonValue )
                            } else {
                                // The payment has been processed!
                                if (result.paymentIntent.status === \'succeeded\') {
                                    // Show a success message to your customer
                                    // There\'s a risk of the customer closing the window before callback
                                    // execution. Set up a webhook or plugin to listen for the
                                    // payment_intent.succeeded event that handles any business critical
                                    // post-payment actions.
                                    window.location.href = "' . site_url() . '/payment-success";
                                }
                            }
                            });
                        });' .
                
                    '} );
                </script>';

        } else {

            return '<button class="btn btn-secondary" style="font-family: \'Arial\', Arial;" disabled>Chybějící nebo chybné platební údaje</button>';
    
        }

    }

}

add_shortcode( 'stripe_payment', 'stripe_payment' );

function redirect_to_home_page() {

    return '
        <script>
            window.setTimeout( () => {
                window.location.href = "' . site_url() . '";
            }, 10000 );
        </script>';

}

add_shortcode( 'redirect_to_home_page', 'redirect_to_home_page' );
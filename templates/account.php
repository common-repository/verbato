<?php
    /* Template Name: Verbato account */
    if ( ! defined( 'ABSPATH' ) ) exit; // Don't allow direct access
    $verbato_project   = get_option( 'verbato_project', '');
    $api_key           = $verbato_project->api_key;
    $project_unique_id = $verbato_project->project_unique_id;
    $api_url           = get_option( 'verbato_project_api_url' );

    function verbato_getProject($api_url, $project_unique_id, $api_key) {
        $projectGet = wp_remote_get("$api_url/sofa/$project_unique_id", array(
            'headers' => array(
               'Content-Type' => 'application/json',
               'apikey' => $api_key,
               'API' => '2'
            ),
            'timeout' => 15
        ));
     
        $responseBodyProjectGet = json_decode(wp_remote_retrieve_body( $projectGet ));

        if(property_exists($responseBodyProjectGet, 'error')) {
             echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseBodyProjectGet->message) . '</p>
                  </div>';
         } else {
             update_option("verbato_project", $responseBodyProjectGet);
         }
    }

    verbato_getProject($api_url, $project_unique_id, $api_key);
    $verbato_project = get_option( 'verbato_project', '');
    $cards =  $verbato_project -> cards;
    if(isset($_POST['save'])) {
        $responseUpdate = wp_remote_request( "$api_url/sofa/$project_unique_id", array(
            'method' => 'PUT',
            'body' => wp_json_encode([
                 'plan_amount'      => sanitize_text_field($_POST['refill-amount']),
                 'refill_threshold' => sanitize_text_field($_POST['refill-threshold']),
             ]),
            'headers' => array(
               'Content-Type' => 'application/json',
               'apikey'       => $api_key,
               'API'          => '2'
            ),
            'timeout' => 15
        ));

        $responseBodyUpdate = json_decode(wp_remote_retrieve_body( $responseUpdate ));

        if(property_exists($responseBodyUpdate, 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseBodyUpdate->message) . '</p>
                  </div>';
        } else {
            update_option("verbato_project", $responseBodyUpdate);
            $verbato_project = get_option( 'verbato_project', '');
        }
    }

    if(isset($_POST['delete-card'])) {
        $responseDelete = wp_remote_request( "$api_url/sofa/$project_unique_id/card", array(
            'method' => 'DELETE',
            'body' => wp_json_encode([
                'id' => sanitize_text_field($_POST['deleted-card-id']),
            ]),
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey'       => $api_key,
                'API'          => '2'
            ),
            'timeout' => 15
        ));
        $status_code = wp_remote_retrieve_response_code($responseDelete);

        if($status_code == 200) {
            verbato_getProject($api_url, $project_unique_id, $api_key);
            $verbato_project = get_option( 'verbato_project', '');
            $cards =  $verbato_project -> cards;
        } else {
            echo '<div class="notice error verbato-notice is-dismissible" >
                  <p>' . esc_html($status_code) . '</p>
            </div>';
        }
    }

    if(isset($_POST['invoice'])) {
        $responseUpdate = wp_remote_request( "$api_url/sofa/$project_unique_id", array(
            'method' => 'PUT',
            'body' => wp_json_encode([
                'plan_amount'      => sanitize_text_field($_POST['refill-amount']),
                'refill_threshold' => sanitize_text_field($_POST['refill-threshold']),
            ]),
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey'       => $api_key,
                'API'          => '2'
            ),
            'timeout' => 15
        ));

        $responseBodyUpdate = json_decode(wp_remote_retrieve_body( $responseUpdate ));

        if(property_exists($responseBodyUpdate, 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseBodyUpdate->message) . '</p>
                  </div>';
        } else {
            update_option("verbato_project", $responseBodyUpdate);
            $verbato_project = get_option( 'verbato_project', '');

            // Invoice request
            $responseInvoice = wp_remote_post( "$api_url/sofa/$project_unique_id/invoice", array(
                'timeout' => 45,
                'headers' => array(
                   'Content-Type' => 'application/json',
                   'apikey'       => $api_key,
                   'API'          => '2'
                ),
                'body' => wp_json_encode([
                   'save_token' => true,
                ]),
            ));

            $responseBodyInvoice = json_decode(wp_remote_retrieve_body( $responseInvoice ));
            if(property_exists($responseBodyInvoice, 'error')) {
                echo '<div class="notice error verbato-notice is-dismissible" >
                          <p>' . esc_html($responseBodyInvoice->message) . '</p>
                      </div>';
            } else {
                $subscribe_url = str_replace(array("&#038;","&amp;"), "&", esc_url($responseBodyInvoice->payment_url));

                echo '<script>window.location.replace("' . $subscribe_url .'");</script>';
            }
        }
    }

    $default_plan_amount = $verbato_project -> plan_amount;
    $default_refill_threshold = $verbato_project -> refill_threshold;


    $responseProducts = wp_remote_get( $api_url . "/sofa" . "/" . $project_unique_id . "/products", array(
        'headers' => array(
           'apikey' => $api_key,
           'API'    => '2'
        ),
        'timeout' => 15
    ));
    $responseProductsBody = json_decode(wp_remote_retrieve_body( $responseProducts ));

    if(is_object($responseProductsBody) && property_exists($responseProductsBody, 'error')) {
        $products = [];
        echo '<div class="notice error verbato-notice is-dismissible" >
                  <p>' . esc_html($responseProductsBody->message) . '</p>
              </div>';
    } else {
        $products = $responseProductsBody;
        $step = 5;
        $max_theshold = 40;
        $refill_threshold_options = [];
        for ($i = 0; $i <= $max_theshold; $i = $i + $step) {
            array_push($refill_threshold_options, $i);
        }
    }

    function verbato_getBalance($api_url, $api_key) {
        $responseUserBalance = wp_remote_get( $api_url . "/user-balance", array(
            'headers' => array(
               'apikey' => $api_key,
               'API'    => '2'
            ),
            'timeout' => 15
        ));
        $responseUserBalanceBody = json_decode(wp_remote_retrieve_body( $responseUserBalance ));

        if(is_object($responseUserBalanceBody) && property_exists($responseUserBalanceBody, 'error')) {
            $user_balance = 0;
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseUserBalanceBody->message) . '</p>
                  </div>';
        } else {
            $user_balance = $responseUserBalanceBody->balance;
        }
        return $user_balance;
    }

    $user_balance = verbato_getBalance($api_url, $api_key);

    if(isset($_POST['api-refresh'])) {
        $responseRefresh = wp_remote_post( $api_url . "/sofa/$project_unique_id/api-key/refresh", array(
            'headers' => array(
               'apikey' => $api_key,
               'API'    => '2'
            ),
            'timeout' => 15
        ));
        $responseRefresh = wp_remote_retrieve_body( $responseRefresh );

        if(is_object(json_decode($responseRefresh)) && property_exists(json_decode($responseRefresh), 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html(json_decode($responseRefresh)->message) . '</p>
                  </div>';
        } else {
            $verbato_project -> api_key = $responseRefresh;
            update_option("verbato_project", $verbato_project);
        }
    }

    if(isset($_POST['user-balance'])) {
        $user_balance = verbato_getBalance($api_url, $api_key);
    }

    if(isset($_POST['unsubscribe'])) {
        $responseUpdate = wp_remote_request( "$api_url/sofa/$project_unique_id", array(
            'method' => 'PUT',
            'body' => wp_json_encode([
                'is_subscribed' => false,
            ]),
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey'       => $api_key,
                'API'          => '2'
            ),
            'timeout' => 15
        ));

        $responseBodyUpdate = json_decode(wp_remote_retrieve_body( $responseUpdate ));

        if(property_exists($responseBodyUpdate, 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseBodyUpdate->message) . '</p>
                  </div>';
        } else {
            update_option("verbato_project", $responseBodyUpdate);
            $verbato_project = get_option( 'verbato_project', '');
        }
    }

    if(isset($_POST['pay'])) {
        $responseInvoice = wp_remote_post( "$api_url/invoice", array(
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'application/json',
                'apikey'       => $api_key,
                'API'          => '2'
            ),
            'body' => wp_json_encode([
               'save_token'   => false,
               'auto_payment' => false,
               'amount'       => intval(sanitize_text_field($_POST['refill-amount'])),
            ]),
        ));

        $responseBodyInvoice = json_decode(wp_remote_retrieve_body( $responseInvoice ));

        if(property_exists($responseBodyInvoice, 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseBodyInvoice -> message) . '</p>
                  </div>';
        } else {
            $payment_url = str_replace(array("&#038;","&amp;"), "&", esc_url($responseBodyInvoice->payment_url));
            echo '<script>window.location.replace("' . $payment_url .'");</script>';
        }
    }

    $loader_url = plugin_dir_url( __DIR__ ) . 'assets/loader.gif';
?>
<div class="verbato-account-container">
    <div class="account-loader"><div class="verbato-loader" style="background-image: url('<?php echo esc_url($loader_url) ?>');"></div></div>
    <div class="verbato-account-info">
        <p>
            When you activate this plugin, Verbato generates an API key for your site. This API key is
            specific to your account and is not transferrable, and is also only valid for this website.
            If you have additional websites, please generate additional API keys for those websites in your
            <a href="https://verbato.ai/" class="native-link" target="_blank">Verbato Dashboard -></a>
        </p>
        <p>
            The Verbato Dashboard has more details on how your funds are used, and includes detailed monthly
            statements. Please sign-in to your dashboard with the same email address you’re using on this
            Wordpress site: <b>info@verbato.ai</b> (and check your junk mailbox if you can’t find our welcome email).
        </p>
    </div>

    <form class="verbato_form" method="post" enctype="multipart/form-data">
        <div>
            <div class="verbato-input-wrapper">
                <label for="default-name">Your API Key</label>
                <div>
                    <div>
                        <input type="text" id="verbato-api-key" name="default-name" value="<?php echo esc_attr($api_key); ?>" disabled></input>
                        <input class="button button-primary regenerate-btn account-btn" type="submit" value="Regenerate"  name="api-refresh"/>
                    </div>
                    <h5>This is generated automatically for you, only change this if you know what you’re doing.</h5>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-name">Your current balance / credits</label>
                <div>
                <div>
                    <input type="text" id="verbato-user-balance" name="api-key" value="<?php echo esc_attr($user_balance . '$'); ?>" disabled></input>
                    <input class="button button-primary refresh-btn account-btn" type="submit" value="Refresh" name="user-balance"/>
                </div>
                <h5>Your balance may have changed since this page has loaded, the Refresh button below will update this value to reflect your new balance.</h5>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-model">Refill amount</label>
                <div>
                    <select id="refill-amount" name="refill-amount">
                        <?php
                            foreach ($products as $option) {
                                echo '<option value="' . esc_attr($option -> price) . '" ' . (($option -> price == $default_plan_amount) ? "selected" : "") . '>' . esc_html($option -> price) . '$</option>';
                            }
                        ?>
                    </select>
                    <h5>When your account gets refilled, this is the amount that will be charged to your payment method.</h5>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-background">Refill threshold</label>
                <div>
                     <select id="refill-threshold" name="refill-threshold">
                        <?php
                            foreach ($refill_threshold_options as $option) {
                                $show_option = ($option == 0 ) ? 'none' : $option . "$";
                                echo '<option value="' . esc_attr($option) . '" ' . (($option == $default_refill_threshold) ? "selected" : "") . '>' . esc_html($show_option) . '</option>';
                            }
                        ?>
                    </select>
                    <h5>If your balance drops below this value, your balance will refill automatically. Select [<b>None</b>] to disable auto-refill.</h5>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-background">Payment method</label>
                <div>
                    <div class="verbato-client-cards-wrapper max-width-350">
                        <?php
                            if(!empty($cards)) {
                                $cards_img = array(
                                    "visa" => "visa.png",
                                    "mastercard" => "master_card.png",
                                    "amex" => "american_express.png",
                                    "diners" => "diners_club.png",
                                    "discover" => "discover_club.png",
                                    "jcb" => "jcb.png",
                                    "unionpay" => "unionpay.png"
                                );
                                ?>
                                    <input
                                        type="text"
                                        id="verbato-client-cards"
                                        name="verbato-client-cards"
                                        value="<?php echo esc_attr(ucfirst($cards[0]->brand) . ' **** ' . $cards[0]->last4 . ' Expires ' . $cards[0]->exp_month . '/' . $cards[0]->exp_year); ?>" disabled
                                    >
                                        <img class="verbato-card-img" src="<?php echo esc_url(plugin_dir_url( __DIR__ ) .'assets/' . $cards_img[$cards[0]->brand]) ?>" width="30" height="30">
                                        <input  id="delete-card" type="submit" name="delete-card" value="X" class="account-btn"></input>
                                        <input  id="deleted-card-id" type="test" name="deleted-card-id" value="<?php echo esc_attr($cards[0]->id) ?>" style="display: none"></input>
                                    </input>

                                <?php
                            } 
                                ?>
                                    <input type="submit" name="pay" class="button button-primary account-btn margin-top-21" value="Pay"></input>
                                    <input type="submit" name="<?php echo $verbato_project->is_subscribed ? 'unsubscribe': 'invoice' ?>" class="button button-primary account-btn margin-top-21" value="<?php echo $verbato_project->is_subscribed ? 'Unsubscribe' : 'Subscribe with Stripe.com' ?>"></input>
                                <?php
                        ?>
                    </div>
                    <h5>Verbato does not retain your card details, they are stored with our billing partners,
                        <a href="#" class="native-link">Stripe.com -></a>
                    </h5>
                    <h5>
                        Please make sure you allow popups for this website if you can’t see the Stripe.com
                        Popup when you click the button above.
                    </h5>
                </div>
            </div>
        </div>
        <div class="verbato-account-buttons-container">
            <a href="https://verbato.ai/" class="button button-primary button-link" target=”_blank”>Visit Dashboard -></a>
            <input id="account-save-btn" class="button button-primary account-btn" type="submit" value="Save" name="save" disabled/>
        </div>
        <script>
            const refillAmount = document.getElementById('refill-amount');
            const refillThreshold = document.getElementById('refill-threshold');
            [refillAmount, refillThreshold].forEach( elem => {
                elem.addEventListener('change', function(e){
                const saveBtn = document.getElementById('account-save-btn');
                const secondSelect = this.id === 'refill-amount' ? refillThreshold : refillAmount;

                const firstDefaultValue = this.id === 'refill-amount' ? <?php  echo esc_html($default_plan_amount) ?> : <?php  echo esc_html($default_refill_threshold) ?>;
                const secondDefaultValue = secondSelect.id === 'refill-amount' ? <?php  echo esc_html($default_plan_amount) ?> : <?php  echo esc_html($default_refill_threshold) ?>;

                const isFirstSavedValue = Number(this.value) === firstDefaultValue;
                const isSecondSavedValue = Number(secondSelect.value) === secondDefaultValue;

                if(isFirstSavedValue && isSecondSavedValue) {
                    saveBtn.setAttribute('disabled', '');
                    return;
                } else {
                    saveBtn.removeAttribute('disabled');
                }
            })
        });
        </script>
        <script>
            const buttons = document.querySelectorAll('.account-btn');

            const btnArray = Array.from(buttons);
            btnArray.forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelector('.account-loader').style.display = 'block';
                });
            })
        </script>
    </form>
</div>

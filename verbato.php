<?php
/*
Plugin Name: Verbato
Description: Intelligent 3D chatbot that supports your customers by answering their questions about your products or services.
Author: Alexander Kurmoiarov
Version: 1.1.8
*/

wp_enqueue_style( 'main', plugin_dir_url( __FILE__ ) . 'admin/css/main.css', false, rand(111,9999), 'all');
add_action( 'wp_ajax_verbato_get_project_ajax', 'verbato_get_project_ajax' );

function verbato_get_project_ajax() {
    $verbato_project    = get_option( 'verbato_project', '');
    $api_url            = get_option( 'verbato_project_api_url' );
    $api_key            = $verbato_project->api_key;
    $project_unique_id  = get_option('verbato_project_id');

    if($api_key) {
        $responseOptions = wp_remote_get( "$api_url/sofa/options", array(
            'headers' => array(
               'apikey' => $api_key,
               'API' => '2'
            ),
        ));

        $responseBodyOptions = wp_remote_retrieve_body( $responseOptions );

        if(property_exists(json_decode($responseBodyOptions), 'error')) {
            wp_send_json_success(json_decode($responseBodyOptions), json_decode($responseBodyOptions) -> status_code);
        } else {
            $options = json_decode($responseBodyOptions);
        }

        if(isset($_POST['prompt_guid'])) {
            $prompt_id = sanitize_text_field($_POST['prompt_guid']);
            $response  = wp_remote_get( $api_url . '/sofa' . '/' . $project_unique_id . '/prompt' . '/' . $prompt_id, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'API'          => '2',
                    'apikey'       => $api_key,
                ),
            ));

            $responseBody = json_decode(wp_remote_retrieve_body( $response ));

            if(property_exists($responseBody, 'error')) {
                wp_send_json_success($responseBody, $responseBodyOptions -> status_code);
            } else {
                $prompt_value = $responseBody->text;
            }
        } else {
            $prompt_value = "";
        }
    }

    $response = array(
        "verbato_project"           => $verbato_project,
        "verbato_options"           => $options,
        "api_url"                   => $api_url,
        "prompt_text"               => $prompt_value,
        "preview_placeholder"       => plugin_dir_url(__FILE__) . 'assets/model.png',
        "preview_block_placeholder" => plugin_dir_url(__FILE__) . 'assets/avatarPreview.png',
    );

    wp_send_json_success($response);
}


function verbato_register_block() {
    register_block_type( __DIR__ );

   /* Add tab to the product data */
    add_filter('woocommerce_product_data_tabs', function($tabs) {
    	$tabs['verbato_context'] = [
    		'label'  => __('Verbato AI', 'txtdomain'),
    		'target'   => 'verbato_product_context',
//     		'class'    => ['hide_if_external'],
    		'priority' => 25
    	];
    	return $tabs;
    });

    /* Add fields to tab panel*/
    add_action('woocommerce_product_data_panels', function() {
    	?>
    	    <div id="verbato_product_context" class="panel woocommerce_options_panel hidden">
    	        <?php
                    global $post;
                    $post_id                      = $post->ID;
                    $product                      = wc_get_product( $post_id );
                    $verbato_product_prompt       = get_post_meta( $product->get_id(), '_verbato_product_prompt', true );
                    $verbato_product_prompt_error = get_post_meta( $product->get_id(), '_verbato_product_prompt_error', true );
                    $api_url                      = get_option('verbato_project_api_url');
                    $project_unique_id            = get_option('verbato_project_id');
                    $verbato_project              = get_option( 'verbato_project');
                    $api_key                      = $verbato_project->api_key;
                    $product                      = wc_get_product($post_id);

                    if (!empty($verbato_product_prompt_error)) {
                        echo '<div class="notice error verbato-notice is-dismissible" >
                            <p> Verbato prompt error: ' . esc_html($verbato_product_prompt_error) . '</p>
                        </div>';
                        delete_metadata('post', $product->get_id(), '_verbato_product_prompt_error');
                    }

                    if(empty($verbato_product_prompt)) {
                        $prompt_value = '';
                    } else {
                        $prompt_id = json_decode($verbato_product_prompt)->guid;
                        $response  = wp_remote_get( $api_url . '/sofa' . '/' . $project_unique_id . '/prompt' . '/' . $prompt_id, array(
                            'headers' => array(
                                'Content-Type' => 'application/json',
                                'API'          => '2',
                                'apikey'       => $api_key,
                            ),
                        ));

                        $responseBody = json_decode(wp_remote_retrieve_body( $response ));

                        if(property_exists($responseBody, 'error')) {
                            $prompt_value = '';
                            echo '<div class="notice error verbato-notice is-dismissible" >
                                    <p> Error fetch prompt: ' . esc_html($responseBody->message) . '</p>
                                </div>';
                        } else {
                            $prompt_value = $responseBody->text;
                            $product->update_meta_data('_verbato_product_prompt', wp_remote_retrieve_body( $response ));
                            $product->save();
                        }
                    }

                    woocommerce_wp_textarea_input([
                        'id'    => '_verbato_context',
                        'label' => __('Context', 'txtdomain'),
                        'value' => esc_textarea($prompt_value),
            //     		'wrapper_class' => 'show_if_simple',
                    ]);
                    ?>
                </div>
            <?php
        }
    );

    /* Save product metadata */
    add_action('woocommerce_process_product_meta', function($post_id) {
        // Create product prompt and get ID
        $product                = wc_get_product($post_id);
        $project_unique_id      = get_option('verbato_project_id');
        $api_url                = get_option('verbato_project_api_url');
        $verbato_project        = get_option( 'verbato_project');
        $api_key                = $verbato_project->api_key;
        $verbato_product_prompt = get_post_meta( $product->id, '_verbato_product_prompt', true );

        if(empty($verbato_product_prompt)) {
            $response = wp_remote_post( $api_url . '/sofa' . '/' . $project_unique_id . '/prompt', array(
                 'body' => wp_json_encode([
                     'text' => sanitize_text_field($_POST['_verbato_context']),
                ]),
                'headers' => array(
                     'Content-Type' => 'application/json',
                     'API'          => '2',
                     'apikey'       => $api_key,
                ),
                'timeout' => 15
            ));

            $responseBody = json_decode(wp_remote_retrieve_body( $response ));

            if(property_exists($responseBody, 'error')) {
                $product->update_meta_data('_verbato_product_prompt_error', sanitize_text_field($responseBody->message));
                $product->save();
            } else {
                $product->update_meta_data('_verbato_product_prompt', sanitize_text_field(wp_remote_retrieve_body( $response )));
                $product->save();
            }
        } else {
            $response = wp_remote_request( "$api_url/sofa/$project_unique_id/prompt", array(
                'method' => 'PUT',
                'body'   => wp_json_encode([
                    'prompt_guid' => json_decode($verbato_product_prompt)->guid,
                    'text'        => sanitize_text_field($_POST['_verbato_context']),
                 ]),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'apikey'       => $api_key,
                    'API'          => '2'
                ),
                'timeout' => 15
            ));
            $responseBody = json_decode(wp_remote_retrieve_body( $response ));

            if(property_exists($responseBody, 'error')) {
                $product->update_meta_data('_verbato_product_prompt_error', sanitize_text_field($responseBody->message));
                $product->save();
            } else {
                $product->update_meta_data('_verbato_product_prompt', sanitize_text_field(wp_remote_retrieve_body( $response )));
                $product->save();
            }
        }
    });
}
add_action( 'init', 'verbato_register_block' );
add_action('admin_menu', 'verbato_plugin_setup_menu');

function verbato_plugin_setup_menu(){
    $parent_slug = 'verbato-plugin';
    $submenus = array(
        array(
            "title" => "Conversation Logs",
            "slug"  => "?page=verbato-plugin&tab=logs",
        ),
        array(
            "title" => "Verbato Account",
            "slug"  => "?page=verbato-plugin&tab=account",
        )
    );
    add_menu_page(
        'Verbato Plugin',
        'Verbato',
        'manage_options',
        'verbato-plugin',
        'verbato_plugin_init',
        'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBzdGFuZGFsb25lPSJubyI/Pgo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDIwMDEwOTA0Ly9FTiIKICJodHRwOi8vd3d3LnczLm9yZy9UUi8yMDAxL1JFQy1TVkctMjAwMTA5MDQvRFREL3N2ZzEwLmR0ZCI+CjxzdmcgdmVyc2lvbj0iMS4wIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciCiB3aWR0aD0iMTI4LjAwMDAwMHB0IiBoZWlnaHQ9IjEyOC4wMDAwMDBwdCIgdmlld0JveD0iMCAwIDEyOC4wMDAwMDAgMTI4LjAwMDAwMCIKIHByZXNlcnZlQXNwZWN0UmF0aW89InhNaWRZTWlkIG1lZXQiPgoKPGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMC4wMDAwMDAsMTI4LjAwMDAwMCkgc2NhbGUoMC4xMDAwMDAsLTAuMTAwMDAwKSIKZmlsbD0iIzAwMDAwMCIgc3Ryb2tlPSJub25lIj4KPHBhdGggZD0iTTg4OCAxMTk5IGMtMTAgLTUgLTIyIC0xOCAtMjggLTI5IC01IC0xMCAtMzkgLTIwOCAtNzUgLTQ0MSAtMzYKLTIzMiAtNjggLTQzMyAtNzEgLTQ0NiAtMTQgLTUzIC04NCAtNjkgLTEyOCAtMzEgLTI3IDI1IC0zMCAzOSAtMTMyIDczNiBsLTI4CjE5MiAtOTMgMCBjLTkyIDAgLTkzIDAgLTg4IC0yMiAzIC0xMyAyNSAtMTQ5IDUwIC0zMDMgOTAgLTU2NCAxMDMgLTYzMSAxMjMKLTY3MSA0MyAtODMgMTAyIC0xMTQgMjIyIC0xMTQgNjEgMCA5MCA1IDEyMSAyMCA0NyAyNSA5MiA4MyAxMDggMTQyIDYgMjQgNDUKMjQzIDg2IDQ4OCA0MiAyNDUgNzggNDU1IDgwIDQ2OCA1IDIxIDMgMjIgLTYyIDIyIC0zOCAwIC03NiAtNSAtODUgLTExeiIvPgo8L2c+Cjwvc3ZnPgo=' );

    add_submenu_page( $parent_slug, "Global Settings", "Global Settings", 'manage_options', $parent_slug );

    foreach ($submenus as &$menu) {
        add_submenu_page( $parent_slug, $menu['title'], $menu['title'], 'manage_options', $menu['slug'] );
    }

    add_filter('parent_file', 'verbato_parent_file');

    function verbato_parent_file($parent_file){
        global $submenu_file;
        if (isset($_GET['tab']) && sanitize_text_field($_GET['tab']) == 'logs') $submenu_file = '?page=verbato-plugin&tab=logs';
        if (isset($_GET['tab']) && sanitize_text_field($_GET['tab']) == 'account') $submenu_file = '?page=verbato-plugin&tab=account';

        return $parent_file;
    }
}

function verbato_plugin_init(){
    verbato_handle_changes();
}

function verbato_handle_changes(){
    // check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : null;
?>
    <div class="verbato-nav-container">
        <!-- Print the page title -->
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <!-- Here are our tabs -->
        <nav class="nav-tab-wrapper verbato-nav-tab-wrapper">
          <a href="?page=verbato-plugin" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">Global settings</a>
          <a href="?page=verbato-plugin&tab=logs" class="nav-tab <?php if($tab==='logs'):?>nav-tab-active<?php endif; ?>">Conversation Logs</a>
          <a href="?page=verbato-plugin&tab=account" class="nav-tab <?php if($tab==='account'):?>nav-tab-active<?php endif; ?>">Verbato Account</a>
        </nav>
    </div>
    <div class="verbato-tab-content">
        <?php
             switch($tab) :
                case 'logs':
                    $template = dirname( __FILE__ ) . '/templates/' . 'logs.php';
                    break;
                case 'account':
                    $template = dirname( __FILE__ ) . '/templates/' . 'account.php';
                    break;
                default:
                    $template = dirname( __FILE__ ) . '/templates/' . 'global-settings.php';
                    break;
            endswitch;

            include_once($template);
        ?>
    </div>
    <script>
        const url = new URL(window.location.href);
        const searchParams = url.searchParams;
        const currentTab = searchParams.get('tab');

        if(currentTab === 'logs') {
            const logsScroll = document.querySelector('.verbato-customer-wrapper');
            
            if (logsScroll) {
                logsScroll.scrollTop = sessionStorage.getItem('logsScroll');

                logsScroll.addEventListener('scroll', function(){
                  sessionStorage.setItem('logsScroll', this.scrollTop);
                });
            }
        } else {
            sessionStorage.removeItem('logsScroll');
        }
    </script>
    <?php
}

/**
 *Activation hook.
 */

function verbato_plugin_activate() {
    $api_url = get_option('verbato_project_api_url');

    if(empty($api_url) || $api_url != 'https://api.verbato.ai') {
        $api_url = "https://api.verbato.ai";
        add_option('verbato_project_api_url', $api_url, '', 'yes');
    }

    $project_unique_id = get_option('verbato_project_id');

    if(empty($project_unique_id)) {
        $project_unique_id = wp_generate_uuid4();
        add_option('verbato_project_id', $project_unique_id, '', 'yes');
    }

    $project_name = get_bloginfo('name');
    $current_user = wp_get_current_user();

    $response = wp_remote_post( $api_url . '/sofa', array(
     'body'    => wp_json_encode([
         'project_unique_id' => $project_unique_id,
         'project_name'      => $project_name,
         'email'             => $current_user->user_email,
         'username'          => $current_user->user_login,
         'website_url'       => get_bloginfo('url'),
         'timezone'          => wp_timezone_string(),
     ]),
     'headers' => array(
         'Content-Type' => 'application/json',
         'API'          => '2'
     ),
     'timeout' => 15
    ));

    $responseBody = json_decode(wp_remote_retrieve_body( $response ));

    /* todo: stop activating plugin if request error */
    if(property_exists($responseBody, 'error')) {
        wp_die( __( "Verbato project fetch error. Please contact support. <a href='/wp-admin/plugins.php'>Back to admin</a>", 'textdomain' ) );
    }

    add_option('verbato_project', $responseBody, '', 'yes');
}

register_activation_hook(
	__FILE__,
	'verbato_plugin_activate'
);

/**
 * Deactivation hook.
 */
function verbato_plugin_deactivate() {
    delete_option( 'verbato_project' );
	delete_option( 'widget_verbato_widget' );
	delete_option( 'verbato_project_logs' );
    delete_option( 'verbato_project_api_url' );
    delete_option( 'verbato_options' );
}

register_deactivation_hook( __FILE__, 'verbato_plugin_deactivate' );


/**
 * Uninstall hook.
 */

function verbato_plugin_uninstall() {}

register_uninstall_hook( __FILE__, 'verbato_plugin_uninstall' );

/**
 * Add a custom product data tab
 */

add_filter('woocommerce_product_tabs', 'verbato_add_custom_product_tab');

function verbato_add_custom_product_tab($tabs) {
    if( is_product() ) {
        global $post, $product;

        $product_prompt = json_decode(get_post_meta($post->ID)["_verbato_product_prompt"]["0"]);
    }

    if (!empty($product_prompt->guid)) {
        $tabs["verbato_ai"] = array("title" => "Verbato AI", "priority" => 50, "callback" => "verbato_get_custom_product_tab_content");
    }
    return $tabs;
}

function verbato_get_custom_product_tab_content() {
    global $current_user;
    wp_get_current_user();
    if( is_product() ) {
        global $post, $product;

        $product_prompt = json_decode(get_post_meta($post->ID)["_verbato_product_prompt"]["0"]);
    }

    $verbato_project = get_option( 'verbato_project', '');
    $api_key         = $verbato_project->api_key;
    $scene_guid      = $verbato_project->scene_guid;
    $loader_url       = plugin_dir_url( __FILE__ ) . '/assets/loader.gif';

    echo '<div id="verbato-container_product">
            <div
                class="verbato-loader_product"
                style="background-image: url(' . esc_url($loader_url) . ');"
            ></div>
          </div>';
    echo '<script>
            fetch("https://api.verbato.ai/session/create/url", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "API": "2",
                    "apikey": "' . esc_html($api_key) . '"
                },
                body: JSON.stringify({
                    scene_guid: "' . esc_html($scene_guid) . '",
                    prompt_guid: "' . esc_html($product_prompt->guid) .'",
                    x_data: {
                        source_url: window.location.href,
                        username: "' . esc_html($current_user->user_login) . '"
                    }
                })
            })
            .then(res => res.json())
            .then(res => {
                if(res?.error) {
                    throw new Error(res.error);
                }
                const container = document.getElementById("verbato-container_product");
                const loaderElem = document.querySelector(".verbato-loader_product");
                const iFrame = document.createElement("iframe");
                iFrame.src = res.iframe_url;
                iFrame.width = 600;
                iFrame.height = 450;
                iFrame.allow = "accelerometer; camera *; microphone *; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
                iFrame.frameBorder="0";
                container.append(iFrame);
                iFrame.onload = function() {loaderElem.remove()};
            })
            .catch(error => {
                const container = document.querySelector("#verbato-container_product");
                container.remove();
                console.error(error);
            });
    </script>';
}
?>

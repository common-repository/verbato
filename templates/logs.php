<?php
    /*Template Name: Conversation Logs
    */
    if ( ! defined( 'ABSPATH' ) ) exit; // Don't allow direct access

    $verbato_project    = get_option( 'verbato_project', '' );
    $project_logs       = get_option( 'verbato_project_logs', '' );
    $api_url            = get_option( 'verbato_project_api_url' );
    $api_key            = $verbato_project->api_key;
    $project_unique_id  = $verbato_project->project_unique_id;

    if(isset($_POST['refresh']) || !$project_logs) {
        $responseConversationsJson = wp_remote_get( "$api_url/sofa/conversations/$project_unique_id", array(
                'headers'   => array(
                  'API'     => '2',
                  'apikey'  => $api_key,
                ),
                'timeout' => 15
            ));

        $responseConversations = json_decode(wp_remote_retrieve_body( $responseConversationsJson ));

        if(is_object($responseConversations) && property_exists($responseConversations, 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html($responseConversations->message) . '</p>
                  </div>';
        } else {
            if(!$project_logs) {
                add_option('verbato_project_logs', $responseConversations, '', 'yes');
            } else {
                update_option('verbato_project_logs', $responseConversations);
            }
            $project_logs = $responseConversations;
        }
    }

    $session_guid = isset($_GET['session']) ? sanitize_text_field($_GET['session']) : sanitize_text_field($project_logs["0"]->guid);

    $session_logs = [];
    if (!empty($session_guid)) {
        $session_logs_json = wp_remote_get( "$api_url/sofa/conversation/$session_guid", array(
            'headers' => array(
            'API'     => '2',
            'apikey'  => $api_key,
            ),
            'timeout' => 15
        ));

        $session_logs_body = json_decode(wp_remote_retrieve_body($session_logs_json));

        if (is_array($session_logs_body)) {
            $session_logs = array_reverse($session_logs_body);
        }
    }
?>
    <form class="verbato_form" method="post">
        <div class="verbato-logs-wrapper">
            <div class="verbato-customer-wrapper">
                <?php
                    $default_session = $project_logs["0"]->guid;
                    $current_session = isset($_GET['session']) ? sanitize_text_field($_GET['session']) : sanitize_text_field($default_session);

                    foreach ($project_logs as $session) {
                    $dt = new DateTime($session->created_at);
                    $dt->setTimezone(new DateTimeZone(wp_timezone_string()));
                    $date_time = $dt->format('Y-m-d H:i');
                    $user_name = $session -> x_data -> username ? $session -> x_data -> username : "anonymous";

                        $isActive = $current_session === $session->guid ? 'verbato-logs-active' : '';
                        echo '<a class="verbato-logs-customers-list ' . esc_attr($isActive) . '" href="' . esc_url("?page=verbato-plugin&tab=logs&session=$session->guid") .  '">
                                <div> ' . esc_html($user_name) . ' </div>
                                <div>' . esc_html($date_time) . '</div>
                              </a>';
                    }
                ?>
            </div>
            <div class="verbato-qa-wrapper">
                <?php
                    $product_link_object = array_filter($project_logs, function($log) use ($current_session){
                        return $log->guid === $current_session;
                    });

                    if (!empty($product_link_object)) {
                        echo '<a class="verbato-qa-product-link" href="' . esc_url($product_link_object["0"]->x_data->source_url) . '">Product page link</a>';
                    }

                    foreach ($session_logs as $log) {
                        if($log->event_type == "TEXT") {
                            $log_date = new DateTime($log->created_at);
                            $log_date->setTimezone(new DateTimeZone(wp_timezone_string()));
                            $log_time = $log_date->format('H:i:s');

                            if($log->is_input == true) {

                                echo '<div class="verbato-logs-qa">
                                        <div class="verbato-logs-question verbato-logs-date">' . esc_html($log_time) . '</div>
                                        <div class="verbato-logs-question">' . esc_html($log->data->message) . '</div>
                                      </div>';
                            }
                            if($log->is_output == true) {
                                echo '<div class="verbato-logs-qa">
                                        <div class="verbato-logs-answer verbato-logs-date">' . esc_html($log_time) . '</div>
                                        <div class="verbato-logs-answer">' . esc_html($log->data->message) . '</div>
                                      </div>';
                            }
                        } else if ($log->event_type != "JOIN_SESSION" ){
                            echo '<div class="verbato-logs-qa">
                                    <div class="verbato-logs-event">' . esc_html($log->event_type) . ":" . esc_html($log->data->message) . '</div>
                                 </div>';
                        }
                    }
                ?>
            </div>
        </div>
        <div class="verbato-buttons-container global-settings">
            <input class="button button-primary save-btn" type="submit" name="refresh" value="Refresh" />
        </div>
    </form>

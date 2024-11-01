<?php
 /*Template Name: Preview
 */
    if ( ! defined( 'ABSPATH' ) ) exit; // Don't allow direct access

    $verbato_project = get_option( 'verbato_project', '');
    $api_key         = $verbato_project->api_key;
    $scene_guid      = $verbato_project->scene_guid;
    $api_url         = get_option( 'verbato_project_api_url' );

    if(isset($_POST['show_preview'])) {
        if(!empty($scene_guid) && !empty($api_key)) {
            $responseUrl = wp_remote_post( "$api_url/session/create/url", array(
                 'body'    => wp_json_encode([
                     'scene_guid' => $scene_guid,
                 ]),
                 'headers' => array(
                     'Content-Type' => 'application/json',
                     'API'          => '2',
                     'apikey'       => $api_key,
                 ),
                 'timeout' => 15
            ));

            $responseUrlBody = json_decode(wp_remote_retrieve_body( $responseUrl ));

            if(property_exists($responseUrlBody, 'error')) {
                echo '<div class="notice error verbato-notice is-dismissible" >
                          <p>' . esc_html($responseUrlBody->error) . '</p>
                      </div>';
            } else {
                $iframe_url = $responseUrlBody->iframe_url;
            }
        }
    }
?>
    <form class="verbato_form" method="post">
        <div class="verbato-label">Live Preview</div>
        <div id="verbato-container" class="verbato-preview-container widget-text wp_widget_plugin_box" style="text-align: center; position: relative">
            <?php
                if($iframe_url) {
                    echo '<div class="verbato-loader"></div>';
                    echo '<iframe class="vebato-preview-iframe" src="' . esc_url($iframe_url) . '"
                            width="600" height="450" allow="accelerometer; camera *; microphone *; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                          ></iframe>';
                    echo '<script>
                              document.querySelector(".vebato-preview-iframe").onload = function() {
                                  const loaderElem = document.querySelector(".verbato-loader");
                                  loaderElem.remove()
                              }
                          </script>';
                } else {
                    echo '<input href="?page=verbato-plugin&tab=preview" class="button button-primary save-btn" type="submit" value="Click to show a preview" name="show_preview"/>';
                }
            ?>
        </div>
    </form>
    <div class="verbato-input-wrapper" style="visibility: hidden;">
        <label class="verbato-label" for="custom-css">Custom CSS</label>
        <div>
            <textarea id="custom-css" class="custom-css" name="custom-css" value=""></textarea>
            <h5>Change these values to match the style to your theme</h5>
        </div>
    </div>
    <div class="verbato-buttons-container global-settings">
        <input style="visibility: hidden;" class="button button-primary cancel-btn reset-btn" type="button" value="Reset to default colors" />
        <input class="button button-primary save-btn" type="button" value="Refresh" />
    </div>

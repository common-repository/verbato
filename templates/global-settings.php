<?php
    /* Template Name: Global Settings */
    if ( ! defined( 'ABSPATH' ) ) exit; // Don't allow direct access

    $verbato_project    = get_option( 'verbato_project', '');
    $api_url            = get_option( 'verbato_project_api_url' );
    $api_key            = $verbato_project->api_key;
    $project_unique_id  = $verbato_project->project_unique_id;

    if(isset($_POST['save'])) {
        $responseUpdate = wp_remote_request( "$api_url/sofa/$project_unique_id", array(
            'method'    => 'PUT',
            'body'      => wp_json_encode([
                'character_name'            => stripslashes(sanitize_text_field($_POST['default-name'])),
                'character_guid'            => sanitize_text_field($_POST['default-model']),
                'background_guid'           => sanitize_text_field($_POST['default-background']),
                'prompt_prefix'             => stripslashes(sanitize_textarea_field($_POST['default-prefix'])),
                'intro_speech'              => stripslashes(sanitize_textarea_field($_POST['default-intro-speech'])),
                'voice_id'                  => sanitize_text_field($_POST['default-voice']),
                'welcome_popup_text'        => stripslashes(sanitize_textarea_field($_POST['welcome-popup-text'])),
                'welcome_popup_action_text' => stripslashes(sanitize_text_field($_POST['welcome-popup-action-text'])),
             ]),
            'headers'           => array(
                'Content-Type'  => 'application/json',
                'apikey'        => $api_key,
                'API'           => '2'
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
        }
        $verbato_project = get_option( 'verbato_project', '');
    }

    if($api_key) {
        $responseOptions = wp_remote_get( "$api_url/sofa/options", array(
            'headers' => array(
               'apikey' => $api_key,
               'API' => '2'
            ),
        ));

        $responseBodyOptions = wp_remote_retrieve_body( $responseOptions );

        if(property_exists(json_decode($responseBodyOptions), 'error')) {
            echo '<div class="notice error verbato-notice is-dismissible" >
                      <p>' . esc_html(json_decode($responseBodyOptions)->message) . '</p>
                  </div>';
        } else {
            $options = json_decode($responseBodyOptions);
        }
    }
    $default_character_id      = $verbato_project->character->guid;
    $default_background_id     = $verbato_project->background->guid;
    $intro_speech              = $verbato_project->intro_speech;
    $prompt_prefix             = $verbato_project->prompt_prefix;
    $character_name            = $verbato_project->character_name;
    $character_assets          = $verbato_project->character->assets;
    $welcome_popup_text        = $verbato_project->welcome_popup_text;
    $welcome_popup_action_text = $verbato_project->welcome_popup_action_text;
    $characters_options        = $options -> characters;
    $voices_options            = $options -> voices;
    $backgrounds_options       = $options -> backgrounds;
    $default_voice_id          = $verbato_project->voice_id;
    $preview_img               = array_pop(
                                     array_filter(
                                        $options->characters,
                                        function($v) use ($default_character_id){
                                            return $v->id == $default_character_id;
                                        }
                                     )
                                 )->image_url;
    $background_img            = array_pop(
                                     array_filter(
                                         $options->backgrounds,
                                         function($v) use ($default_background_id ){
                                             return $v->id == $default_background_id;
                                         }
                                     )
                                 )->image_url;
    $default_voice_url         = "$api_url/voice/preview/$default_voice_id";

    if(empty($preview_img)) $preview_img = plugin_dir_url(__DIR__) . 'assets/model.png';
    $loader_url = plugin_dir_url( __DIR__ ) . 'assets/loader.gif';
?>
<div class="global-loader"><div class="verbato-loader" style="background-image: url('<?php echo esc_url($loader_url) ?>');"></div></div>
<form class="verbato_form" method="post" enctype="multipart/form-data">
    <div class="settings-form__grid-container">
        <div>
            <div class="verbato-input-wrapper">
                <label for="default-name">Default Name</label>
                <div>
                    <input type="text" id="default-name" name="default-name" value="<?php echo esc_attr($character_name); ?>"></input>
                    <h5>You can pick a specific name in widgets or products</h5>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-model">Default Model</label>
                <div>
                    <select id="default-model" name="default-model">
                    <?php
                        foreach ($characters_options as $option) {
                            echo '<option value="' . esc_attr($option->id) . '"' . (($option->id == $default_character_id) ? "selected" : "") .
                                 ' data-image_url="' . esc_attr($option->image_url) . '">' . esc_html($option->name) .
                                 '</option>';
                        }
                    ?>
                    </select>
                    <h5>You can pick a specific model in widgets or products</h5>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-voice">Default Voice</label>
                <div class="verbato-default-voice max-width-350">
                    <select id="default-voice" name="default-voice">
                    <?php
                        foreach ($voices_options as $option) {
                            echo '<option value="' . esc_attr($option -> id) . '"' . (($option -> id == $default_voice_id) ? "selected" : "") . '>' . esc_html($option -> name) . '</option>';
                        }
                    ?>
                    </select>
                    <span class="dashicons dashicons-format-audio verbato-icon-button verbato-icon-button__audio"></span>
                </div>
            </div>
            <div class="verbato-input-wrapper">
                <label for="default-background">Default Background</label>
                <div class="verbato-default-background">
                    <select id="default-background" name="default-background">
                    <?php
                        foreach ($backgrounds_options as $option) {
                            echo '<option value="' . esc_attr($option -> id) . '" ' . (($option -> id == $default_background_id) ? "selected" : "") . ' data-image_url="' . esc_attr($option -> image_url) . '">' . esc_html($option -> name) . '</option>';
                        }
                    ?>
                    </select>
                </div>
            </div>
            <div class="verbato-input-wrapper" style="visibility: hidden;">
                <label class="verbato-file-input-label" for="file">Drop custom background here<br>(JPG, PNG, GIF, etc)</label>
                <input type="file" id="file" name="file" value=""></input>
            </div>
        </div>
        <div>
            <div class="verbato-preview">
                <img class="model-image" src="<?php echo esc_url($preview_img) ?>">
                <img class="background-image" src="<?php echo esc_url($background_img) ?>">
            </div>
        </div>
    </div>
    <div>
        <div class="verbato-input-wrapper">
            <label for="default-prefix">Global Prompt</label>
            <div>
                <textarea id="default-prefix" name="default-prefix" value=""><?php echo esc_textarea(stripslashes($prompt_prefix)); ?></textarea>
                <h5>This prompt is added to specific prompt (for widgets & products)</h5>
            </div>
        </div>
        <div class="verbato-input-wrapper">
            <label for="default-intro-speech">Intro Speech</label>
            <div>
                <textarea id="default-intro-speech" name="default-intro-speech"><?php echo esc_textarea(stripslashes($intro_speech)); ?></textarea>
            </div>
        </div>
        <div class="verbato-input-wrapper margin-top-21">
            <label for="default-name">Welcome popup action text</label>
            <div id="welcome-popup-action-text">
                <input type="text" id="welcome-popup-action-text" class="max-width-454" name="welcome-popup-action-text" value="<?php echo esc_attr($welcome_popup_action_text); ?>"></input>
            </div>
        </div>
        <div class="verbato-input-wrapper welcome-popup-text">
            <label for="default-intro-speech">Welcome popup text</label>
            <div>
                <textarea id="welcome-popup-text" name="welcome-popup-text"><?php echo esc_textarea(stripslashes($welcome_popup_text)); ?></textarea>
            </div>
        </div>
    </div>
    <div class="verbato-buttons-container global-settings">
        <input class="button button-primary cancel-btn" type="submit" name="cancel" value="Cancel" />
        <input class="button button-primary save-btn global-btn" type="submit" name="save" value="Save" />
    </div>
</form>
<script>
    // Play ang change voices
    const audioBtn = document.querySelector(".verbato-icon-button__audio");
    const voiceSelect = document.getElementById("default-voice");
    let selectedVoice = "<?php echo esc_url($default_voice_url) ?>";

    audioBtn.addEventListener('click', function() {
        const audio = new Audio(selectedVoice);
        audio.play();
    })

    voiceSelect.addEventListener('change', function(option) {
        selectedVoice = "<?php echo esc_url($api_url) ?>" + "/voice/preview/" + this.options[this.selectedIndex].value;
    });
</script>
<script>
    // Change background image and model image
    const modelSelect = document.getElementById("default-model");
    const backgroundSelect = document.getElementById("default-background");

    [{imageElemSelector: '.model-image', select: modelSelect},
    {imageElemSelector: '.background-image', select: backgroundSelect}].forEach(obj => {
        obj.select.addEventListener('change', function() {
            const image_url = this.options[this.selectedIndex].getAttribute("data-image_url");
            const image_elem = document.querySelector(obj.imageElemSelector);
            image_elem.style.opacity = '10%';
            image_elem.onload = function() {image_elem.style.opacity = '100%';}
            image_elem.src = image_url;
        })
    })
</script>
<script>
    const buttons = document.querySelectorAll('.global-btn');

    const btnArray = Array.from(buttons);
    btnArray.forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelector('.global-loader').style.display = 'block';
        });
    })
</script>

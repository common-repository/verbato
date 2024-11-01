<?php
    global $current_user, $post;

    $block_unique_id = wp_generate_uuid4();
    $block_attributes = $block->parsed_block["attrs"];
    $verbato_project = get_option( 'verbato_project', '');
    $api_key = $verbato_project->api_key;
    $scene_guid = $verbato_project->scene_guid;
    $api_url = get_option( 'verbato_project_api_url' );
    $loader_url = plugin_dir_url( __FILE__ ) . '/assets/loader.gif';
?>
<div class="verbato-container verbato-container-<?php echo esc_html($block_unique_id) ?>">
    <div
        class="verbato-loader verbato-loader-<?php echo esc_html($block_unique_id) ?>"
        style="background-image: url(<?php echo esc_url($loader_url) ?>);"
    ></div>
</div>
<script>
    fetch( "<?php echo esc_url($api_url) ?>/session/create/url", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "API": '2',
            "apikey": '<?php echo esc_html($api_key) ?>',
        },
        body: JSON.stringify({
            scene_guid: '<?php echo esc_html($scene_guid) ?>',
            background_guid: '<?php echo esc_html($block_attributes["background_guid"]) ?>',
            character_guid: '<?php echo esc_html($block_attributes["character_guid"]) ?>',
            prompt_guid: '<?php echo esc_html($block_attributes["prompt_guid"]) ?>',
            voice_guid: '<?php echo esc_html($block_attributes["voice_id"]) ?>',
            x_data: {
                source_url: window.location.href,
                username: '<?php echo esc_html($current_user->user_login) ?>',
            },
            context: {
                character_name: '<?php echo esc_html($block_attributes['character_name']) ?>',
            }
        }),
    })
    .then((response) => response.json())
    .then((data) => {
        if(data?.error) {
            throw new Error(data.error);
        }
        if(data.iframe_url) {
            const container = document.querySelector('.verbato-container-<?php echo esc_html($block_unique_id) ?>');
            const loaderElem = document.querySelector('.verbato-loader-<?php echo esc_html($block_unique_id) ?>');
            const iFrame = document.createElement('iframe');
            iFrame.src = data.iframe_url;
            iFrame.width = 600;
            iFrame.height = 450;
            iFrame.allow = "accelerometer; camera *; microphone *; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
            iFrame.frameBorder="0";
            container.append(iFrame);
            iFrame.onload = function() {loaderElem.remove()};
        }
    })
    .catch((error) => {
        const container = document.querySelector('.verbato-container-<?php echo esc_html($block_unique_id) ?>');
        container.remove();
        console.error(error);
    });
</script>


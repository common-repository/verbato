( function ( blocks, i18n, element, blockEditor) {
    const el = element.createElement;
    const __ = i18n.__;
    const useBlockProps = blockEditor.useBlockProps;
    const InspectorControls = blockEditor.InspectorControls;
    const blockStyle = {
        backgroundColor: '#fff',
        color: '#000',
        padding: '20px',
    };
    const styles = {
        imageWrapper: {
            borderBottom: '1px solid #D8D8D8',
            borderTop: '1px solid #D8D8D8',
        },
        wrapperStyle: {
            padding: '16px',
        },
        labelStyle: {
            fontWeight: 700,
            fontSize: '14px',
        },
        inputStyle: {
            margin: '6px 0 10px',
            fontWeight: 400,
            width: '100%',
            border: '1px solid #818489',
            borderRadius: '3px',
            height: '30px',
            padding: '7px'
        },
        selectStyle: {
            padding: '0 0 0 7px',
        },
        imageStyle: {
            background: '#D9D9D9',
            borderRadius: '10px',
            marginTop: '10px',
            minHeight: '200px'
        },
        textareaStyle: {
            height: '188px'
        },
        saveBtnContainer: {
            textAlign: 'right'
        },
        saveBtn: {
            padding: '10px',
            background: '#007cba',
            borderColor: '#007cba',
        },
        imageAndLoaderWrapper: {
            position: 'relative'
        }
    }

    blocks.registerBlockType( 'verbato/verbato-widget', {
        edit: function (props) {
            const [projectOptions, setProjectOptions] = element.useState({
                api_url: '',
                verbato_options: {
                    backgrounds: [],
                    characters: [],
                    voices: []
                }
            });
            const [isDisabled, setIsDisabled] = element.useState(true);
            const [fetchError, setFetchError] = element.useState({
                isError: false,
                statusCode: '',
                message: ''
            });
            const [blockSettings, setBlockSettings] = element.useState({
                character_name: "",
                prompt_text: ""
            })
            const [imageLoading, setImageLoading] = element.useState(false)

            element.useEffect(() => {
                const data = new FormData();
                data.append( 'action', 'verbato_get_project_ajax' );
                if(props.attributes.prompt_guid) data.append('prompt_guid', props.attributes.prompt_guid)
                fetch("/wp-admin/admin-ajax.php", {
                    method: "POST",
                    body: data
                })
                    .then(res => res.json())
                    .then(res => {
                        if(res?.data?.error) {
                            setFetchError({
                                isError: true,
                                statusCode: res?.data?.status_code || '',
                                message: res?.data?.message || ''
                            });
                            return;
                        }
                        setProjectOptions(res.data);
                        const {
                            character_name,
                            character_guid,
                            background_guid,
                            prompt_guid,
                            voice_id,
                        } = props.attributes;
                        const settingsObj = {
                            character_name: character_name || res.data?.verbato_project?.character_name || '',
                            character_guid: character_guid || res.data?.verbato_project?.character?.guid,
                            background_guid: background_guid || res.data?.verbato_project?.background?.guid,
                            voice_id: voice_id || res.data?.verbato_project?.voice_id,
                            prompt_guid,
                            prompt_text: res?.data?.prompt_text,
                            preview_placeholder: res?.data?.preview_placeholder,
                        }

                        let preview_url;

                        if (character_guid) {
                            preview_url = res.data?.verbato_options?.characters?.find(character => character.id === character_guid)?.image_url;
                        } else {
                            preview_url = res.data?.verbato_project?.character?.assets?.find(asset => asset.photo_url).photo_url;
                        }

                        setBlockSettings({
                            prompt_text: res?.data?.prompt_text,
                            preview_placeholder: res.data?.preview_placeholder,
                            preview_url,
                            preview_block_placeholder: res.data?.preview_block_placeholder
                        })
                        if (!character_name || !character_guid || !background_guid || !voice_id) {
                            props.setAttributes(settingsObj)
                        }
                    })
                    .catch(e => console.log('error:', e));
            }, []);

            const handleFieldChange = (field) => (e) => {
                if (field === 'prompt_text') {
                    setBlockSettings({
                        ...blockSettings,
                        [field]: e.target.value
                    })
                    setIsDisabled(false);
                } else {
                    props.setAttributes({
                        [field]: e.target.value
                    })
                }

                if (field === 'character_guid') {
                    setImageLoading(true);
                    setBlockSettings({
                        ...blockSettings,
                        preview_url: projectOptions?.verbato_options?.characters?.find(character => character.id === e.target.value)?.image_url
                    })
                }
            }

            const handleSavePrompt = () => {
                let fetchConfig = {
                    url: `${projectOptions?.api_url}/sofa/${projectOptions?.verbato_project?.project_unique_id}/prompt`,
                    options: {
                        headers: {
                            'Content-Type': 'application/json',
                            'API': '2',
                            'apikey': projectOptions?.verbato_project?.api_key,
                        },
                    }
                }
                if(props.attributes.prompt_guid) {
                    fetchConfig.options.method = 'PUT'
                    fetchConfig.options.body = JSON.stringify({
                        text: blockSettings?.prompt_text,
                        prompt_guid: props.attributes.prompt_guid
                    })
                    fetchConfig.updateFn = (res) => {
                        props.setAttributes({
                            prompt_guid: ""
                        })
                        props.setAttributes({
                            prompt_guid: res?.guid
                        })
                        setIsDisabled(true);
                    }
                } else {
                    fetchConfig.options.method = 'POST'
                    fetchConfig.options.body = JSON.stringify({
                        text: blockSettings?.prompt_text
                    })
                    fetchConfig.updateFn = (res) => {
                        props.setAttributes({
                            prompt_guid: res?.guid
                        })
                        setIsDisabled(true);
                    }
                }
                    fetch(fetchConfig.url, fetchConfig.options)
                        .then(res => res.json())
                        .then(res => {
                            if(res?.error) {
                                setFetchError({
                                    isError: true,
                                    statusCode: res?.status_code || '',
                                    message: `Error save prompt. ${res?.message}`|| ''
                                })
                                return;
                            }
                            fetchConfig.updateFn(res)
                        })
                        .catch(e => {
                            console.error(e)
                        });
            }

            if (fetchError?.isError) return el(
                'div',
                useBlockProps( { style: { ...blockStyle, border: '2px solid red' } } ),
                `Error: ${fetchError?.message}. StatusCode: ${fetchError?.statusCode}`
            );

            return el(
                'div',
                useBlockProps( { style: {
                    ...blockStyle,
                    // backgroundImage: `url(${blockSettings.preview_block_placeholder})`,
                    backgroundSize: '100%',
                    backgroundRepeat: 'no-repeat',
                    padding: 0,
                } } ),
                el('img', {src: blockSettings.preview_block_placeholder}, null),
                el(
                    InspectorControls,
                    {},
                    el('div', null,
                        el('div', {
                                style: {
                                    ...styles.wrapperStyle,
                                    paddingTop: 0,
                                    textAlign: 'right'
                                },
                            }),
                        el('div', {
                                style: {
                                    ...styles.wrapperStyle,
                                    paddingTop: 0
                                },
                            },
                            el('label', {
                                    style: styles.labelStyle
                                }, 'Name',
                                el('input', {
                                        style: styles.inputStyle,
                                        value: props?.attributes?.character_name,
                                        onChange: handleFieldChange('character_name')
                                    }
                                )
                            ),
                            el('label', {
                                style: styles.labelStyle
                            }, '3D Model', el(
                                'select',
                                {
                                    style: {
                                        ...styles.inputStyle,
                                        ...styles.selectStyle
                                    },
                                    onChange: handleFieldChange('character_guid')
                                },
                                projectOptions.verbato_options.characters.map(character => el(
                                        'option', {
                                            value: character.id,
                                            selected: character.id === props?.attributes?.character_guid
                                        }, character.name
                                    )
                                )
                            )),
                            el('label', {
                                style: styles.labelStyle
                            }, 'Voice', el(
                                'select',
                                {
                                    style: {
                                        ...styles.inputStyle,
                                        ...styles.selectStyle
                                    },
                                    onChange: handleFieldChange('voice_id')
                                },
                                projectOptions.verbato_options.voices.map(voice => el(
                                        'option', {
                                            value: voice.id,
                                            selected: voice.id === props?.attributes?.voice_id
                                        }, voice.name
                                    )
                                )
                            )),
                            el('label', {
                                style: styles.labelStyle
                            }, 'Background Image', el(
                                'select',
                                {
                                    style: {
                                        ...styles.inputStyle,
                                        ...styles.selectStyle
                                    },
                                    onChange: handleFieldChange('background_guid')
                                },
                                projectOptions.verbato_options.backgrounds.map(bg => el(
                                    'option', {
                                        value: bg.id,
                                        selected: bg.id === props?.attributes?.background_guid
                                    }, bg.name
                                ))
                            ))
                        ),
                        el('div', {
                            style: {
                                ...styles.wrapperStyle,
                                ...styles.imageWrapper
                            }
                        }, el('div', {
                                style: styles.labelStyle
                            }, 'Preview'),
                            el('div',
                                {
                                    style: styles.imageAndLoaderWrapper
                                },
                                el(
                                    'img',
                                    {
                                        style: {
                                            ... styles.imageStyle,
                                            opacity: imageLoading ? 0.6 : 1
                                        },
                                        onLoad: function(){setImageLoading(false)},
                                        src: blockSettings?.preview_url || blockSettings?.preview_placeholder
                                    }),
                                    imageLoading ? el('div', {className: 'verbato-loader'}) : null
                                )
                        ),
                        el('div', {
                                style: styles.wrapperStyle,
                            },
                            el('label', {
                                style: styles.labelStyle
                            }, 'Prompt',
                                el(
                                'textarea',
                                {
                                    style: {
                                        ...styles.inputStyle,
                                        ...styles.textareaStyle
                                    },
                                    onInput: handleFieldChange('prompt_text'),
                                    value: blockSettings.prompt_text
                                },
                            )), el('div', {
                                style: {
                                    ...styles.saveBtnContainer,
                                },
                            }, el('button', {
                                    disabled: isDisabled,
                                    onClick: handleSavePrompt,
                                    style: {
                                        ...styles.saveBtn,
                                        color: isDisabled ? 'hsla(0,0%,100%,.4)' : '#fff',
                                        cursor: isDisabled ? 'not-allowed' : 'pointer',
                                    }
                                },
                                'Save prompt'
                            ))
                        )
                    ),
                )
            );
        },
        save: function (props) {
            return el(
                'p',
                useBlockProps.save( {
                    style: {
                        ... blockStyle
                    },
                } ),
                __(
                    'Verbato widget',
                    'verbato'
                ),
                __(
                    'Verbato widget',
                    'verbato'
                )
            );
        },
    } );
} )(
    window.wp.blocks,
    window.wp.i18n,
    window.wp.element,
    window.wp.blockEditor,
);

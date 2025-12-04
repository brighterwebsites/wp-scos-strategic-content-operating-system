(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, CheckboxControl, SelectControl, ToggleControl } = wp.components;
    const { createElement: el, Fragment, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;

    registerBlockType('brighter/faq-selector', {
        title: __('FAQ Selector', 'brighterwebsites'),
        description: __('Display selected FAQs with customizable format and schema control', 'brighterwebsites'),
        icon: 'editor-help',
        category: 'common',
        attributes: {
            selectedFaqs: {
                type: 'array',
                default: [],
            },
            displayFormat: {
                type: 'string',
                default: 'accordion',
            },
            headingLevel: {
                type: 'string',
                default: 'h3',
            },
            enableSchema: {
                type: 'boolean',
                default: true,
            },
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { selectedFaqs, displayFormat, headingLevel, enableSchema } = attributes;
            const [allFaqs, setAllFaqs] = useState([]);
            const [loading, setLoading] = useState(true);

            // Fetch all FAQs on component mount
            useEffect(function() {
                wp.apiFetch({
                    path: '/brighter-core/v1/faqs'
                }).then(function(faqs) {
                    setAllFaqs(faqs);
                    setLoading(false);
                }).catch(function(error) {
                    console.error('Error fetching FAQs:', error);
                    setLoading(false);
                });
            }, []);

            function toggleFaq(faqId) {
                const newSelected = selectedFaqs.includes(faqId)
                    ? selectedFaqs.filter(function(id) { return id !== faqId; })
                    : selectedFaqs.concat([faqId]);
                setAttributes({ selectedFaqs: newSelected });
            }

            function moveUp(index) {
                if (index === 0) return;
                const newSelected = selectedFaqs.slice();
                const temp = newSelected[index - 1];
                newSelected[index - 1] = newSelected[index];
                newSelected[index] = temp;
                setAttributes({ selectedFaqs: newSelected });
            }

            function moveDown(index) {
                if (index === selectedFaqs.length - 1) return;
                const newSelected = selectedFaqs.slice();
                const temp = newSelected[index + 1];
                newSelected[index + 1] = newSelected[index];
                newSelected[index] = temp;
                setAttributes({ selectedFaqs: newSelected });
            }

            function removeFaq(index) {
                const newSelected = selectedFaqs.slice();
                newSelected.splice(index, 1);
                setAttributes({ selectedFaqs: newSelected });
            }

            // Get selected FAQ objects
            const selectedFaqObjects = selectedFaqs.map(function(id) {
                return allFaqs.find(function(faq) { return faq.id === id; });
            }).filter(Boolean);

            return el(Fragment, null,
                // Inspector Controls (Sidebar)
                el(InspectorControls, null,
                    el(PanelBody, { title: __('Display Settings', 'brighterwebsites'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Display Format', 'brighterwebsites'),
                            value: displayFormat,
                            options: [
                                { label: __('Accordion', 'brighterwebsites'), value: 'accordion' },
                                { label: __('Plain', 'brighterwebsites'), value: 'plain' },
                            ],
                            onChange: function(value) { setAttributes({ displayFormat: value }); }
                        }),
                        el(SelectControl, {
                            label: __('Heading Level', 'brighterwebsites'),
                            value: headingLevel,
                            options: [
                                { label: 'H2', value: 'h2' },
                                { label: 'H3', value: 'h3' },
                                { label: 'H4', value: 'h4' },
                                { label: 'P', value: 'p' },
                            ],
                            onChange: function(value) { setAttributes({ headingLevel: value }); },
                            help: __('Only applies to Plain format', 'brighterwebsites')
                        }),
                        el(ToggleControl, {
                            label: __('Enable Schema', 'brighterwebsites'),
                            checked: enableSchema,
                            onChange: function(value) { setAttributes({ enableSchema: value }); },
                            help: __('Disable to exclude schema for this block', 'brighterwebsites')
                        })
                    ),

                    el(PanelBody, { title: __('Select FAQs', 'brighterwebsites'), initialOpen: true },
                        loading
                            ? el('p', null, __('Loading FAQs...', 'brighterwebsites'))
                            : allFaqs.length === 0
                                ? el('p', null, __('No FAQs found. Create some FAQs first!', 'brighterwebsites'))
                                : allFaqs.map(function(faq) {
                                    return el(CheckboxControl, {
                                        key: faq.id,
                                        label: faq.question,
                                        checked: selectedFaqs.includes(faq.id),
                                        onChange: function() { toggleFaq(faq.id); }
                                    });
                                })
                    )
                ),

                // Block Preview
                el('div', { className: 'bw-faq-selector-editor', style: { border: '2px dashed #ccc', padding: '20px', borderRadius: '4px', background: '#f9f9f9' } },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '15px' } },
                        el('span', { className: 'dashicons dashicons-editor-help', style: { fontSize: '24px', marginRight: '10px' } }),
                        el('h3', { style: { margin: 0 } }, __('FAQ Selector', 'brighterwebsites'))
                    ),

                    selectedFaqObjects.length === 0
                        ? el('p', { style: { color: '#666', fontStyle: 'italic' } },
                            __('No FAQs selected. Select FAQs from the sidebar →', 'brighterwebsites'))
                        : el('div', null,
                            el('p', { style: { marginBottom: '10px', fontWeight: 'bold' } },
                                selectedFaqObjects.length + ' ' + __('FAQ(s) selected', 'brighterwebsites')
                            ),
                            el('ul', { style: { listStyle: 'none', padding: 0 } },
                                selectedFaqObjects.map(function(faq, index) {
                                    return el('li', {
                                        key: faq.id,
                                        style: {
                                            padding: '10px',
                                            marginBottom: '5px',
                                            background: '#fff',
                                            border: '1px solid #ddd',
                                            borderRadius: '4px',
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center'
                                        }
                                    },
                                        el('span', null, (index + 1) + '. ' + faq.question),
                                        el('div', { style: { display: 'flex', gap: '5px' } },
                                            index > 0 && el('button', {
                                                onClick: function() { moveUp(index); },
                                                className: 'button button-small',
                                                title: __('Move up', 'brighterwebsites')
                                            }, '↑'),
                                            index < selectedFaqObjects.length - 1 && el('button', {
                                                onClick: function() { moveDown(index); },
                                                className: 'button button-small',
                                                title: __('Move down', 'brighterwebsites')
                                            }, '↓'),
                                            el('button', {
                                                onClick: function() { removeFaq(index); },
                                                className: 'button button-small',
                                                style: { color: '#dc3232' },
                                                title: __('Remove', 'brighterwebsites')
                                            }, '×')
                                        )
                                    );
                                })
                            ),

                            el('p', { style: { marginTop: '15px', fontSize: '12px', color: '#666' } },
                                __('Format: ', 'brighterwebsites') + displayFormat +
                                (displayFormat === 'plain' ? ' (' + headingLevel.toUpperCase() + ')' : '') +
                                ' | ' +
                                __('Schema: ', 'brighterwebsites') + (enableSchema ? __('Enabled', 'brighterwebsites') : __('Disabled', 'brighterwebsites'))
                            )
                        )
                )
            );
        },

        save: function() {
            // Return null to use dynamic rendering (PHP callback)
            return null;
        },
    });
})(window.wp);

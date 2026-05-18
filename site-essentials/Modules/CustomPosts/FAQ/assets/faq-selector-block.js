/**
 * FAQ Selector block — editor UI
 *
 * Block name: brighter/faq-selector (retained for backward compatibility).
 * Owned by: site-essentials/Modules/CustomPosts/FAQ/
 *
 * Fetches FAQ list from /site-essentials/v1/faqs (editor-context, nonce-auth).
 * The previous brighter-core/v1/faqs route required a token and returned 401
 * inside the editor — that's the bug this file fixes.
 *
 * v1.0 | 2026-05-19
 */
(function (wp) {
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var components = wp.components;
	var PanelBody = components.PanelBody;
	var CheckboxControl = components.CheckboxControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var __ = wp.i18n.__;

	registerBlockType('brighter/faq-selector', {
		title: __('FAQ Selector', 'site-essentials'),
		description: __('Display selected FAQs with customizable format and schema control', 'site-essentials'),
		icon: 'editor-help',
		category: 'common',
		attributes: {
			selectedFaqs: { type: 'array', default: [] },
			displayFormat: { type: 'string', default: 'accordion' },
			headingLevel: { type: 'string', default: 'h3' },
			enableSchema: { type: 'boolean', default: true },
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var selectedFaqs = attributes.selectedFaqs || [];
			var displayFormat = attributes.displayFormat;
			var headingLevel = attributes.headingLevel;
			var enableSchema = attributes.enableSchema;

			var allFaqsState = useState([]);
			var allFaqs = allFaqsState[0];
			var setAllFaqs = allFaqsState[1];

			var loadingState = useState(true);
			var loading = loadingState[0];
			var setLoading = loadingState[1];

			var errorState = useState('');
			var error = errorState[0];
			var setError = errorState[1];

			var filterState = useState('');
			var filter = filterState[0];
			var setFilter = filterState[1];

			useEffect(function () {
				wp.apiFetch({ path: '/site-essentials/v1/faqs' })
					.then(function (faqs) {
						setAllFaqs(Array.isArray(faqs) ? faqs : []);
						setLoading(false);
					})
					.catch(function (err) {
						console.error('FAQ Selector: error fetching FAQs', err);
						setError((err && err.message) || __('Could not load FAQs.', 'site-essentials'));
						setLoading(false);
					});
			}, []);

			function toggleFaq(faqId) {
				var newSelected = selectedFaqs.indexOf(faqId) !== -1
					? selectedFaqs.filter(function (id) { return id !== faqId; })
					: selectedFaqs.concat([faqId]);
				setAttributes({ selectedFaqs: newSelected });
			}

			function moveUp(index) {
				if (index === 0) return;
				var newSelected = selectedFaqs.slice();
				var temp = newSelected[index - 1];
				newSelected[index - 1] = newSelected[index];
				newSelected[index] = temp;
				setAttributes({ selectedFaqs: newSelected });
			}

			function moveDown(index) {
				if (index === selectedFaqs.length - 1) return;
				var newSelected = selectedFaqs.slice();
				var temp = newSelected[index + 1];
				newSelected[index + 1] = newSelected[index];
				newSelected[index] = temp;
				setAttributes({ selectedFaqs: newSelected });
			}

			function removeFaq(index) {
				var newSelected = selectedFaqs.slice();
				newSelected.splice(index, 1);
				setAttributes({ selectedFaqs: newSelected });
			}

			var selectedFaqObjects = useMemo(function () {
				return selectedFaqs
					.map(function (id) {
						return allFaqs.find(function (faq) { return faq.id === id; });
					})
					.filter(Boolean);
			}, [selectedFaqs, allFaqs]);

			var filteredFaqs = useMemo(function () {
				var q = (filter || '').toLowerCase().trim();
				if (!q) return allFaqs;
				return allFaqs.filter(function (faq) {
					return (faq.question || '').toLowerCase().indexOf(q) !== -1;
				});
			}, [allFaqs, filter]);

			return el(Fragment, null,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Display Settings', 'site-essentials'), initialOpen: true },
						el(SelectControl, {
							label: __('Display Format', 'site-essentials'),
							value: displayFormat,
							options: [
								{ label: __('Accordion', 'site-essentials'), value: 'accordion' },
								{ label: __('Plain', 'site-essentials'), value: 'plain' },
							],
							onChange: function (value) { setAttributes({ displayFormat: value }); },
						}),
						el(SelectControl, {
							label: __('Heading Level', 'site-essentials'),
							value: headingLevel,
							options: [
								{ label: 'H2', value: 'h2' },
								{ label: 'H3', value: 'h3' },
								{ label: 'H4', value: 'h4' },
								{ label: 'P', value: 'p' },
							],
							onChange: function (value) { setAttributes({ headingLevel: value }); },
							help: __('Only applies to Plain format', 'site-essentials'),
						}),
						el(ToggleControl, {
							label: __('Add to page FAQPage schema', 'site-essentials'),
							checked: enableSchema,
							onChange: function (value) { setAttributes({ enableSchema: value }); },
							help: __('When on, these FAQs are merged into the page schema @graph.', 'site-essentials'),
						})
					),

					el(PanelBody, { title: __('Select FAQs', 'site-essentials'), initialOpen: true },
						loading
							? el('p', null, __('Loading FAQs…', 'site-essentials'))
							: error
								? el('p', { style: { color: '#b32d2e' } }, error)
								: allFaqs.length === 0
									? el('p', null, __('No FAQs found. Create some FAQs first.', 'site-essentials'))
									: el(Fragment, null,
										el(TextControl, {
											label: __('Filter FAQs', 'site-essentials'),
											value: filter,
											onChange: function (value) { setFilter(value); },
											placeholder: __('Search by question…', 'site-essentials'),
										}),
										filteredFaqs.length === 0
											? el('p', { style: { color: '#666', fontStyle: 'italic' } },
												__('No FAQs match your filter.', 'site-essentials'))
											: filteredFaqs.map(function (faq) {
												return el(CheckboxControl, {
													key: faq.id,
													label: faq.question,
													checked: selectedFaqs.indexOf(faq.id) !== -1,
													onChange: function () { toggleFaq(faq.id); },
												});
											})
									)
					)
				),

				el('div', {
					className: 'bw-faq-selector-editor',
					style: { border: '2px dashed #ccc', padding: '20px', borderRadius: '4px', background: '#f9f9f9' },
				},
					el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '15px' } },
						el('span', { className: 'dashicons dashicons-editor-help', style: { fontSize: '24px', marginRight: '10px' } }),
						el('h3', { style: { margin: 0 } }, __('FAQ Selector', 'site-essentials'))
					),

					loading
						? el('p', null, __('Loading FAQs…', 'site-essentials'))
						: error
							? el('p', { style: { color: '#b32d2e' } }, error)
							: selectedFaqObjects.length === 0
								? el('p', { style: { color: '#666', fontStyle: 'italic' } },
									__('No FAQs selected. Select FAQs from the sidebar →', 'site-essentials'))
								: el('div', null,
									el('p', { style: { marginBottom: '10px', fontWeight: 'bold' } },
										selectedFaqObjects.length + ' ' + __('FAQ(s) selected', 'site-essentials')
									),
									el('ul', { style: { listStyle: 'none', padding: 0 } },
										selectedFaqObjects.map(function (faq, index) {
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
													alignItems: 'center',
												},
											},
												el('span', null, (index + 1) + '. ' + faq.question),
												el('div', { style: { display: 'flex', gap: '5px' } },
													index > 0 && el('button', {
														onClick: function () { moveUp(index); },
														className: 'button button-small',
														title: __('Move up', 'site-essentials'),
													}, '↑'),
													index < selectedFaqObjects.length - 1 && el('button', {
														onClick: function () { moveDown(index); },
														className: 'button button-small',
														title: __('Move down', 'site-essentials'),
													}, '↓'),
													el('button', {
														onClick: function () { removeFaq(index); },
														className: 'button button-small',
														style: { color: '#dc3232' },
														title: __('Remove', 'site-essentials'),
													}, '×')
												)
											);
										})
									),
									el('p', { style: { marginTop: '15px', fontSize: '12px', color: '#666' } },
										__('Format: ', 'site-essentials') + displayFormat +
										(displayFormat === 'plain' ? ' (' + headingLevel.toUpperCase() + ')' : '') +
										' | ' +
										__('Schema: ', 'site-essentials') + (enableSchema ? __('Enabled', 'site-essentials') : __('Disabled', 'site-essentials'))
									)
								)
				)
			);
		},

		save: function () {
			return null;
		},
	});
})(window.wp);

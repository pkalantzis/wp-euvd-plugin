/* EUVD Vulnerabilities Block (v0.2.1) - no-build editor script */
(function (wp) {
	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;

	// Provided by @wordpress/server-side-render package
	var ServerSideRender = wp.serverSideRender;

	registerBlockType('euvd/latest-vulnerabilities', {
		edit: function (props) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;

			var type = attributes.type || 'latest';
			var count = typeof attributes.count === 'number' ? attributes.count : 5;

			return el(
				wp.element.Fragment,
				{},
				InspectorControls
					? el(
							InspectorControls,
							{},
							el(
								PanelBody,
								{ title: 'EUVD Settings' },
								el(SelectControl, {
									label: 'Type',
									value: type,
									options: [
										{ label: 'Latest', value: 'latest' },
										{ label: 'Critical', value: 'critical' },
										{ label: 'Exploited', value: 'exploited' },
									],
									onChange: function (v) {
										setAttributes({ type: v });
									},
								}),
								el(RangeControl, {
									label: 'Count',
									value: count,
									min: 1,
									max: 100,
									onChange: function (v) {
										setAttributes({ count: v });
									},
								})
							)
					  )
					: null,
				ServerSideRender
					? el(ServerSideRender, {
							block: 'euvd/latest-vulnerabilities',
							attributes: { type: type, count: count },
					  })
					: el('p', {}, 'Loading…')
			);
		},
		save: function () {
			return null;
		},
	});
})(window.wp);
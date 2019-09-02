<div id="pages-controller-cms-content" class="has-panel cms-content flexbox-area-grow fill-width fill-height $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content" data-ignore-tab-state="true">
	$Tools

	<div class="fill-height flexbox-area-grow">
		<div class="cms-content-header north">
			<div class="cms-content-header-info flexbox-area-grow vertical-align-items fill-width">
				<% include SilverStripe\\Admin\\CMSBreadcrumbs %>
			</div>
		</div>

		<div class="flexbox-area-grow fill-height">
			<div style="padding:25px;">
				<% if $InfoMessage %><div style="max-width:600px;" class="mb-4 alert alert-{$InfoMessageCls}">$InfoMessage</div><% end_if %>
				$ExportForm
			</div>
		</div>
	</div>
</div>

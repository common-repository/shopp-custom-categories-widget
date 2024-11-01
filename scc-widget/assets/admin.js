$j = jQuery.noConflict();

// Add the admin stylesheet
$j("head").append('$css_link');

/**
 * Iterates through the checkboxes to rebuild the comma
 * separated inclusion/exclusion lists.
 */
function scc_update_lists(lists, checkboxes) {	
	// Find the inclusion input element and clear out
	inc = $j(lists).find(".inclist");
	$j(inc).val("");

	// Add the checked values to the correct lists
	$j(checkboxes).find("input").each(function() {
		if ($j(this).is(':checked')) {
			id = $j(this).val();
			$j(inc).val($j(inc).val()+","+id);
		}
	});
}

// Add checked options
function scc_change_logic(checklist) {
	// Sanity! Remove the directly bound listener
	if ($j(checklist).attr('onmouseover'))
		$j(checklist).removeAttr('onmouseover');
	
	// Add change handler
	$j(checklist).find("input").change(function() {
		lists = $j(this).parents(".widget-content").find(".scc_inclusion_lists");
		checkboxes = $j(this).parents(".scc_widget_checklist");
		scc_update_lists(lists, checkboxes);
	});
}

<?php
class SCC_Printer
{
	/**
	 * Styles of list.
	 * 
	 * @var enum
	 */
	const UL_FLAT = 1;
	const UL_HIERARCHY = 2;
	const DD_FLAT = 3;
	const DD_HIERARCHY =4;
	
	/**
	 * Container for the list of category objects
	 * 
	 * @var array
	 */
	protected $list;
	
	/**
	 * Indicates if empty categories should be working
	 * links.
	 * 
	 * @var mixed
	 */
	protected $link_empty = false;
	
	
	
	/**
	 * Outputs an HTML (with additional JS where required) representation
	 * of the list.
	 * 
	 * @param array $list
	 * @param const $style
	 * @param bool $link_empty = false
	 * @return SCC_Printer
	 */
	public function __construct(array $list, $style, $link_empty = false)
	{
		// Definitions
		$this->list = $list;
		$this->link_empty = (bool) $link_empty;
		$output = '';
		
		switch ($style)
		{
			// Hierarchical dropdown list
			case self::DD_HIERARCHY:
				$output = $this->dropdown(true);
				$this->add_redirect_behaviour();
			break;
			
			// Flat dropdown list 
			case self::DD_FLAT:
				$output = $this->dropdown(false);
				$this->add_redirect_behaviour();
			break;
			
			// Hierarchical unordered list
			case self::UL_HIERARCHY:
				$output = $this->unordered_list(true);
			break;
			
			// Default to a flat unordered list
			default: case self::UL_FLAT: 
				$output = $this->unordered_list(false);
			break;
		}
		
		// Allow the formatting to be altered
		echo apply_filters('scc_widget_output', $output);
	}
	
	
	/**
	 * Renders the list of categories as an HTML unordered list.
	 * Nested categories can optionally be represented as a set of
	 * nested lists if $hierarchical is set to true.
	 * 
	 * The $categories parameter is used for recursive purposes.
	 * 
	 * @param bool $hierarchical = false
	 * @param array $categories
	 * @return string
	 */
	protected function unordered_list($hierarchical = false, array $categories = null)
	{		
		// Flat, hierarchical or opening?
		if (is_null($categories) or $hierarchical) 
		{
			$output = '<ul class="scc_categories">';
		}
		else $output = '';
		
		// If $categories is null, set it to the value of the base list
		$categories = is_null($categories) ? $this->list : $categories;
		
		// Iterate through the categories
		foreach ($categories as $category)
		{
			// List item title
			$title = $this->list_item_title($category);
			
			// Open list item	
			$output .= '<li'.$title.'>';

			// Link and title
			$output .= $this->get_linked_name($category);
			
			// Any children?
			if (is_array($category->children) and !empty($category->children))
				$output .= $this->unordered_list($hierarchical, $category->children);
				
			// Close list item			
			$output .= '</li>';
		}
		
		// Flat, hierarchical or opening?
		if (is_null($categories) or $hierarchical)
		{
			return $output.'</ul>';
		}
		else return $output;
	}
	
	
	/**
	 * Returns a title attribute if the category object has a
	 * non-empty description, otherwise an empty string is returned.
	 * 
	 * @param ProductCategory $category
	 * @return string
	 */
	protected function list_item_title(ProductCategory $category)
	{
		if (property_exists($category, 'description'))
				$title = trim($category->description);
				
		if (empty($title)) $title = '';
		else $title = ' title="'.esc_attr($category->description).'" ';
		
		return $title;
	}
	
	
	/**
	 * Returns the category name. Unless $this->link_empty is false it will
	 * be returned as an HTML link.
	 * 
	 * @param ProductCategory $category
	 * @return string
	 */
	protected function get_linked_name(ProductCategory $category)
	{
		$output = esc_html($category->name);
		
		// Do we wish to link to the category?
		if ($this->link_empty or $category->count >= 1)
		{
			// Build the category link
			$link = get_term_link((int) $category->term_id, $category->taxonomy);
			if (is_wp_error($link)) $link = '';
			
			$output = '<a href="'.$link.'">'
			        . $output
			        . '</a>';
		}
		
		return $output;
	}
	
	
	/**
	 * Renders the list of categories as an HTML select element
	 * (dropdown). Markers (defaults to dashes) to convey hierarchy
	 * can optionally be added if $hierarchical is set to true.
	 * 
	 * The $categories parameter is used for recursive purposes.
	 * 
	 * @param bool $hierarchical = false
	 * @param array $categories = null
	 * @return string
	 */
	protected function dropdown($hierarchical = false, array $categories = null)
	{
		// Allow the markers (by default these are dashes) to be modified for each
		// level of the hierarchy
		$marker = apply_filters('scc-hierarchical-dashes', '&ndash;');
		
		// Opening select
		if ($categories === null) // Not on recursive calls
			$output = '<select class="scc_categories">';

		// If $categories is null, set it to the value of the base list
		$categories = is_null($categories) ? $this->list : $categories;
		
		// Iterate through the categories
		foreach ($categories as $category)
		{
			// Do we wish to link to the category?
			if ($this->link_empty or $category->count >= 1)
			{
				// Build the category link
				$link = get_term_link((int) $category->term_id, $category->taxonomy);
				if (is_wp_error($link)) $link = '';
			}
			
			// Category name
			$name = esc_html($category->name);
			
			// Add hierarchical markers
			if ($hierarchical)
				$name = str_repeat($marker, $category->depth)
					.$name;
			
			// Create the option
			$output .= '<option value="'.$link.'">'
				.$name.'</option>';
				
			// Any children?
			if (is_array($category->children) and !empty($category->children))
				$output .= $this->dropdown($hierarchical, $category->children);
		}
		
		// Close off at the end of the initial call
		if ($categories === null)
		{
			return $output.'</select>';
		}	
		else return $output; // For recursive calls
	}
	
	
	/**
	 * Inserts a small piece of Javascript used to handle clicks
	 * on dropdown options.
	 */
	protected function add_redirect_behaviour()
	{
		// Keep count of the number of calls to this method
		static $count = 0;
		
		// On the first call only, embed our JS
		if ($count === 0)
			add_action('wp_footer', array($this, 'redirect_script'), 100);
		
		// Increment our call counter
		$count++;
	}
	
	
	/**
	 * Outputs a JS snippet to deal with redirects. jQuery is required for 
	 * this to work successfully.
	 */
	public function redirect_script()
	{
		echo <<<HTML
<script type="text/javascript"> /* <![CDATA[ */
	jQuery(document).ready(function() {
		jQuery("select.scc_categories").change(function() { 
			var url = jQuery("select.scc_categories").val().toString();
			if (url.length > 0) window.location.replace(url);
		});
	});
/* ]]> */ </script>
HTML;
	}
}
<?php
/*
Plugin Name: Shopp Custom Categories Widget
Version: 0.5.5
Description: Works with Shopp 1.2 to provide a highly customizable category widget that compliments or can replace the category widget shipped with Shopp.
Author: Barry Hughes
Author URI: http://freshlybakedwebsites.net

	Shopp Custom Categories Widget
    Copyright (C) 2012 Barry Hughes

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
*/


/**
 * Creates a highly customizable categories widget allowing site
 * admins to easily configure which categories should be displayed
 * and how they should render.
 *
 * @copyright (2012) Barry Hughes
 * @license GPL v3
 * @version 0.5.0
 */
class SCC_Widget extends WP_Widget
{
	/**
	 * List of all Shopp product categories.
	 *
	 * @var array
	 */
	protected $shopp_categories = array();
	
	/**
	 * List of all Shopp product category IDs.
	 * 
	 * @var array
	 */
	protected $shopp_category_ids = array();

	/**
	 * Plugin environmentals.
	 */
	public $plugin_dir;
	public $asset_url;
	
	/**
	 * Number of times this class has been instantiated.
	 *
	 * @var int
	 */
	protected static $instances = 0;

	/**
	 * Widget instance options.
	 * 
	 * @var array
	 */
	protected $options = array();
	
	/**
	 * List of IDs to be included.
	 * 
	 * @var array
	 */
	protected $inclusion = array();
	
	/**
	 * List of IDs to be excluded.
	 * 
	 * @var array
	 */
	protected $exclusion = array();

	

	/**
	 * Ensures that Shopp exists (version 1.2 or higher) then registers
	 * the widget.
	 */
	public static function register()
	{
		if (defined('SHOPP_VERSION') and version_compare(SHOPP_VERSION, '1.2', '>='))
			register_widget('SCC_Widget');
	}


	/**
	 * Builds a new instance of the Shopp Custom Categories Widget.
	 *
	 * @return SCC_Widget
	 */
	public function __construct()
	{
		// Increment the object counter
		self::$instances++;

		// Self locate
		$this->plugin_dir = dirname(__FILE__);
		$this->asset_url = WP_PLUGIN_URL.'/'.basename($this->plugin_dir).'/assets';
		
		// Dependencies
		require $this->plugin_dir.'/inc/categories.php';
		require $this->plugin_dir.'/inc/printer.php';
		
		// Declare the widget title and description
		parent::__construct(
			false, __('Shopp Custom Categories'),
			array('description' => __('A configurable Shopp categories widget. Targets Shopp 1.2.'))
		);
	}


	/**
	 * Filters updates to ensure data integrity.
	 *
	 * @param array $new
	 * @param array $old
	 * @return array
	 */
	public function update(array $new, array $old)
	{
		$this->load_categories();
		
		// Validate and cleanup the ID lists
		$this->options = $new;
		$this->build_list();
		
		$new['exc_list'] = implode(',', $this->exclusion);
		$new['inc_list'] = implode(',', $this->inclusion);
		
		return $new;
	}


	/**
	 * Renders the widget user interface.
	 *
	 * @param array $options
	 */
	public function form(array $options) 
	{		
		// Save the options
		$this->options = (array) $options;

		// Setup
		$this->load_categories();
		$this->build_list();
		
		// Form/UI
		$this->inputs();
		$this->checklist($this->shopp_categories, array());
		$this->options();
		
		// UI styles and scripts
		add_action('admin_footer', array($this, 'ui_behaviours'));
	}
	
	
	/**
	 * Grabs a list of Shopp product categories and forms a second
	 * list of category IDs.
	 */
	protected function load_categories()
	{
		// Do not do this task unnecessarily
		if (!empty($this->shopp_categories) and !empty($this->shopp_category_ids))
			return;
			
		$categories = new SCC_Categories;
		$this->shopp_categories = $categories->get_flattened_list();
		
		foreach ($this->shopp_categories as $category)
			$this->shopp_category_ids[] = $category['id'];
	}
	
	
	/**
	 * Builds array representations of the inclusion and exclusion
	 * lists.
	 */
	protected function build_list()
	{
		$this->inclusion = $this->actual_categories_filter(
			$this->extract_ids_to_array($this->options['inc_list']));
	}
	
	
	/**
	 * Takes a comma separated list and places the resulting
	 * values in an array. Empty values are not included.
	 * 
	 * @param string $string
	 * @return array
	 */
	protected function extract_ids_to_array($string)
	{
		$array = array();
		$elements = explode(',', $string);
		
		foreach ($elements as $item)
		{
			$item = trim($item);
			if (!empty($item) and !in_array($item, $array))
				$array[] = $item;
		}
		
		return $array;
	}
	
	
	/**
	 * Takes the provided array (of IDs) and returns a new array
	 * containing only those original IDs which are valid Shopp
	 * product categories.
	 * 
	 * @param array $id_list
	 * @return array
	 */
	protected function actual_categories_filter(array $id_list)
	{
		$array = array();
		
		foreach ($id_list as $item)
			if (in_array($item, $this->shopp_category_ids))
				$array[] = $item;
				
		return $array;
	}

	
	/**
	 * Display the CSV inputs for categories to include and
	 * exclude (these will normally be hidden).
	 */
	public function inputs()
	{
		// Form values and labels
		$inc_list_id    = $this->get_field_id('inc_list');
		$inc_list_label = __('Include (Comma Separated IDs)');
		$inc_list_name  = $this->get_field_name('inc_list');
		$includes       = $this->options['inc_list'];
		
		$title_id    = $this->get_field_id('title');
		$title_label = __('Widget title');
		$title_name  = $this->get_field_name('title');
		$title       = $this->options['title'];
		
		echo <<<HTML
			<div class="scc_inclusion_lists"> <p>
				<label for="$inc_list_name">$inc_list_label</label> 
				<input class="widefat inclist" id="$inc_list_id" name="$inc_list_name" type="text" 
				value="$includes" />
			</p> </div>
			
			<p>
				<label for="$title_name">$title_label</label>
				<input class="widefat" id="$title_id" name="$title_name" type="text" value="$title" />
			</p>
HTML;

	}
	

	/**
	 * Forms a checklist. $list should be an array of objects each
	 * with "id" and "name" properties, with $selected contaning the 
	 * IDs of all selected items.
	 *
	 * @param array $list
	 * @param array $selected
	 */
	public function checklist(array $list, array $selected)
	{
		// Open the inclusion checklist
		echo '<div class="scc_widget_checklist" onmouseover="scc_change_logic(this)">';
		
		// Labels
		$category = __('Categories to include');

		// Header table
		echo <<<HTML
			<table class="header"> <thead>
					<th>$category</th>
			</thead> </table>
HTML;

		// Striping variable
		$zebra = 0;

		// List table
		echo '<div class="scrollable"> <table>';
		
		// Run through the list
		foreach ($list as $item)
		{
			// To stripe or not to stripe
			$stripe = (++$zebra % 2) == 0 ? ' class="zebra"' : '';

			// Item status
			$check = in_array($item['id'], $this->inclusion) ? 'checked="checked"' : '';
			$val = $item['id'];
			
			// Row name
			$name = $item['name'];
			for ($i = 0; $i < $item['depth']; $i++)
				$name = '- '.$name;
				
			// Row
			echo <<<HTML
				<tr $stripe>
					<td class="chk"> <input type="checkbox" name="inc[]" value="$val" $check> </td>	
					<td class="cat"> $name </td>		
				</tr>
HTML;
		}
		
		// Close off
		echo '</table> </div> </div>'; // .scrollable and .scc_widget_checklist
	}

	
	/**
	 * Displays additional configuration options.
	 * 
	 * @param array $options
	 */
	public function options($options)
	{
		// Form values and labels		
		$format_id    = $this->get_field_id('format');
		$format_label = __('Formatting options');
		$format_name  = $this->get_field_name('format');
		$format       = $this->options['format'];
		
		$opt_1 = __('Flat list');
		$opt_2 = __('Hierarchical list');
		$opt_3 = __('Dropdown');
		$opt_4 = __('Hierarchical dropdown');
		
		$chk_1 = $format == SCC_Printer::UL_FLAT ? 'selected="selected"' : '';
		$chk_2 = $format == SCC_Printer::UL_HIERARCHY ? 'selected="selected"' : '';
		$chk_3 = $format == SCC_Printer::DD_FLAT ? 'selected="selected"' : '';
		$chk_4 = $format == SCC_Printer::DD_HIERARCHY ? 'selected="selected"' : '';
		
		$empties_id    = $this->get_field_id('empties');
		$empties_label = __('Link to empty categories');
		$empties_name  = $this->get_field_name('empties');
		$empties       = $this->options['empties'] == 'on' ? 'checked="checked"' : '';
		
		echo <<<HTML
			<p>
				<label for="$format_name">$format_label</label> <br />
				<select name="$format_name" id="$format_id">
					<option value="1" $chk_1>$opt_1</option>
					<option value="2" $chk_2>$opt_2</option>
					<option value="3" $chk_3>$opt_3</option>
					<option value="4" $chk_4>$opt_4</option>
				</select>
			</p>
			
			<p>
				<input type="checkbox" name="$empties_name" id="$empties_id" value="on" $empties />
				<label for="$empties_name">$empties_label</label>
			</p>
HTML;
	}
	

	/**
	 * Loads scripts required by the widget user interface.
	 */
	public function ui_behaviours()
	{
		// Build CSS link
		$css_link = $this->asset_url.'/admin.css';
		$css_link = '<link href="'.$css_link.'" rel="stylesheet" type="text/css">';
		
		// Load the behavioural script (and inject the $css_link)
		$js = file_get_contents($this->plugin_dir.'/assets/admin.js');
		$js = str_replace('$css_link', $css_link, $js);
		
		// Footer script
		echo '<script type="text/javascript">'.$js.'</script>';
	}


	/**
	 * Renders the public facing widget.
	 */
	public function widget(array $args, array $options)
	{
		global $Shopp;
		extract($args);

		// Setup
		$this->options = (array) $options;
		$this->load_categories();
		$this->build_list();
		
		// Open the widget markup
		$title = apply_filters('widget_title', $options['title']);
		echo $before_widget.$before_title.$title.$after_title;
		
		// Create the category list
		$categories = new SCC_Categories();
		$categories->refactor($this->inclusion);
		
		// Render
		$link_empty = $this->options['empties'] == 'on' ? true : false;
		new SCC_Printer($categories->structure, $this->options['format'], $link_empty);
		
		// Close the widget markup
		echo $after_widget;
	}
}


// Hook in our registration method
add_action('widgets_init', array('SCC_Widget', 'register'));
<?php
class SCC_Categories
{
	/**
	 * Container for the basic Shopp Categories list.
	 * Destroyed after construction routines run.
	 * 
	 * @var array
	 */
	protected $shopp_categories;
	
	/**
	 * Hierarchical structure of category based on IDs and
	 * parent/child relationships.
	 * 
	 * @var array
	 */
	public $structure = array();
	
	
	
	/**
	 * Builds and organizes a list of Shopp Categories.
	 * 
	 * @return SCC_Categories
	 */
	public function __construct()
	{
		$this->shopp_categories = shopp_product_categories(array('orderby' => 'name'));
		$this->organize_structurally();
		
		// Clean up
		unset($this->shopp_categories);
	}
	
	
	/**
	 * Builds a list of categories based on their hierarchy.
	 */
	protected function organize_structurally(array $collection)
	{
		$this->get_base_categories();
		$this->marshall_into($this->structure, 1);
	}
	
	
	/**
	 * Copies parent categories across to the $this->structure
	 * array. Additionally appends a depth property to each applicable
	 * category (since they are all base categories, this will be 0).
	 */
	protected function get_base_categories()
	{
		foreach ($this->shopp_categories as $key => $category)
			if ($category->parent == 0)
			{
				unset($category->meta);
				$category->depth = 0;
				$this->structure[$key] = $category;
			}
	}
	
	
	/**
	 * Scans $this->shopp_categories for children of objects already
	 * in the $target array. 
	 * 
	 * The optional depth parameter indicates how many levels deep in 
	 * the hierarchy we are (this will then be used to append a depth 
	 * property to each category object).
	 * 
	 * @param mixed $target
	 * @param mixed $depth = 0
	 */
	protected function marshall_into($target, $depth = 0)
	{
		foreach ($target as $key => $category)
			foreach ($this->shopp_categories as $child_key => $child_obj)
				if ($child_obj->parent == $key)
				{
					unset($child_obj->meta);
					$child_obj->depth = $depth;
					$target[$key]->children[$child_key] = $child_obj;
					$this->marshall_into($target[$key]->children, $depth + 1);
				}
	}
	
	
	/**
	 * Returns an array of Shopp product categories. Each element
	 * is comprised of an associative array with the following stucture:
	 * 
	 * 		id
	 * 		name
	 * 		slug
	 * 		depth
	 * 
	 * @param array $source
	 * @return array
	 */
	public function get_flattened_list(array $source = null)
	{
		$category_list = array();
		if ($source === null) $source = $this->structure;
		
		foreach ($source as $category)
		{
			$category_list[] = array(
				'depth' => $category->depth,
				'id' => $category->id,
				'name' => $category->name,
				'slug' => $category->slug
			);
			
			if (is_array($category->children) and !empty($category->children))
				$category_list = array_merge($category_list, $this->get_flattened_list($category->children));
		}
		
		return $category_list;
	}
	
	
	/**
	 * Reduces the $this->structure array to contain only those
	 * category objects whose IDs are listed in $include.
	 */
	public function refactor(array $include)
	{
		$this->structure = $this->rationalize_to($include, $this->structure);
	}
	
	
	/**
	 * Reduces $source according to the list of IDs in $include.
	 * 
	 * This serves to avoid hierarchical orphans and revises
	 * the structure of $this->structure appropriately.
	 * 
	 * @param array $include
	 * @param array $source
	 * @return array
	 */
	protected function rationalize_to(array $include, array $source)
	{	
		// Container for the revised array
		$target = array();

		// Scan the source 
		foreach ($source as $key => $element)
		{
			// Record the parent depth
			$depth = $element->depth;
			
			// Match?
			if (in_array($key, $include))
			{
				// Save to the target array
				$target[$key] = $element;
				
				// Do we need to scan this element also
				if (is_array($element->children) and !empty($element->children))
					 $target[$key]->children = $this->rationalize_to($include, $element->children);
			}
			// No match? Scan any children
			elseif (is_array($element->children) and !empty($element->children))
			{
				$children = (array) $this->rationalize_to($include, $element->children);
				
				if (!empty($children))
					foreach ($children as $key => $element)
					{
						$element->depth = $depth;
						$target[$key] = $element;
					}
			}
		}
		
		return $target;
	}
}

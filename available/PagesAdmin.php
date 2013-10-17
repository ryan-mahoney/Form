<?php
/* Copyright 2011, 2012 Virtue Center for Art & Technology */
namespace virtuecenter\all\admin;
use vc\pf as VCPF;

class PagesAdmin extends VCPF\DOMFormTable {
	public $storage = array(
		'collection' => 'pages',
		'key' => '_id'
	);
	public $fieldsetTemplate = 'vc\ms\site\admin\PagesAdmin';
	public static $categories = false;
	public static $categoriesTried = false;
	public $thing = 'Page';
	public $things = 'Pages';
	public $filterClass = '\vc\ms\site\form\PageFilterAdmin';
	
	function created_dateField() {
		return VCPF\DOMFormTableArray::createdDate();
	}
	
	function titleField () {
		return array(
			'name' => 'title',
			'label' => 'Page Name (for Admin)',
			'required' => true,
			'display' => VCPF\Field::inputText()			
		);
	}
	
	function code_nameField () {
		return array_merge(
			VCPF\DOMFormTableArray::codename('title', 'pages'),
			[
				'path' => '/page/',
				'selector' => '#title-field input',
				'mode' => 'after'
			]
		);
	}
	
	function page_titleField () {
		return array(
			'name' => 'page_title',
			'label' => 'Browser Title',
			'required' => false,
			'display' => VCPF\Field::inputText(),
			
		);
	}
	
	function meta_desField () {
		return array(
			'name' => 'meta_des',
			'label' => 'Meta Description',
			'required' => false,
			'display' => VCPF\Field::textarea()			
		);
	}
	
	function meta_keyField () {
		return array(
			'name' => 'meta_key',
			'label' => 'Meta Keywords',
			'required' => false,
			'display' => VCPF\Field::textarea()			
		);
	}
		
	function parentPageField () {
		return array(
			'name' => 'parentPage',
			'label' => 'Parent Page',
			'required' => false,
			'options' => function () {
				return VCPF\Model::db('pages')->find()->fetchAllGrouped('_id', 'title');
			},
			'display' => VCPF\Field::select(),
			'nullable'=> true
		);
	}
	
	function headerField () {
		return array(
			'name' => 'header',
			'label' => 'Heading',
			'required' => false,
			'display' => VCPF\Field::inputText()
		);
	}
	
	function headerShortField () {
		return array(
			'name' => 'header_short',
			'label' => 'Short Heading',
			'required' => false,
			'display' => VCPF\Field::inputText()
		);
	}

	function categoryField () {
		return array(
			'name'		=> 'category',
			'label'		=> 'Category',
			'required'	=> false,
			'tooltip'	=> 'Add a category.',
			'options'	=> function () {
				return VCPF\Model::db('categories')->
					find(['section' => 'Pages'])->
					sort(array('title' => 1))->
					fetchAllGrouped('_id', 'title');
			},
			'display'	=> VCPF\Field::select(),
			'nullable'	=> true
		);
	}	

	function templateField () {
		return array(
			'name' => 'template',
			'label' => 'Admin Template',
			'required' => false,
			'options' => function () {			
				return unserialize(N__INTRANET_PAGE_TEMPLATE);
			},
			'display' => VCPF\Field::select()
		);
	}
	
	function html_templateField () {
		return array(
			'name' => 'html_template',
			'label' => 'Frontend Template',
			'required' => false,
			'options' => function () {
                $templates = VCPF\Model::db('lookup_templates')->
                    find(['tags' => 'pages'])->
                    sort(array('name' => 1))->
                    fetchAllGrouped('path', 'name');
                if (defined('N__INTRANET_PAGE_TEMPLATE_HTML')) {
					return unserialize(N__INTRANET_PAGE_TEMPLATE_HTML);
				} elseif (is_array($templates) && count($templates) > 0) {
                    return $templates;
                }
				return ['pageFindOne' => 'Default'];
			},
			'display' => VCPF\Field::select()
		);
	}

    function event_tagsField () {
        return array(
            'name' => 'event_tags',
            'label' => 'Tags of Events to be included',
            'required' => false,
            'transformIn' => function ($data) {
                return VCPF\Regex::csvToArray($data);
            },
            'display' => VCPF\Field::inputToTags(),
            'autocomplete' => function () {
                return VCPF\Model::mongoDistinct('events', 'tags');
            }
        );
    }

    function blog_tagsField () {
        return array(
            'name' => 'blog_tags',
            'label' => 'Tags of Blogs to be included',
            'required' => false,
            'transformIn' => function ($data) {
                return VCPF\Regex::csvToArray($data);
            },
            'display' => VCPF\Field::inputToTags(),
            'autocomplete' => function () {
                return VCPF\Model::mongoDistinct('blogs', 'tags');
            }
        );
    }

	function defaultTable () {
		return array (
			'columns' => array(				
				array('title', '30%', 'Title', false),
				array('category', '25%', 'Category', function ($data) {
					if (empty($data)) {
						return;
					}
					if (PagesAdmin::$categories === false && PagesAdmin::$categoriesTried === false) {
						PagesAdmin::$categoriesTried = true;
						PagesAdmin::$categories = VCPF\Model::db('categories')->
							find(['section' => 'Pages'], ['_id', 'title'])->
							fetchAllGrouped('_id', 'title');
					}					
					if (isset(PagesAdmin::$categories[$data])) {
						return PagesAdmin::$categories[$data];
					}
				}),
				['created_date', '25%', 'Created Date', function ($data) {
					if (empty($data)) {
						 return '';
					}
					return date('m/d/Y', $data->sec);
				}],
			),
			'title' => 'Pages',
			'link' => 'title',
			'features' => array('delete', 'search', 'add', 'edit', 'pagination', 'sortable'),
			'sort' => array('created_date' => -1)
		);
	}
}
<?php
/* Copyright 2011, 2012 Virtue Center for Art & Technology */
namespace virtuecenter\all\admin;
use vc\pf as VCPF;

class CategoriesAdmin extends VCPF\DOMFormTable {
	public $storage = array(
		'collection' => 'categories',
		'key' => '_id'
	);
	public $fieldsetTemplate = 'vc\ms\site\admin\CategoryAdmin';
	public $thing = 'Category';
	public $things = 'Categories';

	function sectionField () {
		return array(
			'name' => 'section',
			'label' => 'Section',
			'required' => true,
			'display' => VCPF\Field::inputText()
		);
	}

	function titleField () {
		return array(
			'name' => 'title',
			'label' => 'Title',
			'required' => true,
			'display' => VCPF\Field::inputText()
		);
	}
	
	function tagsField () {
		return array(
			'name' => 'tags',
			'label' => 'Tags for Categories',
			'required' => false,
			'transformIn' => function ($data) {
				return VCPF\Regex::csvToArray($data);
			},
			'display' => VCPF\Field::inputToTags(),
			'autocomplete' => function () {
				return VCPF\Model::mongoDistinct('categories', 'tags');
			},
			'tooltip' => 'Another way to make entries more findable.'
		);
	}	
	

	function imageField () {
		return array(
			'name' => 'image',
			'label' => 'Image',
			'display' => VCPF\Field::inputFile()
		);
	}

	function featuredField () {
		return array(
			'name' => 'featured',
			'label' => false,
			'required' => false,
			'options' => array(
				't' => 'Yes',
				'f' => 'No'
			),
			'display' => VCPF\Field::inputRadioButton(),
			'default' => 'f'
		);
	}
	
	function templateField () {
		return [
			'name' => 'template',
			'label' => 'Type',
			'required' => true,
			'options' => function () {
				$templates = VCPF\Config::category()['templates'];
				if (!is_array($templates) || count($templates) == 0) {
					$templates = ['__vc__ms__site__admin__CategoryAdmin' => 'Basic'];
				}
				return $templates;
			},
			'display' => VCPF\Field::select(),
			//'nullable' => 'Choose a Template'
		];
	}
	
	public function subcategoryField() {
		return array(
			'name' => 'subcategory',
			'label' => 'Sub Category',
			'required' => false,
			'display'	=>	VCPF\Field::admin(),
			'adminClass'	=> 'vc\ms\site\subdocuments\CategorySubAdmin'
		);
	}
	
	function code_nameField () {
		return array_merge(
			VCPF\DOMFormTableArray::codename('title', 'categories'),
			[
				'path' => '/category/',
				'selector' => '#title-field input',
				'mode' => 'after'
			]
		);
	}

	function defaultTable () {
		return array (
			'columns' => array(
				array('section', '25%', 'Section', false),
				array('title', '70%', 'Title', false)
			),
			'title' => 'Categories',
			'link' => 'title',
			'sort' => array('section' => 1, 'title' => 1),
			'features' => array('delete', 'search', 'add', 'edit', 'pagination', 'sortable')
		);
	}
}
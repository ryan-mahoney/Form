<?php
/* Copyright 2011, 2012 Virtue Center for Art & Technology */
namespace virtuecenter\all\admin;
use vc\pf as VCPF;
use vc\cl\SolrIndexor as SolrIndexor;

class MenusAdmin extends VCPF\DOMFormTable {
	public $storage = [
		'collection' => 'menus',
		'key' => '_id'
	];
	public $fieldsetTemplate = '\vc\ms\site\admin\MenuAdmin';
	public $thing = 'Navigation Menu';
	public $things = 'Navigation Menus';

	function labelField () {
		return [
			'name'		=> 'label',
			'label'		=> 'Label',
			'required'	=> true,
			'display'	=> VCPF\Field::inputText()
		];
	}	

	function urlField () {
		return [
			'name'		=> 'url',
			'label'		=> 'URL',
			'required'	=> false,
			'display'	=> VCPF\Field::inputText()
		];
	}

	function loggedinUrlField () {
		return [
			'name'		=> 'loggedin_url',
			'label'		=> 'Logged In Url',
			'required'	=> false,
			'display'	=> VCPF\Field::inputText()
		];
	}

	function submenuClassField () {
		return [
			'name'		=> 'submenu_class',
			'label'		=> 'Submenu Class',
			'required'	=> false,
			'display'	=> VCPF\Field::inputText()
		];
	}

	function imageField () {
		return [
			'name' => 'file',
			'label' => 'Image',
			'display' => VCPF\Field::inputFile()
		];
	}

	public function linkField() {
		return [
			'name' => 'link',
			'label' => 'Link',
			'required' => false,
			'display'	=>	VCPF\Field::admin(),
			'adminClass'	=> '\vc\ms\site\subdocuments\LinkSubAdmin'
		];
	}

	function defaultTable () {
		return [
			'columns' => [
				['label', '60%', 'Label', false]
			],
			'title' => 'Navigation Menus',
			'link' => 'Label',
			'sort' => ['sort_key' => 1, 'label' => 1],
			'features' => ['delete', 'search', 'add', 'edit', 'pagination', 'sortable']
		];
	}
}
<?php
namespace virtuecenter\all\admin;
use vc\pf as VCPF;

class PracticeAreasAdmin extends VCPF\DOMFormTable {
	public $fieldsetTemplate = 'vc\ms\legal\admin\PracticeAdmin';
	public $storage = array(
		'collection' => 'practice_areas',
		'key' => '_id'
	);	
	public static $categories = array();
	public $thing = 'Practice';
	public $things = 'Practices';	
	
	function code_nameField () {
		return VCPF\DOMFormTableArray::codename('name', 'practice_areas');
	}
	
	function created_dateField() {
		return VCPF\DOMFormTableArray::createdDate();
	}
	
	function naemField () {
		return array(
			'name' => 'name',
			'label' => 'Name',
			'required' => true,
			'display' => VCPF\Field::inputText()
		);
	}

	function summaryField () {
		return array(
			'name' => 'summary',
			'label' => 'Short Description',
			'required' => false,
			'display' => VCPF\Field::textarea()
		);
	}

	function descriptionField () {
		return [
			'name' => 'description',
			'label' => 'Description',
			'required' => false,
			'display' => VCPF\Field::ckeditor()
		];
	}		
	
	function defaultTable () {
		return array (
			'columns' => array(
				['name', '30%', 'Name', false],
				['summary', '40%', 'Short Description', false]
			),
			'title' => 'Practice Areas',
			'link' => 'name',
			'features' => array('delete', 'search', 'add', 'edit', 'pagination', 'sortable')
		);
	}
}
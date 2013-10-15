<?php
class contact {
	public function __construct ($field) {
		$this->field = $field;
	}
	public $storage = [
		'collection'	=> 'contacts',
		'key'			=> '_id'
	];
	public $saveRedirect = '/page/contact_receipt.html';

	function first_nameField() {
		return [
			'name'		=> 'first_name',
			'display'	=> $this->field->inputText(),
			'required' 	=> true
		];
	}
	
	function last_nameField() {
		return [
			'name'		=> 'last_name',
			'label'		=> 'Last Name',
			'display'	=> $this->field->inputText(),
			'required'	=> true
		];
	}

	function phoneField() {
		return [
			'name'		=> 'phone',
			'label'		=> 'Phone',
			'display'	=> $this->field->inputText(),
			'required'	=> true
		];
	}
	
	function emailField() {
		return [
			'name'		=> 'email',
			'label'		=> 'Email',
			'display'	=> $this->field->inputText(),
			'required'	=> true
		];
	}
	
	function messageField() {
		return [
			'name'		=> 'message',
			'label'		=> 'Message',
			'display'	=> $this->field->textarea(),
			'required'	=> true
		];
	}
}
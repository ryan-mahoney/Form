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
			'placeholder' => 'First Name',
			'display'	=> $this->field->inputText(),
			'required' 	=> true
		];
	}
	
	function last_nameField() {
		return [
			'name'		=> 'last_name',
			'placeholder' => 'Last Name',
			'label'		=> 'Last Name',
			'display'	=> $this->field->inputText(),
			'required'	=> true
		];
	}

	function phoneField() {
		return [
			'name'		=> 'phone',
			'placeholder'	=> 'Phone',
			'display'	=> $this->field->inputText(),
			'required'	=> true
		];
	}
	
	function emailField() {
		return [
			'name'		=> 'email',
			'placeholder'		=> 'Email Address',
			'display'	=> $this->field->inputText(),
			'required'	=> true
		];
	}
	
	function messageField() {
		return [
			'name'		=> 'message',
			'placeholder'		=> 'Enter your message here',
			'display'	=> $this->field->textarea(),
			'required'	=> true
		];
	}
}
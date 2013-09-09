<?php
class contact {
	use Form;
	
	public $storage = [
		'collection' => 'contacts',
		'key' => '_id'
	];
	public $saveRedirect = '/page/contact_receipt.html';

	function first_nameField() {
		return [
			'name'=>'first_name',
			'label'=>'First Name',
			'display'=> Field::inputText(),
			'required' => true
		];
	}
	
	function last_nameField() {
		return [
			'name'=>'last_name',
			'label'=>'Last Name',
			'display'=> Field::inputText(),
			'required' => true
		];
	}

	function phoneField() {
		return [
			'name'=>'phone',
			'label'=>'Phone',
			'display'= Field::inputText(),
			'required' => true
		];
	}
	
	function emailField() {
		return [
			'name'=>'email',
			'label'=>'Email',
			'display'= Field::inputText(),
			'required' => true
		];
	}
	
	function messageField() {
		return [
			'name'=>'message',
			'label'=>'Message',
			'display'= Field::textarea(),
			'required' => true
		];
	}

	function documentSaved () {		
		//return VCDC\contact\Library::documentSaved();
	}
}
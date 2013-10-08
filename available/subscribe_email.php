<?php
use Form\Form;
use Field\Field;

class subscribe_email {
	use Form;

	public $storage = [
		'collection'	=> 'contacts',
		'key'			=> '_id'
	];
	public $saveRedirect = '/page/subscribe_receipt.html';

	function emailField() {
		return [
			'name'		=> 'email',
			'label'		=> 'Email',
			'display'	=> Field::inputText(),
			'required'	=> true
		];
	}
	
	function documentSaved () {		
		//return VCDC\contact\Library::documentSaved();
	}
}
<?php
namespace Form;

class FormModel {
	private $db;

	public function __construct ($db) {
		$this->db = $db;
	}

	public static function delete ($form, $id) {}

	public function post ($form) {
		$id = self::documentSave($form, $_POST[$form->marker]);
		$form->activeRecord = self::documentFindOne($form, $id);
		self::jsonResponse($form);
	}

	public static function get ($form, $id) {
		$form->activeRecord = self::documentFindOne($form, $id);
	}

	private static function jsonResponse ($form) {
		echo json_encode([
			'success' => 1,
			'errors' => (array)$form->errors,
			'notices' => (array)$form->notices,
			'alerts' => (array)$form->alerts,
			'marker' => $form->marker,
			'id' => (string)$form->activeRecord['_id'],
			'updateJSFunction' => (isset($form->updateJSFunction) ? $form->updateJSFunction : ''),
			'saveJSFunction' => (isset($form->saveJSFunction) ? $form->saveJSFunction : ''),
			'appendJSFunction' => (isset($form->appendJSFunction) ? $form->appendJSFunction : ''),
			'updateJSFunctionContinue' => (isset($form->updateJSFunctionContinue) ? $form->updateJSFunctionContinue : ''),
			'saveJSFunctionContinue' => (isset($form->saveJSFunctionContinue) ? $form->saveJSFunctionContinue : ''),
			'appendJSFunctionContinue' => (isset($form->appendJSFunctionContinue) ? $form->appendJSFunctionContinue : ''),
			'saveRedirect' => (isset($form->saveRedirect) ? $form->saveRedirect : ''),
			'updateRedirect' => (isset($form->updateRedirect) ? $form->updateRedirect : '')
		], JSON_HEX_AMP);
	}
	
	private static function applyFieldTransformationIn ($admin, &$request, $mode, $fieldCheck=false, $parentId=false) {
		foreach ($admin->fields as $field) {
			if ($fieldCheck !== false && $field['name'] != $fieldCheck) {
				continue;
			}
			if (!isset($field['transformIn'])) {
				continue;
			}
			if (!isset($request[$admin->marker][$field['name']])) {
				continue;
			}
			$function = $field['transformIn'];
			$request[$admin->marker][$field['name']] = $function($request[$admin->marker][$field['name']], $request[$admin->marker], $mode, $field, $admin, $parentId);
		}
	}
	
	private static function documentSave (&$admin, &$post) {
		if (empty($post['id'])) {
			$post['id'] = new \MongoId();
		}
		if (!isset($admin->storage['collection']) || empty($admin->storage['collection'])) {
			throw new \Exception('Can not save document: no collection specified in admin.');
		}
		if (self::documentValidate($admin, $post)) {
			self::applyFieldTransformationIn($admin, $post, 'save');
			//try {
			//	self::callCallback($admin, 'documentSave', $post);
				$this->db->collection($admin->storage['collection'])->
					update(
						['_id' => $this->db->id($post['id'])], 
						['$set' => self::noKeyForUpate((array)$post)], 
						['safe' => true, 'fsync' => true, 'upsert' => true]);
			//	self::callCallback($admin, 'documentSaved', $post);
				$admin->notices[] = 'Record has been saved.';
			//} catch (Exception $e) {
			//	$admin->errors[] = $e->getMessage();
			//	return;
			//}
			return $post['id'];
		}
	}

	private static function noKeyForUpate ($document) {
		unset ($document['_id']);
		unset ($document['id']);
		return $document;
	}

	private static function callCallback ($admin, $key, &$document) {
		if (isset($admin->{$key}) && is_array($admin->{$key})) {
			foreach ($admin->{$key} as $callback) {
				$callback($admin, $document);
			}
		} else {
			$function = $admin->{$key}();
			$function($admin, $document);
		}
	}
	
	private static function documentFindOne ($form, $id) {
		$document = DB::collection($form->storage['collection'])->findOne(['_id' => DB::id($id)], []);
		self::documentTransformOut($form, $document);
		return $document;
	}
	
	private static function documentTransformOut ($admin, &$document) {
		foreach ($admin->fields as &$field) {
			if (!isset($field['transformOut'])) {
				continue;
			}
			if (isset($field['transformOutDone'])) {
				continue;
			}
			$field['transformOutDone'] = true;
			$function = $field['transformOut'];
			$document[$field['name']] = $function($document[$field['name']]);
		}
	}

	public static function documentValidate ($admin, &$post) {
		$passed = true;
		foreach ($admin->fields as $field) {
            if (!isset($field['label']) || empty($field['label']) == '') {
            	if (isset($field['errorLabel'])) {
 	               $field['label'] = $field['errorLabel'];
 	            } else {
 	            	$field['label'] = ucwords(str_replace('_', ' ', $field['name']));
 	            }
            }
			if (isset($field['required']) && is_callable($field['required'])) {
				$required = $field['required'];
				$field['required'] = $required($post[$field['name']], $post);
			}
			if (isset($field['required']) && $field['required'] == true) {
				if (!self::fieldValidateRequired ($field, $post)) {
					$passed = false;
					$admin->errors[] = $field['label'] . ' must have a value.';
					continue;
				}
			}
			if (isset($field['validate'])) {
				$validate = $field['validate'];
				$error = $validate($post[$field['name']], $post);
				if ($error !== true) {
					$passed = false;
					$admin->errors[] = $field['label'] . ': ' . $error;
				}
			}
		}
		return $passed;
	}

	private static function fieldValidateRequired ($field, &$post) {
		if (isset($post[$field['name']])) {
			if (is_array($post[$field['name']])) {
				if (count($post[$field['name']]) == 0) {
					return false;
				}
			} elseif (trim($post[$field['name']]) == '') {
				return false;
			}
		} else {
			return false;
		}
		return true;
	}
}
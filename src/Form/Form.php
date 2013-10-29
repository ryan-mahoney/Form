<?php
namespace Form;

class Form {
	private $root;
	private $field;
	private $response;
	private $post;
	private $db;

	public function __construct ($root, $field, $post, $db, $response) {
		$this->root = $root;
		$this->field = $field;
		$this->post = $post;
		$this->response = $response;
		$this->db = $db;
	}

	public function factory ($form, $id=false, $bundle='') {
		if (empty($bundle)) {
			$class = $this->root . '/../forms/' . $form . '.php';
		} else {
			$class = $this->root . '/../bundles/' . $bundle . '/forms/' . $form . '.php';
		}
		if (!file_exists($class)) {
			throw new \Exception ($class . ': unknown file.');
		}
		require_once($class);
		if (!class_exists($form)) {
			throw new \Exception ($form . ': unknown class.');
		}
		$formObject = new $form($this->field);
		$formObject->fields = $this->parseFieldMethods($formObject);
		$formObject->marker = strtolower(str_replace('\\', '__', $form));
		$formObject->document = new \ArrayObject();
		if ($id !== false) {
			$document = $this->db->collection($formObject->storage['collection'])->findOne([
				'_id' => $this->db->id($id)
			]);
			if (isset($document['_id'])) {
				$formObject->document = new \ArrayObject($document);
			}
		}
		if ($id === false) {
			$formObject->id = new \MongoId();
		} else {
			$formObject->id = new \MongoId((string)$id);
		}
		return $formObject;
	}

	public function parseFieldMethods ($object) {
		$reflector = new \ReflectionClass($object);
		$methods = $reflector->getMethods();
		$fields = [];
		foreach ($methods as $method) {
			if (preg_match('/Field$/', (string)$method->name) == 0) {
				continue;
			}
			$data = new \ArrayObject($method->invoke($object));
			$fields[$data['name']] = $data;
		}
		return $fields;
	}

	public function json ($formObject) {
		$out = [];
		foreach ($formObject->fields as $field) {
            if (!isset($field['display'])) {
            	continue;
            }
	        if (isset($formObject->document[$field['name']])) {
            	$field['data'] = $formObject->document[$field['name']];
            	if (isset($field['transformOut'])) {
	            	$function = $field['transformOut'];
					$field['data'] = $function($field['data'], $formObject);
				}
            }
            $field['marker'] = $formObject->marker;
            $field['__CLASS__'] = get_class($formObject);
            $method = $field['display'];
            ob_start();
            $method($field, $formObject);
            $out[$field['name']] = ob_get_clean();
        }
        $out['id'] = '<input type="hidden" name="' . $formObject->marker . '[id]" value="' . (string)$formObject->id . '" />';
        return json_encode($out, JSON_PRETTY_PRINT);
	}

    public function sanitize ($formObject) {
		if ($this->post->{$formObject->marker} === false) {
			throw new \Exception('form not in post');
		}
		$formPost = $formObject->marker;
		foreach ($formObject->fields as $field) {
			if (!isset($field['transformIn'])) {
				continue;
			}
			if (!isset($formPost[$field['name']])) {
				continue;
			}
			$function = $field['transformIn'];
			$formPost[$field['name']] = $function($formPost[$field['name']], $formPost);
		}
	}

	public function validate ($formObject) {
		$passed = true;
		$formPost = $this->post->{$formObject->marker};
		foreach ($formObject->fields as $field) {
            if (!isset($field['label']) || empty($field['label']) == '') {
            	if (isset($field['errorLabel'])) {
 	            	$field['label'] = $field['errorLabel'];
 	            } else {
 	            	$field['label'] = ucwords(str_replace('_', ' ', $field['name']));
 	            }
            }
			if (isset($field['required']) && is_callable($field['required'])) {
				$required = $field['required'];
				$field['required'] = $required($formPost[$field['name']], $formPost);
			}
			if (isset($field['required']) && $field['required'] == true) {
				if (!self::fieldValidateRequired ($field, $formPost)) {
					$passed = false;
					$this->post->errorFieldSet($formObject->marker, $field['label'] . ' must have a value.', $field['name']);
					continue;
				}
			}
			if (isset($field['validate'])) {
				$validate = $field['validate'];
				$error = $validate($formPost[$field['name']], $formPost);
				if ($error !== true) {
					$passed = false;
					$this->post->errorFieldSet($formObject->marker, $field['label'] . ': ' . $error, $field['name']);
				}
			}
		}
		return $passed;
	}

	private static function fieldValidateRequired ($field, $formPost) {
		if (isset($formPost[$field['name']])) {
			if (is_array($formPost[$field['name']])) {
				if (count($formPost[$field['name']]) == 0) {
					return false;
				}
			} elseif (trim($formPost[$field['name']]) == '') {
				return false;
			}
		} else {
			return false;
		}
		return true;
	}

	public function responseSuccess ($formObject) {
		$response = [
			'success' => true
		];
		if (!isset($formObject->after)) {
			$formObject->after = 'notice';
		}
		$response['after'] = $formObject->after;
		switch ($formObject->after) {
			case 'redirect':
				$response['redirect'] = $formObject->redirect;
				break;

			case 'notice':
				$response['notice'] = $formObject->notice;
				if (!isset($formObject->noticeDetails)) {
					$formObject->noticeDetails = '';
				}
				$response['noticeDetails'] = $formObject->noticeDetails;
				break;

			case 'function':
				$response['function'] = $formObject->function;
				break;
		}
		$this->response->body = json_encode($response, JSON_HEX_AMP);
	}

	public function responseError () {
		$this->response->body = json_encode([
			'success' => false,
			'errors' => $this->post->errorsGet()
		], JSON_HEX_AMP);
	}
}
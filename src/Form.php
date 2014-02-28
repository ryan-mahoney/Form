<?php
/**
 * Opine\Form
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Opine;

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

    public function after ($mode, $data) {
        switch ($mode) {
            case 'function':
                $this->formObject->after = 'function';
                $this->formObject->function = $data;
                break;

            case 'notice':
                $this->formObject->after = 'notice';
                $this->formObject->notice = $data;
                break;

            case 'redirect':
                $this->formObject->after = 'redirect';
                $this->formObject->redirect = $data;
                break;

            default:
                throw new \Exception('Unknown form "after" mode set');
        }
    }

    public function factory ($form, $dbURI=false, $bundle='', $path='forms', $namespace='Form\\') {
        if (empty($bundle)) {
            $class = $this->root . '/../' . $path . '/' . $form . '.php';
        } else {
            $class = $this->root . '/../bundles/' . $bundle . '/' . $path . '/' . $form . '.php';
        }
        if (!file_exists($class)) {
            throw new \Exception ($class . ': unknown file.');
        }
        require_once($class);
        if (empty($bundle)) {
            $className = $namespace . $form;
        } else {
            $className = $bundle . '\\' . $namespace . $form; 
        }
        if (!class_exists($className)) {
            throw new \Exception ($className . ': unknown class.');
        }
        $formObject = new $className($this->field);
        $formObject->fields = $this->parseFieldMethods($formObject);
        $formObject->db = $this->db;
        $formObject->marker = strtolower(str_replace('\\', '__', $form));
        $formObject->document = new \ArrayObject();
        if ($dbURI !== false) {
            $document = $this->db->documentStage($dbURI)->current();
            if (isset($document['_id'])) {
                $formObject->document = new \ArrayObject($document);
            }
        }
        if ($dbURI === false) {
            $formObject->id = $formObject->storage['collection'] . ':' . new \MongoId();
        } else {
            $formObject->id = $dbURI;
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
            } else {
                if (isset($field['default'])) {
                    $default = $field['default'];
                    if (is_callable($default)) {
                        $field['data'] = $default($field);
                    } else {
                        $field['data'] = $default;
                    }
                }
            }
            $field['marker'] = $formObject->marker;
            $out[$field['name']] = $this->field->render($field['display'], $field, $formObject->document, $formObject->fields);
        }
        if (isset($formObject->document['modified_date'])) {
            $out['modified_date'] = self::date($formObject->document['modified_date']);
        }
        if (isset($formObject->document['created_date'])) {
            $out['created_date'] = self::date($formObject->document['created_date']);
        }
        $out['id_spare'] = (string)new \MongoId();
        $out['id'] = '<input type="hidden" name="' . $formObject->marker . '[id]" value="' . (string)$formObject->id . '" />';
        return json_encode($out, JSON_PRETTY_PRINT);
    }

    private static function date ($date) {
        if (is_object($date)) {
            $date = date('c', $date->sec);
        } elseif (empty($date)) {
            $date = '';
        } else {
            $date = date('c', strtotime($date));
        }
        return $date;
    }

    public function sanitize ($formObject) {
        if ($this->post->{$formObject->marker} === false) {
            throw new \Exception('form not in post');
        }
        $formPost = $this->post->{$formObject->marker};
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
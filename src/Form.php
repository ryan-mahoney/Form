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
    private $post;
    private $db;
    private $formStorage;
    private $separation;
    private $topic;
    private $showTopics = false;
    private $showMarker = false;

    public function __construct ($root, $field, $post, $db, $separation, $topic) {
        $this->root = $root;
        $this->field = $field;
        $this->post = $post;
        $this->db = $db;
        $this->formStorage = [];
        $this->separation = $separation;
        $this->topic = $topic;
    }

    public function showTopics () {
        $this->showTopics = true;
    }

    public function showMarker () {
        $this->showMarker = true;
    }

    public function stored ($form) {
        if (!isset($this->formStorage[$form])) {
            return false;
        }
        return $this->formStorage[$form];
    }

    public function after ($form, $mode, $data) {
        $form = $this->stored($form);
        if ($form === false) {
            return false;
        }
        switch ($mode) {
            case 'function':
                $form->after = 'function';
                $form->function = $data;
                break;

            case 'notice':
                $form->after = 'notice';
                $form->notice = $data;
                break;

            case 'redirect':
                $form->after = 'redirect';
                $form->redirect = $data;
                break;

            default:
                throw new \Exception('Unknown form "after" mode set');
        }
    }

    public function factory ($path, $dbURI=false) {
        $type = $this->formType($path);
        $class = array_pop(explode('/', rtrim($path, '.php')));
        $bundle = false;
        $className = $this->bundleNameSpace($path, $bundle) . $type . '\\' . $class;
        if (!class_exists($className)) {
            throw new FormUnknownException($className . ': unknown class.');
        }
        $formObject = new $className($this->field);
        $formObject->fields = $this->parseFieldMethods($formObject);
        $formObject->db = $this->db;
        $formObject->marker = str_replace('\\', '__', $className);
        $formObject->bundle = $bundle;
        if ($this->showMarker === true) {
            echo 'Form marker: ', $formObject->marker, "\n";
        }
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
        $this->formStorage[$formObject->marker] = $formObject;
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
        return json_encode($response, JSON_HEX_AMP);
    }

    public function responseError () {
        return json_encode([
            'success' => false,
            'errors' => $this->post->errorsGet()
        ], JSON_HEX_AMP);
    }

    private function validatePaths (Array &$paths) {
        foreach (['form', 'layout', 'app'] as $type) {
            if (!isset($paths[$type])) {
                throw new FormPathException('Can not find ' . $type);
            }
        }
        if (!isset($paths['class'])) {
            $type = $this->formType($paths['form']);
            $class = array_pop(explode('/', rtrim($paths['form'], '.php')));
            $paths['class'] = $this->bundleNameSpace($paths['form']) . $type . '\\' . $class;
        }
        if (!isset($paths['name'])) {
            $paths['name'] = $class;
        }
    }

    private function formType ($form) {
        if (substr_count($form, 'managers/') > 0) {
            return 'Manager';
        }
        return 'Form';
    }

    private function bundleNameSpace ($path, &$bundle='') {
        $parts = explode('/', $path);
        $length = count($parts);
        for ($i=0; $i < $length; $i++) {
            if ($parts[$i] == 'bundles') {
                $bundle = $parts[($i + 1)];
                return $bundle . '\\';
            }
        }
        return '';
    }

    public function view (Array $paths, $id=false) {
        $this->validatePaths($paths);
        $this->separation->app($paths['app'])->
            layout($paths['layout'])->
            args($paths['name'], ['id' => $id])->
            template()->
            write();
    }

    public function viewJson ($formClass, $id=false) {
        $formObject = $this->form->factory($form, $id);
        echo $this->form->json($formObject, $id);
    }

    public function upsert ($formClass, $id=false) {
        $formObject = $this->factory($formClass, $id);
        if ($id === false) {
            if (isset($this->post->{$formObject->marker}['id'])) {
                $id = $this->post->{$formObject->marker}['id'];
            } else {
                throw new \Exception('ID not supplied in post.');
            }
        }
        $context = [
            'dbURI' => $id,
            'formMarker' => $formObject->marker,
            'formObject' => $formObject
        ];
        if (!$this->validate($formObject)) {
            return $this->responseError();
        }
        $this->sanitize($formObject);
        $topic = $formObject->marker . '-save';
        $this->showTopic($topic);
        $this->topic->publish($topic, $context);
        $this->topic->publish('Form-save', $context);
        if ($formObject->bundle !== false) {
            $this->topic->publish($formObject->bundle . '-Form-save');
        }
        if ($this->post->statusCheck() == 'saved') {
            $topic = $formObject->marker . '-saved';
            $this->topic->publish('Form-saved', $context);
            if ($formObject->bundle !== false) {
                $this->topic->publish($formObject->bundle . '-Form-saved');
            }
            $this->showTopic($topic);
            $this->topic->publish($topic, $context);
            return $this->responseSuccess($formObject);
        } else {
            return $this->responseError();    
        }
    }

    private function showTopic ($topic) {
        if ($this->showTopics === false) {
            return;
        }
        echo $topic, "\n";
    }

    public function delete ($formClass, $id) {
        $formObject = $this->factory($formClass, $id);
        if ($id === false) {
            throw new \Exception('ID not supplied in post.');
        }
        $context = [
            'dbURI' => $id,
            'formMarker' => $formObject->marker,
            'formObject' => $formObject
        ];
        if (!$this->validate($formObject)) {
            return $this->responseError();
        }
        $this->sanitize($formObject);
        $topic = $formObject->marker . '-delete';
        $this->showTopic($topic);
        $this->topic->publish($topic, $context);
        $this->topic->publish('Form-delete', $context);
        if ($formObject->bundle !== false) {
            $this->topic->publish($formObject->bundle . '-Form-delete');
        }
        if ($this->post->statusCheck() == 'deleted') {
            $topic = $formObject->marker . '-deleted';
            $this->topic->publish('Form-deleted', $context);
            if ($formObject->bundle !== false) {
                $this->topic->publish($formObject->bundle . '-Form-deleted');
            }
            $this->showTopic($topic);
            $this->topic->publish($topic, $context);
            return $this->responseSuccess($formObject);
        } else {
            return $this->responseError();    
        }
    }

    public function markerToClassPath ($marker) {
        $pieces = explode('__', $marker);
        $count = count($pieces);
        $type = $pieces[($count - 2)];
        if ($type == 'Form') {
            $type = 'forms';
        } elseif ($type == 'Manager') {
            $type = 'managers';
        } else {
            throw new FormUnknownTypeException($marker);
        }
        if ($count == 2) {
            return $type . '/' . $pieces[1] . '.php';
        } elseif ($count == 3) {
            return 'bundles/' . $pieces[0] . '/' . $type . '/' . $pieces[2] . '.php';
        } else {
            throw new FormBadMarkerException($marker);
        }
    }
}

class FormUnknownException extends \Exception {}
class FormUnknownTypeException extends \Exception {}
class FormBadMarkerException extends \Exception {}
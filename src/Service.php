<?php
/**
 * Opine\Form\Service
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
namespace Opine\Form;
use ArrayObject;
use MongoId;
use ReflectionClass;
use Exception;

class Service {
    private $root;
    private $field;
    private $post;
    private $db;
    private $formStorage;
    private $topic;
    private $collection;
    private $showTopics = false;
    private $showMarker = false;

    public function __construct ($root, $field, $post, $db, $collection, $topic) {
        $this->root = $root;
        $this->field = $field;
        $this->post = $post;
        $this->db = $db;
        $this->formStorage = [];
        $this->topic = $topic;
        $this->collection = $collection;
    }

    public function tokenHashGet ($formObject) {
        return md5($this->root . session_id() . get_class($formObject));
    }

    private function tokenHashMatch (Array $input, $formObject) {
        if (!isset($input['form-token'])) {
            return false;
        }
        if ($input['form-token'] != $this->tokenHashGet($formObject)) {
            return false;
        }
        return true;
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
                throw new Exception('Unknown form "after" mode set');
        }
    }

    public function factory ($formObject, $dbURI=false) {
        $formObject->field = $this->field;
        $formObject->fields = $this->parseFieldMethods($formObject);
        $formObject->db = $this->db;
        $formObject->marker = str_replace('\\', '__', get_class($formObject));
        $tmp = explode('__', $formObject->marker);
        $firstPiece = array_shift($tmp);
        $formObject->bundle = (!in_array($firstPiece, ['Form', 'Manager'])) ? $firstPiece : '';
        $formObject->token = $this->tokenHashGet($formObject);
        if ($this->showMarker === true) {
            echo 'Form marker: ', $formObject->marker, "\n";
        }
        $formObject->document = new ArrayObject();
        if (!empty($dbURI)) {
            $document = $this->db->documentStage($dbURI)->current();
            if (isset($document['_id'])) {
                $formObject->document = new ArrayObject($document);
            }
        }
        if ($dbURI === false) {
            if (property_exists($formObject, 'storage')) {
                $formObject->id = $formObject->storage['collection'] . ':' . new MongoId();
            } elseif (property_exists($formObject, 'collection')) {
                $collection = $formObject->collection;
                $collection = $this->collection->factory(new $collection());
                $formObject->id = $collection->collection . ':' . new MongoId();
            } else {
                throw new Exception('Can not generate dbURI, unknown storage for form');
            }
        } else {
            $formObject->id = $dbURI;
        }
        $this->formStorage[$formObject->marker] = $formObject;
        return $formObject;
    }

    public function parseFieldMethods ($object) {
        $reflector = new ReflectionClass($object);
        $methods = $reflector->getMethods();
        $fields = [];
        foreach ($methods as $method) {
            if (preg_match('/Field$/', (string)$method->name) == 0) {
                continue;
            }
            $data = new ArrayObject($method->invoke($object));
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
        $out['id_spare'] = (string)new MongoId();
        $out['id'] = '<input type="hidden" name="' . $formObject->marker . '[id]" value="' . (string)$formObject->id . '" />';
        $out['form-token'] = '<input type="hidden" name="' . $formObject->marker . '[form-token]" value="' . $formObject->token . '" />';
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
            throw new Exception('form not in post');
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
        if (!$this->tokenHashMatch((array)$formPost, $formObject)) {
            $passed = false;
            $this->post->errorFieldSet($formObject->marker, 'Invalid form submission.');        
        }
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
        http_response_code(400);
        return json_encode([
            'success' => false,
            'errors' => $this->post->errorsGet()
        ], JSON_HEX_AMP);
    }

    private function formType ($form) {
        if (substr_count($form, 'managers/') > 0) {
            return 'Manager';
        }
        return 'Form';
    }

    public function viewJson ($formObject, $id=false) {
        $formObject = $this->form->factory($formObject, $id);
        echo $this->form->json($formObject, $id);
    }

    public function upsert ($formObject, $id=false) {
        $formObject = $this->factory($formObject, $id);
        if ($id === false) {
            if (isset($this->post->{$formObject->marker}['id'])) {
                $id = $this->post->{$formObject->marker}['id'];
            } else {
                throw new Exception('ID not supplied in post.');
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
        if (isset($formObject->bundle) && !empty($formObject->bundle)) {
            $this->topic->publish($formObject->bundle . '-Form-save', $context);
        }
        if (isset($formObject->topicSave) && !empty($formObject->topicSave)) {
            $this->topic->publish($formObject->topicSave, $context);
        }
        if ($this->post->statusCheck() == 'saved') {
            $topic = $formObject->marker . '-saved';
            $this->topic->publish('Form-saved', $context);
            if (isset($formObject->bundle) && !empty($formObject->bundle)) {
                $this->topic->publish($formObject->bundle . '-Form-saved', $context);
            }
            if (isset($formObject->topicSaved) && !empty($formObject->topicSaved)) {
                $this->topic->publish($formObject->topicSaved, $context);
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

    public function delete ($formObject, $id, $token) {
        $formObject = $this->factory($formObject, $id);
        if ($id === false) {
            throw new Exception('ID not supplied in post.');
        }
        if (!$this->tokenHashMatch(['form-token' => $token], $formObject)) {
            $passed = false;
            $this->post->errorFieldSet($formObject->marker, 'Invalid form submission.');
            return $this->responseError();      
        }
        $context = [
            'dbURI' => $id,
            'formMarker' => $formObject->marker,
            'formObject' => $formObject
        ];
        $topic = $formObject->marker . '-delete';
        $this->showTopic($topic);
        $this->topic->publish($topic, $context);
        $this->topic->publish('Form-delete', $context);
        if (isset($formObject->bundle) && !empty($formObject->bundle)) {
            $this->topic->publish($formObject->bundle . '-Form-delete', $context);
        }
        if (isset($formObject->topicDelete) && !empty($formObject->topicDelete)) {
            $this->topic->publish($formObject->topicDelete, $context);
        }
        if ($this->post->statusCheck() == 'deleted') {
            $topic = $formObject->marker . '-deleted';
            $this->topic->publish('Form-deleted', $context);
            if (isset($formObject->bundle) && !empty($formObject->bundle)) {
                $this->topic->publish($formObject->bundle . '-Form-deleted', $context);
            }
            if (isset($formObject->topicDeleted) && !empty($formObject->topicDeleted)) {
                $this->topic->publish($formObject->topicDeleted, $context);
            }
            $this->showTopic($topic);
            $this->topic->publish($topic, $context);
            return $this->responseSuccess($formObject);
        } else {
            return $this->responseError();    
        }
    }

    public function markerToClassName ($marker) {
        return str_replace('__', '\\', $marker);
    }

    public function markerToClass ($marker) {
        $class = str_replace('__', '\\', $marker);
        return new $class();
    }
}
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
use stdClass;
use Opine\Interfaces\Topic as TopicInterface;
use Opine\Interfaces\DB as DBInterface;
use Opine\Interfaces\Route as RouteInterface;

class Service {
    private $root;
    private $route;
    private $post;
    private $db;
    private $formStorage;
    private $formModel;
    private $topic;
    private $collection;

    public function __construct ($root, $formModel, RouteInterface $route, $post, DBInterface $db, $collection, TopicInterface $topic) {
        $this->root = $root;
        $this->route = $route;
        $this->post = $post;
        $this->formModel = $formModel;
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

            case 'refresh':
                $form->after = 'refresh';
                break;

            default:
                throw new Exception('Unknown form "after" mode set');
        }
    }

    public function factory ($formName, $dbURI=false) {
        $formObject = new stdClass();
        $form = $this->formModel->cacheGetForm($formName);
        if ($form === false) {
            throw new Exception('Can not get: ' . $formName . ' from cache');
        }
        foreach ($form as $key => $value) {
            $formObject->{$key} = $value;
        }
        $formObject->token = $this->tokenHashGet($formObject);
        $formObject->document = new ArrayObject();
        if (!empty($dbURI)) {
            $document = $this->db->document($dbURI)->current();
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
                $formObject->id = '';
            }
        } else {
            $formObject->id = $dbURI;
        }
        $this->formStorage[$formObject->slug] = $formObject;
        return $formObject;
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
                    $field['data'] = $this->route->serviceMethod($field['transformOut'], $field['data'], $formObject);
                }
            } else {
                if (isset($field['default'])) {
                    if (substr_count($field['default'], '@') == 1) {
                        $field['data'] = $this->route->serviceMethod($field['default'], $field, $formObject);
                    } else {
                        $field['data'] = $default;
                    }
                }
            }
            $out[$field['name']] = $this->route->serviceMethod($field['display'], $field, $formObject->document, $formObject);
        }
        if (isset($formObject->document['modified_date'])) {
            $out['modified_date'] = self::date($formObject->document['modified_date']);
        }
        if (isset($formObject->document['created_date'])) {
            $out['created_date'] = self::date($formObject->document['created_date']);
        }
        $out['id_spare'] = (string)new MongoId();
        $out['id'] = '<input type="hidden" name="' . $formObject->slug . '[id]" value="' . (string)$formObject->id . '" />';
        $out['form-token'] = '<input type="hidden" name="' . $formObject->slug . '[form-token]" value="' . $formObject->token . '" />';
        $out['form-marker'] = $formObject->slug;
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
        $formPost = $this->post->get($formObject->slug);
        if ($formPost === false) {
            throw new Exception('form not in post');
        }
        foreach ($formObject->fields as $field) {
            if (!isset($field['transformIn'])) {
                continue;
            }
            if (!isset($formPost[$field['name']])) {
                continue;
            }
            $formPost[$field['name']] = $this->route->serviceMethod($field['transformIn'], $field, $formObject, $formPost);
        }
    }

    public function validate ($formObject) {
        $passed = true;
        $formPost = $this->post->get($formObject->slug);
        if (!$this->tokenHashMatch((array)$formPost, $formObject)) {
            $passed = false;
            $this->post->errorFieldSet($formObject->slug, 'Invalid form submission.');
        }
        foreach ($formObject->fields as $field) {
            if (!isset($field['label']) || empty($field['label']) == '') {
                if (isset($field['errorLabel'])) {
                    $field['label'] = $field['errorLabel'];
                } else {
                    $field['label'] = ucwords(str_replace('_', ' ', $field['name']));
                }
            }
            if (isset($field['required']) && substr_count($field['required'], '@') == 1) {
                $field['required'] = $this->route->serviceMethod($field['required'], $field, $formObject, $formPost);
            }
            if (isset($field['required']) && $field['required'] == true) {
                if (!self::fieldValidateRequired ($field, $formPost)) {
                    $passed = false;
                    $this->post->errorFieldSet($formObject->slug, $field['label'] . ' must have a value.', $field['name']);
                    continue;
                }
            }
            if (isset($field['validate'])) {
                $validate = $field['validate'];
                $error = $this->route->serviceMethod($field['validate'], $field, $formObject, $formPost);
                if ($error !== true) {
                    $passed = false;
                    $this->post->errorFieldSet($formObject->slug, $field['label'] . ': ' . $error, $field['name']);
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
        $response = array_merge($this->post->responseFieldsGet(), $response);
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

    public function viewJson ($form, $id=false) {
        $formObject = $this->form->factory($form, $id);
        echo $this->form->json($formObject, $id);
    }

    public function upsert ($form, $id=false) {
        $formObject = $this->factory($form, $id);
        if ($id === false) {
            $formPost = $this->post->get($formObject->slug);
            if (isset($formPost['id'])) {
                $id = $formPost['id'];
            } else {
                throw new Exception('ID not supplied in post.');
            }
        }
        $context = [
            'dbURI' => $id,
            'formMarker' => $formObject->slug,
            'formObject' => $formObject
        ];
        if (!$this->validate($formObject)) {
            return $this->responseError();
        }
        $this->sanitize($formObject);
        $topic = 'FORM:SAVE';
        $this->topic->publish($topic . ':' . $formObject->slug, new ArrayObject($context));
        $this->topic->publish($topic, new ArrayObject($context));
        if (isset($formObject->topicSave) && !empty($formObject->topicSave)) {
            $this->topic->publish($formObject->topicSave, new ArrayObject($context));
        }
        if ($this->post->statusCheck() == 'saved') {
            $topic = 'FORM:SAVED';
            $this->topic->publish($topic . ':' . $formObject->slug, new ArrayObject($context));
            $this->topic->publish($topic, new ArrayObject($context));
            if (isset($formObject->topicSaved) && !empty($formObject->topicSaved)) {
                $this->topic->publish($formObject->topicSaved, new ArrayObject($context));
            }
            return $this->responseSuccess($formObject);
        } else {
            return $this->responseError();
        }
    }

    public function delete ($form, $id, $token) {
        $formObject = $this->factory($form, $id);
        if ($id === false) {
            throw new Exception('ID not supplied in post.');
        }
        if (!$this->tokenHashMatch(['form-token' => $token], $formObject)) {
            $passed = false;
            $this->post->errorFieldSet($formObject->slug, 'Invalid form submission.');
            return $this->responseError();
        }
        $context = [
            'dbURI' => $id,
            'formMarker' => $formObject->slug,
            'formObject' => $formObject
        ];
        $topic = 'FORM:DELETE';
        $this->topic->publish($topic . ':' . $formObject->slug, new ArrayObject($context));
        $this->topic->publish($topic, new ArrayObject($context));
        if (isset($formObject->topicDelete) && !empty($formObject->topicDelete)) {
            $this->topic->publish($formObject->topicDelete, new ArrayObject($context));
        }
        if ($this->post->statusCheck() == 'deleted') {
            $topic = 'FORM:DELETED';
            $this->topic->publish($topic, new ArrayObject($context));
            $this->topic->publish($topic . ':' . $formObject->slug, new ArrayObject($context));
            if (isset($formObject->topicDeleted) && !empty($formObject->topicDeleted)) {
                $this->topic->publish($formObject->topicDeleted, new ArrayObject($context));
            }
            return $this->responseSuccess($formObject);
        } else {
            return $this->responseError();
        }
    }
}
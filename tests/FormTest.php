<?php
namespace Opine\Form;

use PHPUnit_Framework_TestCase;
use Opine\Config\Service as Config;
use Opine\Container\Service as Container;
use stdClass;

class FormTest extends PHPUnit_Framework_TestCase {
    private $db;
    private $root;
    private $formRoute;
    private $formModel;
    private $form;
    private $post;
    private $topic;
    private $contactId = 'contacts:538887dbed88e5a5527c1ef5';
    private $blogId = 'blogs:538887dded88e5a5527c1ef6';
    private $contactPost = [
        'first_name' => 'Test',
        'last_name' => 'Test',
        'phone' => '555-555-5555',
        'email' => 'test@test.com',
        'message' => 'Test'
    ];

    public function setup () {
        $this->root = __DIR__ . '/../public';
        $config = new Config($this->root);
        $config->cacheSet();
        $container = Container::instance($this->root, $config, $this->root . '/../config/container.yml');
        $this->db = $container->get('db');
        $this->formRoute = $container->get('formRoute');
        $this->formController = $container->get('formController');
        $this->formModel = $container->get('formModel');
        $this->formView = $container->get('formView');
        $this->form = $container->get('form');
        $this->post = $container->get('post');
        $this->topic = $container->get('topic');
        $this->route = $container->get('route');
        $this->route->testMode();
        $this->ensureDocuments();
        $this->post->clear();
        $this->formRoute->paths();
    }

    private function ensureDocuments () {
        $this->db->document($this->contactId, [
            'first_name' => 'Test'
        ])->upsert();
        $this->db->document($this->blogId, [
            'title' => 'Test'
        ])->upsert();
    }

    private function jsonValidate ($json) {
        $json = json_decode($json);
        if (isset($json->id)) {
            return true;
        }
        return false;
    }

    private function matchFirstName ($json, $bundle='') {
        $json = json_decode($json, true);
        if ($bundle != '') {
            $bundle .= '__';
        }
        return $json['first_name'] === '<input value="Test" type="text" placeholder="First Name" name="' . $bundle . 'contact[first_name]" />';
    }

    private function matchTitle ($json, $bundle='') {
        $json = json_decode($json, true);
        if ($bundle != '') {
            $bundle .= '__';
        }
        return $json['title'] === '<input value="Test" type="text" name="' . $bundle . 'Manager__Form__Blogs[title]" />';
    }

    private function matchTitle2 ($json, $bundle='') {
        $json = json_decode($json, true);
        if ($bundle != '') {
            $bundle .= '__';
        }
        return $json['title'] === '<input value="Test" type="text" name="' . $bundle . 'Manager__Blogs[title]" />';
    }

    public function testBuild () {
        $cache = json_decode($this->formModel->build(), true);
        $this->formModel->cacheSet($cache);
        $this->assertTrue('contact' === $cache['contact']['name']);
    }

    public function testFormFactorySuccess () {
        $formObject = $this->form->factory('contact');
        $this->assertTrue(get_class($formObject) == 'stdClass');
    }

    public function testFormJsonSuccess () {
        $formObject = $this->form->factory('contact');
        $this->assertTrue($this->jsonValidate($this->form->json($formObject)));
    }

    public function testFormJsonPopulatedSuccess () {
        $formObject = $this->form->factory('contact', $this->contactId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchFirstName($this->form->json($formObject));
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testFormSubmitError () {
        $response = json_decode($this->form->upsert('contact', $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormSubmitFail () {
        $this->post->populate(['contact' => $this->contactPost]);
        $response = json_decode($this->form->upsert('contact', $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormSubmitSuccess () {
        $this->topic->subscribe('contact-save', 'test@fakeSubmit');
        $this->post->populate([
            'contact' => array_merge(
                $this->contactPost,
                ['form-token' => $this->form->tokenHashGet(new stdClass())]
            )]
        );
        $response = json_decode($this->form->upsert('contact', $this->contactId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testFormView () {
        ob_start();
        $this->formView->html(
            $this->form->factory('contact'),
            'forms/contact',
            'forms/contact'
        );
        $markup = ob_get_clean();
        $found = false;
        if (substr_count($markup, '<input type="text" placeholder="First Name" name="contact[first_name]"') == 1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testViewFromRouteSuccess () {
        $markup = $this->route->run('GET', '/form/contact');
        if (substr_count($markup, '<input type="text" placeholder="First Name" name="contact[first_name]"') ==  1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testUpdateFromRouteSuccess () {
        $this->topic->subscribe('contact-save', 'test@fakeSubmit');
        $this->post->populate([
            'contact' => array_merge(
                $this->contactPost,
                ['form-token' => $this->form->tokenHashGet(new stdClass())]
            )
        ]);
        $response = json_decode($this->route->run('POST', '/api/form/contact/' . $this->contactId), true);
        $this->assertTrue($response['success'] == true);
    }
}
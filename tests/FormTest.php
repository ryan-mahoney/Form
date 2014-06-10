<?php
namespace Opine;

class FormTest extends \PHPUnit_Framework_TestCase {
    private $db;
    private $formRoute;
    private $form;
    private $post;
    private $topic;
    private $contactId = 'contacts:538887dbed88e5a5527c1ef5';
    private $blogId = 'blogs:538887dded88e5a5527c1ef6';
    private static $routesCalled = false;

    public function setup () {
        date_default_timezone_set('UTC');
        $root = __DIR__ . '/../public';
        $container = new Container($root, $root . '/../container.yml');
        $this->db = $container->db;
        $this->formRoute = $container->formRoute;
        $this->form = $container->form;
        $this->post = $container->post;
        $this->topic = $container->topic;
        $this->ensureDocuments();
        $this->post->clear();
        $this->separation = $container->separation;
        $this->separation->forceLocal();
        if (self::$routesCalled === false) {
            $this->formRoute->json();
            self::$routesCalled = true;
        }
    }

    private function ensureDocuments () {
        $this->db->documentStage($this->contactId, [
            'first_name' => 'Test'
        ])->upsert();
        $this->db->documentStage($this->blogId, [
            'title' => 'Test'
        ])->upsert();
    }

    public function testFormFactorySuccess () {
        $formObject = $this->form->factory(new \Form\Contact);
        $this->assertTrue(get_class($formObject) == 'Form\Contact');
    }

    public function testFormFactoryBundleSuccess () {
        $formObject = $this->form->factory(new \Sample\Form\Contact);
        $this->assertTrue(get_class($formObject) == 'Sample\Form\Contact');
    }

    public function testFormFactoryManagerSuccess () {
        $formObject = $this->form->factory(new \Manager\Blogs);
        $this->assertTrue(get_class($formObject) == 'Manager\Blogs');
    }

    public function testFormFactoryManagerBundleSuccess () {
        $formObject = $this->form->factory(new \Sample\Manager\Blogs);
        $this->assertTrue(get_class($formObject) == 'Sample\Manager\Blogs');
    }

    private function jsonValidate ($json) {
        $json = json_decode($json);
        if (isset($json->id)) {
            return true;
        }
        return false;
    }

    public function testFormJsonSuccess () {
        $formObject = $this->form->factory(new \Form\Contact);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $this->assertTrue($jsonValid);
    }

    private function matchFirstName ($json, $bundle='') {
        $json = json_decode($json, true);
        if ($bundle != '') {
            $bundle .= '__';
        }
        return $json['first_name'] === '<input value="Test" type="text" placeholder="First Name" name="' . $bundle . 'Form__Contact[first_name]" />';
    }

    private function matchTitle ($json, $bundle='') {
        $json = json_decode($json, true);
        if ($bundle != '') {
            $bundle .= '__';
        }
        return $json['title'] === '<input value="Test" type="text" name="' . $bundle . 'Manager__Blogs[title]" />';
    }

    public function testFormJsonPopulatedSuccess () {
        $formObject = $this->form->factory(new \Form\Contact, $this->contactId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchFirstName($this->form->json($formObject));
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testBundleFormJsonPopulatedSuccess () {
        $formObject = $this->form->factory(new \Sample\Form\Contact, $this->contactId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchFirstName($this->form->json($formObject), 'Sample');
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testManagerJsonPopulatedSuccess () {
        $formObject = $this->form->factory(new \Manager\Blogs, $this->blogId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchTitle($this->form->json($formObject));
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testBundleManagerJsonPopulatedSuccess () {
        $formObject = $this->form->factory(new \Sample\Manager\Blogs, $this->blogId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchTitle($this->form->json($formObject), 'Sample');
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testFormSubmitError () {
        $response = json_decode($this->form->upsert(new \Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormSubmitFail () {
        $this->post->populate('/', [
            'Form__Contact' => [
                'first_name' => 'Test',
                'last_name' => 'Test',
                'phone' => '555-555-5555',
                'email' => 'test@test.com',
                'message' => 'Test'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormSubmitSuccess () {
        $this->topic->subscriber('fake-submit', function ($context, $post) {
            $post->statusSaved();
        });
        $this->topic->subscribe('Form__Contact-save', 'fake-submit', ['post']);
        $this->post->populate('/', [
            'Form__Contact' => [
                'first_name' => 'Test',
                'last_name' => 'Test',
                'phone' => '555-555-5555',
                'email' => 'test@test.com',
                'message' => 'Test'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testFormBundleSubmitFail () {
        $this->post->populate('/', [
            'Sample__Form__Contact' => [
                'first_name' => 'Test',
                'last_name' => 'Test',
                'phone' => '555-555-5555',
                'email' => 'test@test.com',
                'message' => 'Test'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormBundleSubmitSuccess () {
        $this->topic->subscriber('fake-submit', function ($context, $post) {
            $post->statusSaved();
        });
        $this->topic->subscribe('Sample__Form__Contact-save', 'fake-submit', ['post']);
        $this->post->populate('/', [
            'Sample__Form__Contact' => [
                'first_name' => 'Test',
                'last_name' => 'Test',
                'phone' => '555-555-5555',
                'email' => 'test@test.com',
                'message' => 'Test'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testManagerSubmitFail () {
        $this->post->populate('/', [
            'Manager__Blogs' => [
                'title' => 'Test'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Manager\Blogs, $this->blogId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testManagerSubmitSuccess () {
        $this->topic->subscriber('fake-submit', function ($context, $post) {
            $post->statusSaved();
        });
        $this->topic->subscribe('Manager__Blogs-save', 'fake-submit', ['post']);
        $this->post->populate('/', [
            'Manager__Blogs' => [
                'title' => 'Test',
                'status' => 'draft',
                'display_date' => '2000-01-01'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Manager\Blogs, $this->blogId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testBundleManagerSubmitFail () {
        $this->post->populate('/', [
            'Sample__Manager__Blogs' => [
                'title' => 'Test'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Manager\Blogs, $this->blogId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testBundleManagerSubmitSuccess () {
        $this->topic->subscriber('fake-submit', function ($context, $post) {
            $post->statusSaved();
        });
        $this->topic->subscribe('Sample__Manager__Blogs-save', 'fake-submit', ['post']);
        $this->post->populate('/', [
            'Sample__Manager__Blogs' => [
                'title' => 'Test',
                'status' => 'draft',
                'display_date' => '2000-01-01'
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Manager\Blogs, $this->blogId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testFormView () {
        ob_start();
        $this->form->view(
            new \Form\Contact,
            'app/forms/contact.yml',
            'public/layouts/forms/contact.html'
        );
        $markup = ob_get_clean();
        $found = false;
        if (substr_count($markup, '<input type="text" placeholder="First Name" name="Form__Contact[first_name]"') == 1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testManagerView () {
        ob_start();
        $this->form->view(
            new \Manager\Blogs,
            'bundles/Manager/app/forms/blogs.yml',
            'public/layouts/Manager/forms/any.html'
        );
        $markup = ob_get_clean();
        $found = false;
        if (substr_count($markup, '<input type="text" name="Manager__Blogs[title]"') ==  1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testBundleFormView () {
        ob_start();
        $this->form->view(
            new \Sample\Form\Contact,
            'bundles/Sample/app/forms/contact.yml',
            'public/layouts/Sample/forms/contact.html'
        );
        $markup = ob_get_clean();
        $found = false;
        if (substr_count($markup, '<input type="text" placeholder="First Name" name="Sample__Form__Contact[first_name]"') ==  1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testBundleManagerView () {
        ob_start();
        $this->form->view(
            new \Sample\Manager\Blogs,
            'bundles/Manager/app/forms/blogs.yml',
            'public/layouts/Manager/forms/any.html'
        );
        $markup = ob_get_clean();
        $found = false;
        if (substr_count($markup, '<input type="text" name="Manager__Blogs[title]"') ==  1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

//DELETE
//ROUTE VIEW
//ROUTE UPDATE
//ROUTE DELETE
}
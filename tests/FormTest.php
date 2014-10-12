<?php
namespace Opine;
use PHPUnit_Framework_TestCase;

class FormTest extends PHPUnit_Framework_TestCase {
    private $db;
    private $formRoute;
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
        date_default_timezone_set('UTC');
        $root = __DIR__ . '/../public';
        $container = new Container($root, $root . '/../container.yml');
        $this->db = $container->db;
        $this->formRoute = $container->formRoute;
        $this->formController = $container->formController;
        $this->formModel = $container->formModel;
        $this->formView = $container->formView;
        $this->form = $container->form;
        $this->post = $container->post;
        $this->topic = $container->topic;
        $this->route = $container->route;
        $this->route->testMode();
        $this->ensureDocuments();
        $this->post->clear();
        $this->formRoute->paths();
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
        $formObject = $this->form->factory(new \Form\Contact());
        $this->assertTrue(get_class($formObject) == 'Form\Contact');
    }

    public function testFormFactoryBundleSuccess () {
        $formObject = $this->form->factory(new \Sample\Form\Contact());
        $this->assertTrue(get_class($formObject) == 'Sample\Form\Contact');
    }

    public function testFormFactoryManagerSuccess () {
        $formObject = $this->form->factory(new \Manager\Form\Blogs());
        $this->assertTrue(get_class($formObject) == 'Manager\Form\Blogs');
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
        return $json['title'] === '<input value="Test" type="text" name="' . $bundle . 'Manager__Form__Blogs[title]" />';
    }

    private function matchTitle2 ($json, $bundle='') {
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
        $formObject = $this->form->factory(new \Manager\Form\Blogs, $this->blogId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchTitle($this->form->json($formObject));
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testBundleManagerJsonPopulatedSuccess () {
        $formObject = $this->form->factory(new \Sample\Manager\Blogs, $this->blogId);
        $jsonValid = $this->jsonValidate($this->form->json($formObject));
        $fieldMatched = $this->matchTitle2($this->form->json($formObject), 'Sample');
        $this->assertTrue($jsonValid && $fieldMatched);
    }

    public function testFormSubmitError () {
        $response = json_decode($this->form->upsert(new \Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormSubmitFail () {
        $this->post->populate('/', [
            'Form__Contact' => $this->contactPost
        ]);
        $response = json_decode($this->form->upsert(new \Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormSubmitSuccess () {
        $this->topic->subscribe('Form__Contact-save', 'test@fakeSubmit');
        $this->post->populate('/', [
            'Form__Contact' => array_merge(
                $this->contactPost, 
                ['form-token' => $this->form->tokenHashGet(new \Form\Contact())]
            )]
        );
        $response = json_decode($this->form->upsert(new \Form\Contact(), $this->contactId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testFormBundleSubmitFail () {
        $this->post->populate('/', [
            'Sample__Form__Contact' => $this->contactPost
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Form\Contact(), $this->contactId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormBundleSubmitSuccess () {
        $this->topic->subscribe('Sample__Form__Contact-save', 'test@fakeSubmit');
        $this->post->populate('/', [
            'Sample__Form__Contact' => array_merge(
                $this->contactPost,
                ['form-token' => $this->form->tokenHashGet(new \Sample\Form\Contact)]
            )
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Form\Contact, $this->contactId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testManagerSubmitFail () {
        $this->post->populate('/', [
            'Manager__Form__Blogs' => [
                'title' => 'Test',
                'form-token' => $this->form->tokenHashGet(new \Manager\Form\Blogs())
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Manager\Form\Blogs(), $this->blogId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testManagerSubmitSuccess () {
        $this->topic->subscribe('Manager__Form__Blogs-save', 'test@fakeSubmit');
        $this->post->populate('/', [
            'Manager__Form__Blogs' => [
                'title' => 'Test',
                'status' => 'draft',
                'display_date' => '2000-01-01',
                'form-token' => $this->form->tokenHashGet(new \Manager\Form\Blogs())
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Manager\Form\Blogs(), $this->blogId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testBundleManagerSubmitFail () {
        $this->post->populate('/', [
            'Sample__Manager__Blogs' => [
                'title' => 'Test',
                'form-token' => $this->form->tokenHashGet(new \Sample\Manager\Blogs())
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Manager\Blogs(), $this->blogId), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testBundleManagerSubmitSuccess () {
        $this->topic->subscribe('Sample__Manager__Blogs-save', 'test@fakeSubmit');
        $this->post->populate('/', [
            'Sample__Manager__Blogs' => [
                'title' => 'Test',
                'status' => 'draft',
                'display_date' => '2000-01-01',
                'form-token' => $this->form->tokenHashGet(new \Sample\Manager\Blogs())
            ]
        ]);
        $response = json_decode($this->form->upsert(new \Sample\Manager\Blogs(), $this->blogId), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testFormView () {
        ob_start();
        $this->formView->html(
            new \Form\Contact(),
            'app/forms/contact.yml',
            'forms/contact'
        );
        $markup = ob_get_clean();
        $found = false;

echo $markup, "\n\n";
exit;

        if (substr_count($markup, '<input type="text" placeholder="First Name" name="Form__Contact[first_name]"') == 1) {
            $found = true;
        }
        $this->assertTrue($found);
    }
/*
    public function testManagerView () {
        ob_start();
        $this->form->view(
            new \Manager\Form\Blogs,
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

    public function testFormDeleteError () {
        $response = json_decode($this->form->delete(new \Form\Contact, $this->contactId, $this->form->tokenHashGet(new \Form\Contact)), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testFormDeleteSuccess () {
        $this->topic->subscriber('fake-delete', function ($context, $post) {
            $post->statusDeleted();
        });
        $this->topic->subscribe('Form__Contact-delete', 'fake-delete', ['post']);
        $response = json_decode($this->form->delete(new \Form\Contact, $this->contactId, $this->form->tokenHashGet(new \Form\Contact)), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testViewFromRouteSuccess () {
        $markup = $this->route->run('GET', '/form/Contact');
        if (substr_count($markup, '<input type="text" placeholder="First Name" name="Form__Contact[first_name]"') ==  1) {
            $found = true;
        }
        $this->assertTrue($found);
    }

    public function testUpdateFromRouteSuccess () {
        $this->topic->subscriber('fake-submit', function ($context, $post) {
            $post->statusSaved();
        });
        $this->topic->subscribe('Form__Contact-save', 'fake-submit', ['post']);
        $this->post->populate('/', [
            'Form__Contact' => array_merge(
                $this->contactPost,
                ['form-token' => $this->form->tokenHashGet(new \Form\Contact)]
            )
        ]);
        $response = json_decode($this->route->run('POST', '/form/Contact/' . $this->contactId), true);
        $this->assertTrue($response['success'] == true);
    }

    public function testUpdateFromRouteTokenFail () {
        $this->topic->subscriber('fake-submit', function ($context, $post) {
            $post->statusSaved();
        });
        $this->topic->subscribe('Form__Contact-save', 'fake-submit', ['post']);
        $this->post->populate('/', [
            'Form__Contact' => array_merge(
                $this->contactPost
            )
        ]);
        $response = json_decode($this->route->run('POST', '/form/Contact/' . $this->contactId), true);
        $this->assertTrue($response['success'] == false);
    }

    public function testDeleteFromRouteSuccess () {
        $this->topic->subscriber('fake-delete', function ($context, $post) {
            $post->statusDeleted();
        });
        $this->topic->subscribe('Form__Contact-delete', 'fake-delete', ['post']);
        $response = json_decode($this->route->run('DELETE', '/form/Contact/' . $this->contactId . '?form-token=' . $this->form->tokenHashGet(new \Form\Contact)), true);
        $this->assertTrue($response['success'] === true);
    }

    public function testDeleteFromRouteTokenFail () {
        $this->topic->subscriber('fake-delete', function ($context, $post) {
            $post->statusDeleted();
        });
        $this->topic->subscribe('Form__Contact-delete', 'fake-delete', ['post']);
        $response = json_decode($this->route->run('DELETE', '/form/Contact/' . $this->contactId . '?form-token=123'), true);
        $this->assertTrue($response['success'] === false);
    }

    public function testBundleDetermination () {
        $formObject = $this->form->factory(new \Sample\Form\Contact);
        $this->assertTrue($formObject->bundle == 'Sample');
    }
*/
}
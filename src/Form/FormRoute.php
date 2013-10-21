<?php
namespace Form;

class FormRoute {
	private $separation;
	private $post;
	private $slim;
	private $topic;
	private $response;

	public function __construct ($slim, $form, $post, $separation, $topic, $response) {
		$this->slim = $slim;
		$this->post = $post;
		$this->form = $form;
		$this->separation = $separation;
		$this->topic = $topic;
		$this->response = $response;
	}

	public function json () {
		$this->slim->get('/json-form/:form(/:id)', function ($form, $id=false) {
			if (isset($_GET['id']) && $id === false) {
				$id = $_GET['id'];
			} 
		    $formObject = $this->form->factory($form, $id);
		    $head = null;
		    $tail = null;
		    if (isset($_GET['pretty'])) {
		        $head = '<html><head></head><body style="margin:0; border:0; padding: 0"><textarea wrap="off" style="overflow: auto; margin:0; border:0; padding: 0; width:100%; height: 100%">';
		        $tail = '</textarea></body></html>';
		    } elseif (isset($_GET['callback'])) {
		    	$head = $_GET['callback'] . '(';
		    	$tail = ');';
		   	}
		    echo $head, $this->form->json($formObject, $id), $tail;
		});
	}

	public function app ($root) {
		$cacheFile = $root . '/../forms/cache.json';
		if (!file_exists($cacheFile)) {
			return;
		}
		$forms = (array)json_decode(file_get_contents($cacheFile), true);
		if (!is_array($forms)) {
			return;
		}
	    foreach ($forms as $form) {
	    	$this->slim->get('/form/' . $form . '(/:id)', function ($id=false) use ($form) {
                if ($id === false) {
                	$this->separation->layout('form-' . $form)->template()->write($this->response->body);
                } else {
                	$this->separation->layout('form-' . $form)->args($form, ['id' => $id])->template()->write($this->response->body);
                }
            })->name('form ' . $form);
            $this->slim->post('/form/' . $form . '(/:id)', function ($id=false) use ($form) {
            	$formObject = $this->form->factory($form, $id);
            	if ($id === false) {
            		if (isset($this->post->{$formObject->marker}['id'])) {
            			$id = $this->post->{$formObject->marker}['id'];
            		} else {
            			throw new \Exception('ID not supplied in post.');
            		}
            	}
               	$event = [
            		'dbURI' => $formObject->storage['collection'] . ':' . $id,
            		'formMarker' => $formObject->marker
            	];
            	if (!$this->form->validate($formObject)) {
            		$this->form->responseError();
            		return;
            	}
            	$this->form->sanitize($formObject);
            	$this->topic->publish('form-' . $form . '-save', $event);
            	if ($this->post->statusCheck() == 'saved') {
            		$this->form->responseSuccess($formObject);
            	} else {
            		$this->form->responseError();	
            	}
            });
	    }
	}
}
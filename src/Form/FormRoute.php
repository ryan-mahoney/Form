<?php
namespace Form;

class FormRoute {
	private $separation;
	private $slim;

	public function __construct ($slim, $formModel, $separation) {
		$this->slim = $slim;
		$this->formModel = $formModel;
		$this->separation = $separation;
	}

	public function json ($root) {
		$this->slim->get('/json-form/:form(/:id)', function ($form, $id=false) {
			if (isset($_GET['id']) && $id === false) {
				$id = $_GET['id'];
			}
		    $class = $root . '/forms/' . $form . '.php';
		    if (!file_exists($class)) {
		        exit ($class . ': unknown file.');
		    }
		    require_once($class);
		    if (!class_exists($form)) {
		        exit ($form . ': unknown class.');
		    }
		    $formObject = new $form();
		    $head = null;
		    $tail = null;
		    if (isset($_GET['pretty'])) {
		        $head = '<html><head></head><body style="margin:0; border:0; padding: 0"><textarea wrap="off" style="overflow: auto; margin:0; border:0; padding: 0; width:100%; height: 100%">';
		        $tail = '</textarea></body></html>';
		    } elseif (isset($_GET['callback'])) {
		    	$head = $_GET['callback'] . '(';
		    	$tail = ');';
		   	}
		    echo $head, $formObject->json($id), $tail;
		});
	}

	public function pages ($root) {
		$cacheFile = $root . '/forms/cache.json';
		if (!file_exists($cacheFile)) {
			return;
		}
		$forms = (array)json_decode(file_get_contents($cacheFile), true);
		if (!is_array($forms)) {
			return;
		}
	    foreach ($forms as $form) {
	    	require $root . '/forms/' . $form . '.php';
			$formObject = new $form();
	    	$this->slim->get('/form/' . $form . '(/:id)', function ($id=false) use ($form, $formObject) {
                if ($id === false) {
                	$this->separation->layout('form-' . $form)->template()->write();
                } else {
                	$this->separation->layout('form-' . $form)->set([
                		['Sep' => $form, 'a' => ['id' => $id]]
                	])->template()->write();
                }
            })->name('form ' . $form);
            $this->slim->post('/form/' . $form . '(/:id)', function ($id=false) use ($formObject) {
                $this->formModel->post($formObject);
            });
	    }
	}
}
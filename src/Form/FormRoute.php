<?php
namespace Form;
use Separation\Separation;

class FormRoute {
	public static function json ($app) {
		$app->get('/json-form/:form(/:id)', function ($form, $id=false) {
			if (isset($_GET['id']) && $id === false) {
				$id = $_GET['id'];
			}
		    $class = $_SERVER['DOCUMENT_ROOT'] . '/forms/' . $form . '.php';
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

	public static function pages (&$app) {
		$cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/forms/cache.json';
		if (!file_exists($cacheFile)) {
			return;
		}
		$forms = (array)json_decode(file_get_contents($cacheFile), true);
		if (!is_array($forms)) {
			return;
		}
	    foreach ($forms as $form) {
	    	require $_SERVER['DOCUMENT_ROOT'] . '/forms/' . $form . '.php';
			$formObject = new $form();
	    	$app->get('/form/' . $form . '(/:id)', function ($id=false) use ($form, $formObject) {
                if ($id === false) {
                	$separation = Separation::layout('form-' . $form)->template()->write();
                } else {
                	$separation = Separation::layout('form-' . $form)->set([
                		['Sep' => $form, 'a' => ['id' => $id]]
                	])->template()->write();
                }
            })->name('form ' . $form);
            $app->post('/form/' . $form . '(/:id)', function ($id=false) use ($formObject) {
                FormModel::post($formObject);
            });
	    }
	}
}
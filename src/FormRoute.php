<?php
class FormRoute {
		public static function json ($app) {
		$app->get('/json-form/:form', function ($form) {
		    $class = $_SERVER['DOCUMENT_ROOT'] . '/forms/' . $form . '.php';
		    if (!file_exists($class)) {
		        exit ($class . ': unknown file.');
		    }
		    require_once($class);
		    if (!class_exists($form)) {
		        exit ($form . ': unknown class.');
		    }
		    $obj = new $form();
		    $head = null;
		    $tail = null;
		    if (isset($_GET['pretty'])) {
		        $head = '<html><head></head><body style="margin:0; border:0; padding: 0"><textarea wrap="off" style="overflow: auto; margin:0; border:0; padding: 0; width:100%; height: 100%">';
		        $tail = '</textarea></body></html>';
		    }
		    echo $head, $obj->json(), $tail;
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
            $app->get('/form/' . $form, function ($form) {
                $separation = Separation::layout('form-' . $form . '.html')->template()->write();
            });
	    }
	}
}
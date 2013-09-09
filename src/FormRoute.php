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
		        $options = JSON_PRETTY_PRINT;
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
		return;
	    foreach ($forms as $form) {
	        if (isset($collection['p'])) {
	            $app->get('/' . $collection['p'] . '(/:method(/:limit(/:skip(/:sort))))', function ($method='all', $limit=null, $skip=0, $sort=[]) use ($collection) {
		            if ($limit === null) {
		            	if (isset($collection['limit'])) {
		                	$limit = $collection['limit'];
		            	} else {
			            	$limit = 10;
			            }
		            }
		            foreach (['limit', 'skip', 'sort'] as $option) {
		            	$key = $collection['p'] . '-' . $method . '-' . $option;
		            	if (isset($_GET[$key])) {
		                	${$option} = $_GET[$key];
		            	}
		            }
		            $separation = Separation::layout($collection['p'] . '.html')->template()->write();
		        });
		    }
	        if (!isset($collection['s'])) {
	        	continue;
	        }
            $app->get('/' . $collection['s'] . '/:slug', function ($slug) use ($collection) {
                $separation = Separation::layout($collection['s'] . '.html')->set([
                	['Sep' => $collection['p'], 'a' => ['slug' => basename($slug, '.html')]]
                ])->template()->write();
            });
            if (isset($collection['partials']) && is_array($collection['partials'])) {
            	foreach ($collection['partials'] as $template) {
					$app->get('/' . $collection['s'] . '-' . $template . '/:slug', function ($slug) use ($collection, $template) {
		               	$separation = Separation::html($collection['s'] . '-' . $template . '.html')->template()->write();
        			});
        		}
            }
	    }
	}
}
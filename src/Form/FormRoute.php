<?php
namespace Form;

class FormRoute {
	private $separation;
	private $post;
	private $slim;
	private $topic;
	private $response;
	private $field;
	public $cache = false;

	public function __construct ($slim, $form, $field, $post, $separation, $topic, $response) {
		$this->slim = $slim;
		$this->post = $post;
		$this->form = $form;
		$this->separation = $separation;
		$this->topic = $topic;
		$this->response = $response;
		$this->field = $field;
	}

	public function cacheSet ($cache) {
		$this->cache = $cache;
	}

	public function json ($bundle='') {
		$bundlePath = '';
		if ($bundle != '') {
			$bundlePath = '/' . $bundle;
		}
		$this->slim->get($bundlePath . '/json-form/:form(/:id)', function ($form, $id=false) use ($bundle) {
			if (isset($_GET['id']) && $id === false) {
				$id = $_GET['id'];
			} 
		    $formObject = $this->form->factory($form, $id, $bundle);
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

	public function app ($root, $bundle='') {
		$bundlePath = '';
		if ($bundle != '') {
			$bundlePath = '/' . $bundle;
		}
		if (!empty($this->cache) && $bundle == '') {
			$forms = $this->cache;
		} else {
			$cacheFile = $root . '/../forms/cache.json';
			if (!file_exists($cacheFile)) {
				return;
			}
			$forms = (array)json_decode(file_get_contents($cacheFile), true);
		}
		if (!is_array($forms)) {
			return;
		}
	    foreach ($forms as $form) {
	    	//view
	    	$this->slim->get($bundlePath . '/form/' . $form . '(/:id)', function ($id=false) use ($form, $bundle) {
	    		$bundlePath = '';
	    		if ($bundle != '') {
	    			$bundlePath = $bundle . '/';
	    		}
                if ($id === false) {
                	$this->separation->layout('forms/' . $bundlePath . $form)->template()->write($this->response->body);
                } else {
                	$this->separation->layout('forms/' . $bundlePath . $form)->args($form, ['id' => $id])->template()->write($this->response->body);
                }
            })->name('form ' . $form);
            
            //update
            $this->slim->post($bundlePath . '/form/' . $form . '(/:id)', function ($id=false) use ($form, $bundle) {
            	$formObject = $this->form->factory($form, $id, $bundle);
            	if ($id === false) {
            		if (isset($this->post->{$formObject->marker}['id'])) {
            			$id = $this->post->{$formObject->marker}['id'];
            		} else {
            			throw new \Exception('ID not supplied in post.');
            		}
            	}
               	$context = [
            		'dbURI' => $formObject->storage['collection'] . ':' . $id,
            		'formMarker' => $formObject->marker
            	];
            	if (!$this->form->validate($formObject)) {
            		$this->form->responseError();
            		return;
            	}
            	$bundleTopic = '';
            	if (!empty($bundle)) {
            		$bundleTopic = $bundle . '-';
            	}
            	$this->form->sanitize($formObject);
            	$this->topic->publish($bundleTopic . 'form-' . $form . '-save', $context);
            	if (!empty($bundle)) {
            		$this->topic->publish($bundleTopic . 'form-save', $context);
            	}
            	if ($this->post->statusCheck() == 'saved') {
            		$this->form->responseSuccess($formObject);
            	} else {
            		$this->form->responseError();	
            	}
            });

			//delete
			$this->slim->delete($bundlePath . '/form/' . $form . '(/:id)', function ($id=false) use ($form, $bundle) {
            	$formObject = $this->form->factory($form, $id, $bundle);
            	if ($id === false) {
            		if (isset($this->post->{$formObject->marker}['id'])) {
            			$id = $this->post->{$formObject->marker}['id'];
            		} else {
            			throw new \Exception('ID not supplied in post.');
            		}
            	}
               	$context = [
            		'dbURI' => $formObject->storage['collection'] . ':' . $id,
            		'formMarker' => $formObject->marker
            	];
            	$bundleTopic = '';
            	if (!empty($bundle)) {
            		$bundleTopic = $bundle . '-';
            	}
            	$this->topic->publish($bundleTopic . 'form-' . $form . '-delete', $context);
            	if (!empty($bundle)) {
            		$this->topic->publish($bundleTopic . 'form-delete', $context);
            	}
            	if ($this->post->statusCheck() == 'deleted') {
            		$this->form->responseSuccess($formObject);
            	} else {
            		$this->form->responseError();	
            	}
            });
	    }
	}

	private static function stubRead ($name, &$collection, $url, $root) {
		return str_replace(['{{$url}}', '{{$plural}}', '{{$singular}}'], [$url, $collection['p'], $collection['s']], $data);
	}

	public function build ($root, $url=false, $bundle='') {
		$rootProject = $root . '/..';
		$bundlePath = '';
		if ($bundle != '') {
			$bundlePath = $bundle . '/';
			$tmp = explode('/', $root);
			array_pop($tmp);
			array_pop($tmp);
			array_pop($tmp);
			$rootProject = implode('/', $tmp);
		}
		$cache = [];
		$dirFiles = glob($root . '/../forms/*.php');
		foreach ($dirFiles as $form) {
			$class = basename($form, '.php');
			$cache[] = $class;
		}
		$json = json_encode($cache, JSON_PRETTY_PRINT);
		file_put_contents($root . '/../forms/cache.json', $json);
		foreach ($cache as $form) {
			$filename = $root . '/layouts/forms/' . $form . '.html';
			if (!file_exists($filename)) {
				$data = file_get_contents($rootProject . '/vendor/virtuecenter/build/static/form.html');
				$data = str_replace(['{{$form}}'], [$form], $data);
				file_put_contents($filename, $data);
			}
			$filename = $root . '/partials/forms/' . $form . '.hbs';
			if (!file_exists($filename)) {
				$data = file_get_contents($rootProject . '/vendor/virtuecenter/build/static/form.hbs');
				$formObject = $this->form->factory($form, false, $bundle);
				ob_start();
				echo '
<form class="ui form segment" data-xhr="true" method="post">', "\n";

				foreach ($formObject->fields as $field) {
					echo '
    <div class="field">
        <label>', ucwords(str_replace('_', ' ', $field['name'])), '</label>
        <div class="ui left labeled input">
            {{{', $field['name'], '}}}
            <div class="ui corner label">
            	<i class="icon asterisk"></i>
            </div>
        </div>
    </div>', "\n";
				}
				echo '
    {{{id}}}
	<input type="submit" class="ui blue submit button" value="Submit" />
</form>';
				$generated = ob_get_clean();
				$data = str_replace(['{{$form}}', '{{$generated}}'], [$form, $generated], $data);
				file_put_contents($filename, $data);
			}
			if ($url !== false) {
				$filename = $root . '/../app/forms/' . $form . '.yml';
				if (!file_exists($filename)) {
					$data = file_get_contents($rootProject . '/vendor/virtuecenter/build/static/app-form.yml');
					$data = str_replace(['{{$form}}', '{{$url}}'], [$form, $url], $data);
					file_put_contents($filename, $data);
				}
			}
		}
		return $json;
	}
}
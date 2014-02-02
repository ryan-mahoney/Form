<?php
/**
 * Opine\FormRoute
 *
 * Copyright (c)2013 Ryan Mahoney, https://github.com/virtuecenter <ryan@virtuecenter.com>
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
namespace Opine;

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

    public function json ($bundle='', $path='forms', $namespace='Form\\', $route='form', $prefix='') {
        $bundlePath = '';
        if ($bundle != '') {
            $bundlePath = '/' . $bundle;
        }
        $this->slim->get($prefix . $bundlePath . '/json-' . $route . '/:form(/:id)', function ($form, $id=false) use ($bundle, $namespace, $path) {
            if (isset($_GET['id']) && $id === false) {
                $id = $_GET['id'];
            }
            $formObject = $this->form->factory($form, $id, $bundle, $path, $namespace);
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

    public function app ($root, $bundle='', $path='forms', $namespace='Form\\', $route='form', $prefix='') {
        $bundlePath = '';
        if ($bundle != '') {
            $bundlePath = '/' . $bundle;
        }
        if (!empty($this->cache) && $bundle == '' && $route == 'form') {
            $forms = $this->cache;
        } else {
            $cacheFile = $root . '/../' . $path . '/cache.json';
            if (!file_exists($cacheFile)) {
                return;
            }
            $forms = (array)json_decode(file_get_contents($cacheFile), true);
            if (isset($forms['managers'])) {
            	$managers = $forms['managers'];
            	$forms = [];
            	foreach ($managers as $manager) {
            		$forms[] = $manager['manager'];
            	}
            }
        }
        if (!is_array($forms)) {
            return;
        }
        foreach ($forms as $form) {
            //view
            $this->slim->get($prefix . $bundlePath . '/' . $route . '/' . $form . '(/:id)', function ($id=false) use ($form, $bundle, $path) {
                $bundlePath = '';
                if ($bundle != '') {
                    $bundlePath = $bundle . '/';
                }
                if ($id === false) {
                    $this->separation->layout($path. '/' . $bundlePath . $form)->template()->write($this->response->body);
                } else {
                    $this->separation->layout($path . '/' . $bundlePath . $form)->args($form, ['id' => $id])->template()->write($this->response->body);
                }
            })->name(ucfirst($form) . ': form');
            
            //update
            $this->slim->post($prefix . $bundlePath . '/' . $route . '/' . $form . '(/:id)', function ($id=false) use ($form, $bundle, $path, $namespace, $route, $prefix) {
                $formObject = $this->form->factory($form, $id, $bundle, $path, $namespace);
                if ($id === false) {
                    if (isset($this->post->{$formObject->marker}['id'])) {
                        $id = $this->post->{$formObject->marker}['id'];
                    } else {
                        throw new \Exception('ID not supplied in post.');
                    }
                }
                $context = [
                    'dbURI' => $id,
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
                $prefixTopic = '';
                if (!empty($prefix)) {
                    $prefixTopic = trim($prefix, '/') . '-';
                }
                $this->form->sanitize($formObject);
                $this->topic->publish($prefixTopic . $bundleTopic . $route . '-' . $form . '-save', $context);
                if (!empty($bundle)) {
                    $this->topic->publish($bundleTopic . $route . '-save', $context);
                }
                if (!empty($prefix)) {
                    $this->topic->publish($prefixTopic . $route . '-save', $context);
                }
                if ($this->post->statusCheck() == 'saved') {
                    $this->form->responseSuccess($formObject);
                } else {
                    $this->form->responseError();    
                }
            });

            //delete
            $this->slim->delete($prefix . $bundlePath . '/' . $route . '/' . $form . '(/:id)', function ($dbURI=false) use ($form, $bundle, $path, $namespace, $route, $prefix) {
                $formObject = $this->form->factory($form, $dbURI, $bundle, $path, $namespace);
                if ($dbURI === false) {
                    if (isset($this->post->{$formObject->marker}['id'])) {
                        $dbURI = $this->post->{$formObject->marker}['id'];
                    } else {
                        throw new \Exception('ID not supplied in post.');
                    }
                }
                $context = [
                    'dbURI' => $dbURI,
                    'formMarker' => $formObject->marker
                ];
                $bundleTopic = '';
                if (!empty($bundle)) {
                    $bundleTopic = $bundle . '-';
                }
                $prefixTopic = '';
                if (!empty($prefix)) {
                    $prefixTopic = trim($prefix, '/') . '-';
                }
                $this->topic->publish($prefixTopic . $bundleTopic . $route . '-' . $form . '-delete', $context);
                if (!empty($bundle)) {
                    $this->topic->publish($bundleTopic . $route . '-delete', $context);
                }
                if (!empty($prefix)) {
                    $this->topic->publish($prefixTopic . $route . '-delete', $context);
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
                $buffer = '';
                $buffer .= '
<form class="ui form segment" data-xhr="true" method="post">' . "\n";

                foreach ($formObject->fields as $field) {
                    $buffer .= '
    <div class="field">
        <label>' . ucwords(str_replace('_', ' ', $field['name'])) . '</label>
        <div class="ui left labeled input">
            {{{' . $field['name'] . '}}}
            <div class="ui corner label">
                <i class="icon asterisk"></i>
            </div>
        </div>
    </div>' . "\n";
                }
                $buffer .= '
    {{{id}}}
    <input type="submit" class="ui blue submit button" value="Submit" />
</form>';
                $data = str_replace(['{{$form}}', '{{$generated}}'], [$form, $buffer], $data);
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

    public function upgrade ($root) {
        $manifest = (array)json_decode(file_get_contents('https://raw.github.com/virtuecenter/form/master/available/manifest.json'), true);
        $upgraded = 0;
        foreach (glob($root . '/../forms/*.php') as $filename) {
            $lines = file($filename);
            $version = false;
            $mode = false;
            $link = false;
            foreach ($lines as $line) {
                if (substr_count($line, ' * @') != 1) {
                    continue;
                }
                if (substr_count($line, '* @mode') == 1) {
                    $mode = trim(str_replace('* @mode', '', $line));
                    continue;
                }
                if (substr_count($line, '* @version') == 1) {
                    $version = floatval(trim(str_replace('* @version', '', $line)));
                    continue;
                }
                if (substr_count($line, '* @link') == 1) {
                    $link = trim(str_replace('* @link', '', $line));
                    continue;
                }
            }
            if ($mode === false || $version === false || $link === false) {
                continue;
            }
            if ($version == '' || $link == '' || $mode == '') {
                continue;
            }
            if ($mode != 'upgrade') {
                continue;
            }
            if ($version == $manifest['forms'][basename($filename, '.php')]) {
                continue;
            }
            $newVersion = floatval($manifest['forms'][basename($filename, '.php')]);
            if ($newVersion > $version) {
                file_put_contents($filename, file_get_contents($link));
                echo 'Upgraded Form: ', basename($filename, '.php'), ' to version: ', $newVersion, "\n";
                $upgraded++;
            }
        }
        echo 'Upgraded ', $upgraded, ' forms.', "\n";
    }
}
<?php
/**
 * Opine\FormRoute
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
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
    private $route;
    private $topic;
    private $field;
    private $root;
    public $cache = false;

    public function __construct ($root, $route, $form, $field, $post, $separation, $topic) {
        $this->route = $route;
        $this->post = $post;
        $this->form = $form;
        $this->separation = $separation;
        $this->topic = $topic;
        $this->field = $field;
        $this->root = $root;
    }

    public function cacheSet ($cache) {
        $this->cache = $cache;
    }

    public function paths () {
        //json fields
        $callback = function ($marker, $id=false) {
            $formObject = $this->form->markerToClass($marker);
            if (isset($_GET['id']) && $id === false) {
                $id = $_GET['id'];
            }
            $formObject = $this->form->factory($formObject, $id);
            $head = '';
            $tail = '';
            if (isset($_GET['pretty'])) {
                $head = '<html><head></head><body style="margin:0; border:0; padding: 0"><textarea wrap="off" style="overflow: auto; margin:0; border:0; padding: 0; width:100%; height: 100%">';
                $tail = '</textarea></body></html>';
            } elseif (isset($_GET['callback'])) {
                $head = $_GET['callback'] . '(';
                $tail = ');';
            }
            echo $head, $this->form->json($formObject, $id), $tail;
        };
        $this->route->get('/json-form/{marker}', $callback);
        $this->route->get('/json-form/{marker}/{id}', $callback);

        //view
        $callback = function ($form, $dbURI=false) {
            $this->form->view(
                $this->form->markerToClass('Form__' . $form),
                'app/forms/' . strtolower($form) . '.yml',
                'public/layouts/forms/' . strtolower($form) . '.html',
                $dbURI
            );
        };
        $this->route->get('/form/{form}', $callback);
        $this->route->get('/form/{form}.html', $callback);
        $this->route->get('/form/{form}/{dbURI}', $callback);
        $callback = function ($bundle, $form, $dbURI=false) {
            $this->form->view(
                $this->form->markerToClass($bundle . '__Form__' . $form),
                'bundles/' . $bundle . '/app/forms/' . strtolower($form) . '.yml',
                'public/layouts/' . $bundle . '/forms/' . strtolower($form) . '.html',
                $dbURI
            );
        };
        $this->route->get('/subform/{bundle}/{form}', $callback);
        $this->route->get('/subform/{bundle}/{form}.html', $callback);
        $this->route->get('/subform/{bundle}/{form}/{dbURI}', $callback);

        //update
        $callback = function ($form, $dbURI=false) {
            echo $this->form->upsert($this->form->markerToClass('Form__' . $form), $dbURI);
        };
        $this->route->post('/form/{form}', $callback);
        $this->route->post('/form/{form}/{dbURI}', $callback);
        $callback = function ($bundle, $form, $dbURI=false) {
            echo $this->form->upsert($this->form->markerToClass($bundle . '__Form__' . $form), $dbURI);
        };
        $this->route->post('/subform/{bundle}/{form}', $callback);
        $this->route->post('/subform/{bundle}/{form}/{dbURI}', $callback);

        //delete
        $callback = function ($form, $dbURI) {
            $token = false;
            if (isset($_GET['form-token'])) {
                $token = $_GET['form-token'];
            }
            echo $this->form->delete($this->form->markerToClass('Form__' . $form), $dbURI, $token);
        };
        $this->route->delete('/form/{form}/{dbURI}', $callback);
        $callback = function ($bundle, $form, $dbURI) {
            $token = false;
            if (isset($_GET['form-token'])) {
                $token = $_GET['form-token'];
            }
            echo $this->form->delete($this->form->markerToClass($bundle . '__Form__' . $form), $dbURI, $token);
        };
        $this->route->delete('/subform/{bundle}/{form}/{dbURI}', $callback);
    }

    private static function stubRead ($name, &$collection, $url, $root) {
        return str_replace(['{{$url}}', '{{$plural}}', '{{$singular}}'], [$url, $collection['p'], $collection['s']], $data);
    }

    public function build ($root=false, $url=false, $bundle='') {
        if ($root === false) {
            $root = $this->root;
        }
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
                $data = file_get_contents($rootProject . '/vendor/opine/build/static/form.html');
                $data = str_replace(['{{$form}}'], [$form], $data);
                file_put_contents($filename, $data);
            }
            $filename = $root . '/partials/forms/' . $form . '.hbs';
            if (!file_exists($filename)) {
                $data = file_get_contents($rootProject . '/vendor/opine/build/static/form.hbs');
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
                    $data = file_get_contents($rootProject . '/vendor/opine/build/static/app-form.yml');
                    $data = str_replace(['{{$form}}', '{{$url}}'], [$form, $url], $data);
                    file_put_contents($filename, $data);
                }
            }
        }
        return $json;
    }

    public function upgrade ($root=false) {
        if ($root === false) {
            $root = $this->root;
        }
        $manifest = (array)json_decode(file_get_contents('https://raw.github.com/opine/form/master/available/manifest.json'), true);
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

class FormPathException extends \Exception {}
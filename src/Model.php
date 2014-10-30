<?php
/**
 * Opine\Form\Model
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
namespace Opine\Form;

class Model {
    private $root;
    private $service;
    private $cacheService;
    private $bundleModel;
    private $cacheFile;
    private $cacheKey;

    public function __construct ($root, $service, $bundleModel) {
        $this->root = $root;
        $this->cacheFile = $root . '/../cache/forms.json';
        $this->bundleModel = $bundleModel;
    }

    public function cacheSet ($cache) {
        $this->cache = $cache;
    }

    private function cacheWrite ($cache) {
        file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    private static function stubRead ($name, &$collection, $url, $root) {
        return str_replace(['{{$url}}', '{{$plural}}', '{{$singular}}'], [$url, $collection['p'], $collection['s']], $data);
    }

    private function directoryScan ($path, &$forms, $bundle='') {
        if ($bundle != '') {
            $bundle .= '\\';
        }
        $dirFiles = glob($path);
        foreach ($dirFiles as $form) {
            $form = basename($form, '.php');
            $className = $bundle . 'Form\\' . $form;
            $staticName = strtolower($this->toUnderscore($form));
            $forms[] = [
                'class'     => $className,
                'fullname'  => str_replace('\\', '__', $className),
                'name'      => $form,
                'layout'    => $this->root . '/layouts/' . str_replace('\\', '/', $bundle) . 'forms/' . $staticName . '.html',
                'partial'   => $this->root . '/partials/' . str_replace('\\', '/', $bundle) . 'forms/' . $staticName . '.hbs',
                'app'       => ($bundle == '') 
                                ? $this->root . '/../app/forms/' . $form . '.yml'
                                : $this->root . '/../bundles/' . str_replace('\\', '/', $bundle) . 'app/forms/' . $staticName . '.yml'
            ];
        }
    }

    private function toUnderscore ($value) {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
    }

    public function build () {
        $forms = [];
        $this->directoryScan($this->root . '/../forms/*.php', $forms);
        $bundles = $this->bundleModel->bundles();
        foreach ($bundles as $bundle) {
            $this->directoryScan($bundle['root'] . '/../collections/*.php', $forms, $bundle['name']);
        }
        $this->cacheWrite($forms);
        foreach ($forms as $form) {
            if (!file_exists($form['layout'])) {
                $data = file_get_contents($this->root . '/../vendor/opine/build/static/form.html');
                $data = str_replace(['{{$form}}'], [$form], $data);
                file_put_contents($form['layout'], $data);
            }
            if (!file_exists($form['partial'])) {
                $data = file_get_contents($this->root . '/../vendor/opine/build/static/form.hbs');
                $className = $form['class'];
                $formObject = $this->service->factory(new $className);
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
                file_put_contents($form['partial'], $data);
            }
            if (!file_exists($form['app'])) {
                $data = file_get_contents($this->root . '/../vendor/opine/build/static/app-form.yml');
                $data = str_replace(['{{$form}}', '{{$url}}'], [$form, ''], $data);
                file_put_contents($form['app'], $data);
            }
        }
        return json_encode($forms, JSON_PRETTY_PRINT);
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
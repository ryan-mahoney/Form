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
        $this->service = $service;
        $this->cacheFile = $root . '/../cache/forms.json';
        $this->bundleModel = $bundleModel;
    }

    public function cacheSet ($cache) {
        if (empty($cache)) {
            if (!file_exists($this->cacheFile)) {
                return;
            }
            $this->cache = json_decode(file_get_contents($this->cacheFile), true);
            return;
        }
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
            $name_ = strtolower($this->toUnderscore($form));
            $forms[] = [
                'class'     => $className,
                'fullname'  => str_replace('\\', '__', $className),
                'name'      => $form,
                'name_'     => $name_,
                'layout'    => $this->root . '/layouts/' . str_replace('\\', '/', $bundle) . 'forms/' . $name_ . '.html',
                'partial'   => $this->root . '/partials/' . str_replace('\\', '/', $bundle) . 'forms/' . $name_ . '.hbs',
                'app'       => ($bundle == '')
                                ? $this->root . '/../app/forms/' . $name_ . '.yml'
                                : $this->root . '/../app/' . str_replace('\\', '/', $bundle) . '/forms/' . $name_ . '.yml'
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
            if (!isset($bundle['root'])) {
                continue;
            }
            $this->directoryScan($bundle['root'] . '/../forms/*.php', $forms, $bundle['name']);
        }
        $this->cacheWrite($forms);
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
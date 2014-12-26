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

use Symfony\Component\Yaml\Yaml;

class Model
{
    private $cache;
    private $root;
    private $bundleModel;
    private $cacheFile;
    private $cacheKey;

    public function __construct($root, $bundleModel)
    {
        $this->root = $root;
        $this->cacheFile = $root.'/../var/cache/forms.json';
        $this->bundleModel = $bundleModel;
    }

    public function cacheSet($cache)
    {
        if (empty($cache)) {
            if (!file_exists($this->cacheFile)) {
                return;
            }
            $this->cache = json_decode(file_get_contents($this->cacheFile), true);

            return;
        }
        $this->cache = $cache;
    }

    public function cacheGetForm($slug)
    {
        if (!isset($this->cache[$slug])) {
            return false;
        }

        return $this->cache[$slug];
    }

    private function cacheWrite($cache)
    {
        file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    private static function stubRead($name, &$collection, $url, $root)
    {
        return str_replace(['{{$url}}', '{{$plural}}', '{{$singular}}'], [$url, $collection['p'], $collection['s']], $data);
    }

    private function directoryScan($path, &$forms, $bundle = '')
    {
        $separator = '';
        if (strlen($bundle) > 0) {
            $separator = '/';
        }
        $dirFiles = glob($path);
        foreach ($dirFiles as $formPath) {
            $form = $this->yaml($formPath);
            $form = $form['form'];
            if (!isset($form['slug'])) {
                echo 'malformatted form: ', $formPath, ', no slug', "\n";
                continue;
            }
            if (!isset($form['fields']) || !is_array($form['fields'])) {
                echo 'malformatted form: ', $formPath, ', no field', "\n";
                continue;
            }
            foreach ($form['fields'] as $key => &$value) {
                $value['name'] = $key;
                $value['marker'] = $form['slug'];
            }
            $forms[$form['slug']] = array_merge($form, [
                'bundle'    => $bundle,
                'name'      => $form['slug'],
                'layout'    => $this->root.'/layouts/'.$bundle.$separator.'forms/'.$form['slug'].'.html',
                'partial'   => $this->root.'/partials/'.$bundle.$separator.'forms/'.$form['slug'].'.hbs',
                'app'       => ($bundle == '')
                                ? $this->root.'/../config/layouts/forms/'.$form['slug'].'.yml'
                                : $this->root.'/../config/layouts/'.$bundle.$separator.'/forms/'.$form['slug'].'.yml'
            ]);
        }

        return true;
    }

    public function build()
    {
        $forms = [];
        $this->directoryScan($this->root.'/../config/forms/*.yml', $forms);
        $bundles = $this->bundleModel->bundles();
        foreach ($bundles as $bundle) {
            if (!isset($bundle['root'])) {
                continue;
            }
            $this->directoryScan($bundle['root'].'/../config/forms/*.yml', $forms, $bundle['name']);
        }
        $this->cacheWrite($forms);

        return json_encode($forms, JSON_PRETTY_PRINT);
    }

    private function yaml($file)
    {
        return Yaml::parse(file_get_contents($file));
    }
}

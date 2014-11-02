<?php
/**
 * Opine\Form\Controller
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

class Controller {
	private $service;
	private $view;
	private $model;

	public function __construct ($model, $view, $service) {
		$this->model = $model;
		$this->view = $view;
		$this->service = $service;
	}

    public function update ($form, $dbURI=false) {
        echo $this->service->upsert($this->service->markerToClass('Form__' . $form), $dbURI);
    }

    public function bundleUpdate ($bundle, $form, $dbURI=false) {
        echo $this->service->upsert($this->service->markerToClass($bundle . '__Form__' . $form), $dbURI);
    }

    public function delete ($form, $dbURI) {
        $token = false;
        if (isset($_GET['form-token'])) {
            $token = $_GET['form-token'];
        }
        echo $this->service->delete($this->service->markerToClass('Form__' . $form), $dbURI, $token);
    }

    public function bundleDelete ($bundle, $form, $dbURI) {
        $token = false;
        if (isset($_GET['form-token'])) {
            $token = $_GET['form-token'];
        }
        echo $this->service->delete($this->service->markerToClass($bundle . '__Form__' . $form), $dbURI, $token);
    }

    public function json ($form, $id=false) {
        if (isset($_GET['id']) && $id === false) {
            $id = $_GET['id'];
        }
        $className = '\Form\\' . $form;
        echo $this->jsonDecorate($this->service->json($this->service->factory(new $className, $id), $id));
    }

    public function jsonBundle ($bundle, $form, $id=false) {
        if (isset($_GET['id']) && $id === false) {
            $id = $_GET['id'];
        }
        $className = '\\' . $bundle . '\Form\\' . $form;
        echo $this->jsonDecorate($this->service->json($this->service->factory(new $className, $id), $id));        
    }

    private function jsonDecorate ($json) {
        $head = '';
        $tail = '';
        if (isset($_GET['pretty'])) {
            $head = '<html><head></head><body style="margin:0; border:0; padding: 0"><textarea wrap="off" style="overflow: auto; margin:0; border:0; padding: 0; width:100%; height: 100%">';
            $tail = '</textarea></body></html>';
        } elseif (isset($_GET['callback'])) {
            $head = $_GET['callback'] . '(';
            $tail = ');';
        }
        return $head . $json . $tail;
    }

    public function html ($form, $dbURI=false) {
        $this->view->html(
            $this->service->markerToClass('Form__' . $form),
            'app/forms/' . strtolower($form) . '.yml',
            'forms/' . strtolower($form) . '.html',
            $dbURI
        );
    }

    public function bundleHtml ($bundle, $form, $dbURI=false) {
        $this->view->html(
            $this->service->markerToClass($bundle . '__Form__' . $form),
            'bundles/' . $bundle . '/app/forms/' . strtolower($form) . '.yml',
            'public/layouts/' . $bundle . '/forms/' . strtolower($form) . '.html',
            $dbURI
        );
    }
}
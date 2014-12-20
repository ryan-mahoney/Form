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
        $formObject = $this->service->factory($form);
        echo $this->service->upsert($formObject, $dbURI);
    }

    public function bundleUpdate ($bundle, $form, $dbURI=false) {
        //attach bundle topics?
        $formObject = $this->service->factory($form);
        echo $this->service->upsert($formObject, $dbURI);
    }

    public function delete ($form, $dbURI) {
        $token = false;
        if (isset($_GET['form-token'])) {
            $token = $_GET['form-token'];
        }
        echo $this->service->delete($form, $dbURI, $token);
    }

    public function bundleDelete ($bundle, $form, $dbURI) {
        $token = false;
        if (isset($_GET['form-token'])) {
            $token = $_GET['form-token'];
        }
        echo $this->service->delete($bundle . '__' . $form, $dbURI, $token);
    }

    public function json ($form, $id=false) {
        if (isset($_GET['id']) && $id === false) {
            $id = $_GET['id'];
        }
        $formObject = $this->service->factory($form);
        $json = $this->service->json($formObject, $id);
        echo $this->jsonDecorate($json);
    }

    public function jsonBundle ($bundle, $form, $id=false) {
        if (isset($_GET['id']) && $id === false) {
            $id = $_GET['id'];
        }
        echo $this->jsonDecorate($this->service->json($this->service->factory($form), $id));
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

    public function html ($slug, $dbURI=false) {
        $this->view->html(
            $this->service->factory($slug),
            'forms/' . $slug,
            'forms/' . $slug,
            $dbURI
        );
    }

    public function bundleHtml ($bundle, $slug, $dbURI=false) {
        echo 'Hello';
        exit;
        $this->view->html(
            $this->service->factory($slug),
            [strtolower($bundle) . '/forms/' . $slug . '.yml', $bundle . '/forms/' . $slug . '.yml'],
            [strtolower($bundle) . '/forms/' . $slug . '.html', $bundle . '/forms/' . $slug . '.html'],
            $dbURI
        );
    }
}
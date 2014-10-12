<?php
/**
 * Opine\Form\Route
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

class Route {
	private $route;

	public function __construct ($route) {
		$this->route = $route;
	}

	public function paths () {
        $this->route->get('/api/form/{form}', 'formController@json');
        $this->route->get('/api/form/{form}/{id}', 'formController@json');
        $this->route->get('/{bundle}/api/form/{form}', 'formController@jsonBundle');
        $this->route->get('/{bundle}/api/form/{form}/{id}', 'formController@jsonBundle');
        $this->route->get('/form/{form}', 'formController@html');
        $this->route->get('/form/{form}.html', 'formController@html');
        $this->route->get('/form/{form}/{dbURI}', 'formController@html');
        $this->route->get('/{bundle}/form/{form}', 'formController@bundleHtml');
        $this->route->get('/{bundle}/form/{form}.html', 'formController@bundleHtml');
        $this->route->get('/{bundle}/form/{form}/{dbURI}', 'formController@bundleHtml');
        $this->route->post('/form/{form}', 'formController@update');
        $this->route->post('/form/{form}/{dbURI}', 'formController@update');
        $this->route->post('/{bundle}/form/{form}', 'formController@bundleUpdate');
        $this->route->post('/{bundle}/form/{form}/{dbURI}', 'formController@bundleUpdate');
        $this->route->delete('/form/{form}/{dbURI}', 'formController@delete');
        $this->route->delete('/{bundle}/form/{form}/{dbURI}', 'formController@bundleDelete');
    }
}
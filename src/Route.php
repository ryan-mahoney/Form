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
        $this->route->get('/api/form/{form}', 'formCollecion@json');
        $this->route->get('/api/form/{form}/{id}', 'formCollecion@json');
        $this->route->get('/{bundle}/api/form/{form}', 'formCollecion@jsonBundle');
        $this->route->get('/{bundle}/api/form/{form}/{id}', 'formCollecion@jsonBundle');
        $this->route->get('/form/{form}', 'formCollecion@html');
        $this->route->get('/form/{form}.html', 'formCollecion@html');
        $this->route->get('/form/{form}/{dbURI}', 'formCollecion@html');
        $this->route->get('/{bundle}/form/{form}', 'formCollecion@bundleHtml');
        $this->route->get('/{bundle}/form/{form}.html', 'formCollecion@bundleHtml');
        $this->route->get('/{bundle}/form/{form}/{dbURI}', 'formCollecion@bundleHtml');
        $this->route->post('/form/{form}', 'formCollecion@update');
        $this->route->post('/form/{form}/{dbURI}', 'formCollecion@update');
        $this->route->post('/{bundle}/form/{form}', 'formCollecion@bundleUpdate');
        $this->route->post('/{bundle}/form/{form}/{dbURI}', 'formCollecion@bundleUpdate');
        $this->route->delete('/form/{form}/{dbURI}', 'formCollecion@delete');
        $this->route->delete('/{bundle}/form/{form}/{dbURI}', 'formCollecion@bundleDelete');
    }
}
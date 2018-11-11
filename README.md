Humming Widget Template - simple php5 widget template
=======================================

This library provides a fast implementation of simple widgets.

Install
-------

To install with composer:

```sh
composer require humming/template
```

Requires PHP 5.3 or newer.

Usage
-----

Here's a basic usage example:

```php
<?php

require '/path/to/vendor/autoload.php';

$templateDir = __DIR__ . '/template';
$compiledDir = __DIR__ . '/compiled';
$template = new \Humming\Template($templateDir, $compiledDir, new \Humming\Widget(), new \Humming\Pagination());

$template->assign('something', $somthing);
$template->display("test");
```
```html
:test.html
<html>
<body>
<h1>{$global.something}</h1>
</body>
</html>
```
### Widgets

```php
class HighSchoolStudent extends \Humming\Widget
{
    public function getItems($limit = 10, $name = '')
    {
        return array('title' => 'Students', 'rows=> array(
            array('id' => 1, 'name'=>'Li'),
            array('id' => 2, 'name'=>'Ming'),
            array('id' => 3, 'name'=>$name),
        );
    }
}
```
```html
<html>
<body>
<h1>{var from=$widget.high_school_student.items.title name='Coco' limit=2 cache=3600}</h1>
<ul>
{section loop=$widget.high_school_student.items.rows limit=2 name='Coco'}
<li>{$rows.name}</li>
{/section}
</ul>
<h3>First Boy is {$widget.high_school_student.items.rows.0.name}</h3>
</body>
</html>
```
### Include 

```html
<div class="main">{include file='main.html'}</div>
```

#### Paging

```html
<div class="pagination">
{paging link="/test/?page=@number@" page=$global.page size=20 total=$global.total}
</div>
```
OR
```php
<?php
$template->getPagination()->setUrl("/test/?page=@number@");
$template->getPagination()->setNumber(1);
$template->getPagination()->setSize(20);
$template->getPagination()->setTotal(100);
?>
<div class="pagination">
{paging template="/frontend/paging.html"}
</div>
```
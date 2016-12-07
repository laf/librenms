<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Collection;

$collection = new Collection(array(1, 2, 3, 4));

print_r($collection);

//$last = $collection->last();
$last = $collection->last(function ($v) {
	return $v < 4;
});


var_dump($last);

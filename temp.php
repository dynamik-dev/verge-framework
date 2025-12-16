<?php

use Verge\App;
use Verge\Verge;

app()->get('/hello', fn (App $app) 
    => ['message' => 'Hello, Verge!']
);
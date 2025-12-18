<?php

use Illuminate\Support\Facades\Route;

Route::view(uri: '/{any}', view: 'spa')->where(name: 'any', expression: '.*');

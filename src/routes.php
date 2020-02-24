<?php

Route::get('swagger', static function () {
    return File::get(public_path('swagger') . '/index.html');
});

Route::get('swagger.json', static function () {
    return response(File::get(public_path('swagger') . '/swagger.json'));
});

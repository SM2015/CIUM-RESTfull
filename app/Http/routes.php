<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
});


Route::get('api/v2/tareas/{num?}', ['middleware' => 'tokenPermiso', 'permisos' => 'user.view', 'uses'=>'TareasController@getIndex']);
Route::post('api/v2/tareas/{num?}',['middleware' => 'token', 'uses'=>'TareasController@postIndex']);



// rutas api v1
Route::group(array('prefix' => 'api/v1'), function()
{
	//catalogos
    Route::resource('cone', 'Catalogos\ConeController');
    Route::resource('criterio', 'Catalogos\CriterioController');
    Route::resource('indicador', 'Catalogos\IndicadorController');

    Route::resource('accion', 'Catalogos\AccionController');
    Route::resource('plazoAccion', 'Catalogos\PlazoAccionController');
    Route::resource('lugarVC', 'Catalogos\LugarVerificacionCriterioController');
});
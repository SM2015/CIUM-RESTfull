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

// rutas api v1
Route::group(array('prefix' => 'api/v1', 'middleware' => 'tokenPermiso'), function()
{
	//catalogos
	Route::resource('clues', 'v1\Catalogos\CluesController');
    Route::resource('cone', 'v1\Catalogos\ConeController');
    Route::resource('criterio', 'v1\Catalogos\CriterioController');
    Route::resource('indicador', 'v1\Catalogos\IndicadorController');
    Route::resource('accion', 'v1\Catalogos\AccionController');
    Route::resource('plazoAccion', 'v1\Catalogos\PlazoAccionController');
    Route::resource('lugarVerificacionCriterio', 'v1\Catalogos\LugarVerificacionCriterioController');
	
	//sistema
	Route::resource('SysModulo', 'v1\Sistema\SysModuloController');
    Route::resource('SysModuloAccion', 'v1\Sistema\SysModuloAccionController');
	Route::resource('Usuario', 'v1\Sistema\UsuarioController');
    Route::resource('Grupo', 'v1\Sistema\GrupoController');
	
	// transaccion
	Route::resource('Evaluacion', 'v1\Transacciones\EvaluacionController');	
});

Route::get('api/v1/menu', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Sistema\SysModuloController@menu']);
Route::get('api/v1/moduloAccion', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Sistema\SysModuloController@moduloAccion']);

Route::get('api/v1/CriterioEvaluacion/{cone}/{indicador}', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Catalogos\CriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionVer/{evaluacion}', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Catalogos\CriterioController@CriterioEvaluacionVer']);

Route::get('api/v1/Estadistica/{evaluacion}', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Catalogos\CriterioController@Estadistica']);
Route::get('api/v1/EvaluacionCriterio', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Transacciones\EvaluacionController@Criterios']);


// en rutas api v1
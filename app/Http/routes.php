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
	
	//transaccion
	Route::resource('Evaluacion', 'v1\Transacciones\EvaluacionController');	
	Route::resource('Seguimiento', 'v1\Transacciones\SeguimientoController');	
});

//Permisos a catalogos solo con token para no mostrar en menu
Route::group(array('prefix' => 'api/v1', 'middleware' => 'token'), function()
{	
	Route::get('clues', 'v1\Catalogos\CluesController@index');
	Route::get('clues/{clues}', 'v1\Catalogos\CluesController@show');
	Route::get('CluesUsuario', 'v1\Catalogos\CluesController@CluesUsuario');
	Route::get('cone', 'v1\Catalogos\ConeController@index');
	Route::get('criterio', 'v1\Catalogos\CriterioController@index');
	Route::get('indicador', 'v1\Catalogos\IndicadorController@index');
	Route::get('accion', 'v1\Catalogos\AccionController@index');
	Route::get('plazoAccion', 'v1\Catalogos\PlazoAccionController@index');
	Route::get('lugarVerificacionCriterio', 'v1\Catalogos\LugarVerificacionCriterioController@index');
	
	Route::resource('Notificacion', 'v1\Transacciones\NotificacionController');
});



//Guardar Criterios evaluados
Route::get('api/v1/EvaluacionCriterio', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Transacciones\EvaluacionController@Criterios']);

//Menu
Route::get('api/v1/menu', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@menu']);
Route::get('api/v1/moduloAccion', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@moduloAccion']);

//Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion)
Route::get('api/v1/CriterioEvaluacion/{cone}/{indicador}', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionVer/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CriterioController@CriterioEvaluacionVer']);
Route::get('api/v1/Estadistica/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CriterioController@Estadistica']);

//Crear catalogo de seleccion jurisdiccion para asignar permisos a usuario
Route::get('api/v1/jurisdiccion', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CluesController@jurisdiccion']);

//Informacion del usuario logueado
Route::get('api/v1/UsuarioInfo', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UsuarioInfo']);
Route::put('api/v1/UpdateInfo', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UpdateInfo']);
//end rutas api v1
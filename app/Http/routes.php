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
	Route::resource('alerta', 'v1\Catalogos\AlertaController');
    Route::resource('plazoAccion', 'v1\Catalogos\PlazoAccionController');
    Route::resource('lugarVerificacion', 'v1\Catalogos\LugarVerificacionController');
	
	//sistema
	Route::resource('SysModulo', 'v1\Sistema\SysModuloController');
    Route::resource('SysModuloAccion', 'v1\Sistema\SysModuloAccionController');
	Route::resource('Usuario', 'v1\Sistema\UsuarioController');
    Route::resource('Grupo', 'v1\Sistema\GrupoController');
	
	//transaccion
	Route::resource('Evaluacion', 'v1\Transacciones\EvaluacionController');	
	Route::resource('EvaluacionCalidad', 'v1\Transacciones\EvaluacionCalidadController');	
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
	Route::get('lugarVerificacion', 'v1\Catalogos\LugarVerificacionController@index');
	
	Route::resource('Notificacion', 'v1\Transacciones\NotificacionController');
	Route::resource('Pendiente', 'v1\Transacciones\PendienteController');
	
	Route::get('abasto', 'v1\Transacciones\DashboardController@indicadorAbasto');
	Route::get('abastoDimension', 'v1\Transacciones\DashboardController@indicadorAbastoDimension');
	Route::get('abastoClues', 'v1\Transacciones\DashboardController@indicadorAbastoClues');
	
	Route::get('calidad', 'v1\Transacciones\DashboardController@indicadorCalidad');
	Route::get('calidadDimension', 'v1\Transacciones\DashboardController@indicadorCalidadDimension');
	Route::get('calidadClues', 'v1\Transacciones\DashboardController@indicadorCalidadClues');
});

//Prueba Criterio
Route::group(array('prefix' => 'api/v1'), function()
{	
	Route::get('operacion', 'v1\Catalogos\CriterioController@operacion');
});


//Menu
Route::get('api/v1/menu', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@menu']);
Route::get('api/v1/moduloAccion', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@moduloAccion']);

//Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion Abasto)
Route::get('api/v1/CriterioEvaluacion/{cone}/{indicador}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionVer/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCriterioController@CriterioEvaluacionVer']);
Route::get('api/v1/Estadistica/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCriterioController@Estadistica']);
//Guardar Criterios evaluados
Route::get('api/v1/EvaluacionCriterio', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Transacciones\EvaluacionController@Criterios']);
Route::get('api/v1/EvaluacionHallazgo', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionController@Hallazgos']);



//Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion calidad)
Route::get('api/v1/CriterioEvaluacionCalidad/{cone}/{indicador}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionCalidadVer/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacionVer']);
Route::get('api/v1/EstadisticaCalidad/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@Estadistica']);
//Guardar Criterios evaluados
Route::get('api/v1/EvaluacionCalidadCriterio', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Transacciones\EvaluacionCalidadController@Criterios']);
Route::get('api/v1/EvaluacionCalidadHallazgo', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadController@Hallazgos']);


//Crear catalogo de seleccion jurisdiccion para asignar permisos a usuario
Route::get('api/v1/jurisdiccion', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CluesController@jurisdiccion']);

//Informacion del usuario logueado
Route::get('api/v1/UsuarioInfo', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UsuarioInfo']);
Route::put('api/v1/UpdateInfo', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UpdateInfo']);
//end rutas api v1
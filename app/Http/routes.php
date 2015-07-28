<?php
use Illuminate\Http\Response as HttpResponse;
use App\Models\Sistema\usuario;
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


Route::post('/refresh-token', function(){
    try{
        
        $refresh_token =  Crypt::decrypt(Input::get('refresh_token'));
        $access_token = str_replace('Bearer ','',Request::header('Authorization'));	
        $post_request = 'grant_type=refresh_token'
                    .'&client_id='.env('CLIENT_ID')
                    .'&client_secret='.env('CLIENT_SECRET')
                    .'&refresh_token='.$refresh_token
                    .'&access_token='.$access_token; 
                 
                    
        $ch = curl_init();
        $header[]         = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $header);
        curl_setopt($ch, CURLOPT_URL, env('OAUTH_SERVER').'/oauth/access_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_request);
         
        // Execute & get variables
        $api_response = json_decode(curl_exec($ch)); 
        $curlError = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if($curlError){ 
        	 throw new Exception("Hubo un problema al intentar hacer la autenticacion. cURL problem: $curlError");
        }
        
        if($http_code != 200){
            return Response::json(['error'=>$api_response->error],$http_code);
        }        
        
		try
		{
			$user = Sentry::findUserByLogin(Input::get('user_email'));
			Sentry::login($user, false); 
			Request::session()->put('email',Input::get('user_email'));
		}
		catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
		{
			return Response::json(array("status"=>403,"messages"=>"Prohibido"),403);
		}
		
        //Encriptamos el refresh token para que no quede 100% expuesto en la aplicacion web
        $refresh_token_encrypted = Crypt::encrypt($api_response->refresh_token);
                    
        return Response::json(['access_token'=>$api_response->access_token,'refresh_token'=>$refresh_token_encrypted],200);
    }catch(Exception $e){
         return Response::json(['error'=>$e->getMessage()],500);
    }
});

Route::post('/signin', function (Request $request) {
    try{
        $credentials = Input::only('email', 'password');
    
        $post_request = 'grant_type=password'
                    .'&client_id='.env('CLIENT_ID')
                    .'&client_secret='.env('CLIENT_SECRET')
                    .'&username='.$credentials['email']
                    .'&password='.$credentials['password']; 
                 
           
        $ch = curl_init();
        $header[]         = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $header);
        curl_setopt($ch, CURLOPT_URL, env('OAUTH_SERVER').'/oauth/access_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_request);
      
        // Execute & get variables
        $api_response = json_decode(curl_exec($ch)); 
        $curlError = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if($curlError){ 
        	 throw new Exception("Hubo un problema al intentar hacer la autenticacion. cURL problem: $curlError");
        }
        
        if($http_code != 200){
          if(isset($api_response->error)){
				return Response::json(['error'=>$api_response->error],$http_code);	
			}else{
				return Response::json(['error'=>"Error"],$http_code);
			}
        } 
		 
		try
		{
			$user = Sentry::findUserByLogin($credentials['email']);
			Sentry::login($user, false); 
			Request::session()->put('email', $credentials['email']);
		}
		catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
		{
			return Response::json(array("status"=>403,"messages"=>"Prohibido"),403);
		}
        //Encriptamos el refresh token para que no quede 100% expuesto en la aplicacion web
        $refresh_token_encrypted = Crypt::encrypt($api_response->refresh_token);
       
                   
        return Response::json(['access_token'=>$api_response->access_token,'refresh_token'=>$refresh_token_encrypted],200);
    }catch(Exception $e){
         return Response::json(['error'=>$e->getMessage()],500);
    }
    
});

Route::group([ 'prefix' => 'api'], function () {
    
    Route::group([ 'middleware' => 'oauth'], function(){
        Route::resource('recomendaciones', 'RecomendacionController', ['only' => ['index', 'show']]);
    });
    
    Route::group([ 'prefix' => 'v1','middleware' => 'oauth'], function(){
          Route::post('/permisos-autorizados', function () { 
				if(!Sentry::check())
				{
					try
					{
						$user = Sentry::findUserByLogin(Input::get('user_email'));
						Sentry::login($user, false); 
						Request::session()->put('email', Input::get('user_email'));
					}
					catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
					{
						return Response::json(array("status"=>403,"messages"=>"Prohibido"),403);
					}
				}
				$user = Sentry::getUser();
                $usuario = Usuario::with("Grupos")->find($user->id);
				$permiso=[];
				foreach($usuario->grupos as $value)
				{
					foreach($value->permissions as $k => $v)
					{
						if($v==1)
							array_push($permiso,$k);
					}
				}
				foreach($usuario->permissions as $k => $v)
				{
					if($v==1)
						array_push($permiso,$k);
				}
				
				return Response::json(['permisos'=>$permiso]);
           });
           
           Route::post('/validacion-cuenta', function () {
               try{
                    
                    // En este punto deberíamos buscar en la base de datos la cuenta del usuario
                    // que previamente el adminsitrador debió haber regitrado, incluso aunque sea una cuenta
                    // OAuth valida.
                    // si no existe regresamos el siguiente error:
                    // return Response::json(['error'=>"CUENTA_VALIDA_NO_AUTORIZADA"],403);
                    
                    
                    $access_token = str_replace('Bearer ','',Request::header('Authorization'));	
                    $post_request = 'access_token='.$access_token; 
                             
                                
                    $ch = curl_init();
                    $header[]         = 'Content-Type: application/x-www-form-urlencoded';
                    curl_setopt($ch, CURLOPT_HTTPHEADER,     $header);
                    curl_setopt($ch, CURLOPT_URL, env('OAUTH_SERVER').'/oauth/vinculacion');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_request);
                     
                    // Execute & get variables
                    $api_response = json_decode(curl_exec($ch)); 
                    $curlError = curl_error($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    
                    if($curlError){ 
                    	 throw new Exception("Hubo un problema al intentar hacer la vinculación. cURL problem: $curlError");
                    }
                    
                    if($http_code != 200){
                        return Response::json(['error'=>$api_response->error],$http_code);
                    }        
                    
                    
                                
                    return Response::json(['data'=>'Vinculación exitosa'],200);
                }catch(Exception $e){
                     return Response::json(['error'=>$e->getMessage()],500);
                }
           });
           
           Route::resource('recomendaciones', 'RecomendacionController',
                        ['only' => ['index', 'show','store', 'update', 'destroy']]);
						
		Route::get('/restricted', function () {
		   return ['data' => 'This has come from a dedicated API subdomain with restricted access.'];
	   });
    });
   
   
   
   
   
});

// rutas api v1
Route::group(array('prefix' => 'api/v1', 'middleware' => 'tokenPermiso'), function()
{
	//catalogos
	Route::resource('Clues', 'v1\Catalogos\CluesController');
    Route::resource('Cone', 'v1\Catalogos\ConeController');
    Route::resource('Criterio', 'v1\Catalogos\CriterioController');
	Route::resource('Zona', 'v1\Catalogos\ZonaController');
    Route::resource('Indicador', 'v1\Catalogos\IndicadorController');
    Route::resource('Accion', 'v1\Catalogos\AccionController');
	Route::resource('Alerta', 'v1\Catalogos\AlertaController');
    Route::resource('PlazoAccion', 'v1\Catalogos\PlazoAccionController');
    Route::resource('LugarVerificacion', 'v1\Catalogos\LugarVerificacionController');
	
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
	Route::get('Clues/{clues}', 'v1\Catalogos\CluesController@show');
	Route::get('CluesUsuario', 'v1\Catalogos\CluesController@CluesUsuario');
	Route::get('Cone', 'v1\Catalogos\ConeController@index');
	Route::get('Criterio', 'v1\Catalogos\CriterioController@index');
	Route::get('Indicador', 'v1\Catalogos\IndicadorController@index');
	Route::get('Accion', 'v1\Catalogos\AccionController@index');
	Route::get('PlazoAccion', 'v1\Catalogos\PlazoAccionController@index');
	Route::get('LugarVerificacion', 'v1\Catalogos\LugarVerificacionController@index');
	
	Route::resource('Notificacion', 'v1\Transacciones\NotificacionController');
	Route::resource('Pendiente', 'v1\Transacciones\PendienteController');
	
	Route::get('abasto', 'v1\Transacciones\DashboardController@indicadorAbasto');
	Route::get('abastoDimension', 'v1\Transacciones\DashboardController@indicadorAbastoDimension');
	Route::get('abastoClues', 'v1\Transacciones\DashboardController@indicadorAbastoClues');
	
	Route::get('calidad', 'v1\Transacciones\DashboardController@indicadorCalidad');
	Route::get('calidadDimension', 'v1\Transacciones\DashboardController@indicadorCalidadDimension');
	Route::get('calidadClues', 'v1\Transacciones\DashboardController@indicadorCalidadClues');
	
	Route::get('alertaDash', 'v1\Transacciones\DashboardController@alerta');
	Route::get('hallazgoGauge', 'v1\Transacciones\DashboardController@hallazgoGauge');
	Route::get('gaugeDimension', 'v1\Transacciones\DashboardController@gaugeDimension');
	
	Route::get('CalidadGlobal', 'v1\Transacciones\DashboardController@indicadorCalidadGlobal');
	Route::get('pieVisita', 'v1\Transacciones\DashboardController@pieVisita');
	Route::get('pieDimension', 'v1\Transacciones\DashboardController@pieDimension');
	
	// export
	Route::post('Export', 'v1\ExportController@Export');
	Route::get('ExportOpen', 'v1\ExportController@ExportOpen');
});

//Prueba Criterio
Route::group(array('prefix' => 'api/v1'), function()
{	
	Route::get('operacion', 'v1\Catalogos\CriterioController@operacion');
	Route::post('ordenKey', 'v1\Sistema\SysModuloController@ordenKey');
});


//Menu
Route::get('api/v1/menu', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@menu']);
Route::get('api/v1/moduloAccion', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@moduloAccion']);
Route::get('api/v1/permiso', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@permiso']);

//Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion Abasto)
Route::get('api/v1/CriterioEvaluacion/{cone}/{indicador}/{id}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionVer/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCriterioController@CriterioEvaluacionVer']);
Route::get('api/v1/Estadistica/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCriterioController@Estadistica']);
//Guardar Criterios evaluados
Route::post('api/v1/EvaluacionCriterio', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Transacciones\EvaluacionController@Criterios']);
Route::post('api/v1/EvaluacionHallazgo', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionController@Hallazgos']);



//Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion calidad)
Route::get('api/v1/CriterioEvaluacionCalidad/{cone}/{indicador}/{id}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionCalidadVer/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacionVer']);
Route::get('api/v1/EstadisticaCalidad/{evaluacion}/{indicador}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@Estadistica']);
//Guardar Criterios evaluados
Route::post('api/v1/EvaluacionCalidadCriterio', ['middleware' => 'tokenPermiso', 'uses'=>'v1\Transacciones\EvaluacionCalidadController@Criterios']);
Route::post('api/v1/EvaluacionCalidadHallazgo', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadController@Hallazgos']);


//Crear catalogo de seleccion jurisdiccion para asignar permisos a usuario
Route::get('api/v1/jurisdiccion', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CluesController@jurisdiccion']);

//Informacion del usuario logueado
Route::get('api/v1/UsuarioInfo', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UsuarioInfo']);
Route::put('api/v1/UpdateInfo', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UpdateInfo']);

//end rutas api v1
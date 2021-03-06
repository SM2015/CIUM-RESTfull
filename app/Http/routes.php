<?php 
use Illuminate\Http\Response as HttpResponse;
use App\Models\Sistema\usuario;
/**
 * Route 
 * 
 * @package    CIUM API
 * @subpackage Routes* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
 * @created    2015-07-20
 
* Rutas de la aplicación
*
* Aquí es donde se registran todas las rutas para la aplicación.
* Simplemente decirle a laravel los URI que debe responder y poner los filtros que se ejecutará cuando se solicita la URI .
*
*/

Route::get('/', function()
{
});
/**
* si se tiene un token y expira podemos renovar con el refresh token proporcionado
*/
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
			$user = Sentry::findUserByLogin(Request::header('X-Usuario'));
			Sentry::login($user, false); 
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
/**
* Obetener el token y refresh token con las credenciales de un usuario y el CLIENT_ID y SECRET_ID de la aplicacion cliente
*/
Route::post('/signin', function (Request $request) {
    try{
        $credentials = Input::only('email', 'password');
		// Si no se puede recibir como POST recibir entonces como json
		if($credentials['email']=="")
		{
			$credentials = Input::json()->all();			
		}
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
/**
* obtener lo permisos del usuario para mostrar el menu según corresponda.
*/
Route::group([ 'prefix' => 'api'], function () {
    
    Route::group([ 'prefix' => 'v1','middleware' => 'oauth'], function(){
          Route::post('/permisos-autorizados', function () { 
		  
				if(!Sentry::check())
				{
					try
					{
						$user = Sentry::findUserByLogin(Request::header('X-Usuario'));
						Sentry::login($user, false); 					
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
/**
* rutas api v1 protegidas con middleware tokenPermiso que comprueba si el usuario tiene o no permisos para el recurso solicitado
*/
Route::group(array('prefix' => 'api/v1', 'middleware' => 'tokenPermiso'), function()
{
	//catalogos
	Route::resource('Clues', 'v1\Catalogos\CluesController');
    Route::resource('Cone', 'v1\Catalogos\ConeController');
    Route::resource('Criterio', 'v1\Catalogos\CriterioController');
	Route::resource('Zona', 'v1\Catalogos\ZonaController');
	Route::resource('Nuevo', 'v1\Catalogos\ZonaController');
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
	Route::resource('EvaluacionRecurso', 'v1\Transacciones\EvaluacionRecursoController');	
	Route::resource('EvaluacionRecursoCriterio', 'v1\Transacciones\EvaluacionRecursoCriterioController');
	Route::resource('EvaluacionCalidad', 'v1\Transacciones\EvaluacionCalidadController');	
	Route::resource('EvaluacionCalidadCriterio', 'v1\Transacciones\EvaluacionCalidadCriterioController');
	Route::resource('Hallazgo', 'v1\Transacciones\HallazgoController');	
});
/**
* Acceso a catálogos sin permisos pero protegidas para que se solicite con un token 
*/
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
	
	Route::get('recurso', 'v1\Transacciones\DashboardController@indicadorRecurso');
	Route::get('recursoDimension', 'v1\Transacciones\DashboardController@indicadorRecursoDimension');
	Route::get('recursoClues', 'v1\Transacciones\DashboardController@indicadorRecursoClues');
	
	Route::get('calidad', 'v1\Transacciones\DashboardController@indicadorCalidad');
	Route::get('calidadDimension', 'v1\Transacciones\DashboardController@indicadorCalidadDimension');
	Route::get('calidadClues', 'v1\Transacciones\DashboardController@indicadorCalidadClues');
	
	Route::get('alertaDash', 'v1\Transacciones\DashboardController@alerta');
	Route::get('alertaEstricto', 'v1\Transacciones\DashboardController@alertaEstricto');
	
	Route::get('hallazgoGauge', 'v1\Transacciones\DashboardController@hallazgoGauge');
	Route::get('hallazgoDimension', 'v1\Transacciones\HallazgoController@hallazgoDimension');
	
	Route::get('TopCalidadGlobal', 'v1\Transacciones\DashboardController@topCalidadGlobal');
	Route::get('TopRecursoGlobal', 'v1\Transacciones\DashboardController@topRecursoGlobal');
	Route::get('pieVisita', 'v1\Transacciones\DashboardController@pieVisita');
	
	Route::get('indexCriterios', 'v1\Transacciones\HallazgoController@indexCriterios');
	Route::get('showCriterios', 'v1\Transacciones\HallazgoController@showCriterios');
	
	/*export
	Route::post('Export', 'v1\ExportController@Export');
	Route::post('exportGenerate', 'v1\ExportController@exportGenerate');*/
});
/**
* Ordena los criterios y los clasifica
*/
Route::group(array('prefix' => 'api/v1'), function()
{	
	Route::post('ordenKey', 'v1\Sistema\SysModuloController@ordenKey');
});

/**
* permisos por modulo
* Para proteger una ruta hay que agregar el middleware correspondiente según sea el caso de protección
* para peticiones como cátalogos que no se necesita tener permisos se le asigna el middleware token
* para peticiones que se necesitan permisos para acceder se asigna el middleware tokenPermiso
*/
Route::get('api/v1/permiso', ['middleware' => 'token', 'uses'=>'v1\Sistema\SysModuloController@permiso']);
/**
*Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion Recurso)
*/
Route::get('api/v1/CriterioEvaluacionRecurso/{cone}/{indicador}/{id}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionRecursoCriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionRecursoImprimir/{cone}/{indicador}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionRecursoCriterioController@CriterioEvaluacionImprimir']);
Route::get('api/v1/EstadisticaRecurso/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionRecursoCriterioController@Estadistica']);
/**
* Guardar hallazgos encontrados
*/
Route::post('api/v1/EvaluacionRecursoHallazgo', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionRecursoController@Hallazgos']);
/**
* Lista criterios evaluacion y estadistica de evaluacion por indicador (Evaluacion calidad)
*/
Route::get('api/v1/CriterioEvaluacionCalidad/{cone}/{indicador}/{id}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacion']);
Route::get('api/v1/CriterioEvaluacionCalidadImprimir/{cone}/{indicador}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacionImprimir']);
Route::get('api/v1/CriterioEvaluacionCalidadIndicador/{id}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@CriterioEvaluacionCalidadIndicador']);
Route::get('api/v1/EstadisticaCalidad/{evaluacion}', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadCriterioController@Estadistica']);
/**
* Guardar hallazgos encontrados
*/
Route::post('api/v1/EvaluacionCalidadHallazgo', ['middleware' => 'token', 'uses'=>'v1\Transacciones\EvaluacionCalidadController@Hallazgos']);

/**
* Crear catalogo de seleccion jurisdiccion para asignar permisos a usuario
*/
Route::get('api/v1/jurisdiccion', ['middleware' => 'token', 'uses'=>'v1\Catalogos\CluesController@jurisdiccion']);
/**
* Actualizar información del usuario logueado
*/
Route::put('api/v1/UpdateInfo/{email}', ['middleware' => 'token', 'uses'=>'v1\Sistema\UsuarioController@UpdateInfo']);
//end rutas api v1
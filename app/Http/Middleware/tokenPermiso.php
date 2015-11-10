<?php 
namespace App\Http\Middleware;

use Closure;
use Request;
use Response;
use Sentry;
/**
* Middleware tokenPermiso
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Middleware `Token-Permiso`: Controla las peticiones a los controladores y las protege por token y permisos de usuario
*
*/
class tokenPermiso {

	/**
	 * Comprueba que el solicitante tenga un token valido y permisos para acceder al recurso solicitado.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// Obetener el recurso que se pretende acceder
		$action = $request->route()->getAction();
		$value = explode('\\',$action["controller"]);
		$value = explode('@',$value[count($value)-1]);
        $value  =$value[0].'.'.$value[1];
		
		// validar que el token es enviado por la cabecera
		$token  = str_replace('Bearer ','',Request::header('Authorization'));	
        if(!$token)
			return Response::json(array("status"=>400,"messages"=>"Petición incorrecta"),400);
        
		// validar con el servidor SALUD-ID que el token es valido y pertenezca al usuario que lo envio
	    $result = @json_decode(file_get_contents(env('OAUTH_SERVER').'/oauth/check/'.$token.'/'.Request::header('X-Usuario')));
	   
	    if (isset($result->data) ) 
	    {			
			//if($request->get("Export"))
			//	return $next($request);
			
			// verificar que la sessión de sentry exista si no crearla
	    	if(!Sentry::check())
			{
				try
				{
					$user = Sentry::findUserByLogin(Request::header('X-Usuario'));
					Sentry::login($user, false); 
				}
				catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
				{
					return Response::json(array("status"=>403,"messages"=>"Prohibido"),200);
				}
			}
			// validar que se tiene permiso al recurso solicitado si no regresar error con estado 401
	        $user=Sentry::getUser();
	       	if (!$user->hasAccess($value))
				return Response::json(array("status"=>401,"messages"=>"No autorizado ".$value),200);
	        return $next($request); 
	    }
	    else
			return Response::json(array("status"=>407,"messages"=>"Autenticación requerida"),407);
	}

}

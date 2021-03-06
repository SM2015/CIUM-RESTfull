<?php 
namespace App\Http\Middleware;

use Closure;
use Request;
use Response;
use Sentry;
/**
* Middleware token
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Middleware `Token`: Controla las peticiones a los controladores y las protege por token
*
*/
class token 
{

	/**
	 * Comprueba que el solicitante tenga un token valido.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		// validar que el token es enviado por la cabecera
		$token = str_replace('Bearer ','',Request::header('Authorization'));
        if(!$token)
        	return Response::json(array("status"=>400,"messages"=>"Petición incorrecta"),400);
		
		// validar con el servidor SALUD-ID que el token es valido y pertenezca al usuario que lo envio
	    $result = @json_decode(file_get_contents(env('OAUTH_SERVER').'/oauth/check/'.$token.'/'.Request::header('X-Usuario')));
		
	    if (!isset($result->data)) 
	    {
	        return Response::json(array("status"=>407,"messages"=>"Autenticación requerida"),407);
	    }
	    else
		{
			if($request->get("Export"))
				return $next($request);
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
				}
			}
	    	return $next($request);
		}
	}

}

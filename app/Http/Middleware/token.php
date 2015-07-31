<?php namespace App\Http\Middleware;

use Closure;
use Request;
use Response;
use Sentry;
/**
 * Middleware token
 *
 * @package     APIRESTFULL
 * @subpackage  Middleware
 * @author     	Eliecer
 * @created     2015-16-02
 */
class token 
{

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$token = str_replace('Bearer ','',Request::header('Authorization'));
        if(!$token)
        	return Response::json(array("status"=>400,"messages"=>"Petición incorrecta"),400);
		
	    $result = @json_decode(file_get_contents(env('OAUTH_SERVER').'/oauth/check/'.$token.'/'.Request::header('X-Usuario')));
		
	    if (!isset($result->data)) 
	    {
	        return Response::json(array("status"=>407,"messages"=>"Autenticación requerida"),407);
	    }
	    else
		{
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

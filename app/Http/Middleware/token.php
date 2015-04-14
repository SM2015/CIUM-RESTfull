<?php namespace App\Http\Middleware;

use Closure;
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
		$token  = $request->header();
        if(!array_key_exists("authorization",$token))
        	return Response::json(array("status"=>400,"messages"=>"Petición incorrecta"),400);
		$token  = $token["authorization"];

	    $result = @json_decode(file_get_contents('http://SaludID.dev/oauth/check?access_token='.$token[0]));
		
	    if (!isset($result->status)) 
	    {
	        return Response::json(array("status"=>407,"messages"=>"Autenticación requerida"),407);
	    }
	    else
		{
			if(!Sentry::check())
			{
				try
				{
					$user = Sentry::findUserByLogin($result->info->email);
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
